<?php

namespace App\Services;

use App\Attributes\FlightAttributes;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use App\Transformers\AirblueFlightTransformer;
use App\Transformers\AirsialFlightTransformer;
use App\Transformers\FlightTransformer;
use App\Transformers\FlydubaiFlightTransformer;
use App\Transformers\OneApiFlightTransformer;
use App\Transformers\PIAFlightTransformer;
use App\Transformers\TravelPortFlightNormalization;
use App\Transformers\TravelPortFlightTransformer;
use Carbon\Carbon;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Psy\CodeCleaner\ReturnTypePass;

class FlightAggregationService
{
    protected $sabreApiService;
    protected $sooperApiService;

    protected $flightTransformer;
    protected $airsialFlightTransformer;

    protected $flyDubaiFlightTransformer;
    protected $piaFlightTransformer;

    protected $airBlueFlightTransformer;

    protected $travelPortService;
    protected $oneApiFlightTransformer;


    public function __construct(SabreApiService $sabreApiService, SooperApiService $sooperApiService, AirsialFlightTransformer $airsialFlightTransformer, FlydubaiFlightTransformer $flyDubaiFlightTransformer, PIAFlightTransformer $piaFlightTransformer, AirblueFlightTransformer $airblueFlightTransformer, OneApiFlightTransformer $oneApiFlightTransformer)
    {
        $this->sabreApiService = $sabreApiService;
        $this->sooperApiService = $sooperApiService;
        $this->flightTransformer = new FlightTransformer();
        $this->airsialFlightTransformer = $airsialFlightTransformer;
        $this->flyDubaiFlightTransformer = $flyDubaiFlightTransformer;
        $this->piaFlightTransformer = $piaFlightTransformer;
        $this->airBlueFlightTransformer = $airblueFlightTransformer;
        $this->oneApiFlightTransformer = $oneApiFlightTransformer;

    }



    public function getFlights($params)
    {

        $sabreFlights = null;



        $transformedFlights = [];
        if (in_array($params['airline'], ['TravelPort', 'TravelPort-GDS', 'TravelPort-NDC'], true)) {
            if ($params['airline'] === 'TravelPort-GDS') {
                $params['travelport_content_source'] = 'GDS';
            } elseif ($params['airline'] === 'TravelPort-NDC') {
                $params['travelport_content_source'] = 'NDC';
            }
            $travelPortService = new TravelPortService();
            $travelPortFlights = $travelPortService->searchFlights($params);
            $travelportNorm = new TravelPortFlightNormalization();
            // Transform TravelPort flights
            $normalizedFlights = $travelportNorm->fromTravelPort($travelPortFlights, $params);
            Log::info("Normalized TravelPort Flights: " . json_encode($normalizedFlights));
            $travelPortTransformer = new TravelPortFlightTransformer();
            $transformedFlights = $travelPortTransformer->transformTravelPort($normalizedFlights, $params);

        }
        if ($params['airline'] == 'Sabre') {
            $sabreFlights = $this->sabreApiService->searchFlights($params);
        }
        if ($params['airline'] == 'AirBlue') {
            $airBlueApiService = new AirBlueApiService();
            $airBlueFlights = $airBlueApiService->searchFlights($params);
            // Log::info("AirBlue Response: " . json_encode($airBlueFlights));
            // Transform AirBlue flights
            $transformedFlights = $this->airBlueFlightTransformer->fromAirBlue($airBlueFlights, $params);
            Log::info("Transformed AirBlue Flights: " . json_encode($transformedFlights));
        }
        if ($params['airline'] == 'flydubai') {
            $flyDubaiApiService = new FlyDubaiApiService();
            $flyDubaiFlights = $flyDubaiApiService->searchFlights($params);

            $result = $flyDubaiFlights['RetrieveFareQuoteDateRangeResponse']['RetrieveFareQuoteDateRangeResult'] ?? null;

            $hasSegments = false;
            if ($result) {
                // Check explicit leg/segment details
                $legDetails = $result['LegDetails']['LegDetail'] ?? [];
                $segmentDetails = $result['SegmentDetails']['SegmentDetail'] ?? [];
                if (!empty($legDetails) || !empty($segmentDetails)) {
                    $hasSegments = true;
                }

                // Inspect FlightSegments for non-zero LFID/LegCount
                $flightSegments = $result['FlightSegments']['FlightSegment'] ?? null;
                if ($flightSegments) {
                    // normalize to array
                    $segments = is_array($flightSegments) && array_values($flightSegments) === $flightSegments
                        ? $flightSegments
                        : [$flightSegments];
                    foreach ($segments as $seg) {
                        $lfid = isset($seg['LFID']) ? intval($seg['LFID']) : null;
                        $legCount = isset($seg['LegCount']) ? intval($seg['LegCount']) : null;
                        if (($lfid !== null && $lfid > 0) || ($legCount !== null && $legCount > 0)) {
                            $hasSegments = true;
                            break;
                        }
                    }
                }
            }

            if ($result && $hasSegments) {
                $transformedFlights = $this->flyDubaiFlightTransformer->fromFlydubai($flyDubaiFlights, $params);
            }
            else {
                Log::info('FlyDubai Response contained no usable segments/legs — skipping Flydubai transformer.');
            }

        }
        if ($params['airline'] == 'AirSial') {
            $airsialApiService = new AirSialApiService();
            $response = $airsialApiService->searchFlights($params);
            $transformedFlights = $this->airsialFlightTransformer->fromAirSial($response, $params);
        }
        if ($params['airline'] == 'OneApi') {
            $oneApiService = new OneApiService();
            $response = $oneApiService->searchFlights($params);
            $transformedFlights = $this->oneApiFlightTransformer->fromOneAPI($response, $params);
            Log::info(json_encode($transformedFlights));

        }
        if ($params['airline'] == 'PIA') {
            $piaApiService = new PIAApiService();
            $response = $piaApiService->searchFlights($params);
            $transformedFlights = $this->piaFlightTransformer->fromPIA($response, $params);
            Log::info(json_encode($transformedFlights));
        }


        if ($sabreFlights != null && $params['airline'] == 'Sabre') {
            $flights = $sabreFlights['groupedItineraryResponse'];
            $results = [

                'itineraries' => [],
            ];

            //$sabreFlights = $this->sabreApiService->searchFlights($params);

            //Log::info("sabreFlights: " . json_encode($sabreFlights));


            $flights = $sabreFlights['groupedItineraryResponse'];
            $fareComponentDescs = $flights['fareComponentDescs'] ?? [];
            $baggageAllowanceDescs = $flights['baggageAllowanceDescs'] ?? [];
            $passengersDescs = $flights['passengerDescs'] ?? [];
            $validatingCarrierDescs = $flights['validatingCarrierDescs'] ?? [];
            $uniqueItineraries = [];

            foreach ($flights['itineraryGroups'] ?? [] as $group) {

                $groupDescription = $group['groupDescription'] ?? [];
                $legDescriptions = $groupDescription['legDescriptions'] ?? [];

                foreach ($group['itineraries'] ?? [] as $itinerary) {
                    $processedItinerary = [
                        'id' => $itinerary['id'] . uniqid(),
                        'source' => $itinerary['pricingInformation'][0]['distributionModel'] == 'NDC' ? 'SB-NDC' : 'SB',
                        'passenger' => $passengersDescs,
                        'legs' => [],
                        'pricing' => [],
                        'validatingCarriers' => [],
                        'dates' => [],

                    ];
                    // Add dates from legDescriptions
                    foreach ($legDescriptions as $legDescription) {
                        $processedItinerary['dates'][] = [
                            'departureDate' => $legDescription['departureDate'] ?? null,
                            'departureLocation' => $legDescription['departureLocation'] ?? null,
                            'arrivalLocation' => $legDescription['arrivalLocation'] ?? null,
                        ];
                    }

                    // Process legs
                    foreach ($itinerary['legs'] ?? [] as $index => $leg) {
                        $legDetails = $flights['legDescs'][$leg['ref'] - 1] ?? null;
                        if ($legDetails) {
                            $flightInfo = $this->processLeg($legDetails, $flights, $index);
                            $processedItinerary['legs'][] = $flightInfo;
                        }
                    }

                    // Process pricing
                    $processedItinerary['pricing'] = $this->processPricing($itinerary, $fareComponentDescs, $baggageAllowanceDescs);

                    // Process validating carriers
                    $processedItinerary['validatingCarriers'] = $this->processValidatingCarriers($itinerary, $validatingCarrierDescs);

                    // Process passenger info and baggage
                    $processedItinerary['passengerInfo'] = $this->processPassengerInfo($itinerary, $baggageAllowanceDescs);


                    // Generate unique key for itinerary to prevent duplication
                    $itineraryKey = md5(json_encode($processedItinerary['legs']) . json_encode($processedItinerary['pricing']));

                    // Add only unique itineraries
                    if (!isset($uniqueItineraries[$itineraryKey])) {
                        $uniqueItineraries[$itineraryKey] = true;
                        $results['itineraries'][] = $processedItinerary;
                    }
                }
            }
            Log::info("Processed Itineraries: " . json_encode($results['itineraries']));
            $transformedFlights = FlightTransformer::fromSabre($results['itineraries']);
        }
        else {
            $results = [
                'itineraries' => [],
            ];
        }

        //Cache::put('flights', $results);
        // $sooperFlights;
        // return $results;
        // if($params['airline'] == 'TRAVELPORTJ'){
        //  $sooperFlights = $this->sooperApiService->searchFlights($params);
        // $sooperFlights = $this->processSooperfare($sooperFlights);
        // Log::info($sooperFlights);
        // $sooperFlights = $this->flightTransformer->fromSooper($sooperFlights);
        // $transformedFlights = array_merge($transformedFlights, $airsialResponse ?? []);
        // $transformedFlights = array_merge($transformedFlights ?? []);
        // }

        return [
            // 'sooperFlights' => $sooperFlights,
            'results' => $transformedFlights,
        ];
    }

    private function processSooperfare($sooperFlights)
    {
        $processedSooperFlights = [];
        foreach ($sooperFlights as $providerKey => &$providerData) {
            if (isset($providerData['data']['providers']) && is_array($providerData['data']['providers'])) {
                foreach ($providerData['data']['providers'] as &$providerInfo) {
                    if (!empty($providerInfo['legs']) && is_array($providerInfo['legs'])) {
                        foreach ($providerInfo['legs'] as &$leg) {
                            if (isset($leg['flights']) && is_array($leg['flights'])) {
                                foreach ($leg['flights'] as &$flight) {
                                    $airline = Airline::where('iata_code', $flight['marketing_carrier']['iata'] ?? null)->first();
                                    $margin_amount = $airline ? $airline->margin_amount : 0;
                                    $amount_type = $airline ? $airline->amount_type : null;
                                    $margin_type = $airline ? $airline->margin_type : null;

                                    if (isset($flight['fares']) && is_array($flight['fares'])) {
                                        foreach ($flight['fares'] as &$fare) {
                                            $fare['margin_amount'] = $margin_amount;
                                            $fare['amount_type'] = $amount_type;
                                            $fare['margin_type'] = $margin_type;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach ($sooperFlights as $providerKey => $providerData) {
            $providerRef = $providerData['data']['ref_id'] ?? null;
            if (isset($providerData['data']['providers']) && is_array($providerData['data']['providers'])) {
                foreach ($providerData['data']['providers'] as $providerInfo) {
                    $providerDetails = $providerInfo['provider'] ?? null;

                    if (!empty($providerInfo['legs']) && is_array($providerInfo['legs'])) {
                        foreach ($providerInfo['legs'] as $leg) {
                            $processedSooperFlights[] = [
                                'ref_id' => $providerRef,
                                'provider' => $providerDetails,
                                'leg' => $leg,

                            ];
                        }
                    }
                }
            }
        }
        // Log::info("Sooper API Search Flights All Results:': \n" . json_encode($processedSooperFlights, JSON_PRETTY_PRINT));

        // return response()->json([
        //     'status' => true,
        //     'data' => $processedSooperFlights
        // ]);
        return $processedSooperFlights;

    }



    private function processLeg($legDetails, $flights, $index)
    {
        $flightInfo = [
            'duration' => $legDetails['elapsedTime'] ?? null,
            'totalMilesFlown' => $legDetails['totalMilesFlown'] ?? null,
            'stops' => [],
            'baggage' => [],
        ];

        foreach ($legDetails['schedules'] ?? [] as $schedule) {
            $scheduleDetails = $flights['scheduleDescs'][$schedule['ref'] - 1] ?? null;
            if ($scheduleDetails) {
                $flightInfo['stops'][] = [
                    'flightNumber' => $scheduleDetails['carrier']['marketingFlightNumber'] ?? null,
                    'departure' => $this->processAirportInfo($scheduleDetails['departure']),
                    'arrival' => $this->processAirportInfo($scheduleDetails['arrival']),
                    'airline' => Airline::where('iata_code', $scheduleDetails['carrier']['marketing'])->first() ?? null,
                    'aircraft' => Aircraft::where('iata_code', $scheduleDetails['carrier']['equipment']['code'])->first() ?? null,
                    'aircraftEquipmentFirst' => $scheduleDetails['carrier']['equipment']['typeForFirstLeg'] ?? null,
                    'aircraftEquipmentLast' => $scheduleDetails['carrier']['equipment']['typeForLastLeg'] ?? null,
                    'adjustment' => $schedule['departureDateAdjustment'] ?? null,


                ];
            }
        }

        return $flightInfo;
    }

    private function processAirportInfo($airportDetails)
    {
        return [
            'airport' => Airport::where('iata_code', $airportDetails['airport'])->first() ?? null,
            'city' => $airportDetails['city'] ?? null,
            'country' => $airportDetails['country'] ?? null,
            'time' => $airportDetails['time'] ?? null,
            'terminal' => $airportDetails['terminal'] ?? null,
        ];
    }

    private function processPricing($itinerary, $fareComponentDescs, $baggageAllowanceDescs)
    {


        if (empty($itinerary['pricingInformation'])) {
            return [];
        }

        $pricingList = $itinerary['pricingInformation'] ?? [];
        $fareComponents = $fareComponentDescs ?? [];
        $baggageAllowances = $baggageAllowanceDescs ?? [];

        $result = [];

        foreach ($pricingList as $priceInfo) {
            // Skip sold-out prices
            if (
            (isset($priceInfo['soldOut']) && $priceInfo['soldOut']) ||
            (isset($priceInfo['soldOut']['status']) && $priceInfo['soldOut']['status'] === 'F')
            ) {
                continue;
            }

            $baggagePolicies = [];
            $enrichedFareComponents = [];

            foreach ($priceInfo['fare']['passengerInfoList'] ?? [] as $passengerInfo) {
                $baggageInfo = $passengerInfo['passengerInfo']['baggageInformation'] ?? [];
                $passengerType = $passengerInfo['passengerInfo']['passengerType'] ?? 'adult';

                // Process baggage information
                foreach ($baggageInfo as $baggage) {
                    $allowanceRef = $baggage['allowance']['ref'] ?? null;
                    $allowanceDetails = [];

                    if ($allowanceRef !== null && isset($baggageAllowances[$allowanceRef - 1])) {
                        $allowanceDetails = $baggageAllowances[$allowanceRef - 1];
                    }
                    // Determine baggage type based on provisionType
                    $baggageType = ($baggage['provisionType'] === 'A') ? 'checked' : 'carry';

                    // Generate description based on allowance details
                    $description = '';
                    $weightLimit = $allowanceDetails['weight'] ?? null;
                    $weightUnit = $allowanceDetails['unit'] ?? null;
                    $pieces = $allowanceDetails['pieceCount'] ?? 1;

                    // Try to extract kg from description1 if weight/unit not available
                    $description1 = $allowanceDetails['description1'] ?? '';
                    $extractedKg = null;
                    if (!$weightLimit && !empty($description1)) {
                        // Match patterns like "UP TO 15 POUNDS/7 KILOGRAMS"
                        if (preg_match('/(\d+(\.\d+)?)\s*(KG|KILOGRAMS)/i', $description1, $matches)) {
                            $extractedKg = $matches[1];
                            $weightLimit = $extractedKg;
                            $weightUnit = 'KG';
                        }
                    }

                    // Use description1 if weight/unit not available
                    if ($baggageType === 'carry') {
                        if ($pieces > 0 || $weightLimit && $weightUnit) {
                            $description = "{$pieces} hand bag(s), up to {$weightLimit} {$weightUnit}";
                        }
                        elseif (!empty($description1)) {
                            $description = $description1;
                        }
                        else if ($pieces > 0) {
                            $description = "{$pieces} bag pieces Allowed";
                        }
                        else {
                            $description = "Carry-on baggage not included";
                        }
                        $description2 = $allowanceDetails['description2'] ?? null;
                    }
                    else {
                        if ($weightLimit && $weightUnit) {
                            $description = "Checked bag(s), up to {$weightLimit} {$weightUnit}";
                        }
                        elseif (!empty($description1)) {
                            $description = $description1;
                        }
                        else if ($pieces > 0) {
                            $description = "{$pieces} bag pieces Allowed";
                        }
                        else {
                            $description = "Checked baggage not included";
                        }
                        $description2 = $allowanceDetails['description2'] ?? null;
                    }




                    // Get fare component for title
                    $title = 'Unknown';
                    $fareBasisCode = [];
                    foreach ($passengerInfo['passengerInfo']['fareComponents'] ?? [] as $fareComponent) {
                        $fareRef = $fareComponent['ref'] ?? null;
                        if ($fareRef !== null && isset($fareComponents[$fareRef - 1])) {
                            $fareComp = $fareComponents[$fareRef - 1];
                            $title = $fareComp['brand']['brandName'] ?? 'Economy';
                        }
                    }

                    // Add baggage policy for each segment
                    foreach ($baggage['segments'] ?? [] as $segment) {
                        $baggagePolicies[] = [
                            'segment_ref_id' => $segment['id'] ?? uniqid(), // Using segment.id as placeholder
                            'title' => $title,
                            'description' => $description,
                            'description2' => $description2 ?? null,
                            'weight_limit' => $weightLimit,
                            'weight_unit' => $weightUnit,
                            'pieces' => $pieces,
                            'type' => $baggageType,
                            'traveler_type' => strtolower($passengerType),
                        ];
                    }
                }


                // Process fare components
                foreach ($passengerInfo['passengerInfo']['fareComponents'] ?? [] as $fareComponent) {
                    $fareRef = $fareComponent['ref'] ?? null;
                    $fareComponentDetail = [];

                    if ($fareRef !== null && isset($fareComponents[$fareRef - 1])) {
                        $fareComp = $fareComponents[$fareRef - 1];

                        // Get segments belonging to this fareComponent
                        $fareSegmentIds = array_map(function ($seg) {
                            return $seg['segment']['id'] ?? null;
                        }, $fareComp['segments'] ?? []);

                        // Filter baggage only for these segments
                        $baggageForFare = [];
                        foreach ($passengerInfo['passengerInfo']['baggageInformation'] ?? [] as $baggage) {
                            foreach ($baggage['segments'] ?? [] as $segment) {
                                if (in_array($segment['id'] ?? null, $fareSegmentIds)) {
                                    $allowanceRef = $baggage['allowance']['ref'] ?? null;
                                    $allowanceDetails = $allowanceRef !== null && isset($baggageAllowances[$allowanceRef - 1])
                                        ? $baggageAllowances[$allowanceRef - 1]
                                        : [];

                                    $baggageForFare[] = [
                                        'segment_ref_id' => $segment['id'] ?? uniqid(),
                                        'weight_limit' => $allowanceDetails['weight'] ?? null,
                                        'weight_unit' => $allowanceDetails['unit'] ?? null,
                                        'pieces' => $allowanceDetails['pieceCount'] ?? 0,
                                        'type' => ($baggage['provisionType'] === 'A') ? 'checked' : 'carry',
                                    ];
                                }
                            }
                        }
                        $fareComponentDetail = [

                            'brandName' => $fareComp['brand']['brandName'] ?? null,
                            'brandCode' => $fareComp['brand']['code'] ?? null,
                            'programId' => $fareComp['brand']['programId'] ?? null,
                            'fareBasisCode' => $fareComp['fareBasisCode'] ?? null,
                            'fareAmount' => $fareComp['fareAmount'] ?? null,
                            'fareCurrency' => $fareComp['fareCurrency'] ?? null,
                            'cabinCode' => $fareComp['cabinCode'] ?? null,
                            'baggage_policies' => $baggagePolicies, // 👈 only baggage for these segments
                        ];
                    }

                    $enrichedFareComponents[] = $fareComponentDetail;
                }

            }
            $passengerInfoList = [];

            foreach ($priceInfo['fare']['passengerInfoList'] as $pricingInformation) {
                $info = $pricingInformation['passengerInfo'] ?? [];

                // Extract baggage info
                $baggageInfo = $info['baggageInformation'] ?? [];
                $info['baggage'] = $this->processBaggage($baggageInfo, $baggageAllowanceDescs);

                // Mark refundable status
                $info['nonRefundable'] = $pricingInformation['nonRefundable'] ?? false;

                // Add to passenger list
                $passengerInfoList[] = $info;
            }

            // Collect pricing summary with enriched data
            $result[] = [
                'totalPrice' => $priceInfo['fare']['totalFare']['equivalentAmount'] ?? null,
                'totalTaxAmount' => $priceInfo['fare']['totalFare']['totalTaxAmount'] ?? null,
                'currency' => $priceInfo['fare']['totalFare']['currency'] ?? null,
                'offerId' => $priceInfo['offer']['offerId'] ?? null,
                'offer_item_id' => $priceInfo['fare']['offerItemId'] ?? null,
                'service_id' => $priceInfo['fare']['serviceId'] ?? null,
                'passenger_fares' => $passengerInfoList, // now an array of passengers
                'baggage_policies' => $baggagePolicies,
                'fareComponents' => $enrichedFareComponents,
            ];

        }

        return $result;
    }

    // private function processPricing($itinerary , $fareComponentDescs,$baggageAllowanceDescs)
    // {
    //     if (empty($itinerary['pricingInformation'])) {
    //         return [];
    //     }
    //     $pricing = $itinerary['pricingInformation'][0]['fare']['totalFare'] ?? [];
    //     return [
    //         'offerId' => $itinerary['pricingInformation'][0]['offer']['offerId'] ?? "null",
    //         'totalPrice' => $pricing['totalPrice'] ?? null,
    //         'totalTaxAmount' => $pricing['totalTaxAmount'] ?? null,
    //         'currency' => $pricing['currency'] ?? null,
    //     ];
    // }

    private function processValidatingCarriers($itinerary, $validatingCarrierDescs)
    {
        $carriers = [];
        if (!empty($itinerary['pricingInformation'][0]['fare']['validatingCarriers'])) {
            foreach ($itinerary['pricingInformation'][0]['fare']['validatingCarriers'] as $validatingCarrier) {
                $validatingCarrierRef = $validatingCarrier['ref'] ?? null;
                if ($validatingCarrierRef !== null && isset($validatingCarrierDescs[$validatingCarrierRef - 1])) {
                    $carrierDesc = $validatingCarrierDescs[$validatingCarrierRef - 1];
                    $carriers[] = [
                        'code' => $carrierDesc['default']['code'] ?? null,
                        'settlementMethod' => $carrierDesc['settlementMethod'] ?? null,
                        'newVcxProcess' => $carrierDesc['newVcxProcess'] ?? null,
                    ];
                }
            }
        }
        return $carriers;
    }

    // private function processPassengerInfo($itinerary, $baggageAllowanceDescs)
    // {
    //     $passengerInfoList = [];

    //     if (!empty($itinerary['pricingInformation'])) {
    //         foreach ($itinerary['pricingInformation'][0]['fare']['passengerInfoList'] as $pricingInformation) {
    //             $info = $pricingInformation['passengerInfo'] ?? [];
    //             $baggageInfo = $info['baggageInformation'] ?? [];

    //             // Initialize fareComponents array
    //             $info['fareComponents'] = [];

    //             if (!empty($pricingInformation['fareComponents']) && is_array($pricingInformation['fareComponents'])) {
    //                 foreach ($pricingInformation['fareComponents'] as $fareComponent) {
    //                     if (!is_array($fareComponent)) {
    //                         continue; // Skip invalid fareComponent entries
    //                     }

    //                     $processedFareComponent = [
    //                         'ref' => $fareComponent['ref'] ?? null,
    //                         'beginAirport' => $fareComponent['beginAirport'] ?? null,
    //                         'endAirport' => $fareComponent['endAirport'] ?? null,
    //                         'segments' => [],
    //                     ];

    //                     // Process segments if available
    //                     if (!empty($fareComponent['segments']) && is_array($fareComponent['segments'])) {
    //                         foreach ($fareComponent['segments'] as $segment) {
    //                             if (!is_array($segment)) {
    //                                 continue; // Skip invalid segment entries
    //                             }

    //                             $processedSegment = [
    //                                 'bookingCode' => $segment['bookingCode'] ?? null,
    //                                 'cabinCode' => $segment['cabinCode'] ?? null,
    //                                 'seatsAvailable' => $segment['seatsAvailable'] ?? null,
    //                             ];

    //                             // Include mealCode if it exists
    //                             if (isset($segment['mealCode'])) {
    //                                 $processedSegment['mealCode'] = $segment['mealCode'];
    //                             }

    //                             // Include availabilityBreak if it exists
    //                             if (isset($segment['availabilityBreak'])) {
    //                                 $processedSegment['availabilityBreak'] = $segment['availabilityBreak'];
    //                             }

    //                             $processedFareComponent['segments'][] = $processedSegment;
    //                         }
    //                     }

    //                     $info['fareComponents'][] = $processedFareComponent;
    //                 }
    //             }

    //             // Keep baggage information untouched
    //             $info['baggage'] = $this->processBaggage($baggageInfo, $baggageAllowanceDescs);

    //             $passengerInfoList[] = $info;
    //         }
    //     }

    //     return $passengerInfoList;
    // }


    private function processPassengerInfo($itinerary, $baggageAllowanceDescs)
    {
        $passengerInfoList = [];
        if (!empty($itinerary['pricingInformation'])) {
            foreach ($itinerary['pricingInformation'] as $pricingInfo) {
                if (
                (isset($pricingInfo['soldOut']) && $pricingInfo['soldOut']) ||
                (isset($pricingInfo['soldOut']['status']) && $pricingInfo['soldOut']['status'] === 'F')
                ) {
                    continue;
                }
                // Each pricingInfo may contain multiple passengerInfo entries
                foreach ($pricingInfo['fare']['passengerInfoList'] as $pricingInformation) {
                    $info = $pricingInformation['passengerInfo'] ?? [];

                    // Extract baggage info
                    $baggageInfo = $info['baggageInformation'] ?? [];
                    $info['baggage'] = $this->processBaggage($baggageInfo, $baggageAllowanceDescs);

                    // Mark refundable status
                    $info['nonRefundable'] = $pricingInfo['nonRefundable'] ?? false;

                    // Add to final list
                    $passengerInfoList[] = $info;
                }
            }
        }

        return $passengerInfoList;
    }


    private function processBaggage($baggageInfo, $baggageAllowanceDescs)
    {
        $baggageList = [];
        foreach ($baggageInfo as $baggage) {
            foreach ($baggage['segments'] ?? [] as $segment) {
                $allowanceRef = $baggage['allowance']['ref'] ?? null;
                if ($allowanceRef !== null && isset($baggageAllowanceDescs[$allowanceRef - 1])) {
                    $allowance = $baggageAllowanceDescs[$allowanceRef - 1];
                    $baggageList[] = [
                        'pieces' => $allowance['pieceCount'] ?? null,
                        'weight' => $allowance['weight'] ?? null,
                        'unit' => $allowance['unit'] ?? null,
                        'provider' => $baggage['airlineCode'] ?? null,
                    ];
                }

            }
        }
        return $baggageList;
    }



    public function getBookingDetails()
    {
        return $this->sabreApiService->getBookingDetails();
    }
}
