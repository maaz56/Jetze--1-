<?php
namespace App\Transformers;

use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class FlightTransformer
{
    public static function fromSooper($flightData)
    {
        // //LOg::info("SooperFlights: " . json_encode($flightData));

        $result = [];

        foreach ($flightData as $flight) {
            $flights = [];

            foreach ($flight['leg']['flights'] as $legFlight) {
                // Collect fares for this flight
                $fares = [];
                foreach ($legFlight['fares'] as $fare) {
                    $passengerFares = [];
                    foreach ($fare['passenger_fares'] as $pf) {
                        $passengerFares[] = [
                            "traveler_type" => $pf['traveler_type'],
                            "total_passenger" => $pf['total_passenger'],
                            "base_price" => $pf['base_price'],
                            "surchage" => $pf['surchage'] ?? 0,
                            "taxes" => $pf['taxes'],
                            "fees" => $pf['fees'] ?? 0,
                            "service_charges" => $pf['service_charges'] ?? 0,
                            "ancillaries_charges" => $pf['ancillaries_charges'] ?? 0,
                            "total_price" => $pf['total_price'],
                        ];
                    }

                    // Map baggage policies
                    $baggagePolicies = [];
                    if (!empty($fare['baggage_policies'])) {
                        foreach ($fare['baggage_policies'] as $bp) {
                            $baggagePolicies[] = [
                                "segment_ref_id" => $bp['segment_ref_id'],
                                "title" => $bp['title'],
                                "description" => $bp['description'],
                                "weight_limit" => $bp['weight_limit'],
                                "weight_unit" => $bp['weight_unit'],
                                "pieces" => $bp['pieces'],
                                "type" => $bp['type'],
                                "traveler_type" => $bp['traveler_type'],
                            ];
                        }
                    }

                    // Map fare policies
                    $farePolicies = [];
                    if (!empty($fare['fare_policies'])) {
                        foreach ($fare['fare_policies'] as $fp) {
                            $farePolicies[] = [
                                "segment_ref_id" => $fp['segment_ref_id'],
                                "title" => $fp['title'],
                                "description" => $fp['description'],
                                "type" => $fp['type'],
                                "price" => $fp['price'],
                                "price_type" => $fp['price_type'],
                                "currency" => $fp['currency'],
                                "traveler_type" => $fp['traveler_type'],
                            ];
                        }
                    }

                    $fares[] = [
                        "ref_id" => $fare['ref_id'],
                        "is_refundable" => $fare['is_refundable'],
                        "name" => $fare['name'],
                        "class" => $fare['class'],
                        "name_class" => $fare['name_class'],
                        "available_seats" => $fare['available_seats'] ?? 0,
                        "passenger_fares" => $passengerFares,
                        "fare_policies" => $farePolicies,
                        "baggage_policies" => $baggagePolicies,
                        "currency" => $fare['currency'],
                        "base_price" => $fare['base_price'],
                        "surchage" => $fare['surchage'] ?? 0,
                        "taxes" => $fare['taxes'],
                        "fees" => $fare['fees'] ?? 0,
                        "service_charges" => $fare['service_charges'] ?? 0,
                        "ancillaries_charges" => $fare['ancillaries_charges'] ?? 0,
                        "total_price" => $fare['total_price'],
                        "total_discount" => $fare['total_discount'] ?? 0,
                        "billable_price" => $fare['billable_price'],
                        "margin_amount" => $fare['margin_amount'],
                        "amount_type" => $fare['amount_type'],
                        "margin_type" => $fare['margin_type'],
                    ];
                }

                $flights[] = [
                    "ref_id" => $legFlight['ref_id'],
                    "flight_operation" => $legFlight['flight_operation'],
                    "is_recommended" => $legFlight['is_recommended'],
                    "is_refundable" => $legFlight['is_refundable'],
                    "recommended_priority" => $legFlight['recommended_priority'],
                    "has_layovers" => $legFlight['has_layovers'],
                    "layovers_count" => $legFlight['layovers_count'],
                    "layovers_time" => $legFlight['layovers_time'],
                    "change_of_plane" => $legFlight['change_of_plane'],
                    "travel_time" => $legFlight['travel_time'],
                    "distance" => $legFlight['distance'],
                    "marketing_carrier" => $legFlight['marketing_carrier'],
                    "departure_at" => $legFlight['departure_at'],
                    "arrival_at" => $legFlight['arrival_at'],
                    "from" => $legFlight['from'],
                    "to" => $legFlight['to'],
                    "segments" => $legFlight['segments'],
                    "ancillaries" => $legFlight['ancillaries'],
                    "fares" => $fares,
                ];
            }
            $flight['provider']['name'] = 'sooper';
            $result[] = [
                "ref_id" => $flight['ref_id'],
                "provider" => $flight['provider'],
                "leg" => [
                    "ref_id" => $flight['leg']['ref_id'],
                    "trip_nature" => $flight['leg']['trip_nature'],
                    "fares_on_request" => $flight['leg']['fares_on_request'],
                    "flights" => $flights,
                ],
            ];
        }

        // //LOg::info("Result Sooper: " . json_encode($result));
        return $result;
    }




    // Helper: convert AirSial datetime (e.g. "28-12-2025 11:30") to ISO 8601




    public static function fromSabre($sabreFlights)
    {

        $result = [];
        foreach ($sabreFlights as $index => $sabreFlight) {
            $flights = [];
            $pricingList = $sabreFlight['pricing'];
            $passengerInfo = $sabreFlight['passengerInfo'];
            $globalSegmentIndexMap = [];

            // Calculate total segments across all legs
            $totalSegments = 0;
            $lastFareCounter = 0;
            $globalSegmentCounter = 0;

            $legSegmentCounts = [];
            foreach ($sabreFlight['legs'] as $legIndex => $leg) {
                $segmentCount = count($leg['stops']);
                $legSegmentCounts[$legIndex] = $segmentCount;
                $totalSegments += $segmentCount;
            }

            foreach ($sabreFlight['legs'] as $legIndex => $leg) {

                $segments = [];
                $totalLayoverTime = 0;
                $previousArrivalTimestamp = null;
                $departureDate = $sabreFlight['dates'][$legIndex]['departureDate'];
                $currentDate = $sabreFlight['dates'][$legIndex]['departureDate'];
                foreach ($leg['stops'] as $index => $stop) {

                    $airline = $stop['airline'] ?? ["name" => "Unknown Airline", "iata_code" => "Unknown IATA", "logo_url" => "Unknown Logo"];
                    $departure = $stop['departure'];
                    $arrival = $stop['arrival'];
                    $aircraft = $stop['aircraft'] ?? ["name" => "", "iata_code" => ""];

                    $layoverTime = 0;
                    // Remove timezone offset (+HH:MM or -HH:MM)
                    $departureTime = preg_split('/[+-]/', $departure['time'])[0];
                    $arrivalTime = preg_split('/[+-]/', $arrival['time'])[0];


                    // Determine base departure timestamp
                    if ($previousArrivalTimestamp) {
                        // If we already have a previous arrival, continue from its day
                        $baseDepartureTimestamp = max($previousArrivalTimestamp, strtotime($currentDate . ' ' . $departureTime));
                    } else {
                        $baseDepartureTimestamp = strtotime($currentDate . ' ' . $departureTime);
                    }

                    // Build departure timestamp based on currentDate
                    $departureTimestamp = strtotime($currentDate . ' ' . $departureTime);

                    // If we already have a previous segment, ensure chronological order
                    if ($previousArrivalTimestamp !== null) {
                        // If this departure is before or equal to previous arrival, push it to next day
                        while ($departureTimestamp <= $previousArrivalTimestamp) {
                            $departureTimestamp = strtotime('+1 day', $departureTimestamp);
                        }
                    }

                    // Build arrival timestamp based on current (or next) date
                    $arrivalTimestamp = strtotime(date('Y-m-d', $departureTimestamp) . ' ' . $arrivalTime);

                    // If arrival happens before departure, it means arrival is next day
                    if ($arrivalTimestamp <= $departureTimestamp) {
                        $arrivalTimestamp = strtotime('+1 day', $arrivalTimestamp);
                    }

                    // Update currentDate for the next segment

                    $layoverTime = 0;
                    if ($previousArrivalTimestamp !== null) {
                        $layoverTime = round(($departureTimestamp - $previousArrivalTimestamp) / 60);
                        if ($layoverTime < 0)
                            $layoverTime = 0;
                        $totalLayoverTime += $layoverTime;
                    }


                    // Create ISO-formatted timestamps
                    $departureIso = date('Y-m-d\TH:i:s', $departureTimestamp);
                    $arrivalIso = date('Y-m-d\TH:i:s', $arrivalTimestamp);


                    $segmentRefId = (string) Str::uuid();
                    $segments[] = [
                        "ref_id" => $segmentRefId,
                        "operating_carrier" => [
                            "name" => $airline['name'] ?? "Unknown Airline",
                            "iata" => $airline['iata_code'] ?? "Unknown IATA",
                            "logo" => $airline['logo_url'] ?? "Unknown Logo",
                        ],
                        "departure_at" => $departureIso,
                        "arrival_at" => $arrivalIso,
                        "from" => [
                            "name" => !empty($departure['airport']['name'])
                                ? $departure['airport']['name']
                                : (!empty($departure['airport']['city_name'])
                                    ? $departure['airport']['city_name']
                                    : (!empty($departure['city'])
                                        ? $departure['city']
                                        : "Unknown Airport")),
                            "iata" => !empty($departure['airport']['iata_code'])
                                ? $departure['airport']['iata_code']
                                : (!empty($departure['city'])
                                    ? strtoupper(substr($departure['city'], 0, 3))
                                    : ""),
                            "city" => [
                                "name" => !empty($departure['airport']['city_name'])
                                    ? $departure['airport']['city_name']
                                    : (!empty($departure['airport']['name'])
                                        ? $departure['airport']['name']
                                        : (!empty($departure['city'])
                                            ? $departure['city']
                                            : "Unknown City")),
                                "iata" => !empty($departure['airport']['iata_city_code'])
                                    ? $departure['airport']['iata_city_code']
                                    : (!empty($departure['airport']['iata_code'])
                                        ? $departure['airport']['iata_code']
                                        : ""),
                                "code" => !empty($departure['airport']['iata_city_code'])
                                    ? $departure['airport']['iata_city_code']
                                    : (!empty($departure['airport']['iata_code'])
                                        ? $departure['airport']['iata_code']
                                        : ""),
                            ]
                        ],
                        "to" => [
                            "name" => !empty($arrival['airport']['name'])
                                ? $arrival['airport']['name']
                                : (!empty($arrival['airport']['city_name'])
                                    ? $arrival['airport']['city_name']
                                    : (!empty($arrival['city'])
                                        ? $arrival['city']
                                        : "Unknown Airport")),
                            "iata" => !empty($arrival['airport']['iata_code'])
                                ? $arrival['airport']['iata_code']
                                : (!empty($arrival['city'])
                                    ? strtoupper(substr($arrival['city'], 0, 3))
                                    : ""),
                            "city" => [
                                "name" => !empty($arrival['airport']['city_name'])
                                    ? $arrival['airport']['city_name']
                                    : (!empty($arrival['airport']['name'])
                                        ? $arrival['airport']['name']
                                        : (!empty($arrival['city'])
                                            ? $arrival['city']
                                            : "Unknown City")),
                                "iata" => !empty($arrival['airport']['iata_city_code'])
                                    ? $arrival['airport']['iata_city_code']
                                    : (!empty($arrival['airport']['iata_code'])
                                        ? $arrival['airport']['iata_code']
                                        : ""),
                                "code" => !empty($arrival['airport']['iata_city_code'])
                                    ? $arrival['airport']['iata_city_code']
                                    : (!empty($arrival['airport']['iata_code'])
                                        ? $arrival['airport']['iata_code']
                                        : ""),
                            ]
                        ],

                        "from_terminal" => [
                            "gate" => $departure['terminal'] ?? "",
                        ],
                        "to_terminal" => [
                            "gate" => $arrival['terminal'] ?? "",
                        ],
                        "cabin_class" => "Y", // Will be populated from fare components
                        "booking_code" => "",
                        "flight_time" => $stop['duration'] ?? $leg['duration'],
                        "layover_time" => $layoverTime,
                        "flight_number" => $stop['flightNumber'],
                        "distance" => $leg['totalMilesFlown'],
                        "aircraft" => [
                            "manufacturer" => $aircraft['name'],
                            "model" => $aircraft['iata_code'],
                        ],
                    ];
                    $previousArrivalTimestamp = $arrivalTimestamp;
                    $currentDate = date('Y-m-d', $arrivalTimestamp);

                    $globalSegmentIndexMap[$globalSegmentCounter] = $segmentRefId;
                    $globalSegmentCounter++;
                }

                // Create segment index map for this leg
                $segmentIndexMap = [];
                foreach ($segments as $index => $segment) {
                    $segmentIndexMap[$index] = $segment['ref_id'];
                }

                $firstStop = $leg['stops'][0]['departure'];
                $lastStop = end($leg['stops'])['arrival'];
                $firstStopTime = preg_split('/[+-]/', $firstStop['time'])[0];
                $lastStopTime = preg_split('/[+-]/', $lastStop['time'])[0];


                // Convert both times to timestamps using the base departure date
                $firstStopTimestamp = strtotime($departureDate . ' ' . $firstStopTime);
                $lastStopTimestamp = strtotime($departureDate . ' ' . $lastStopTime);

                // 🕐 If arrival happens before or equal to departure, add one day
                if ($lastStopTimestamp <= $firstStopTimestamp) {
                    $lastStopTimestamp = strtotime('+1 day', $lastStopTimestamp);
                }

                // Build ISO strings
                $departureAt = date('Y-m-d\TH:i:s', $firstStopTimestamp);
                $arrivalAt = date('Y-m-d\TH:i:s', $lastStopTimestamp);

                $tripNature = ($firstStop['country'] ?? '') === ($lastStop['country'] ?? '') ? "domestic" : "international";
                $legCount = count($sabreFlight['legs'] ?? []); // Get number of legs
                $legCount = $legCount > 0 ? $legCount : 1; // Avoid division by zero

                // Passenger fares



                $flightFares = [];
                $passengerFares = [];
                foreach ($pricingList as $pricingIndex => $pricing) {
                    // Calculate segment offset for this leg
                    // Calculate segment offset for this leg
                    $segmentOffset = 0;
                    $segmentsBookingCode = [];
                    for ($i = 0; $i < $legIndex; $i++) {
                        $segmentOffset += $legSegmentCounts[$i];
                    }
                    $passengerFares = [];

                    $passengers = $pricing['passenger_fares'] ?? [];

                    foreach ($passengers as $passenger) {
                        $passengerTotalFare = $passenger['passengerTotalFare'] ?? [];

                        $passengerFares[] = [
                            'offerItemId' => $passenger['offerItemId'] ?? '',
                            'serviceId' => $passenger['serviceId'] ?? '',
                            'traveler_type' => strtolower($passenger['passengerType'] ?? ''),
                            'total_passenger' => $passenger['passengerNumber'] ?? 1,
                            'base_price' => (($passengerTotalFare['equivalentAmount'] ?? 0) * $passenger['passengerNumber']) / $legCount,
                            'surchage' => 0,
                            'taxes' => (($passengerTotalFare['totalTaxAmount'] ?? 0) * $passenger['passengerNumber']) / $legCount,
                            'fees' => 0,
                            'service_charges' => 0,
                            'ancillaries_charges' => 0,
                            'total_price' => (
                                ((($passengerTotalFare['equivalentAmount'] ?? 0) +
                                    ($passengerTotalFare['totalTaxAmount'] ?? 0)) * $passenger['passengerNumber'])
                            ) / $legCount,
                        ];

                        $segmentsBookingCode = [];

                        // Map only booking codes for this leg's segments
                        $fareComponents = $passenger['fareComponents'] ?? [];
                        $segmentStart = array_sum(array_slice($legSegmentCounts, 0, $legIndex)); // offset
                        $segmentEnd = $segmentStart + $legSegmentCounts[$legIndex];

                        $currentSegment = 0;
                        foreach ($fareComponents as $fareComponent) {
                            foreach ($fareComponent['segments'] as $fareSegment) {
                                if ($currentSegment >= $segmentStart && $currentSegment < $segmentEnd) {
                                    $segmentsBookingCode[] = [
                                        'segment_ref_id' => $globalSegmentIndexMap[$currentSegment] ?? null,
                                        'booking_code' => $fareSegment['segment']['bookingCode'] ?? '',
                                    ];
                                }
                                $currentSegment++;
                            }
                        }

                    }

                    // Map baggage policies for this leg's segments
                    $baggagePolicies = [];
                    foreach ($pricing['baggage_policies'] as $bp) {
                        $originalSegmentIndex = $bp['segment_ref_id'];
                        // Check if this baggage policy belongs to current leg's segments
                        if ($originalSegmentIndex >= $segmentOffset && $originalSegmentIndex < $segmentOffset + $legSegmentCounts[$legIndex]) {
                            $localSegmentIndex = $originalSegmentIndex - $segmentOffset;
                            $mappedSegmentId = $segmentIndexMap[$localSegmentIndex] ?? null;


                            $baggagePolicies[] = [
                                "segment_ref_id" => $mappedSegmentId,
                                "title" => $bp['title'] ?? "",
                                "booking_code" => $bp['booking_code'] ?? "",
                                "description" => $bp['description'] ?? "",
                                "weight_limit" => $bp['weight_limit'] ?? "",
                                "weight_unit" => $bp['weight_unit'] ?? "",
                                "pieces" => $bp['pieces'] ?? "",
                                "type" => $bp['type'] ?? "",
                                "traveler_type" => $bp['traveler_type'] == 'adt' ? "adult" : ($bp['traveler_type'] == 'cnn' ? "child" : ($bp['traveler_type'] == 'inf' ? "infant" : $bp['traveler_type'])),
                            ];
                        }
                    }
                    // Debug: Log segment offset and leg segment counts


                    $legFareComponents = [];
                    foreach ($segments as $fcIndex => $segment) {
                        $segments[$fcIndex]['booking_code'] = $pricing['fareComponents'][$fcIndex]['bookingCode'] ?? '';
                        $fareComponent = $pricing['fareComponents'][$fcIndex] ?? [];
                        $legFareComponents[] = [
                            "fare_basis_code" => $fareComponent['fareBasisCode'] ?? null,
                            "brand_code" => $fareComponent['brandCode'] ?? null,
                            "brand_name" => $fareComponent['brandName'] ?? "Economy",
                            "cabin_class" => $fareComponent['cabinCode'] ?? "Y",
                            "available_seats" => $fareComponent['seatsAvailable'] ?? 0,
                        ];
                    }

                    // Use the first fare component as a sample for this leg
                    $firstFareComponent = $legFareComponents[0] ?? [];


                    $flightFares[] = [
                        "ref_id" => (string) Str::uuid(),
                        "offerId" => $pricing['offerId'] ?? '',
                        "offer_item_id" => $pricing['offer_item_id'] ?? '',
                        "service_id" => $pricing['service_id'] ?? '',
                        "is_refundable" => !($passengerInfo[0]['nonRefundable'] ?? false),
                        "name" => $firstFareComponent['brand_name'] ?? "Economy",
                        "fare_basis_code" => $firstFareComponent['fare_basis_code'] ?? null,
                        "brand_code" => $firstFareComponent['brand_code'] ?? null,
                        "name_class" => $firstFareComponent['brand_name'] ?? "Economy",
                        "available_seats" => $firstFareComponent['available_seats'] ?? 0,
                        "passenger_fares" => $passengerFares,
                        "fare_policies" => [],
                        "booking_codes" => $segmentsBookingCode,
                        "baggage_policies" => $baggagePolicies,
                        "currency" => [
                            "name" => "Currency",
                            "code" => $pricing['currency'],
                            "symbol" => $pricing['currency'],
                            "decimal" => 0,
                            "flag" => "https://www.sooperfare.com/assets/client/images/flags/currency/" . $pricing['currency'] . ".png",
                        ],
                        "taxes" => $pricing['totalTaxAmount'] / count($sabreFlight['legs']),
                        "base_price" => $pricing['totalPrice'] / count($sabreFlight['legs']),
                        "total_price" => $pricing['totalPrice'] / count($sabreFlight['legs']),
                        "billable_price" => ($pricing['totalPrice'] + $pricing['totalTaxAmount']) / count($sabreFlight['legs']),
                        "margin_type" => $airline['margin_type'] ?? "amount",
                        "margin_amount" => $airline['margin_amount'] ?? 0,
                        "amount_type" => $airline['amount_type'] ?? "fixed",
                    ];
                    $passengerFares = [];
                }

                $flights[] = [
                    "ref_id" => (string) Str::uuid(),
                    "flight_operation" => "self",
                    "is_recommended" => 1,
                    "is_refundable" => !($passengerInfo[0]['nonRefundable'] ?? false),
                    "recommended_priority" => 1,
                    "has_layovers" => count($leg['stops']) > 1,
                    "layovers_count" => max(0, count($leg['stops']) - 1),
                    "layovers_time" => $totalLayoverTime,
                    "change_of_plane" => false,
                    "travel_time" => $leg['duration'],
                    "distance" => $leg['totalMilesFlown'],
                    "marketing_carrier" => [
                        "name" => $leg['stops'][0]['airline']['name'] ?? "Unknown Airline",
                        "iata" => $leg['stops'][0]['airline']['iata_code'] ?? "Unknown IATA",
                        "logo" => $leg['stops'][0]['airline']['logo_url'] ?? "Unknown Logo",
                    ],
                    "departure_at" => $departureAt,
                    "arrival_at" => $arrivalAt,
                    "from" => [
                        "name" => !empty($firstStop['airport']['name'])
                            ? $firstStop['airport']['name']
                            : (!empty($firstStop['airport']['city_name'])
                                ? $firstStop['airport']['city_name']
                                : ($firstStop['city'] ?? "Unknown Airport")),
                        "iata" => !empty($firstStop['airport']['iata_code'])
                            ? $firstStop['airport']['iata_code']
                            : (!empty($firstStop['airport']['iata_city_code'])
                                ? $firstStop['airport']['iata_city_code']
                                : ($firstStop['city'] ?? "")),
                        "city" => [
                            "name" => !empty($firstStop['airport']['city_name'])
                                ? $firstStop['airport']['city_name']
                                : ($firstStop['city'] ?? ""),
                            "iata" => !empty($firstStop['airport']['iata_city_code'])
                                ? $firstStop['airport']['iata_city_code']
                                : ($firstStop['city'] ?? ""),
                            "code" => !empty($firstStop['airport']['iata_city_code'])
                                ? $firstStop['airport']['iata_city_code']
                                : ($firstStop['city'] ?? ""),
                        ]
                    ],
                    "to" => [
                        "name" => !empty($lastStop['airport']['name'])
                            ? $lastStop['airport']['name']
                            : (!empty($lastStop['airport']['city_name'])
                                ? $lastStop['airport']['city_name']
                                : ($lastStop['city'] ?? "Unknown Airport")),
                        "iata" => !empty($lastStop['airport']['iata_code'])
                            ? $lastStop['airport']['iata_code']
                            : (!empty($lastStop['airport']['iata_city_code'])
                                ? $lastStop['airport']['iata_city_code']
                                : ($lastStop['city'] ?? "")),
                        "city" => [
                            "name" => !empty($lastStop['airport']['city_name'])
                                ? $lastStop['airport']['city_name']
                                : ($lastStop['city'] ?? ""),
                            "iata" => !empty($lastStop['airport']['iata_city_code'])
                                ? $lastStop['airport']['iata_city_code']
                                : ($lastStop['city'] ?? ""),
                            "code" => !empty($lastStop['airport']['iata_city_code'])
                                ? $lastStop['airport']['iata_city_code']
                                : ($lastStop['city'] ?? ""),
                        ]
                    ],

                    "segments" => $segments,
                    "ancillaries" => [
                        "baggages" => [],
                        "meals" => [],
                        "seatplans" => [],
                        "ssrs" => [],
                    ],
                    "fares" => $flightFares
                ];
            }




            $result[] = [
                "ref_id" => $sabreFlight['id'],
                "provider" => [
                    "name" => "sabre",
                    "source" => $sabreFlight['source'] ?? "unknown",
                    "identifier" => $sabreFlight['carrierCode'] ?? ($flights[0]['marketing_carrier']['iata'] ?? ""),
                ],
                "leg" => [
                    "ref_id" => $sabreFlight['id'],
                    "trip_nature" => $tripNature ?? "domestic",
                    "fares_on_request" => false,
                    "flights" => $flights,
                ]
            ];
        }
        return $result;
    }

    // public static function fromSabre($sabreFlights)
    // {
    //     $result = [];

    //     foreach ($sabreFlights as $sabreFlight) {
    //         $flights = [];

    //         foreach ($sabreFlight['legs'] as $leg) {
    //             $segments = [];
    //             $totalLayoverTime = 0;
    //             $previousArrivalTime = null;
    //             $passenger_information = [];
    //             // Collect all stops for this leg
    //             foreach ($leg['stops'] as $index => $stop) {
    //                 // Airline, departure, arrival, aircraft
    //                 $airline = $stop['airline'] ?? "Unknown Airline";
    //                 $departure = $stop['departure'];
    //                 $arrival = $stop['arrival'];
    //                 $aircraft = $stop['aircraft'];

    //                 $pricing = $sabreFlight['pricing'];
    //                 $passengerInfo = $sabreFlight['passengerInfo'];
    //                 $departureDate = $sabreFlight['dates'][0]['departureDate'];

    //                 // First + last stops
    //                 $firstStop = $leg['stops'][0]['departure'];
    //                 $lastStop = end($leg['stops'])['arrival'];

    //                 $departureAt = $departureDate . 'T' . $firstStop['time'];
    //                 $arrivalAt = $departureDate . 'T' . $lastStop['time'];

    //                 // Domestic vs International
    //                 $tripNature = $departure['country'] === $arrival['country']
    //                     ? "domestic"
    //                     : "international";

    //                 // Cabin, booking, seats
    //                 $cabinClass = "Y";
    //                 $bookingCode = "Y";
    //                 $seatsAvailable = 0;
    //             $passenger_information = [];
    //             foreach ($passengerInfo as $passenger) {
    //                 $traveler = [
    //                     'offerItemId' => $passenger['offerItemId'] ?? '',
    //                     'serviceId' => $passenger['serviceId'] ?? '',
    //                     'passengerType' => $passenger['passengerType'] ?? '',
    //                     'passengerNumber' => $passenger['passengerNumber'] ?? 0,
    //                     'passengers' => [],
    //                 ];

    //                 if (isset($passenger['passengers']) && is_array($passenger['passengers'])) {
    //                     foreach ($passenger['passengers'] as $passenger_detail) {
    //                         $passengerRef = $passenger_detail['ref'];
    //                         $passengerReference = $sabreFlight['passenger'][$passengerRef - 1]['passengerId'] ?? '';
    //                         $traveler['passengers'][] = [
    //                             'passengerRefId' => $passengerReference,
    //                         ];
    //                     }
    //                 }

    //                 // Extract cabin/booking/seats for this traveler type
    //                 foreach ($passenger['fareComponents'] as $fareComponent) {
    //                     foreach ($fareComponent['segments'] as $segment) {
    //                         $traveler['cabinClass'] = $segment['segment']['cabinCode'] ?? 'Y';
    //                         $traveler['bookingCode'] = $segment['segment']['bookingCode'] ?? 'Y';
    //                         $traveler['seatsAvailable'] = $segment['segment']['seatsAvailable'] ?? 0;
    //                     }
    //                 }

    //                 $passenger_information[] = $traveler;
    //             }

    //                 // Passenger fares
    //                 $passengerFares = [];
    //                 foreach ($passengerInfo as $passenger) {
    //                     $passengerTotalFare = $passenger['passengerTotalFare'];
    //                     $passengerFares[] = [
    //                         "traveler_type" => strtolower($passenger['passengerType']),
    //                         "total_passenger" => $passenger['passengerNumber'],
    //                         "base_price" => $passengerTotalFare['totalFare'],
    //                         "surchage" => 0,
    //                         "taxes" => $passengerTotalFare['totalTaxAmount'],
    //                         "fees" => 0,
    //                         "service_charges" => 0,
    //                         "ancillaries_charges" => 0,
    //                         "total_price" => $passengerTotalFare['totalFare'] + $passengerTotalFare['totalTaxAmount'],
    //                     ];
    //                 }

    //                 // Calculate layover time
    //                 $layoverTime = 0;
    //                 if ($previousArrivalTime) {
    //                     $layoverTime = (strtotime($departure['time']) - strtotime($previousArrivalTime)) / 60;
    //                     $totalLayoverTime += $layoverTime;
    //                 }
    //                 $previousArrivalTime = $arrival['time'];

    //                 $departureIso = $departureDate . 'T' . $departure['time'];
    //                 $arrivalIso = $departureDate . 'T' . $arrival['time'];

    //                 // Add each stop as a segment
    //                 $segments[] = [
    //                     "ref_id" => (string) Str::uuid(),
    //                     "operating_carrier" => [
    //                         "name" => $airline['name'] ?? "Unknown Airline",
    //                         "iata" => $airline['iata_code'] ?? "Unknown IATA",
    //                         "logo" => $airline['logo_url'] ?? "Unknown Logo",
    //                     ],
    //                     "departure_at" => $departureIso,
    //                     "arrival_at" => $arrivalIso,
    //                     "from" => [
    //                         "name" => $departure['airport']['name'] ?? "",
    //                         "iata" => $departure['airport']['iata_code'] ?? "",
    //                         "city" => [
    //                             "name" => $departure['airport']['city_name'] ?? "",
    //                             "iata" => $departure['airport']['iata_city_code'] ?? "",
    //                             "code" => $departure['airport']['iata_city_code'] ?? "",
    //                         ],
    //                     ],
    //                     "to" => [
    //                         "name" => $arrival['airport']['name'] ?? "",
    //                         "iata" => $arrival['airport']['iata_code'] ?? "",
    //                         "city" => [
    //                             "name" => $arrival['airport']['city_name'] ?? "",
    //                             "iata" => $arrival['airport']['iata_city_code'] ?? "",
    //                             "code" => $arrival['airport']['iata_city_code'] ?? "",
    //                         ],
    //                     ],
    //                     "from_terminal" => [
    //                         "gate" => $departure['terminal'] ?? "",
    //                     ],
    //                     "to_terminal" => [
    //                         "gate" => $arrival['terminal'] ?? "",
    //                     ],
    //                     "cabin_class" => $cabinClass,
    //                     "flight_time" => $stop['duration'] ?? $leg['duration'],
    //                     "layover_time" => $layoverTime,
    //                     "flight_number" => $stop['flightNumber'],
    //                     "distance" => $leg['totalMilesFlown'],
    //                     "aircraft" => [
    //                         "manufacturer" => $aircraft['name'],
    //                         "model" => $aircraft['iata_code'],
    //                     ],
    //                 ];
    //             }

    //             // First + last stops for the leg
    //             $firstStop = $leg['stops'][0]['departure'];
    //             $lastStop = $leg['stops'][count($leg['stops']) - 1]['arrival'];

    //             // Only 1 flight per leg, all layovers inside segments
    //             $flights[] = [
    //                 "ref_id" => (string) Str::uuid(),
    //                 "flight_operation" => "self",
    //                 "is_recommended" => 1,
    //                 "is_refundable" => !$passengerInfo[0]['nonRefundable'] ?? false,
    //                 "recommended_priority" => 1,
    //                 "has_layovers" => count($leg['stops']) > 1,
    //                 "layovers_count" => max(0, count($leg['stops']) - 1),
    //                 "layovers_time" => $totalLayoverTime,
    //                 "change_of_plane" => false,
    //                 "travel_time" => $leg['duration'],
    //                 "distance" => $leg['totalMilesFlown'],
    //                 "marketing_carrier" => [
    //                     "name" => $leg['stops'][0]['airline']['name'] ?? "Unknown Airline",
    //                     "iata" => $leg['stops'][0]['airline']['iata_code'] ?? "Unknown IATA",
    //                     "logo" => $leg['stops'][0]['airline']['logo_url'] ?? "Unknown Logo",
    //                 ],
    //                 "departure_at" => $departureAt,
    //                 "arrival_at" => $arrivalAt,
    //                 "from" => [
    //                     "name" => $firstStop['airport']['name'] ?? "",
    //                     "iata" => $firstStop['airport']['iata_code'] ?? "",
    //                     "city" => [
    //                         "name" => $firstStop['airport']['city_name'] ?? "",
    //                         "iata" => $firstStop['airport']['iata_city_code'] ?? "",
    //                         "code" => $firstStop['airport']['iata_city_code'] ?? "",
    //                     ],
    //                 ],
    //                 "to" => [
    //                     "name" => $lastStop['airport']['name'] ?? "",
    //                     "iata" => $lastStop['airport']['iata_code'] ?? "",
    //                     "city" => [
    //                         "name" => $lastStop['airport']['city_name'] ?? "",
    //                         "iata" => $lastStop['airport']['iata_city_code'] ?? "",
    //                         "code" => $lastStop['airport']['iata_city_code'] ?? "",
    //                     ],
    //                 ],
    //                 "segments" => $segments,
    //                 "ancillaries" => [
    //                     "baggages" => [],
    //                     "meals" => [],
    //                     "seatplans" => [],
    //                     "ssrs" => [],
    //                 ],
    //                 "fares" => [
    //                     [
    //                         "ref_id" => (string) Str::uuid(),
    //                         "offerId" => $pricing['offerId'] ?? '',
    //                         "is_refundable" => !$passengerInfo[0]['nonRefundable'],
    //                         "name" => "Economy",
    //                         "class" => $bookingCode,
    //                         "name_class" => "Economy",
    //                         "available_seats" => $seatsAvailable,
    //                         "passenger_information" => $passenger_information,

    //                         "passenger_fares" => $passengerFares,
    //                         "fare_policies" => [],
    //                         "baggage_policies" => [],
    //                         "currency" => [
    //                             "name" => "Currency",
    //                             "code" => $pricing['currency'],
    //                             "symbol" => $pricing['currency'],
    //                             "decimal" => 0,
    //                             "flag" => "https://www.sooperfare.com/assets/client/images/flags/currency/" . $pricing['currency'] . ".png",
    //                         ],
    //                         "taxes" => $pricing['totalTaxAmount'],
    //                         "base_price" => $pricing['totalPrice'],
    //                         "total_price" => $pricing['totalPrice'],
    //                         "billable_price" => $pricing['totalPrice'] + $pricing['totalTaxAmount'],
    //                         "margin_type" => $airline['margin_type'] ?? "amount",
    //                         "margin_amount" => $airline['margin_amount'] ?? 0,
    //                         "amount_type" => $airline['amount_type'] ?? "fixed",
    //                     ]
    //                 ],
    //             ];
    //         }

    //         $result[] = [
    //             "ref_id" => $sabreFlight['id'],
    //             "provider" => [
    //                 "name" => "sabre",
    //                 "source" => $sabreFlight['source'] ?? "unknown",
    //                 "identifier" => $sabreFlight['carrierCode'] ?? $flights[0]['marketing_carrier']['iata'],
    //             ],
    //             "leg" => [
    //                 "ref_id" => $sabreFlight['id'],
    //                 "trip_nature" => $tripNature,
    //                 "fares_on_request" => false,
    //                 "flights" => $flights,
    //             ],
    //         ];
    //     }

    //     return $result;
    // }




}

















