<?php
namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use App\Services\OneApiService;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class OneApiFlightTransformer
{

    protected $params;
    protected $itinCount = 0;
    protected $airportTimezones = [];

    private function splitItinAmount($amount)
    {
        $divisor = (int) ($this->itinCount ?: 1);
        if ($divisor <= 0) {
            $divisor = 1;
        }

        return round(((float) ($amount ?? 0)) / $divisor, 2);
    }

    public function fromOneAPI($apiResponse, $params)
    {
        $result = [];
        $this->params = $params;
        $origin = $params['origin'];
        $destination = $params['destination'];
        $ondKey = "$origin/$destination";
        $deptDateMap = $apiResponse['ondWiseFlightCombinations'][$ondKey]['dateWiseFlightCombinations'] ?? null;
        if (empty($deptDateMap) || !is_array($deptDateMap)) {
            Log::info('OneApiFlightTransformer: empty dateWiseFlightCombinations for departure.', [
                'ond_key' => $ondKey,
                'departure_date' => $params['departure_date'] ?? null,
                'flight_type' => $params['flight_type'] ?? null,
            ]);
            return [];
        }

        $deptDateItem = $deptDateMap[$params['departure_date']] ?? null;
        if (empty($deptDateItem) || empty($deptDateItem['flightOptions'])) {
            Log::info('OneApiFlightTransformer: no departure flight options found.', [
                'ond_key' => $ondKey,
                'departure_date' => $params['departure_date'] ?? null,
                'flight_type' => $params['flight_type'] ?? null,
            ]);
            return [];
        }
        Log::info($params['flight_type']);
        if ($params['flight_type'] === 'return') {
            $returnOndKey = "$destination/$origin";
            $arrDateMap = $apiResponse['ondWiseFlightCombinations'][$returnOndKey]['dateWiseFlightCombinations'] ?? null;
            if (empty($arrDateMap) || !is_array($arrDateMap)) {
                Log::info('OneApiFlightTransformer: empty dateWiseFlightCombinations for return.', [
                    'ond_key' => $returnOndKey,
                    'return_date' => $params['return_date'] ?? null,
                ]);
                return [];
            }

            $arrDateItem = $arrDateMap[$params['return_date']] ?? null;
            if (empty($arrDateItem) || empty($arrDateItem['flightOptions'])) {
                Log::info('OneApiFlightTransformer: no return flight options found.', [
                    'ond_key' => $returnOndKey,
                    'return_date' => $params['return_date'] ?? null,
                ]);
                return [];
            }
        }

        if ($params['flight_type'] === 'one-way') {
            Log::info('going with one-way');
            $this->itinCount = 1;
            foreach ($deptDateItem['flightOptions'] as $index => $option) {
                $flight = [];
                $segment = $option['flightSegments'];

                $flight[0]['segments'] = $segment;

                $oneApiService = new OneApiService();
                $response = $oneApiService->getPrice($flight, $params);
                Log::info($response);
                $pricing = json_decode($response, true);
                if (!isset($pricing['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary'])) {
                    continue;
                }
                $cabin = $option['cabinPrices'];
                $req_specific = $pricing['Body']['OTA_AirPriceRS']['@attributes'];
                if (!isset($pricing['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary'])) {
                    continue;
                }
                $legRefId = uniqid();
                $req_specific['SetCookie'] = $pricing['Body']['SetCookie'];
                $flights = array_values(array_filter([
                    $this->buildFlightObject($segment, $cabin, 0, $pricing)
                ]));

                if ($flights === []) {
                    continue;
                }

                $sectorParts = [];
                foreach ($flights as $flight) {
                    $sectorParts[] = $flight['from']['iata'];
                    $sectorParts[] = $flight['to']['iata'];
                }

                $sector = implode('-', $sectorParts);
                $result[] = [
                    "provider" => [
                        "identifier" => 'ONEAPI',
                        "source" => 'OneApi',
                        "name" => 'OneApi',
                        "contentSource" => 'LCC',
                        "sector" => $sector,
                        'travel_date' => $flights[0]['departure_at'] ?? null,

                    ],
                    "req_specific" => $req_specific,
                    "leg" => [
                        "ref_id" => $legRefId,
                        "flights" => $flights
                    ]
                ];

            }
            Log::info($result);
            return $result;
        }

        /* ==========================================================
           RETURN – BUILD PAIRS
           ========================================================== */
        if ($params['flight_type'] === 'return') {
            $this->itinCount = 2;
            foreach ($deptDateItem['flightOptions'] as $deptIndex => $deptOption) {
                $req_specific = null;
                $deptSegment = $deptOption['flightSegments'];
                $deptCabin = $deptOption['cabinPrices'];
                // $deptPricing = $deptOption['pricing'];
                // $dept_req_specific = $deptOption['pricing']['Body']['OTA_AirPriceRS']['@attributes'];


                foreach ($arrDateItem['flightOptions'] as $arrIndex => $arrOption) {
                    $flight = [];
                    $arrSegment = $arrOption['flightSegments'];
                    $flight[0]['segments'] = $deptSegment;
                    $flight[1]['segments'] = $arrSegment;
                    $oneApiService = new OneApiService();
                    $response = $oneApiService->getPrice($flight, $params);
                    Log::info($response);
                    $pricing = json_decode($response, true);
                    if (!isset($pricing['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary'])) {
                        continue;
                    }
                    $req_specific = $pricing['Body']['OTA_AirPriceRS']['@attributes'];
                    $arrCabin = $arrOption['cabinPrices'];
                    $departLegRef = uniqid();
                    $returnLegRef = uniqid();
                    $req_specific['SetCookie'] = $pricing['Body']['SetCookie'];
                    // Pair: One outbound + one inbound
                    $flights = array_values(array_filter([
                        $this->buildFlightObject($deptSegment, $deptCabin, 0, $pricing),
                        $this->buildFlightObject($arrSegment, $arrCabin, 1, $pricing)
                    ]));

                    if (count($flights) !== 2) {
                        continue;
                    }

                    $sectorParts = [];

                    foreach ($flights as $flight) {
                        $sectorParts[] = $flight['from']['iata'];
                        $sectorParts[] = $flight['to']['iata'];
                    }

                    $sector = implode('-', $sectorParts);
                    $result[] = [
                        "provider" => [
                            "identifier" => 'ONEAPI',
                            "source" => 'OneApi',
                            "name" => 'OneApi',
                            "contentSource" => 'LCC',
                            "sector" => $sector,
                            "travel_date" => $flights[0]['departure_at'] ?? null,
                        ],
                        "req_specific" => $req_specific,
                        "leg" => [
                            "ref_id" => $departLegRef,
                            "flights" => $flights
                        ]
                    ];
                }
            }
        }
        return $result;



    }
    private function buildFlightObject($segments, $cabin, $index, $pricing)
    {
        if (empty($segments) || !is_array($segments)) {
            Log::warning('OneApiFlightTransformer received an itinerary with no flight segments.', [
                'pricing_index' => $index,
                'flight_type' => $this->params['flight_type'] ?? null,
                'origin' => $this->params['origin'] ?? null,
                'destination' => $this->params['destination'] ?? null,
            ]);

            return null;
        }

        $flightSegments = [];
        
        $airline = Airline::where('iata_code', '9P' ?? null)->first();
        $margin_amount = $airline ? $airline->margin_amount : 0;
        $amount_type = $airline ? $airline->amount_type : null;
        $margin_type = $airline ? $airline->margin_type : null;
        $priceSegments = $pricing['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'] ?? [];
        if (isset($priceSegments['FlightSegment'])) {
            $priceSegments = [$priceSegments];
        }
        $bundledServiceExt = $pricing['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItinerary']['OriginDestinationOptions']['AABundledServiceExt'] ?? [];
        if ($bundledServiceExt && isset($bundledServiceExt['bundledService'])) {
            $bundledServiceExt = [$bundledServiceExt];
        }
        ;
        $bunbledServices = $bundledServiceExt[$index]['bundledService'] ?? [];

        $ItinTotalFare = $pricing['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItineraryPricingInfo']['ItinTotalFare'];
        $PTCBreakDown = $pricing['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItineraryPricingInfo']['PTC_FareBreakdowns']['PTC_FareBreakdown'] ?? [];
        $PTCBreakDown = $this->ensureList($PTCBreakDown, 'PassengerTypeQuantity');
        $fares = [];


        foreach ($cabin as $fare) {

            
            $bunbledBaggages = [];
            $passengerFares = [];
            $baggage_policies = [];
            foreach ($PTCBreakDown as $paxFare) {

                $paxType = $paxFare['PassengerTypeQuantity']['@attributes']['Code']; // ADT / CHD / INF
                $count = $paxFare['PassengerTypeQuantity']['@attributes']['Quantity']?? 0;  // number of passengers
                $travelerRefs = $this->extractTravelerRefs($paxFare['TravelerRefNumber'] ?? null);
                if ($count > 0) {
                    $taxAmount = 0;
                    $taxItems = $paxFare["PassengerFare"]['Taxes']['Tax'] ?? [];
                    if (isset($taxItems['@attributes'])) {
                        $taxItems = [$taxItems];
                    }
                    if (is_array($taxItems)) {
                        foreach ($taxItems as $tax) {
                            $taxAmount += (float) ($tax['@attributes']['Amount'] ?? 0);
                        }
                    }

                    $feeAmount = 0;
                    $feeItems = $paxFare["PassengerFare"]['Fees']['Fee'] ?? [];
                    if (isset($feeItems['@attributes'])) {
                        $feeItems = [$feeItems];
                    }
                    if (is_array($feeItems)) {
                        foreach ($feeItems as $fee) {
                            $feeAmount += (float) ($fee['@attributes']['Amount'] ?? 0);
                        }
                    }

                    $passengerFares[] = [
                        "traveler_type" => $paxType,
                        "traveler_refs" => $travelerRefs,
                        "base_price" => $this->splitItinAmount($paxFare["PassengerFare"]['BaseFare']['@attributes']['Amount'] ?? 0),
                        "taxes" => $this->splitItinAmount($taxAmount),
                        "fees" => $this->splitItinAmount($feeAmount),
                        "service_charges" => 0,
                        "total_price" => $this->splitItinAmount($paxFare["PassengerFare"]['TotalFare']['@attributes']['Amount'] ?? 0), // multiplied
                        "total_passenger" => $count,                     // optional
                    ];
                    foreach ($segments as $segment) {
                        $baggage_policies[] = [
                            "segment_ref_id" => $segment['flightSegmentRef'],
                            "title" => 'Economy',
                            "description" => "1 hand bag(s), up to 7 kg",
                            "weight_unit" => "kg",
                            "traveler_type" => $paxType,
                            "type" => 'carry',

                        ];

                    }
                }

            }


            $fares[] = [
                "ref_id" => uniqid(),
                'passenger_fares' => $passengerFares,
                'baggage_policies' => $baggage_policies,
                "fareFamily" => $fare['fareFamily'],
                "margin_amount" => $margin_amount,
                "amount_type" => $amount_type,
                "margin_type" => $margin_type,
                "name_class" => 'Economy',
                "base_price" => $this->splitItinAmount($ItinTotalFare["BaseFare"]['@attributes']['Amount'] ?? 0),
                "taxes" => 0,
                "service_charges" => 0,
                "surchage" => 0,
                "fees" => $this->splitItinAmount(
                    ((float) ($ItinTotalFare['TotalFareWithCCFee']['@attributes']['Amount'] ?? 0)) -
                    ((float) ($ItinTotalFare["BaseFare"]['@attributes']['Amount'] ?? 0))
                ),
                "total_price" => $this->splitItinAmount($ItinTotalFare['TotalFareWithCCFee']['@attributes']['Amount'] ?? 0),
                "billable_price" => $this->splitItinAmount($ItinTotalFare['TotalFareWithCCFee']['@attributes']['Amount'] ?? 0),

            ];
        }
        if (count($bunbledServices) > 0) {
            if (isset($bunbledServices['perPaxBundledFee'])) {
                $bunbledServices = [$bunbledServices];
            }
            foreach ($bunbledServices as $service) {
                $passengerFares = []; // reset passenger fares to replace with bundled fares
                $bunbledBaggages = []; // initialize bundled baggage list for this service
                $paxCount = [
                    "ADT" => (int) $this->params['adults'],
                    "CHD" => (int) $this->params['children'],
                ];




                $totalCount = $paxCount["ADT"] + $paxCount["CHD"];
                $bundleFee = $service['perPaxBundledFee'] * $totalCount;
                $baseFare = ($ItinTotalFare['EquiBaseFare']['@attributes']['Amount']) + $bundleFee;
                $totalFareWithoutFee = ($ItinTotalFare['TotalFareWithCCFee']['@attributes']['Amount']) + $bundleFee;
                $totalFare = ($ItinTotalFare['TotalFareWithCCFee']['@attributes']['Amount']) + $bundleFee;
                $fee = $totalFare - $totalFareWithoutFee;
                $surchage = $totalFareWithoutFee - $baseFare;
                foreach ($PTCBreakDown as $ptc) {

                     $taxAmount = 0;
                    $taxItems = $ptc["PassengerFare"]['Taxes']['Tax'] ?? [];
                    if (isset($taxItems['@attributes'])) {
                        $taxItems = [$taxItems];
                    }
                    if (is_array($taxItems)) {
                        foreach ($taxItems as $tax) {
                            $taxAmount += (float) ($tax['@attributes']['Amount'] ?? 0);
                        }
                    }

                    $feeAmount = 0;
                    $feeItems = $ptc["PassengerFare"]['Fees']['Fee'] ?? [];
                    if (isset($feeItems['@attributes'])) {
                        $feeItems = [$feeItems];
                    }
                    if (is_array($feeItems)) {
                        foreach ($feeItems as $feeItem) {
                            $feeAmount += (float) ($feeItem['@attributes']['Amount'] ?? 0);
                        }
                    }

                    
                    
                    $passengerFares[] = [
                        "traveler_type" => $ptc['PassengerTypeQuantity']['@attributes']['Code'],
                        "traveler_refs" => $this->extractTravelerRefs($ptc['TravelerRefNumber'] ?? null),
                        "total_passenger" => $ptc['PassengerTypeQuantity']['@attributes']['Quantity'],
                        "fareBasisCode" => $ptc['FareBasisCodes']['FareBasisCode'],
                        "base_price" => $this->splitItinAmount(
                            ((float) ($ptc['PassengerFare']['EquiBaseFare']['@attributes']["Amount"] ?? 0)) +
                            ($ptc['PassengerTypeQuantity']['@attributes']['Code'] !== 'INF' ? (float) ($service['perPaxBundledFee'] ?? 0) : 0)
                        ),
                        "taxes" => $this->splitItinAmount($taxAmount),
                        "fees" => $this->splitItinAmount($feeAmount),
                        "taxCodes" =>  $ptc['PassengerFare']['Taxes']['Tax'] ?? [],
                        "total_price" => $this->splitItinAmount(
                            ((float) ($ptc['PassengerFare']["TotalFare"]['@attributes']['Amount'] ?? 0)) +
                            ($ptc['PassengerTypeQuantity']['@attributes']['Code'] !== 'INF' ? (float) ($service['perPaxBundledFee'] ?? 0) : 0)
                        ),
                    ];
                    Log::info($service);
                    $serviceDescription = $this->normalizeDescriptionToString($service['description'] ?? null);
                    foreach ($segments as $segment) {
                        $parsedBaggages = $this->extractBaggagePoliciesFromDescription($serviceDescription);

                        foreach ($parsedBaggages as $baggage) {
                            $bunbledBaggages[] = [
                                "segment_ref_id" => $segment['flightSegmentRef'],
                                "title" => $service['bundledServiceName'],
                                "description" => $baggage['description'],
                                "weight_unit" => "kg",
                                "traveler_type" => $ptc['PassengerTypeQuantity']['@attributes']['Code'],
                                "type" => $baggage['type'],
                            ];
                        }
                    }

                }
                $farePolicies = array_values(array_unique(array_filter(array_merge(
                    $this->normalizePolicyList($service['includedServies'] ?? []),
                    $this->extractServicePoliciesFromDescription($serviceDescription),
                ))));

                $fares[] = [
                    "ref_id" => uniqid(),
                    "bundledServiceId" => $service['bunldedServiceId'],
                    "name_class" => $service['bundledServiceName'],
                    'passenger_fares' => $passengerFares,
                    'baggage_policies' => $bunbledBaggages,
                    'fare_policies' => $farePolicies,
                    "class" => $service["bookingClasses"],
                    "margin_amount" => $margin_amount ?? null,
                    "amount_type" => $amount_type ?? null,
                    "margin_type" => $margin_type ?? null,
                    "base_price" => $this->splitItinAmount($baseFare),
                    "taxes" => 0,
                    "fees" => $this->splitItinAmount($fee ?? 0),
                    "surchage" => $this->splitItinAmount($surchage ?? 0),
                    "total_price" => $this->splitItinAmount($totalFare),
                    "billable_price" => $this->splitItinAmount($totalFare),

                ];
                $bunbledBaggages = [];

            }
        }
        $previousArrivalUtc = null;
        $totalLayoverTime = 0;

        foreach ($segments as $segment) {

            $departureLocal = null;
            $arrivalLocal = null;

            $departureUtc = $this->parseZuluDateTime($segment['departureDateTimeZulu'] ?? null);
            $arrivalUtc = $this->parseZuluDateTime($segment['arrivalDateTimeZulu'] ?? null);

            if ($departureUtc === null) {
                $departureLocal = $this->parseLocalDateTime(
                    $segment['departureDateTimeLocal'] ?? null,
                    $segment['origin']['airportCode'] ?? null
                );
                $departureUtc = $departureLocal ? $departureLocal->copy()->utc() : null;
            }

            if ($arrivalUtc === null) {
                $arrivalLocal = $this->parseLocalDateTime(
                    $segment['arrivalDateTimeLocal'] ?? null,
                    $segment['destination']['airportCode'] ?? null
                );
                $arrivalUtc = $arrivalLocal ? $arrivalLocal->copy()->utc() : null;
            }

            // Handle overnight/multi-day arrivals when only local time is available or API sends ambiguous date.
            if ($departureUtc !== null && $arrivalUtc !== null && $arrivalUtc->lt($departureUtc)) {
                $attempts = 0;
                while ($attempts < 3 && $arrivalUtc->lt($departureUtc)) {
                    if ($arrivalLocal) {
                        $arrivalLocal->addDay();
                        $arrivalUtc = $arrivalLocal->copy()->utc();
                    } else {
                        $arrivalUtc->addDay();
                    }
                    $attempts++;
                }
            }
            $layoverTime = 0;

            if ($previousArrivalUtc !== null && $departureUtc !== null) {
                $layoverTime = max(0, $previousArrivalUtc->diffInMinutes($departureUtc, false));
                $totalLayoverTime += $layoverTime;
            }

            $segmentDurationMinutes = 0;
            if ($departureUtc !== null && $arrivalUtc !== null) {
                $segmentDurationMinutes = max(0, $departureUtc->diffInMinutes($arrivalUtc, false));
            }

            $airlineCode = substr($segment['flightNumber'], 0, 2);
            $airline = Airline::where('iata_code', $airlineCode)->first();

            // -----------------------------------
            // Match RPH using origin & destination
            // -----------------------------------
            $matchedRPH = null;

            foreach ($priceSegments as $priceSegment) {

                $priceFlightSegment = $priceSegment['FlightSegment'];

                $priceOrigin = $priceFlightSegment['DepartureAirport']['@attributes']['LocationCode'] ?? null;
                $priceDestination = $priceFlightSegment['ArrivalAirport']['@attributes']['LocationCode'] ?? null;

                if (
                    $priceOrigin === $segment['origin']['airportCode'] &&
                    $priceDestination === $segment['destination']['airportCode']
                ) {
                    $matchedRPH = $priceFlightSegment['@attributes']['RPH'] ?? null;
                    break;
                }
            }

            $flightSegments[] = [
                "ref_id" => $segment['flightSegmentRef'],
                "RPH" => $matchedRPH,
                "code" => $segment['segmentCode'],
                "from" => self::buildAirportData($segment['origin']['airportCode']),
                "to" => self::buildAirportData($segment['destination']['airportCode']),
                "departure_at" => $segment['departureDateTimeLocal'],
                "arrival_at" => $segment['arrivalDateTimeLocal'],
                "flight_number" => substr($segment['flightNumber'], 2),
                "operating_carrier" => [
                    "name" => $airline['name'] ?? "Unknown Airline",
                    "iata" => $airline['iata_code'] ?? "Unknown IATA",
                    "logo" => $airline['logo_url'] ?? "Unknown Logo",
                ],
                "flight_time" => $segmentDurationMinutes,
                "layover_time" => $layoverTime,
                "aircraft" => $segment['aircraftModel'] ?? null,
            ];

            $previousArrivalUtc = $arrivalUtc;
        }

        $segment = $segments[0];// first segment
        $lastSegment = end($segments);// last segment

        $departure = $this->parseZuluDateTime($segment['departureDateTimeZulu'] ?? null);
        if ($departure === null) {
            $departure = $this->parseLocalDateTime(
                $segment['departureDateTimeLocal'] ?? null,
                $segment['origin']['airportCode'] ?? null
            );
        }
        $arrival = $this->parseZuluDateTime($lastSegment['arrivalDateTimeZulu'] ?? null);
        if ($arrival === null) {
            $arrival = $this->parseLocalDateTime(
                $lastSegment['arrivalDateTimeLocal'] ?? null,
                $lastSegment['destination']['airportCode'] ?? null
            );
        }

        // Difference in minutes
        $travelTimeMinutes = 0;
        if ($departure && $arrival) {
            $depUtc = $departure->copy()->utc();
            $arrUtc = $arrival->copy()->utc();

            if ($arrUtc->lt($depUtc)) {
                $attempts = 0;
                while ($attempts < 3 && $arrUtc->lt($depUtc)) {
                    $arrival->addDay();
                    $arrUtc = $arrival->copy()->utc();
                    $attempts++;
                }
            }

            $travelTimeMinutes = max(0, $depUtc->diffInMinutes($arrUtc, false));
        }
        $airlineCode = substr($segment['flightNumber'], 0, 2);
        $airline = Airline::where('iata_code', $airlineCode)->first();
        $hasLayover = count($segments) > 1;
        $layoverCount = $hasLayover ? count($segments) - 1 : 0;
        return [
            "ref_id" => uniqid(),
            "from" => self::buildAirportData($segment['origin']['airportCode']),
            "to" => self::buildAirportData($lastSegment['destination']['airportCode']),
            "departure_at" => $segment['departureDateTimeLocal'],
            "arrival_at" => $lastSegment['arrivalDateTimeLocal'],
            "marketing_carrier" => [
                "name" => $airline['name'] ?? "Unknown Airline",
                "iata" => $airline['iata_code'] ?? "Unknown IATA",
                "logo" => $airline['logo_url'] ?? "Unknown Logo",
            ],
            "has_layovers" => $hasLayover,
            "segments" => $flightSegments,
            "layovers_count" => $layoverCount,
            "layovers_time" => $totalLayoverTime,
            "travel_time" => $travelTimeMinutes,
            "flight_number" => substr($segment['flightNumber'], 2),
            "fares" => $fares
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

    private function parseZuluDateTime(?string $dateTime): ?Carbon
    {
        if (empty($dateTime)) {
            return null;
        }

        try {
            return Carbon::parse($dateTime, 'UTC');
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

    private function ensureList($value, $singleKeyHint = null): array
    {
        if (empty($value) || !is_array($value)) {
            return [];
        }

        if (isset($value['@attributes']) || ($singleKeyHint && isset($value[$singleKeyHint]))) {
            return [$value];
        }

        $keys = array_keys($value);
        $isList = $keys === range(0, count($value) - 1);

        return $isList ? $value : [$value];
    }

    private function extractTravelerRefs($travelerRefNumber): array
    {
        if (empty($travelerRefNumber) || !is_array($travelerRefNumber)) {
            return [];
        }

        if (isset($travelerRefNumber['@attributes'])) {
            $travelerRefNumber = [$travelerRefNumber];
        }

        $refs = [];
        foreach ($travelerRefNumber as $ref) {
            $rph = $ref['@attributes']['RPH'] ?? null;
            if (!$rph) {
                continue;
            }
            $parts = preg_split('/\s*\/\s*/', $rph);
            foreach ($parts as $part) {
                if ($part !== '') {
                    $refs[] = $part;
                }
            }
        }

        return array_values(array_unique($refs));
    }

    private function extractBaggagePoliciesFromDescription(?string $description): array
    {
        if (empty($description) || !is_string($description)) {
            return [];
        }

        $description = preg_replace('/\s+/', ' ', trim($description));
        $policies = [];

        $appendPolicy = function (string $type, string $weight, string $label) use (&$policies): void {
            $weight = trim($weight);
            if ($weight === '') {
                return;
            }
            $policies[] = [
                'type' => $type,
                'description' => "{$weight} Kg {$label}",
            ];
        };

        if (preg_match_all('/(?:Hand\s*bag(?:gage)?|Cabin\s*Baggage|Carry[\s-]*on)\s*:\s*(\d+(?:\.\d+)?)\s*Kg/i', $description, $carryMatches)) {
            foreach ($carryMatches[1] as $weight) {
                $appendPolicy('carry', $weight, 'Carry-on Baggage');
            }
        }

        if (preg_match_all('/Checked\s*Baggage\s*:\s*(\d+(?:\.\d+)?)\s*Kg/i', $description, $checkedMatches)) {
            foreach ($checkedMatches[1] as $weight) {
                $appendPolicy('checked', $weight, 'Checked Baggage');
            }
        }

        if (preg_match('/\bBaggage\s*:\s*([^:]+?)(?=\s+[A-Za-z][A-Za-z0-9\-\s]*\s*:|$)/i', $description, $baggageMatch)) {
            $baggagePart = trim($baggageMatch[1]);
            if ($baggagePart !== '') {
                if (preg_match_all('/\d+(?:\.\d+)?/', $baggagePart, $weights) && !empty($weights[0])) {
                    foreach ($weights[0] as $weight) {
                        $appendPolicy('checked', $weight, 'Included Baggage');
                    }
                } else {
                    $policies[] = [
                        'type' => 'checked',
                        'description' => "{$baggagePart} Included Baggage",
                    ];
                }
            }
        }

        $uniquePolicies = [];
        foreach ($policies as $policy) {
            $key = strtolower(($policy['type'] ?? '') . '|' . ($policy['description'] ?? ''));
            $uniquePolicies[$key] = $policy;
        }

        return array_values($uniquePolicies);
    }

    private function extractServicePoliciesFromDescription(?string $description): array
    {
        if (empty($description) || !is_string($description)) {
            return [];
        }

        $description = preg_replace('/\s+/', ' ', trim($description));
        $policies = [];
        $labelPattern = '/([A-Za-z][A-Za-z0-9\-\s]+)\s*:\s*([^:]+?)(?=\s+[A-Za-z][A-Za-z0-9\-\s]*\s*:|$)/';

        if (preg_match($labelPattern, $description, $firstMatch, PREG_OFFSET_CAPTURE)) {
            $prefix = trim(substr($description, 0, $firstMatch[0][1]));
            if ($prefix !== '') {
                $policies[] = $prefix;
            }
        }

        if (preg_match_all($labelPattern, $description, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $label = trim($match[1] ?? '');
                $value = trim($match[2] ?? '');
                if ($label === '' || $value === '') {
                    continue;
                }

                if (preg_match('/^(Hand\s*bag(?:gage)?|Cabin\s*Baggage|Carry[\s-]*on|Checked\s*Baggage|Baggage)$/i', $label)) {
                    continue;
                }

                $policies[] = "{$label} : {$value}";
            }
        }

        return array_values(array_unique(array_filter($policies)));
    }

    private function normalizePolicyList($policies): array
    {
        $normalized = [];

        $walk = function ($value) use (&$walk, &$normalized): void {
            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    $normalized[] = $trimmed;
                }
                return;
            }

            if (is_numeric($value)) {
                $normalized[] = (string) $value;
                return;
            }

            if (!is_array($value)) {
                return;
            }

            if (isset($value['name']) && isset($value['description']) && is_string($value['name']) && is_string($value['description'])) {
                $combined = trim($value['name']) . ' : ' . trim($value['description']);
                if (trim($combined, ' :') !== '') {
                    $normalized[] = $combined;
                }
                return;
            }

            if (isset($value['description']) && is_string($value['description'])) {
                $trimmed = trim($value['description']);
                if ($trimmed !== '') {
                    $normalized[] = $trimmed;
                }
            }

            foreach ($value as $item) {
                $walk($item);
            }
        };

        $walk($policies);

        return array_values(array_unique(array_filter($normalized)));
    }

    private function normalizeDescriptionToString($description): string
    {
        if (is_string($description)) {
            return trim($description);
        }

        if ($description === null) {
            return '';
        }

        if (is_scalar($description)) {
            return trim((string) $description);
        }

        if (is_array($description)) {
            $flattened = [];
            array_walk_recursive($description, function ($value) use (&$flattened): void {
                if (is_scalar($value)) {
                    $trimmed = trim((string) $value);
                    if ($trimmed !== '') {
                        $flattened[] = $trimmed;
                    }
                }
            });

            return trim(implode(' ', $flattened));
        }

        if (is_object($description) && method_exists($description, '__toString')) {
            return trim((string) $description);
        }

        return '';
    }



    public static function buildAirportData($airport)
    {
        $airport = Airport::where('iata_code', $airport)->first();
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






}


