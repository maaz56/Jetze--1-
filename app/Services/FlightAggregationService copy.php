<?php

namespace App\Services;

use App\Attributes\FlightAttributes;
use App\Models\Aircraft;
use App\Models\Airline;
use App\Models\Airport;
use Carbon\Carbon;
use Carbon\Traits\ToStringFormat;
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

    public function __construct(SabreApiService $sabreApiService, SooperApiService $sooperApiService)
    {
        $this->sabreApiService = $sabreApiService;
        $this->sooperApiService = $sooperApiService;

    }

    /**
     * @throws ConnectionException
     */
    public function getFlights($params)
    {


        $sabreFlights = null;
        //$sabreFlights = $this->sabreApiService->searchFlights($params);

        if ($sabreFlights != null) {
            $flights = $sabreFlights['groupedItineraryResponse'];
            $results = [

                'itineraries' => [],
            ];

            $baggageAllowanceDescs = $flights['baggageAllowanceDescs'] ?? [];
            $validatingCarrierDescs = $flights['validatingCarrierDescs'] ?? [];
            $uniqueItineraries = [];

            foreach ($flights['itineraryGroups'] ?? [] as $group) {

                $groupDescription = $group['groupDescription'] ?? [];
                $legDescriptions = $groupDescription['legDescriptions'] ?? [];

                foreach ($group['itineraries'] ?? [] as $itinerary) {
                    $processedItinerary = [
                        'id' => $itinerary['id'] . uniqid(),
                        'legs' => [],
                        'pricing' => [],
                        'validatingCarriers' => [],
                        'passengerInfo' => [],
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
                    $processedItinerary['pricing'] = $this->processPricing($itinerary);

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
        }else{
            $results = [
                'itineraries' => [],
            ];
        }



        //Cache::put('flights', $results);
        // $sooperFlights;
        // return $results;

        $sooperFlights = $this->sooperApiService->searchFlights($params);
        $sooperFlights = $this->processSooperfare($sooperFlights);
        
        return [
            'sooperFlights' => $sooperFlights,
            'results' => $results,
        ];
    }

     private function processSooperfare($sooperFlights)
    {
        //Log::info("Sooper API Search Flights Results: \n" . json_encode($sooperFlights, JSON_PRETTY_PRINT));
        $processedSooperFlights = [];

        foreach ($sooperFlights as $providerKey => $providerData) {
            if (isset($providerData['data']['providers']) && is_array($providerData['data']['providers'])) {
                foreach ($providerData['data']['providers'] as $providerInfo) {
                    $providerDetails = $providerInfo['provider'] ?? null;

                    if (!empty($providerInfo['legs']) && is_array($providerInfo['legs'])) {
                        foreach ($providerInfo['legs'] as $leg) {
                            $processedSooperFlights[] = [
                                'provider' => $providerDetails,
                                'leg' => $leg,
                            ];
                        }
                    }
                }
            }
        }
        //    Log::info("Sooper API Search Flights All Results:': \n" . json_encode($processedSooperFlights[0], JSON_PRETTY_PRINT));

        return response()->json([
            'status' => true,
            'data' => $processedSooperFlights
        ]);

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

    private function processPricing($itinerary)
    {
        if (empty($itinerary['pricingInformation'])) {
            return [];
        }
        $pricing = $itinerary['pricingInformation'][0]['fare']['totalFare'] ?? [];
        return [
            'totalPrice' => $pricing['totalPrice'] ?? null,
            'totalTaxAmount' => $pricing['totalTaxAmount'] ?? null,
            'currency' => $pricing['currency'] ?? null,
        ];
    }

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

    private function processPassengerInfo($itinerary, $baggageAllowanceDescs)
    {
        $passengerInfoList = [];
        if (!empty($itinerary['pricingInformation'])) {
            foreach ($itinerary['pricingInformation'][0]['fare']['passengerInfoList'] as $pricingInformation) {
                $info = $pricingInformation['passengerInfo'] ?? [];
                $baggageInfo = $info['baggageInformation'] ?? [];
                $info['baggage'] = $this->processBaggage($baggageInfo, $baggageAllowanceDescs);
                $passengerInfoList[] = $info;
            }
        }
        return $passengerInfoList;
    }


    private function processBaggage($baggageInfo, $baggageAllowanceDescs)
    {
        $baggageList = [];
        foreach ($baggageInfo as $baggage) {
            foreach ($baggage['segments'] ?? [] as $segment) {
                if ($segment['id'] == 0) { // Assuming index 0 for current leg
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
        }
        return $baggageList;
    }


    public function getBookingDetails()
    {
        return $this->sabreApiService->getBookingDetails();
    }
}
