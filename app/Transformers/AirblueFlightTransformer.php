<?php
namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class AirblueFlightTransformer
{

    protected $airportTimezones = [];

    public function fromAirblue($flightData, $airlineParams)
    {
        Log::info("Airblue Flight Data: " . $flightData);

        if (is_string($flightData)) {
            $flightData = json_decode($flightData, true);
        }
        $provider = [
            "name" => "airblue",
            "source" => "Airblue",
            "identifier" => "PA",
            "contentSource" => "LCC",
        ];

        $result = [];

        // Extract itineraries
        $pricedItineraries = $flightData['Body']['AirLowFareSearchResponse']['AirLowFareSearchResult']['PricedItineraries']['PricedItinerary'] ?? [];

        // Normalize to array
        if (isset($pricedItineraries['AirItinerary'])) {
            $pricedItineraries = [$pricedItineraries];
        }

        // Separate outbound/inbound. Prefer route-direction classification because
        // some Airblue responses may not reliably use OriginDestinationRefNumber=2.
        $outbound = [];
        $inbound = [];
        $requestedOutboundOrigin = $airlineParams['origin'] ?? null;
        $requestedOutboundDestination = $airlineParams['destination'] ?? null;
        $requestedInboundOrigin = $requestedOutboundDestination;
        $requestedInboundDestination = $requestedOutboundOrigin;

        $extractDirection = function ($itinerary) use (
            $requestedOutboundOrigin,
            $requestedOutboundDestination,
            $requestedInboundOrigin,
            $requestedInboundDestination
        ) {
            $segments = $itinerary['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment'] ?? [];
            if (isset($segments['@attributes'])) {
                $segments = [$segments];
            }

            if (empty($segments) || !is_array($segments)) {
                return null;
            }

            $first = $segments[0] ?? [];
            $last = $segments[count($segments) - 1] ?? [];
            $from = $first['DepartureAirport']['@attributes']['LocationCode'] ?? null;
            $to = $last['ArrivalAirport']['@attributes']['LocationCode'] ?? null;

            if (
                $requestedOutboundOrigin &&
                $requestedOutboundDestination &&
                $from === $requestedOutboundOrigin &&
                $to === $requestedOutboundDestination
            ) {
                return 'outbound';
            }

            if (
                $requestedInboundOrigin &&
                $requestedInboundDestination &&
                $from === $requestedInboundOrigin &&
                $to === $requestedInboundDestination
            ) {
                return 'inbound';
            }

            return null;
        };

        foreach ($pricedItineraries as $itinerary) {
            $direction = $extractDirection($itinerary);
            if ($direction === 'outbound') {
                $outbound[] = $itinerary;
                continue;
            }
            if ($direction === 'inbound') {
                $inbound[] = $itinerary;
                continue;
            }

            // Fallback to OriginDestinationRefNumber only when direction
            // cannot be inferred from route.
            $refNumber = $itinerary['@attributes']['OriginDestinationRefNumber'] ?? '1';
            if ($refNumber == '1') {
                $outbound[] = $itinerary;
            } elseif ($refNumber == '2') {
                $inbound[] = $itinerary;
            }


        }

        // Helper: group by RPH
        $groupByRPH = function ($list) {
            $grouped = [];
            foreach ($list as $it) {
                $rph = $it['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['@attributes']['RPH'] ?? null;
                if ($rph) {
                    $grouped[$rph][] = $it;
                }
            }
            return $grouped;
        };

        $outGroups = $groupByRPH($outbound);
        $inGroups = $groupByRPH($inbound);

        // For return search, do not send partial one-way data.
        // If inbound is missing, return no results.
        if (($airlineParams['flight_type'] ?? null) === 'return' && empty($inGroups)) {
            Log::info('Airblue return search skipped: inbound flight not found in response.');
            return [];
        }

        // CASE 1: One-way (only outbound)
        if (!empty($outGroups) && empty($inGroups)) {
            foreach ($outGroups as $rph => $groupItineraries) {
                $segments = $groupItineraries[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment'] ?? [];
                $itineraryRPH = $groupItineraries[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['@attributes']['RPH'] ?? [];
                if (isset($segments['@attributes'])) {
                    $segments = [$segments];
                }
                $flight = $this->buildAirblueFlight($segments, $groupItineraries, $airlineParams);
                $flight['RPH'] = $itineraryRPH;
                 if(count($flight['fares']) == 0){
                    Log::info("Skipping flight with zero total price: " . json_encode($flight));
                    continue; // Skip flights with zero total price
                }
                $flights = [$flight];

                $sector = collect($flights)
                    ->flatMap(fn($f) => [$f['from']['iata'], $f['to']['iata']])
                    ->implode('-');

                $provider['sector'] = $sector;
                $provider['travel_date'] = $flights[0]['departure_at'] ?? null;

                $result[] = [
                    "ref_id" => uniqid(),
                    "provider" => $provider,
                    "leg" => [
                        "ref_id" => uniqid(),
                        "trip_nature" => "international",
                        "fares_on_request" => false,
                        "flights" => [$flight],
                    ],
                ];
            }
            return $result;
        }

        // CASE 2: One-way (only inbound)
        if (!empty($inGroups) && empty($outGroups)) {
            foreach ($inGroups as $rph => $groupItineraries) {
                $segments = $groupItineraries[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment'] ?? [];
                $itineraryRPH = $groupItineraries[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['@attributes']['RPH'] ?? [];

                if (isset($segments['@attributes'])) {
                    $segments = [$segments];
                }

                $flight = $this->buildAirblueFlight($segments, $groupItineraries, $airlineParams);
                if(count($flight['fares']) == 0){
                    Log::info("Skipping flight with zero total price: " . json_encode($flight));
                    continue; // Skip flights with zero total price
                }
                $flight['RPH'] = $itineraryRPH;
                $flights = [$flight];

                $sector = collect($flights)
                    ->flatMap(fn($f) => [$f['from']['iata'], $f['to']['iata']])
                    ->implode('-');

                $provider['sector'] = $sector;
                $provider['travel_date'] = $flights[0]['departure_at'] ?? null;
                $result[] = [
                    "ref_id" => uniqid(),
                    "provider" => $provider,
                    "leg" => [
                        "ref_id" => uniqid(),
                        "trip_nature" => "international",
                        "fares_on_request" => false,
                        "flights" => [$flight],
                    ],
                ];
            }
            return $result;
        }

        // CASE 3: Round-trip — map each inbound with each outbound
        foreach ($outGroups as $outRph => $outGroup) {
            foreach ($inGroups as $inRph => $inGroup) {
                Log::info("Processing Outbound RPH: $outRph with Inbound RPH: $inRph");
                // Build outbound flight
                $outSegments = $outGroup[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment'] ?? [];
                $outSegmentsRPH = $outGroup[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['@attributes']['RPH'] ?? [];
                if (isset($outSegments['@attributes'])) {
                    $outSegments = [$outSegments];
                }
                $outFlight = $this->buildAirblueFlight($outSegments, $outGroup, $airlineParams);
                if(count($outFlight['fares']) == 0){
                    Log::info("Skipping flight with zero total price: " . json_encode($outFlight));
                    continue; // Skip flights with zero total price
                }
                $outFlight['RPH'] = $outSegmentsRPH;
                // Build inbound flight
                $inSegments = $inGroup[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['FlightSegment'] ?? [];
                $inSegmentsRPH = $inGroup[0]['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption']['@attributes']['RPH'] ?? [];
                if (isset($inSegments['@attributes'])) {
                    $inSegments = [$inSegments];
                }
                $inFlight = $this->buildAirblueFlight($inSegments, $inGroup, $airlineParams);
                if(count($inFlight['fares']) == 0){
                    Log::info("Skipping flight with zero total price: " . json_encode($inFlight));
                    continue; // Skip flights with zero total price
                }
                $inFlight['RPH'] = $inSegmentsRPH;


                $flights = [$outFlight, $inFlight];
                $sector = collect($flights)
                    ->flatMap(fn($f) => [$f['from']['iata'], $f['to']['iata']])
                    ->implode('-');

                $provider['sector'] = $sector;
                $provider['travel_date'] = $outFlight['departure_at'] ?? null;
                // Combine into one round-trip result
                $result[] = [
                    "ref_id" => uniqid(),
                    "provider" => $provider,
                    "leg" => [
                        "ref_id" => uniqid(),
                        "trip_nature" => "international",
                        "fares_on_request" => false,
                        "flights" => [$outFlight, $inFlight],

                    ],
                ];
            }
        }

        return $result;
    }

    public function buildAirblueFlight($segments, $groupItineraries, $params)
    {

        $segmentRefId = uniqid();
        $flightSegments = [];

        $firstDepartureUtc = null;
        $lastArrivalUtc = null;
        $totalLayoverMinutes = 0;
        $previousArrivalUtc = null;

        foreach ($segments as $index => $seg) {

            $attr = $seg['@attributes'];

            $departureAirportCode = $seg['DepartureAirport']['@attributes']['LocationCode'] ?? null;
            $arrivalAirportCode = $seg['ArrivalAirport']['@attributes']['LocationCode'] ?? null;
            $departure = $this->parseLocalDateTime($attr['DepartureDateTime'] ?? null, $departureAirportCode);
            $arrival = $this->parseLocalDateTime($attr['ArrivalDateTime'] ?? null, $arrivalAirportCode);
            $departureUtc = $departure ? $departure->copy()->utc() : null;
            $arrivalUtc = $arrival ? $arrival->copy()->utc() : null;

            // Handle overnight/multi-day arrivals when timezones differ or API sends ambiguous local date.
            if ($departureUtc && $arrivalUtc && $arrivalUtc->lt($departureUtc)) {
                $attempts = 0;
                while ($attempts < 3 && $arrivalUtc->lt($departureUtc)) {
                    if ($arrival) {
                        $arrival->addDay();
                        $arrivalUtc = $arrival->copy()->utc();
                    } else {
                        $arrivalUtc->addDay();
                    }
                    $attempts++;
                }
            }

            if ($index === 0) {
                $firstDepartureUtc = $departureUtc;
            }

            // Calculate layover (gap between previous arrival and current departure)
            $layoverMinutes = 0;
            if ($previousArrivalUtc && $departureUtc) {
                $layoverMinutes = max(0, $previousArrivalUtc->diffInMinutes($departureUtc, false));
                $totalLayoverMinutes += $layoverMinutes;
            }

            $previousArrivalUtc = $arrivalUtc;
            $lastArrivalUtc = $arrivalUtc;

            // Segment flight time (minutes only)
            $segmentMinutes = 0;
            if ($departureUtc && $arrivalUtc) {
                $segmentMinutes = max(0, $departureUtc->diffInMinutes($arrivalUtc, false));
            }

            $fromAirportDetails = Airport::where('iata_code', $departureAirportCode)->first();

            $toAirportDetails = Airport::where('iata_code', $arrivalAirportCode)->first();

            $from = $this->buildAirportData($fromAirportDetails);
            $to = $this->buildAirportData($toAirportDetails);

            $flightSegments[] = [
                "ref_id" => $segmentRefId,
                "RPH" => $attr['RPH'],
                "operating_carrier" => [
                    "name" => "Airblue",
                    "iata" => "PA",
                    "logo" => "https://api.sooperfare.com/assets/client/images/airlines/PA.png",
                ],
                "departure_at" => $attr['DepartureDateTime'],
                "arrival_at" => $attr['ArrivalDateTime'],
                "from" => $from,
                "to" => $to,
                "flight_number" => $attr['FlightNumber'],
                "cabin_class" => $attr['CabinClass'],
                "class_code" => $attr['ResBookDesigCode'],
                "status" => $attr['Status'],
                "stop_quantity" => (int) $attr['StopQuantity'],
                "flight_time" => $segmentMinutes, // numeric only
                "layover_time" => $layoverMinutes,
                "aircraft" => [
                    "model" => $seg['Equipment']['@attributes']['AirEquipType'] ?? ""
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | TOTAL TRAVEL TIME (segments + layovers)
        |--------------------------------------------------------------------------
        */

        $totalTravelMinutes = 0;

        if ($firstDepartureUtc && $lastArrivalUtc) {
            $totalTravelMinutes = max(0, $firstDepartureUtc->diffInMinutes($lastArrivalUtc, false));
        }

        $layoversCount = count($segments) > 1 ? count($segments) - 1 : 0;
        $hasLayovers = $layoversCount > 0;
        $fares = [];
        foreach ($groupItineraries as $itinerary) {

            $pricingInfo = $itinerary['AirItineraryPricingInfo'];
            $totalFare = $pricingInfo['ItinTotalFare'];
            $ptcBreakdown = $pricingInfo['PTC_FareBreakdowns']['PTC_FareBreakdown'] ?? null;

            if (!$ptcBreakdown){
            Log::info("Missing PTC_FareBreakdown for itinerary: " . json_encode($itinerary));    
            continue;}
            $baggageInfo = $pricingInfo['PTC_FareBreakdowns']['PTC_FareBreakdown']['FareInfo'][1]['PassengerFare']['FareBaggageAllowance']['@attributes'] ?? null;
             // Normalize PTC_FareBreakdown to array


    if (isset($ptcBreakdown['PassengerTypeQuantity'])) {
        // Single object → convert to array
        $passengerBreakDown = [$ptcBreakdown];
    } else {
        // Already array
        $passengerBreakDown = $ptcBreakdown;
    }

            $fareBasisCode = null;
            $passengerFares = [];
            $baggage_policies = [];
            foreach ($passengerBreakDown as $index => $paxBreakdown) {
                $passengerFare = $paxBreakdown['PassengerFare'];
                $passengerFares[] = [
                    'traveler_type' => $paxBreakdown['PassengerTypeQuantity']['@attributes']['Code'],
                    'total_passenger' => (int) $paxBreakdown['PassengerTypeQuantity']['@attributes']['Quantity'],
                    'base_price' => (float) $passengerFare['BaseFare']['@attributes']['Amount'],
                    'taxes' => (float) (isset($passengerFare['Taxes']['@attributes']['Amount']) ? $passengerFare['Taxes']['@attributes']['Amount'] : 0),
                    'taxCodes' => $passengerFare['Taxes']['Tax'] ?? "Null",
                    'fees' => (float) ($passengerFare['Fees']['@attributes']['Amount'] ?? 0),
                    'feeCodes' => isset($passengerFare['Fees']['Fee']['@attributes']) ? [$passengerFare['Fees']['Fee']] : $passengerFare['Fees']['Fee'] ?? [],
                    'total_price' => (float) $passengerFare['TotalFare']['@attributes']['Amount'],

                ];
                $fareInfo = $paxBreakdown['FareInfo'];
                if (isset($fareInfo['FareInfo'])) {
                    $fareInfo = [$fareInfo];
                }
                $baggageInfo = $fareInfo[1]['PassengerFare']['FareBaggageAllowance']['@attributes'] ?? null;
                $fareBasisCode = $fareInfo[0]['FareInfo']['@attributes']['FareBasisCode'] ?? null;
                $fareType = $fareInfo[0]['FareInfo']['@attributes']['FareType'] ?? null;
                if ($baggageInfo) {
                    $quantity = (int) ($baggageInfo['UnitOfMeasureQuantity'] ?? 0);
                    $unit = $baggageInfo['UnitOfMeasure'] ?? '';
                    $description = "Up to {$quantity} {$unit}";
                } else {
                    $quantity = 0;
                    $unit = '';
                    $description = 'No baggage included';
                }

                $baggage_policies[] = [
                    'segment_ref_id' => $segmentRefId,
                    'traveler_type' => $paxBreakdown['PassengerTypeQuantity']['@attributes']['Code'] ?? 'ADT',
                    'pieces' => 0,
                    'type' => "checked",
                    'weight' => $quantity,
                    'description' => $description,
                ];
                $baggage_policies[] = [
                    'segment_ref_id' => $segmentRefId,
                    'traveler_type' => $paxBreakdown['PassengerTypeQuantity']['@attributes']['Code'] ?? 'ADT',
                    'pieces' => 0,
                    'type' => "carry",
                    'weight' => "7kg",
                    'description' => "1 carry-on bag up to 7kg",
                ];

            }



            $airline = Airline::where('iata_code', 'PA' ?? null)->first();
            $margin_amount = $airline ? $airline->margin_amount : 0;
            $amount_type = $airline ? $airline->amount_type : null;
            $margin_type = $airline ? $airline->margin_type : null;
            $fares[] = [
                "ref_id" => uniqid(),
                "is_refundable" => true,
                "name_class" => $fareType,
                "fare_basis_code" => $fareBasisCode,
                "class" => "Y",
                "available_seats" => 9,
                "currency" => [
                    "code" => $totalFare['TotalFare']['@attributes']['CurrencyCode'],
                    "symbol" => $totalFare['TotalFare']['@attributes']['CurrencyCode'],
                    "flag" => "https://www.sooperfare.com/assets/client/images/flags/currency/PKR.png",
                ],
                "base_price" => (float) $totalFare['BaseFare']['@attributes']['Amount'],
                "taxes" => (float) $totalFare['Taxes']['@attributes']['Amount'],
                "fees" => (float) (isset($totalFare['Fees']['@attributes']['Amount']) ? $totalFare['Fees']['@attributes']['Amount'] : 0),
                "total_price" => (float) $totalFare['TotalFare']['@attributes']['Amount'],
                "billable_price" => (float) $totalFare['TotalFare']['@attributes']['Amount'],
                "margin_amount" => $margin_amount,
                "amount_type" => $amount_type,
                "margin_type" => $margin_type,
                "passenger_fares" => $passengerFares,
                "baggage_policies" => $baggage_policies,
            ];
        }
        $fromAirportDetails = Airport::where('iata_code', $segments[0]['DepartureAirport']['@attributes']['LocationCode'] ?? null)->first();
        $toAirportDetails = Airport::where('iata_code', $segments[count($segments) - 1]['ArrivalAirport']['@attributes']['LocationCode'] ?? null)->first();
        $from = $this->buildAirportData($fromAirportDetails);
        $to = $this->buildAirportData($toAirportDetails);
        $airline = Airline::where('iata_code', 'PA')->first();
        $marketingCarrier = [
            "name" => $airline->name,
            "iata" => $airline->iata_code,
            "logo" => $airline->logo_url,
        ];
        usort($fares, function ($a, $b) {
            return $a['total_price'] <=> $b['total_price'];
        });
        

        return [
            "ref_id" => uniqid(),
            "flight_operation" => "self",
            "is_recommended" => 1,
            "is_refundable" => true,
            "recommended_priority" => 1,
            "marketing_carrier" => $marketingCarrier,
            "has_layovers" => $hasLayovers,
            "layovers_count" => $layoversCount,
            "layovers_time" => $totalLayoverMinutes,
            "flight_number" => $segments[0]['@attributes']['FlightNumber'] ?? null,
            "travel_time" => $totalTravelMinutes,
            "departure_at" => $flightSegments[0]['departure_at'] ?? null,
            "arrival_at" => ($flightSegments[count($flightSegments) - 1]['arrival_at'] ?? null),
            "from" => $from,
            "to" => $to,
            "segments" => $flightSegments,
            "fares" => $fares,
            "ancillaries" => ["baggages" => [], "meals" => [], "seatplans" => [], "ssrs" => []],

        ];
    }


    public static function buildAirportData($airport)
    {
        if (!$airport) {
            return [
                'name' => null,
                'iata' => null,
                'city' => ['name' => null, 'code' => null, 'country' => ['name' => null, 'code' => null]],
                'country' => ['name' => null, 'code' => null],
            ];
        }

        return [
            'name' => $airport['name'] ?? null,
            'iata' => $airport['iata_code'] ?? null,
            'city' => [
                'name' => $airport['city_name'] ?? null,
                'code' => $airport['iata_city_code'] ?? null,
                'country' => [
                    'name' => $airport['iata_country_code'] ?? null,
                    'code' => $airport['iata_country_code'] ?? null,
                ],
            ],
            'country' => [
                'name' => $airport['iata_country_code'] ?? null,
                'code' => $airport['iata_country_code'] ?? null,
            ],
        ];
    }

    private function parseLocalDateTime(?string $dateTime, ?string $airportCode): ?Carbon
    {
        if (empty($dateTime)) {
            return null;
        }

        $timezone = $this->getAirportTimezone($airportCode);

        try {
            return $timezone ? Carbon::parse($dateTime, $timezone) : Carbon::parse($dateTime);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function getAirportTimezone(?string $airportCode): ?string
    {
        if (empty($airportCode)) {
            return null;
        }

        if (array_key_exists($airportCode, $this->airportTimezones)) {
            return $this->airportTimezones[$airportCode];
        }

        $timezone = Airport::where('iata_code', $airportCode)->value('time_zone');
        $this->airportTimezones[$airportCode] = $timezone ?: null;

        return $this->airportTimezones[$airportCode];
    }





}











