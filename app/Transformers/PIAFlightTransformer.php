<?php
namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class PIAFlightTransformer
{


    public function fromPIA($array, $params)
    {
        if ($params['flight_type'] == 'one-way') {
            return $this->fromOneWayPIA($array);
        } else if ($params['flight_type'] == 'return') {
            return $this->fromPIARoundTrip($array);
        }
    }
    public function fromOneWayPIA($array)
    {
        $results = [];

        $availabilityList = $array['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList'] ?? [];

        // Normalize to array if single date
        if (isset($availabilityList['originDestinationOptionList'])) {
            $availabilityList = [$availabilityList];
        }

        foreach ($availabilityList as $dateData) {
            $date = $dateData['dateList'] ?? null;
            $options = $dateData['originDestinationOptionList'] ?? [];

            // Normalize single vs multiple options
            if (isset($options['fareComponentGroupList'])) {
                $options = [$options];
            }

            foreach ($options as $option) {
                $result = [
                    'provider' => [
                        'name' => 'PIA',
                        'source' => 'PIA',
                        'identifier' => 'PIA',
                    ],
                    'leg' => [
                        'ref_id' => (string) \Illuminate\Support\Str::uuid(),
                        'flights' => [],
                    ],
                ];

                $fareGroups = $option['fareComponentGroupList'] ?? [];

                // Normalize single vs multiple fare groups
                if (isset($fareGroups['boundList'])) {
                    $fareGroups = [$fareGroups];
                }

                $flights = [];

                foreach ($fareGroups as $fareGroup) {
                    $totalLayoverTime = 0;
                    $segments = [];
                    $previousArrivalTimestamp = null;

                    $boundList = $fareGroup['boundList'] ?? [];
                    $segmentsData = $boundList['availFlightSegmentList'] ?? [];

                    // Normalize segmentsData to array if single segment
                    if (isset($segmentsData['flightSegment'])) {
                        $segmentsData = [$segmentsData];
                    }

                    foreach ($segmentsData as $segmentData) {
                        if (!$segmentData) {
                            continue;
                        }

                        $segment = $segmentData['flightSegment'] ?? [];
                        if (empty($segment)) {
                            continue;
                        }

                        $segmentRefId = (string) \Illuminate\Support\Str::uuid();
                        $departureIso = $segment['departureDateTime'] ?? '';
                        $arrivalIso = $segment['arrivalDateTime'] ?? '';

                        // Airline info
                        $airline = [
                            'name' => $segment['airline']['companyShortName'] ?? 'PAKISTAN AIRLINES',
                            'iata_code' => $segment['airline']['code'] ?? 'PK',
                            'logo_url' => 'https://www.sooperfare.com/assets/client/images/airlines/PK.png',
                        ];

                        // Airport data
                        $departure = [
                            'airport' => [
                                'name' => $segment['departureAirport']['locationName'] ?? '',
                                'city_name' => $segment['departureAirport']['cityInfo']['city']['locationName'] ?? '',
                                'iata_code' => $segment['departureAirport']['locationCode'] ?? '',
                                'iata_city_code' => $segment['departureAirport']['cityInfo']['city']['locationCode'] ?? '',
                            ],
                            'city' => $segment['departureAirport']['cityInfo']['city']['locationName'] ?? '',
                            'terminal' => $segment['departureAirport']['terminal'] ?? '',
                        ];

                        $arrival = [
                            'airport' => [
                                'name' => $segment['arrivalAirport']['locationName'] ?? '',
                                'city_name' => $segment['arrivalAirport']['cityInfo']['city']['locationName'] ?? '',
                                'iata_code' => $segment['arrivalAirport']['locationCode'] ?? '',
                                'iata_city_code' => $segment['arrivalAirport']['cityInfo']['city']['locationCode'] ?? '',
                            ],
                            'city' => $segment['arrivalAirport']['cityInfo']['city']['locationName'] ?? '',
                            'terminal' => $segment['arrivalAirport']['terminal'] ?? '',
                        ];

                        // Extract terminal from flightNotes if available
                        $flightNotes = $segment['flightNotes'] ?? [];
                        foreach ($flightNotes as $note) {
                            if ($note['deiCode'] === '98' && $note['explanation'] === 'Arrival Terminal') {
                                $arrival['terminal'] = $note['note'] ?? '';
                            }
                        }

                        // Aircraft info
                        $aircraft = [
                            'name' => $segment['equipment']['airEquipTypeModel'] ?? '',
                            'iata_code' => $segment['equipment']['airEquipType'] ?? '',
                        ];

                        // Flight time calculation
                        $flightTime = $this->convertDurationToMinutes($segment['journeyDuration'] ?? 'PT1H45M');

                        // Layover time calculation
                        $layoverTime = 0;
                        if ($previousArrivalTimestamp !== null && !empty($departureIso)) {
                            try {
                                $departureTimestamp = strtotime($departureIso);
                                $layoverTime = max(0, round(($departureTimestamp - $previousArrivalTimestamp) / 60));
                                $totalLayoverTime += $layoverTime;
                            } catch (\Exception $e) {
                                $layoverTime = 0;
                            }
                        }

                        // Update previous arrival timestamp
                        if (!empty($arrivalIso)) {
                            $previousArrivalTimestamp = strtotime($arrivalIso);
                        }

                        // Distance
                        $distance = $segment['flownMileageQty'] ?? '0';

                        // Booking info from bookingClassList
                        $bookingClassList = $segmentData['bookingClassList'] ?? [];
                        if (!is_array($bookingClassList) || !isset($bookingClassList[0])) {
                            $bookingClassList = [$bookingClassList];
                        }

                        $bookingCode = $bookingClassList[0]['resBookDesigCode'] ?? '';
                        $cabinClass = $bookingClassList[0]['cabin'] ?? 'ECONOMY';
                        $cabinClass = strtoupper($cabinClass) === 'EXECUTIVE_ECONOMY' ? 'P' : 'Y';

                        // Create segment
                        $segments[] = [
                            'ref_id' => $segmentRefId,
                            'operating_carrier' => [
                                'name' => $airline['name'],
                                'iata' => $airline['iata_code'],
                                'logo' => $airline['logo_url'],
                            ],
                            'departure_at' => $departureIso,
                            'arrival_at' => $arrivalIso,
                            'from' => [
                                'name' => $departure['airport']['name'] ?: $departure['airport']['city_name'] ?: $departure['city'] ?: 'Unknown Airport',
                                'iata' => $departure['airport']['iata_code'] ?: strtoupper(substr($departure['city'], 0, 3)) ?: '',
                                'city' => [
                                    'name' => $departure['airport']['city_name'] ?: $departure['airport']['name'] ?: $departure['city'] ?: 'Unknown City',
                                    'iata' => $departure['airport']['iata_city_code'] ?: $departure['airport']['iata_code'] ?: '',
                                    'code' => $departure['airport']['iata_city_code'] ?: $departure['airport']['iata_code'] ?: '',
                                ],
                            ],
                            'to' => [
                                'name' => $arrival['airport']['name'] ?: $arrival['airport']['city_name'] ?: $arrival['city'] ?: 'Unknown Airport',
                                'iata' => $arrival['airport']['iata_code'] ?: strtoupper(substr($arrival['city'], 0, 3)) ?: '',
                                'city' => [
                                    'name' => $arrival['airport']['city_name'] ?: $arrival['airport']['name'] ?: $arrival['city'] ?: 'Unknown City',
                                    'iata' => $arrival['airport']['iata_city_code'] ?: $arrival['airport']['iata_code'] ?: '',
                                    'code' => $arrival['airport']['iata_city_code'] ?: $arrival['airport']['iata_code'] ?: '',
                                ],
                            ],
                            'from_terminal' => [
                                'gate' => $departure['terminal'],
                            ],
                            'to_terminal' => [
                                'gate' => $arrival['terminal'],
                            ],
                            'cabin_class' => $cabinClass,
                            'booking_code' => $bookingCode,
                            'flight_time' => $flightTime,
                            'layover_time' => $layoverTime,
                            'flight_number' => $segment['flightNumber'] ?? '',
                            'distance' => $distance,
                            'aircraft' => [
                                'manufacturer' => $aircraft['name'] ?? '',
                                'model' => $aircraft['iata_code'] ?? '',
                            ],
                        ];
                    }

                    // Parse Fare Components
                    $fareComponents = $fareGroup['fareComponentList'] ?? [];
                    if (isset($fareComponents['fareKind'])) {
                        $fareComponents = [$fareComponents];
                    }

                    $fares = [];
                    foreach ($fareComponents as $fareComponent) {
                        $pricingOverview = $fareComponent['pricingOverview'] ?? [];
                        $passengerFareInfoList = $fareComponent['passengerFareInfoList'] ?? [];

                        // Normalize passenger fare info
                        if (isset($passengerFareInfoList['passengerTypeQuantity'])) {
                            $passengerFareInfoList = [$passengerFareInfoList];
                        }

                        $passengerFares = [];
                        foreach ($passengerFareInfoList as $passengerFareInfo) {
                            $fareInfo = $passengerFareInfo['pricingInfo'] ?? [];
                            $passengerQuantity = $passengerFareInfo['passengerTypeQuantity'] ?? [];

                            $passengerFares[] = [
                                'traveler_type' => strtolower($passengerQuantity['passengerType']['code'] ?? 'adult'),
                                'total_passenger' => (int) ($passengerQuantity['quantity'] ?? 1),
                                'base_price' => (float) ($fareInfo['equivBaseFare']['value'] ?? 0),
                                'surchage' => (float) ($fareInfo['surcharges']['totalAmount']['value'] ?? 0),
                                'taxes' => (float) ($fareInfo['taxes']['totalAmount']['value'] ?? 0) +
                                    (float) ($fareInfo['fees']['totalAmount']['value'] ?? 0) +
                                    (float) ($fareInfo['commissionVat']['totalAmount']['value'] ?? 0) +
                                    (float) ($fareInfo['commissions']['totalAmount']['value'] ?? 0),
                                'total_price' => (float) ($fareInfo['totalFare']['amount']['value'] ?? 0),
                            ];
                        }

                        $fareInfoList = $passengerFareInfoList[0]['fareInfoList'] ?? [];
                        if (isset($fareInfoList['fareGroupName'])) {
                            $fareInfoList = [$fareInfoList];
                        }

                        $fareName = '';
                        $baggageWeight = 20;
                        foreach ($fareInfoList as $fareInfo) {
                            $fareName = trim(($fareInfo['fareGroupName'] ?? '') . ' (' . ($fareInfo['resBookDesigCode'] ?? '') . ')');
                            $baggageWeight = $fareInfo['fareBaggageAllowance']['maxAllowedWeight']['weight'] ?? 20;
                            break; // Use the first fareInfo for name and baggage
                        }

                        $currencyCode = $pricingOverview['totalAmount']['currency']['code'] ?? 'PKR';

                        $baggagePolicies = [];
                        foreach ($segments as $segment) {
                            $baggagePolicies[] = [
                                'segment_ref_id' => $segment['ref_id'],
                                'type' => 'checked',
                                'traveler_type' => 'adult',
                                'description' => "1 checked bag(s), up to {$baggageWeight} kg",
                            ];
                        }

                        $fares[] = [
                            'ref_id' => $fareComponent['internalID'],
                            'name_class' => $fareName,
                            'class' => $fareInfoList[0]['resBookDesigCode'] ?? '',
                            'base_price' => (float) ($pricingOverview['totalBaseFare']['value'] ?? 0),
                            'surchage' => (float) ($pricingOverview['totalSurcharge']['value'] ?? 0),
                            'taxes' => (float) ($pricingOverview['totalTax']['value'] ?? 0),
                            'total_price' => (float) ($pricingOverview['totalAmount']['value'] ?? 0),
                            'billable_price' => (float) ($pricingOverview['totalAmount']['value'] ?? 0),
                            'margin_amount' => 0,
                            'margin_type' => 'amount',
                            'amount_type' => 'markup',
                            'currency' => [
                                'code' => $currencyCode,
                                'name' => $currencyCode === 'PKR' ? 'Pakistani Rupee' : 'US Dollar',
                                'symbol' => $currencyCode === 'PKR' ? 'Rs' : '$',
                                'flag' => "https://www.sooperfare.com/assets/client/images/flags/currency/{$currencyCode}.png",
                            ],
                            'baggage_policies' => $baggagePolicies,
                            'passenger_fares' => $passengerFares,
                        ];
                    }

                    // Create flight object
                    $firstSegment = $segments[0] ?? [];
                    $lastSegment = end($segments) ?: [];
                    reset($segments);

                    $departureAt = $firstSegment['departure_at'] ?? '';
                    $arrivalAt = $lastSegment['arrival_at'] ?? '';

                    // Calculate total travel time
                    $travelTimeMinutes = 0;
                    if (!empty($departureAt) && !empty($arrivalAt)) {
                        try {
                            $dtStart = new DateTime($departureAt);
                            $dtEnd = new DateTime($arrivalAt);
                            $diffSec = $dtEnd->getTimestamp() - $dtStart->getTimestamp();
                            $travelTimeMinutes = max(0, (int) floor($diffSec / 60));
                        } catch (\Exception $e) {
                            $travelTimeMinutes = 0;
                        }
                    }

                    // Fallback calculation
                    if ($travelTimeMinutes === 0) {
                        $sumFlight = 0;
                        foreach ($segments as $s) {
                            $sumFlight += (int) ($s['flight_time'] ?? 0);
                        }
                        $travelTimeMinutes = $sumFlight + $totalLayoverTime;
                    }

                    // Total distance
                    $totalMiles = 0;
                    foreach ($segments as $s) {
                        $totalMiles += (float) ($s['distance'] ?? 0);
                    }

                    // Create flight
                    $flights[] = [
                        'ref_id' => (string) \Illuminate\Support\Str::uuid(),
                        'flight_operation' => 'self',
                        'is_recommended' => 1,
                        'is_refundable' => true,
                        'recommended_priority' => 1,
                        'has_layovers' => count($segments) > 1,
                        'layovers_count' => max(0, count($segments) - 1),
                        'layovers_time' => $totalLayoverTime,
                        'change_of_plane' => false,
                        'travel_time' => $travelTimeMinutes,
                        'distance' => $totalMiles,
                        'marketing_carrier' => [
                            'name' => $firstSegment['operating_carrier']['name'] ?? 'PAKISTAN AIRLINES',
                            'iata' => $firstSegment['operating_carrier']['iata'] ?? 'PK',
                            'logo' => $firstSegment['operating_carrier']['logo'] ?? '',
                        ],
                        'departure_at' => $departureAt,
                        'arrival_at' => $arrivalAt,
                        'from' => $firstSegment['from'] ?? [],
                        'to' => $lastSegment['to'] ?? [],
                        'segments' => $segments,
                        'ancillaries' => [
                            'baggages' => [],
                            'meals' => [],
                            'seatplans' => [],
                            'ssrs' => [],
                        ],
                        'fares' => $fares,
                    ];
                }

                $result['leg']['flights'] = $flights;
                $results[] = $result;
            }
        }

        return $results;
    }

    // Add this helper function to convert duration strings to minutes
    private function convertDurationToMinutes($duration)
    {
        if (empty($duration)) {
            return 0;
        }

        try {
            $interval = new DateInterval($duration);
            $minutes = $interval->h * 60 + $interval->i;
            if ($interval->d > 0) {
                $minutes += $interval->d * 24 * 60;
            }
            return $minutes;
        } catch (\Exception $e) {
            return 0;
        }
    }

    public function fromPIARoundTrip($array)
    {
        $results = [];

        $availabilityResultList = $array['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList'] ?? [];
        $availabilityRoutes = $availabilityResultList['availabilityRouteList'] ?? [];

        // Normalize availabilityRoutes to array
        if (isset($availabilityRoutes['availabilityByDateList'])) {
            $availabilityRoutes = [$availabilityRoutes];
        }

        // Organize flights by direction
        $outboundFlights = [];
        $inboundFlights = [];

        // Process availability routes to extract flight segments
        foreach ($availabilityRoutes as $route) {
            $availabilityByDateList = $route['availabilityByDateList'] ?? [];
            if (isset($availabilityByDateList['originDestinationOptionList'])) {
                $availabilityByDateList = [$availabilityByDateList];
            }

            foreach ($availabilityByDateList as $dateData) {
                $date = $dateData['dateList'] ?? null;
                $options = $dateData['originDestinationOptionList'] ?? [];
                if (isset($options['fareComponentGroupList'])) {
                    $options = [$options];
                }

                foreach ($options as $option) {
                    $fareComponents = [];
                    $fareGroups = $option['fareComponentGroupList'] ?? [];
                    if (isset($fareGroups['boundList'])) {
                        $fareGroups = [$fareGroups];
                    }

                    foreach ($fareGroups as $fareGroup) {
                        $boundCode = $fareGroup['boundList']['boundCode'] ?? '';
                        $availFlightSegmentList = $fareGroup['boundList']['availFlightSegmentList'] ?? [];
                        if (isset($availFlightSegmentList['flightSegment'])) {
                            $availFlightSegmentList = [$availFlightSegmentList];
                        }

                        $fareComponentList = $fareGroup['fareComponentList'] ?? [];
                        if (isset($fareComponentList['internalID']) || isset($fareComponentList['fareKind'])) {
                            $fareComponentList = [$fareComponentList];
                        }

                        foreach ($fareComponentList as $fareComponent) {
                            if (empty($fareComponent)) {
                                continue;
                            }

                            $fareComponents[] = $fareComponent;
                        }

                    }
                    $flightData = [
                        'date' => $date,
                        'fareComponents' => $fareComponents,
                        'segments' => $availFlightSegmentList,
                    ];

                    if ($boundCode === 'Outbound') {
                        $outboundFlights[] = $flightData;
                    } elseif ($boundCode === 'Inbound') {
                        $inboundFlights[] = $flightData;
                    }
                }
            }
        }

        // Create roundtrip pairs
        foreach ($outboundFlights as $outboundFlight) {
            foreach ($inboundFlights as $inboundFlight) {
                $result = [
                    'ref_id' => (string) \Illuminate\Support\Str::uuid(),
                    'provider' => [
                        'name' => 'PIA',
                        'source' => 'PIA',
                        'identifier' => 'PIA',
                    ],
                    'leg' => [
                        'ref_id' => (string) \Illuminate\Support\Str::uuid(),
                        'flights' => [],
                        'trip_nature' => 'domestic', // Adjust based on actual data if needed
                        'fares_on_request' => false,
                    ],
                ];

                // Process outbound flight
                $outboundFlightData = $this->processFlightSegment($outboundFlight, 'outbound');
                if ($outboundFlightData) {
                    $result['leg']['flights'][] = $outboundFlightData;
                }

                // Process inbound flight
                $inboundFlightData = $this->processFlightSegment($inboundFlight, 'inbound');
                if ($inboundFlightData) {
                    $result['leg']['flights'][] = $inboundFlightData;
                }

                // Only add to results if both flights are present
                if (count($result['leg']['flights']) === 2) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * Process individual flight segment
     */
    private function processFlightSegment($flightData, $direction)
    {
        $fareComponent = $flightData['fareComponents'] ?? [];
        $segmentsData = $flightData['segments'] ?? [];

        if (empty($segmentsData)) {
            return null;
        }

        $segments = [];
        $totalLayoverTime = 0;
        $previousArrivalTimestamp = null;

        foreach ($segmentsData as $segmentData) {
            if (!is_array($segmentData)) {
                continue;
            }

            $segment = $segmentData['flightSegment'] ?? [];
            if (empty($segment)) {
                continue;
            }

            $segmentRefId = (string) \Illuminate\Support\Str::uuid();
            $departureIso = $segment['departureDateTime'] ?? '';
            $arrivalIso = $segment['arrivalDateTime'] ?? '';

            // Airline info
            $airline = [
                'name' => $segment['airline']['companyShortName'] ?? 'Pakistan International Airlines',
                'iata_code' => $segment['airline']['code'] ?? 'PK',
                'logo_url' => 'https://www.sooperfare.com/assets/client/images/airlines/PK.png',
            ];

            // Airport data
            $departure = [
                'airport' => [
                    'name' => $segment['departureAirport']['locationName'] ?? '',
                    'city_name' => $segment['departureAirport']['cityInfo']['city']['locationName'] ?? '',
                    'iata_code' => $segment['departureAirport']['locationCode'] ?? '',
                    'country' => [
                        'name' => $segment['departureAirport']['cityInfo']['city']['country']['locationName'] ?? 'Pakistan',
                        'alpha_2' => $segment['departureAirport']['cityInfo']['city']['country']['code'] ?? 'PK',
                        'flag' => 'https://www.sooperfare.com/assets/client/images/flags/png/pk.png',
                    ],
                ],
                'city' => [
                    'name' => $segment['departureAirport']['cityInfo']['city']['locationName'] ?? '',
                    'code' => $segment['departureAirport']['cityInfo']['city']['locationCode'] ?? '',
                    'country' => [
                        'name' => $segment['departureAirport']['cityInfo']['city']['country']['locationName'] ?? 'Pakistan',
                        'alpha_2' => $segment['departureAirport']['cityInfo']['city']['country']['code'] ?? 'PK',
                        'flag' => 'https://www.sooperfare.com/assets/client/images/flags/png/pk.png',
                    ],
                ],
                'terminal' => $segment['departureAirport']['terminal'] ?? null,
            ];

            $arrival = [
                'airport' => [
                    'name' => $segment['arrivalAirport']['locationName'] ?? '',
                    'city_name' => $segment['arrivalAirport']['cityInfo']['city']['locationName'] ?? '',
                    'iata_code' => $segment['arrivalAirport']['locationCode'] ?? '',
                    'country' => [
                        'name' => $segment['arrivalAirport']['cityInfo']['city']['country']['locationName'] ?? 'Pakistan',
                        'alpha_2' => $segment['arrivalAirport']['cityInfo']['city']['country']['code'] ?? 'PK',
                        'flag' => 'https://www.sooperfare.com/assets/client/images/flags/png/pk.png',
                    ],
                ],
                'city' => [
                    'name' => $segment['arrivalAirport']['cityInfo']['city']['locationName'] ?? '',
                    'code' => $segment['arrivalAirport']['cityInfo']['city']['locationCode'] ?? '',
                    'country' => [
                        'name' => $segment['arrivalAirport']['cityInfo']['city']['country']['locationName'] ?? 'Pakistan',
                        'alpha_2' => $segment['arrivalAirport']['cityInfo']['city']['country']['code'] ?? 'PK',
                        'flag' => 'https://www.sooperfare.com/assets/client/images/flags/png/pk.png',
                    ],
                ],
                'terminal' => $segment['arrivalAirport']['terminal'] ?? null,
            ];

            // Extract terminal from flightNotes
            $flightNotes = $segment['flightNotes'] ?? [];
            if (!is_array($flightNotes)) {
                $flightNotes = [];
            }

            foreach ($flightNotes as $note) {
                if (isset($note['deiCode']) && $note['deiCode'] === '98' && isset($note['explanation']) && $note['explanation'] === 'Arrival Terminal') {
                    $arrival['terminal'] = $note['note'] ?? null;
                } elseif (isset($note['deiCode']) && $note['deiCode'] === '99' && isset($note['explanation']) && $note['explanation'] === 'Departure Terminal') {
                    $departure['terminal'] = $note['note'] ?? null;
                }
            }

            // Aircraft info
            $aircraft = [
                'name' => $segment['equipment']['airEquipTypeModel'] ?? '',
                'iata_code' => $segment['equipment']['airEquipType'] ?? '',
            ];

            // Flight time calculation
            $flightTime = $this->convertDurationToMinutes($segment['journeyDuration'] ?? 'PT1H45M');

            // Layover time calculation
            $layoverTime = 0;
            if ($previousArrivalTimestamp !== null && !empty($departureIso)) {
                try {
                    $departureTimestamp = strtotime($departureIso);
                    $layoverTime = max(0, round(($departureTimestamp - $previousArrivalTimestamp) / 60));
                    $totalLayoverTime += $layoverTime;
                } catch (\Exception $e) {
                    $layoverTime = 0;
                }
            }

            if (!empty($arrivalIso)) {
                try {
                    $previousArrivalTimestamp = strtotime($arrivalIso);
                } catch (\Exception $e) {
                    $previousArrivalTimestamp = null;
                }
            }

            // Distance
            $distance = $segment['flownMileageQty'] ?? '0';

            // Booking class from bookingClassList
            $bookingClassList = $segmentData['bookingClassList'] ?? [];
            if (!is_array($bookingClassList) || !isset($bookingClassList[0])) {
                $bookingClassList = [$bookingClassList];
            }

            $bookingCode = $bookingClassList[0]['resBookDesigCode'] ?? '';
            $cabinClass = $bookingClassList[0]['cabin'] ?? 'ECONOMY';
            $cabinClass = strtoupper($cabinClass) === 'EXECUTIVE_ECONOMY' ? 'P' : 'economy';

            $segments[] = [
                'ref_id' => $segmentRefId,
                'operating_carrier' => [
                    'name' => $airline['name'],
                    'iata' => $airline['iata_code'],
                    'logo' => $airline['logo_url'],
                ],
                'departure_at' => $departureIso,
                'arrival_at' => $arrivalIso,
                'from' => [
                    'name' => $departure['airport']['name'] ?: $departure['airport']['city_name'] ?: 'Unknown Airport',
                    'iata' => $departure['airport']['iata_code'] ?: strtoupper(substr($departure['city']['name'], 0, 3)) ?: '',
                    'city' => $departure['city'],
                    'country' => $departure['airport']['country'],
                ],
                'to' => [
                    'name' => $arrival['airport']['name'] ?: $arrival['airport']['city_name'] ?: 'Unknown Airport',
                    'iata' => $arrival['airport']['iata_code'] ?: strtoupper(substr($arrival['city']['name'], 0, 3)) ?: '',
                    'city' => $arrival['city'],
                    'country' => $arrival['airport']['country'],
                ],
                'from_terminal' => [
                    'gate' => $departure['terminal'],
                ],
                'to_terminal' => [
                    'gate' => $arrival['terminal'],
                ],
                'cabin_class' => $cabinClass,
                'booking_code' => $bookingCode,
                'flight_time' => $flightTime,
                'layover_time' => $layoverTime,
                'flight_number' => $segment['flightNumber'] ?? '',
                'distance' => $distance,
                'aircraft' => [
                    'manufacturer' => $aircraft['name'] ?? '',
                    'model' => $aircraft['iata_code'] ?? '',
                ],
            ];
        }

        if (empty($segments)) {
            return null;
        }

        $firstSegment = $segments[0];
        $lastSegment = end($segments);
        reset($segments);

        $departureAt = $firstSegment['departure_at'] ?? '';
        $arrivalAt = $lastSegment['arrival_at'] ?? '';

        // Calculate total travel time
        $travelTimeMinutes = 0;
        if (!empty($departureAt) && !empty($arrivalAt)) {
            try {
                $dtStart = new DateTime($departureAt);
                $dtEnd = new DateTime($arrivalAt);
                $diffSec = $dtEnd->getTimestamp() - $dtStart->getTimestamp();
                $travelTimeMinutes = max(0, (int) floor($diffSec / 60));
            } catch (\Exception $e) {
                $travelTimeMinutes = 0;
            }
        }

        if ($travelTimeMinutes === 0) {
            $sumFlight = 0;
            foreach ($segments as $s) {
                $sumFlight += (int) ($s['flight_time'] ?? 0);
            }
            $travelTimeMinutes = $sumFlight + $totalLayoverTime;
        }

        $totalMiles = 0;
        foreach ($segments as $s) {
            $totalMiles += (float) ($s['distance'] ?? 0);
        }

        // Build fare information
        $fares = $this->buildFareInfo($fareComponent, $segments);

        return [
            'ref_id' => (string) \Illuminate\Support\Str::uuid(),
            'flight_operation' => 'self',
            'is_recommended' => 1,
            'is_refundable' => true,
            'recommended_priority' => 3,
            'has_layovers' => count($segments) > 1,
            'layovers_count' => max(0, count($segments) - 1),
            'layovers_time' => $totalLayoverTime,
            'change_of_plane' => false,
            'travel_time' => $travelTimeMinutes,
            'distance' => $totalMiles,
            'marketing_carrier' => [
                'name' => $firstSegment['operating_carrier']['name'] ?? 'Pakistan International Airlines',
                'iata' => $firstSegment['operating_carrier']['iata'] ?? 'PK',
                'logo' => $firstSegment['operating_carrier']['logo'] ?? '',
            ],
            'departure_at' => $departureAt,
            'arrival_at' => $arrivalAt,
            'from' => $firstSegment['from'] ?? [],
            'to' => $lastSegment['to'] ?? [],
            'segments' => $segments,
            'ancillaries' => [
                'baggages' => [],
                'meals' => [],
                'seatplans' => [],
                'ssrs' => [],
            ],
            'fares' => $fares,
        ];
    }

    /**
     * Build fare information for a flight
     */
   private function buildFareInfo($fareComponent, $segments)
{
    // Ensure $fareComponent is always an array
    $fareComponents = isset($fareComponent[0]) ? $fareComponent : [$fareComponent];
    $fares = [];

    foreach ($fareComponents as $component) {
        if (empty($component) || !is_array($component)) {
            continue;
        }
        $fareName = '';
        $bookingCode = '';
        $currencyCode = 'PKR';
        $baggagePolicies = [];
        $passengerFares = [];
        $pricingOverview = $component['pricingOverview'] ?? [];
        $passengerFareInfoList = $component['passengerFareInfoList'] ?? [];
        if (isset($passengerFareInfoList['fareInfoList'])) {
            $passengerFareInfoList = [$passengerFareInfoList];
        }

        foreach ($passengerFareInfoList as $passengerFareInfo) {
            $fareInfoList = $passengerFareInfo['fareInfoList'] ?? [];
            if (isset($fareInfoList['fareGroupName'])) {
                $fareInfoList = [$fareInfoList];
            }

            $pricingInfo = $passengerFareInfo['pricingInfo'] ?? [];
            $passengerQuantity = $passengerFareInfo['passengerTypeQuantity'] ?? [];

            foreach ($fareInfoList as $fareInfo) {
                if (!is_array($fareInfo)) {
                    continue;
                }

                $fareName = trim(($fareInfo['fareGroupName'] ?? 'Economy') . ' (' . ($fareInfo['resBookDesigCode'] ?? '') . ')');
                $baggageAllowance = $fareInfo['fareBaggageAllowance'] ?? [];
                $baggageWeight = $baggageAllowance['maxAllowedWeight']['weight'] ?? 20;
                $bookingCode = $fareInfo['resBookDesigCode'] ?? '';

                $currencyCode = $pricingInfo['totalFare']['amount']['currency']['code']
                    ?? $pricingOverview['totalAmount']['currency']['code']
                    ?? 'PKR';

                foreach ($segments as $segment) {
                    $segmentRefId = $segment['ref_id'] ?? (string) \Illuminate\Support\Str::uuid();

                    $baggagePolicies[] = [
                        'segment_ref_id' => $segmentRefId,
                        'type' => 'checked',
                        'title' => $fareInfo['fareGroupName'] ?? 'Economy',
                        'traveler_type' => $passengerQuantity['passengerType']['code'] ?? 'adult',
                        'description' => "1 checked bag(s), up to {$baggageWeight} kg",
                        'weight_limit' => (float) $baggageWeight,
                        'weight_unit' => 'kg',
                        'pieces' => 1,
                    ];
                   
                }
            }
            $passengerFares[] = [
                'traveler_type' => strtolower($passengerQuantity['passengerType']['code'] ?? 'adult'),
                'total_passenger' => (int) ($passengerQuantity['quantity'] ?? 1),
                'base_price' => (float) ($pricingInfo['equivBaseFare']['value'] ?? 0),
                'surchage' => (float) ($pricingInfo['surcharges']['totalAmount']['value'] ?? 0),
                'taxes' => (float) ($pricingInfo['taxes']['totalAmount']['value'] ?? 0) +
                    (float) ($pricingInfo['fees']['totalAmount']['value'] ?? 0) +
                    (float) ($pricingInfo['commissionVat']['totalAmount']['value'] ?? 0) +
                    (float) ($pricingInfo['commissions']['totalAmount']['value'] ?? 0),
                'total_price' => (float) ($pricingInfo['totalFare']['amount']['value'] ?? 0),
            ];
        }
         $basePrice = (float) $pricingOverview['totalBaseFare']['value']
                    ?? 0;

                $surcharge = (float) $pricingOverview['totalSurcharge']['value']
                    ?? 0;

                $taxes = (float) $pricingOverview['totalTax']['value']
                    ?? 0;

                $fees = (float) ($pricingOverview['fees']['totalAmount']['value'] ?? 0);

                $totalPrice = (float) $pricingOverview['totalAmount']['value']
                    ?? 0;

                $fares[] = [
                    'ref_id' =>  (string) \Illuminate\Support\Str::uuid(),
                    'internal_id' => $component['internalID'] ?? (string) \Illuminate\Support\Str::uuid(),
                    'name' => $fareInfo['fareGroupName'] ?? 'Economy',
                    'class' => $bookingCode,
                    'name_class' => $fareName,
                    'base_price' => $basePrice,
                    'surchage' => $surcharge,
                    'taxes' => $taxes,
                    'fees' => $fees,
                    'total_price' => $totalPrice,
                    'billable_price' => $totalPrice,
                    'margin_amount' => '0.00',
                    'margin_type' => 'amount',
                    'amount_type' => 'markup',
                    'is_refundable' => true,
                    'total_discount' => 0,
                    'available_seats' => 0,
                    'service_charges' => 0,
                    'ancillaries_charges' => 0,
                    'fare_policies' => [],
                    'currency' => [
                        'code' => $currencyCode,
                        'name' => $currencyCode === 'PKR' ? 'Pakistani Rupee' : 'US Dollar',
                        'symbol' => $currencyCode === 'PKR' ? 'Rs' : '$',
                        'flag' => "https://www.sooperfare.com/assets/client/images/flags/currency/{$currencyCode}.png",
                        'decimal' => 0,
                    ],
                    'baggage_policies' => $baggagePolicies,
                    'passenger_fares' => $passengerFares,
                    
                ];
    }

    return $fares;
}






}











