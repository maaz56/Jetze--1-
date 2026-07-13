<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FlightAggregationService;
use App\Services\SooperApiService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class FlightController extends Controller
{
    protected $flightAggregator;
    protected $sooperApiService;

    public function __construct(FlightAggregationService $flightAggregationService, SooperApiService $sooperApiService)
    {
        $this->flightAggregator = $flightAggregationService;

        $this->sooperApiService = $sooperApiService;
    }
    public function fetchProviders()
    {



        $apiProviders = [
            // ['identifier' => 'AirBlue', 'name' => 'AirBlue'],
            // ['identifier' => 'OneApi', 'name' => 'OneApi'],
            ['identifier' => 'AT', 'name' => 'Akbar Travels'],

            // ['identifier' => 'TravelPort-GDS', 'name' => 'TravelPort-GDS'],
            // ['identifier' => 'TravelPort-NDC', 'name' => 'TravelPort-NDC'],
        ];
        // $apiProviders = array_merge($apiProviders, $sooperProviders);
        return response()->json($apiProviders);
    }

    public function index(Request $request)
    {
        // Determine the flight type
        $flightType = $request->flightType;
        // Log::info($request->airline);
        // Initialize params array
        $params = [
            'airline' => $request->airline,
            'cabin_class' => $request->cabin_class,
            'adults' => $request->adults ?? 1,
            'children' => $request->children ?? 0,
            'infants' => $request->infants ?? 0,
            'flight_type' => $flightType,
            'currency_code' => $request->currencyCode ?? 'PKR', // Default to PKR if not provided
            'flexible_plus_minus_3' => filter_var($request->flexible_plus_minus_3 ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        // Handle params based on flight type
        if ($flightType === 'multi-city') {
            $params['trips'] = $request->trips;
        } else {
            $params['origin'] = $request->origin;
            $params['destination'] = $request->destination;
            $params['departure_date'] = $request->departure_date;
            $params['return_date'] = $request->return_date; // Will be null for one-way
        }

        // Generate a unique cache key prefix based on user or session
        $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();

        Cache::forget($cacheKeyPrefix . '_previous_search');
        Cache::forget($cacheKeyPrefix . '_flights');
        Cache::forget($cacheKeyPrefix . '_sooper_flights');
        Cache::forget($cacheKeyPrefix . '_available_airlines');
        Cache::forget($cacheKeyPrefix . '_currency_code');
        // Store previous search parameters in the cache with TTL
        Cache::put($cacheKeyPrefix . '_previous_search', $params, now()->addHour());
        Cache::put($cacheKeyPrefix . '_currency_code', $request->currencyCode, now()->addHour());
        // Fetch flights from the aggregator
        $flights = $this->flightAggregator->getFlights($params);
        // Log::info('Flights Data: ' . json_encode($flights, JSON_PRETTY_PRINT));
        $sabreFlights = $flights['results'];
        // $sooperFlights = $flights['sooperFlights'];

        // Cache Sabre flights and Sooper flights with TTL


        // Initialize an empty array for airlines
        $airlines = [];

        // Collect airlines from Sabre flights
        // foreach ($sabreFlights['itineraries'] as $itinerary) {
        //     foreach ($itinerary['legs'] as $leg) {
        //         foreach ($leg['stops'] as $stop) {
        //             if (isset($stop['airline'])) {
        //                 $airlines[] = $stop['airline'];
        //             }
        //         }
        //     }
        // }

        // // Collect airlines from Sooper flights
        // foreach ($sooperFlights->original['data'] as $sooperFlight) {
        //     foreach ($sooperFlight['leg']['flights'] as $flight) {

        //         if (isset($flight['marketing_carrier'])) {
        //             $airlines[] = [
        //                 'id' => $flight['marketing_carrier']['iata'],
        //                 'logo' => $flight['marketing_carrier']['logo'],
        //                 'iata_code' => $flight['marketing_carrier']['iata'],
        //                 'name' => $flight['marketing_carrier']['name'],
        //             ];
        //         }
        //     }
        // }

        // Store available airlines in the cache with TTL
        Cache::put($cacheKeyPrefix . '_available_airlines', collect($airlines)->unique('id')->values()->all(), now()->addHour());

        // Initialize filtered flights for Sabre
        $filteredSabreFlights = $sabreFlights;

        // Apply filters for Sabre flights
        // if ($request->airline || $request->stops !== null || $request->price_min !== null || $request->price_max !== null) {
        //     $filteredItineraries = collect($sabreFlights['itineraries'])->filter(function ($itinerary) use ($request) {
        //         $valid = true;

        //         // Filter by airline IDs or IATA codes
        //         if ($request->airline) {
        //             $requestAirlines = (array) $request->airline;
        //             $valid = collect($itinerary['legs'])->flatMap(function ($leg) {
        //                 return $leg['stops'];
        //             })->contains(function ($stop) use ($requestAirlines) {
        //                 return isset($stop['airline']) && (
        //                     in_array($stop['airline']['id'], $requestAirlines) ||
        //                     in_array($stop['airline']['iata_code'], $requestAirlines)
        //                 );
        //             });
        //         }

        //         // Filter by stops count
        //         if ($valid && $request->stops !== null) {
        //             $valid = false;
        //             foreach ($itinerary['legs'] as $leg) {
        //                 $stops = count($leg['stops']);
        //                 if (
        //                     ($request->stops == 1 && $stops == 2) ||
        //                     ($request->stops == 2 && $stops == 3) || $request->stops == 'all'
        //                 ) {
        //                     $valid = true;
        //                     break;
        //                 }
        //             }
        //         }

        //         // Filter by price range
        //         if ($valid && ($request->price_min !== null || $request->price_max !== null)) {
        //             $price = $itinerary['pricing']['totalPrice'];
        //             if ($request->price_min !== null && $price < $request->price_min) {
        //                 return false;
        //             }
        //             if ($request->price_max !== null && $price > $request->price_max) {
        //                 return false;
        //             }
        //         }

        //         return $valid;
        //     })->values()->all();

        //     // Update only the itineraries while keeping the original structure
        //     $filteredSabreFlights['itineraries'] = $filteredItineraries;
        // }

        // Sort Sabre itineraries by price (lowest to highest)
        // usort($filteredSabreFlights['itineraries'], function ($a, $b) {
        //     return $a['pricing']['totalPrice'] <=> $b['pricing']['totalPrice'];
        // });

        // Initialize filtered flights for Sooper
        //$filteredSooperFlights = $sooperFlights;
        // Apply filters for Sooper flights
        // if ($request->stops !== null || $request->price_min !== null || $request->price_max !== null) {

        //     $filteredSooperData = $sooperFlights->filter(function ($sooperFlight) use ($request) {
        //         $valid = true;

        //         // Filter by airline IATA codes
        //         if ($request->airline) {
        //             $requestAirlines = (array) $request->airline;
        //             $valid = collect($sooperFlight['leg']['flights'])->contains(function ($flight) use ($requestAirlines) {
        //                 return isset($flight['marketing_carrier']['iata']) &&
        //                     in_array($flight['marketing_carrier']['iata'], $requestAirlines);
        //             });
        //         }

        //         // Filter by stops count (layovers_count in Sooper API)
        //         if ($valid && $request->stops !== null) {
        //             $valid = collect($sooperFlight['leg']['flights'])->contains(function ($flight) use ($request) {
        //                 $stops = $flight['layovers_count'];
        //                 if (
        //                     ($request->stops == 0 && $stops == 0) ||
        //                     ($request->stops == 1 && $stops == 1) ||
        //                     ($request->stops == 2 && $stops >= 2) ||
        //                     $request->stops == 'all'
        //                 ) {
        //                     return true;
        //                 }
        //                 return false;
        //             });
        //         }

        //         // Filter by price range
        //         if ($valid && ($request->price_min !== null || $request->price_max !== null)) {
        //             $valid = collect($sooperFlight['leg']['flights'])->flatMap(function ($flight) {
        //                 return $flight['fares'];
        //             })->contains(function ($fare) use ($request) {
        //                 $price = $fare['total_price'];
        //                 if ($request->price_min !== null && $price < $request->price_min) {
        //                     return false;
        //                 }
        //                 if ($request->price_max !== null && $price > $request->price_max) {
        //                     return false;
        //                 }
        //                 return true;
        //             });
        //         }

        //         return $valid;
        //     })->values()->all();

        //     // Update only the data while keeping the original structure
        //     $filteredSooperFlights = $filteredSooperData;
        // } else {
        //     // If no filters are applied, use the original Sooper flights data
        //     $filteredSooperFlights = $sooperFlights->original['data'];
        // }

        // Sort Sooper flights by price (lowest to highest)
        // $filteredSooperFlights = collect($filteredSooperFlights)->sortBy(function ($sooperFlight) {
        //     return collect($sooperFlight['leg']['flights'])->flatMap(function ($flight) {
        //         return $flight['fares'];
        //     })->min('total_price');
        // })->values()->all();

        // Get the lowest-priced itinerary for each airline from Sabre flights
        // $cheapestSabreFlightsByAirline = collect($filteredSabreFlights['itineraries'])
        //     ->groupBy(function ($itinerary) {
        //         return $itinerary['legs'][0]['stops'][0]['airline']['id'] ?? 'unknown';
        //     })
        //     ->map(function ($itineraries) {
        //         return $itineraries->sortBy('pricing.totalPrice')->first();
        //     })
        //     ->values()
        //     ->all();

        // Get the lowest-priced flight for each airline from Sooper flights
        // $cheapestSooperFlightsByAirline = collect($filteredSooperFlights)
        //     ->groupBy(function ($sooperFlight) {
        //         return $sooperFlight['leg']['flights'][0]['marketing_carrier']['iata'] ?? 'unknown';
        //     })
        //     ->map(function ($flights) {
        //         return collect($flights)->sortBy(function ($flight) {
        //             return collect($flight['leg']['flights'])->flatMap(function ($f) {
        //                 return $f['fares'];
        //             })->min('total_price');
        //         })->first();
        //     })
        //     ->values()
        //     ->all();

        // Merge cheapest flights from both Sabre and Sooper
        // $filteredSooperFlights = array_merge($sooperFlights);
        // Log::info(json_encode($filteredSooperFlights));
        return [
            // 'flights' => $filteredSabreFlights,
            'sooper_flights' => $filteredSabreFlights,
            // 'cheapest_flights_by_airline' => $cheapestFlightsByAirline,
            // 'previous_search' => Cache::get($cacheKeyPrefix . '_previous_search'),
            // 'available_airlines' => Cache::get($cacheKeyPrefix . '_available_airlines'),
        ];
    }
    // public function index(Request $request)
    // {
    //     // Determine the flight type
    //     $flightType = $request->flightType;

    //     // Initialize params array
    //     $params = [
    //         'cabin_class' => $request->cabin_class,
    //         'adults' => $request->adults ?? 1,
    //         'children' => $request->children ?? 0,
    //         'infants' => $request->infants ?? 0,
    //         'flight_type' => $flightType,
    //     ];

    //     // Handle params based on flight type
    //     if ($flightType === 'multi-city') {
    //         $params['trips'] = $request->trips;
    //     } else {
    //         $params['origin'] = $request->origin;
    //         $params['destination'] = $request->destination;
    //         $params['departure_date'] = $request->departure_date;
    //         $params['return_date'] = $request->return_date; // Will be null for one-way
    //     }

    //     // Generate a unique cache key prefix based on user or session
    //     $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();

    //     Cache::forget($cacheKeyPrefix . '_previous_search');
    //     Cache::forget($cacheKeyPrefix . '_flights');
    //     Cache::forget($cacheKeyPrefix . '_sooper_flights');
    //     Cache::forget($cacheKeyPrefix . '_available_airlines');

    //     // Store previous search parameters in the cache with TTL
    //     Cache::put($cacheKeyPrefix . '_previous_search', $params, now()->addHour());

    //     // Fetch flights from the aggregator
    //     $flights = $this->flightAggregator->getFlights($params);
    //     $sabreFlights = $flights['results'];
    //     $sooperFlights = $flights['sooperFlights'];

    //     Log::info("sooperFlights: " . json_encode($sooperFlights, JSON_PRETTY_PRINT));
    //     Log::info("Sabre Flights: " . json_encode($sabreFlights, JSON_PRETTY_PRINT));
    //     // Cache Sabre flights and Sooper flights with TTL
    //     Cache::put($cacheKeyPrefix . '_flights', $sabreFlights, now()->addHour());
    //     Cache::put($cacheKeyPrefix . '_sooper_flights', $sooperFlights, now()->addHour());

    //     // Initialize an empty array for airlines
    //     $airlines = [];

    //     // Collect airlines from Sabre flights
    //     foreach ($sabreFlights['itineraries'] as $itinerary) {
    //         foreach ($itinerary['legs'] as $leg) {
    //             foreach ($leg['stops'] as $stop) {
    //                 if (isset($stop['airline'])) {
    //                     $airlines[] = $stop['airline'];
    //                 }
    //             }
    //         }
    //     }

    //     // Collect airlines from Sooper flights
    //     foreach ($sooperFlights->original['data'] as $sooperFlight) {
    //         //Log::info("Sooper Flight:2 " . json_encode($sooperFlight, JSON_PRETTY_PRINT));
    //         foreach ($sooperFlight['leg']['flights'] as $flight) {

    //             if (isset($flight['marketing_carrier'])) {
    //                 $airlines[] = [
    //                     'id' => $flight['marketing_carrier']['iata'],
    //                     'logo' => $flight['marketing_carrier']['logo'],
    //                     'iata_code' => $flight['marketing_carrier']['iata'],
    //                     'name' => $flight['marketing_carrier']['name'],
    //                 ];
    //             }
    //         }
    //     }
    //     Log::info("Collected Airlines: " . json_encode($airlines, JSON_PRETTY_PRINT));

    //     // Store available airlines in the cache with TTL
    //     Cache::put($cacheKeyPrefix . '_available_airlines', collect($airlines)->unique('id')->values()->all(), now()->addHour());

    //     // Initialize filtered flights for Sabre
    //     $filteredSabreFlights = $sabreFlights;

    //     // Apply filters for Sabre flights
    //     if ($request->airline || $request->stops !== null || $request->price_min !== null || $request->price_max !== null) {
    //         $filteredItineraries = collect($sabreFlights['itineraries'])->filter(function ($itinerary) use ($request) {
    //             $valid = true;

    //             // Filter by airline IDs or IATA codes
    //             if ($request->airline) {
    //                 $requestAirlines = (array) $request->airline;
    //                 $valid = collect($itinerary['legs'])->flatMap(function ($leg) {
    //                     return $leg['stops'];
    //                 })->contains(function ($stop) use ($requestAirlines) {
    //                     return isset($stop['airline']) && (
    //                         in_array($stop['airline']['id'], $requestAirlines) ||
    //                         in_array($stop['airline']['iata_code'], $requestAirlines)
    //                     );
    //                 });
    //             }

    //             // Filter by stops count
    //             if ($valid && $request->stops !== null) {
    //                 $valid = false;
    //                 foreach ($itinerary['legs'] as $leg) {
    //                     $stops = count($leg['stops']);
    //                     if (
    //                         ($request->stops == 1 && $stops == 2) ||
    //                         ($request->stops == 2 && $stops == 3) || $request->stops == 'all'
    //                     ) {
    //                         $valid = true;
    //                         break;
    //                     }
    //                 }
    //             }

    //             // Filter by price range
    //             if ($valid && ($request->price_min !== null || $request->price_max !== null)) {
    //                 $price = $itinerary['pricing']['totalPrice'];
    //                 if ($request->price_min !== null && $price < $request->price_min) {
    //                     return false;
    //                 }
    //                 if ($request->price_max !== null && $price > $request->price_max) {
    //                     return false;
    //                 }
    //             }

    //             return $valid;
    //         })->values()->all();

    //         // Update only the itineraries while keeping the original structure
    //         $filteredSabreFlights['itineraries'] = $filteredItineraries;
    //     }

    //     // Sort Sabre itineraries by price (lowest to highest)
    //     usort($filteredSabreFlights['itineraries'], function ($a, $b) {
    //         return $a['pricing']['totalPrice'] <=> $b['pricing']['totalPrice'];
    //     });

    //     // Initialize filtered flights for Sooper
    //     $filteredSooperFlight = $sooperFlights;

    //     // Apply filters for Sooper flights
    //     if ($request->airline || $request->stops !== null || $request->price_min !== null || $request->price_max !== null) {

    //         $filteredSooperData = collect($sooperFlights->original['data'])->filter(function ($sooperFlight) use ($request) {
    //             $valid = true;

    //             // Filter by airline IATA codes
    //             if ($request->airline) {
    //                 $requestAirlines = (array) $request->airline;
    //                 $valid = collect($sooperFlight['leg']['flights'])->contains(function ($flight) use ($requestAirlines) {
    //                     return isset($flight['marketing_carrier']['iata']) &&
    //                         in_array($flight['marketing_carrier']['iata'], $requestAirlines);
    //                 });
    //             }

    //             // Filter by stops count (layovers_count in Sooper API)
    //             if ($valid && $request->stops !== null) {
    //                 $valid = collect($sooperFlight['leg']['flights'])->contains(function ($flight) use ($request) {
    //                     $stops = $flight['layovers_count'];
    //                     if (
    //                         ($request->stops == 0 && $stops == 0) ||
    //                         ($request->stops == 1 && $stops == 1) ||
    //                         ($request->stops == 2 && $stops >= 2) ||
    //                         $request->stops == 'all'
    //                     ) {
    //                         return true;
    //                     }
    //                     return false;
    //                 });
    //             }

    //             // Filter by price range
    //             if ($valid && ($request->price_min !== null || $request->price_max !== null)) {
    //                 $valid = collect($sooperFlight['leg']['flights'])->flatMap(function ($flight) {
    //                     return $flight['fares'];
    //                 })->contains(function ($fare) use ($request) {
    //                     $price = $fare['total_price'];
    //                     if ($request->price_min !== null && $price < $request->price_min) {
    //                         return false;
    //                     }
    //                     if ($request->price_max !== null && $price > $request->price_max) {
    //                         return false;
    //                     }
    //                     return true;
    //                 });
    //             }

    //             return $valid;
    //         })->values()->all();

    //         // Update only the data while keeping the original structure
    //         $filteredSooperFlight = $filteredSooperData;
    //     } else {
    //         // If no filters are applied, use the original Sooper flights data
    //         $filteredSooperFlight = $sooperFlights->original['data'];
    //     }

    //     // Sort Sooper flights by price (lowest to highest)
    //     $filteredSooperFlights = collect($filteredSooperFlight)->sortBy(function ($sooperFlight) {
    //         // Get all fares for all flights
    //         $allFares = collect($sooperFlight['leg']['flights'])->flatMap(function ($flight) {
    //             return $flight['fares'];
    //         });

    //         // If more than one flight exists
    //         if (count($sooperFlight['leg']['flights']) > 1) {
    //             // Group fares by flight (assuming one fare per flight for simplification)
    //             $flights = $sooperFlight['leg']['flights'];
    //             $total = 0;

    //             foreach ($flights as $flight) {
    //                 // Get the lowest fare for this flight
    //                 $minFare = collect($flight['fares'])->min('total_price');
    //                 $total += $minFare ?? 0;
    //             }

    //             return $total;
    //         } else {
    //             // Only one flight, return its minimum fare
    //             return $allFares->min('total_price');
    //         }
    //     })->values()->all();

    //     // Get the lowest-priced itinerary for each airline from Sabre flights
    //     $cheapestSabreFlightsByAirline = collect($filteredSabreFlights['itineraries'])
    //         ->groupBy(function ($itinerary) {
    //             return $itinerary['legs'][0]['stops'][0]['airline']['id'] ?? 'unknown';
    //         })
    //         ->map(function ($itineraries) {
    //             return $itineraries->sortBy('pricing.totalPrice')->first();
    //         })
    //         ->values()
    //         ->all();

    //     // Get the lowest-priced flight for each airline from Sooper flights
    //     $cheapestSooperFlightsByAirline = collect($filteredSooperFlights)
    //         ->groupBy(function ($sooperFlight) {
    //             return $sooperFlight['leg']['flights'][0]['marketing_carrier']['iata'] ?? 'unknown';
    //         })
    //         ->map(function ($flights) {
    //             return collect($flights)->sortBy(function ($flight) {
    //                 $flightsInLeg = $flight['leg']['flights'];
    //                 $total = 0;

    //                 if (count($flightsInLeg) > 1) {
    //                     // Multiple flights: sum the lowest fare of each
    //                     foreach ($flightsInLeg as $f) {
    //                         $minFare = collect($f['fares'])->min('total_price');
    //                         $total += $minFare ?? 0;
    //                     }
    //                     return $total;
    //                 } else {
    //                     // Single flight: just get its minimum fare
    //                     return collect($flightsInLeg[0]['fares'])->min('total_price');
    //                 }
    //             })->first();
    //         })
    //         ->values()
    //         ->all();


    //     // Merge cheapest flights from both Sabre and Sooper
    //     $cheapestFlightsByAirline = array_merge($cheapestSooperFlightsByAirline, $cheapestSabreFlightsByAirline);
    //     Log::info("Cheapest Flights by Airline: " . json_encode($cheapestFlightsByAirline, JSON_PRETTY_PRINT));
    //     Log::info("Available Airlines: " . json_encode(Cache::get($cacheKeyPrefix . '_available_airlines'), JSON_PRETTY_PRINT));

    //     return [
    //         'flights' => $filteredSabreFlights,
    //         'sooper_flights' => $filteredSooperFlights,
    //         'cheapest_flights_by_airline' => $cheapestFlightsByAirline,
    //         'previous_search' => Cache::get($cacheKeyPrefix . '_previous_search'),
    //         'available_airlines' => Cache::get($cacheKeyPrefix . '_available_airlines'),
    //     ];
    // }



    // public function index(Request $request)
    // {
    //     // Determine the flight type
    //     $flightType = $request->flightType;

    //     // Initialize params array
    //     $params = [
    //         'cabin_class' => $request->cabin_class,
    //         'adults' => $request->adults ?? 1,
    //         'children' => $request->children ?? 0,
    //         'infants' => $request->infants ?? 0,
    //         'flight_type' => $flightType,
    //     ];

    //     // Handle params based on flight type
    //     if ($flightType === 'multi-city') {
    //         $params['trips'] = $request->trips;
    //     } else {
    //         $params['origin'] = $request->origin;
    //         $params['destination'] = $request->destination;
    //         $params['departure_date'] = $request->departure_date;
    //         $params['return_date'] = $request->return_date; // Will be null for one-way
    //     }

    //     // Generate a unique cache key prefix based on user or session
    //     $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();

    //     Cache::forget($cacheKeyPrefix . '_previous_search');
    //     Cache::forget($cacheKeyPrefix . '_flights');
    //     Cache::forget($cacheKeyPrefix . '_sooper_flights');
    //     Cache::forget($cacheKeyPrefix . '_available_airlines');

    //     // Store previous search parameters in the cache with TTL
    //     Cache::put($cacheKeyPrefix . '_previous_search', $params, now()->addHour());

    //     // Fetch flights from the aggregator
    //     $flights = $this->flightAggregator->getFlights($params);
    //     $sabreFlights = $flights['results'];
    //     $sooperFlights = $flights['sooperFlights'];

    //     // Cache Sabre flights and Sooper flights with TTL
    //     Cache::put($cacheKeyPrefix . '_flights', $sabreFlights, now()->addHour());
    //     Cache::put($cacheKeyPrefix . '_sooper_flights', $sooperFlights, now()->addHour());

    //     // Initialize an empty array for airlines
    //     $airlines = [];

    //     // Collect airlines from the flights
    //     foreach ($sabreFlights['itineraries'] as $itinerary) {
    //         foreach ($itinerary['legs'] as $leg) {
    //             foreach ($leg['stops'] as $stop) {
    //                 if (isset($stop['airline'])) {
    //                     $airlines[] = $stop['airline'];
    //                 }
    //             }
    //         }
    //     }

    //     // Store available airlines in the cache with TTL
    //     Cache::put($cacheKeyPrefix . '_available_airlines', collect($airlines)->unique('id')->values()->all(), now()->addHour());

    //     // Initialize filtered flights with the same structure
    //     $filteredFlights = $sabreFlights;

    //     // Apply filters based on request parameters
    //     if ($request->airline || $request->stops !== null || $request->price_min !== null || $request->price_max !== null) {
    //         $filteredItineraries = collect($sabreFlights['itineraries'])->filter(function ($itinerary) use ($request) {
    //             $valid = true;

    //             // Filter by airline IDs or IATA codes
    //             if ($request->airline) {
    //                 $requestAirlines = (array) $request->airline;
    //                 $valid = collect($itinerary['legs'])->flatMap(function ($leg) {
    //                     return $leg['stops'];
    //                 })->contains(function ($stop) use ($requestAirlines) {
    //                     return isset($stop['airline']) && (
    //                         in_array($stop['airline']['id'], $requestAirlines) ||
    //                         in_array($stop['airline']['iata_code'], $requestAirlines)
    //                     );
    //                 });
    //             }

    //             // Filter by stops count
    //             if ($valid && $request->stops !== null) {
    //                 $valid = false;
    //                 foreach ($itinerary['legs'] as $leg) {
    //                     $stops = count($leg['stops']);
    //                     if (
    //                         ($request->stops == 1 && $stops == 2) ||
    //                         ($request->stops == 2 && $stops == 3) || $request->stops == 'all'
    //                     ) {
    //                         $valid = true;
    //                         break;
    //                     }
    //                 }
    //             }

    //             // Filter by price range
    //             if ($valid && ($request->price_min !== null || $request->price_max !== null)) {
    //                 $price = $itinerary['pricing']['totalPrice'];
    //                 if ($request->price_min !== null && $price < $request->price_min) {
    //                     return false;
    //                 }
    //                 if ($request->price_max !== null && $price > $request->price_max) {
    //                     return false;
    //                 }
    //             }

    //             return $valid;
    //         })->values()->all();

    //         // Update only the itineraries while keeping the original structure
    //         $filteredFlights['itineraries'] = $filteredItineraries;
    //     }

    //     // Sort itineraries by price (lowest to highest)
    //     usort($filteredFlights['itineraries'], function ($a, $b) {
    //         return $a['pricing']['totalPrice'] <=> $b['pricing']['totalPrice'];
    //     });

    //     // Get the lowest-priced itinerary for each airline
    //     $cheapestFlightsByAirline = collect($filteredFlights['itineraries'])
    //         ->groupBy(function ($itinerary) {
    //             return $itinerary['legs'][0]['stops'][0]['airline']['id'] ?? 'unknown';
    //         })
    //         ->map(function ($itineraries) {
    //             return $itineraries->sortBy('pricing.totalPrice')->first();
    //         })
    //         ->values()
    //         ->all();
    //     //     Log::info("______________________________________");
    //     //    // Log::info(Cache::get($cacheKeyPrefix . '_sooper_flights').json_encode($sooperFlights, JSON_PRETTY_PRINT));
    //         Log::info("Sooper Flights:".json_encode($sooperFlights, JSON_PRETTY_PRINT) );
    //     //     Log::info("______________________________________");

    //     return [
    //         'flights' => $filteredFlights,
    //         'cheapest_flights_by_airline' => $cheapestFlightsByAirline,
    //         'previous_search' => Cache::get($cacheKeyPrefix . '_previous_search'),
    //         'available_airlines' => Cache::get($cacheKeyPrefix . '_available_airlines'),
    //         'sooper_flights' => $sooperFlights,
    //     ];
    // }

    // public function show(Request $request, $id)
    // {
    //     // Retrieve all flights from the cache
    //     $flights = Cache::get('flights');

    //     // Check if flights data is available
    //     if (!$flights || !isset($flights['itineraries'])) {
    //         return response()->json(['message' => 'No flights available'], 404);
    //     }

    //     // Search for the specific itinerary by ID
    //     $itinerary = collect($flights['itineraries'])->firstWhere('id', $id);

    //     // Check if the itinerary exists
    //     if (!$itinerary) {
    //         return response()->json(['message' => 'Flight not found'], 404);
    //     }

    //     return response()->json($itinerary);
    // }

    public function show(Request $request, $id, $supplier, $isSooperFlight)
    {
        Log::info($isSooperFlight);
        // Generate the cache key prefix based on user or session
        $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();

        // Retrieve Sabre flights and Sooper flights from the cache
        $sabreFlights = Cache::get($cacheKeyPrefix . '_flights');
        $sooperFlights = Cache::get($cacheKeyPrefix . '_sooper_flights');
        //The itinerary ID is used to fetch specific flight details from either Sabre or Sooper flights.

        // Check if either Sabre or Sooper flights data is available
        if (!$sabreFlights && !$sooperFlights) {
            return response()->json(['message' => 'No flights available'], 404);
        }


        // Search for the specific itinerary by ID in Sabre flights
        $itinerary = null;
        if ($isSooperFlight) {

            $itineraryCollection = collect($sooperFlights);


            //Log::info( $itineraryCollection['original']['data'][0]['leg']['ref_id']);
            foreach ($itineraryCollection['original']['data'] as $item) {
                if (isset($item['leg']['ref_id']) && $item['leg']['ref_id'] === $id) {
                    $itinerary = $item;
                    break; // stop loop once match is found
                }
            }

            if ($itinerary) {
                Log::info("Matched Itinerary Found:", ['itinerary' => $itinerary]);
                return response()->json($itinerary);
            } else {
                Log::info("No matching itinerary found for ref_id: $id");
            }

        }

        if ($sabreFlights && isset($sabreFlights['itineraries'])) {
            $itinerary = collect($sabreFlights['itineraries'])->firstWhere('id', $id);
        }

        // If not found in Sabre flights, search in Sooper flights
        if (!$itinerary && $sooperFlights && isset($sooperFlights['itineraries'])) {
            $itinerary = collect($sooperFlights['itineraries'])->firstWhere('id', $id);
        }

        // Check if the itinerary exists
        if (!$itinerary) {
            return response()->json(['message' => 'Flight not found'], 404);
        }

        return response()->json($itinerary);
    }

    public function sortFlights(Request $request)
    {
        $sooperFlights = $request['flights']; // Changed to $request->all() to handle array input

        // Cache Sabre flights and Sooper flights with TTL


        // Initialize an empty array for airlines
        $airlines = [];

        // Collect airlines from Sabre flights
        // foreach ($sabreFlights['itineraries'] as $itinerary) {
        //     foreach ($itinerary['legs'] as $leg) {
        //         foreach ($leg['stops'] as $stop) {
        //             if (isset($stop['airline'])) {
        //                 $airlines[] = $stop['airline'];
        //             }
        //         }
        //     }
        // }
        $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
        Cache::forget($cacheKeyPrefix . '_available_airlines');

        // Collect airlines from Sooper flights
        foreach ($sooperFlights as $sooperFlight) { // Adjusted to iterate directly over the array
            foreach ($sooperFlight['leg']['flights'] as $flight) {
                if (isset($flight['marketing_carrier'])) {
                    $airlines[] = [
                        'id' => $flight['marketing_carrier']['iata'],
                        'logo' => $flight['marketing_carrier']['logo'],
                        'iata_code' => $flight['marketing_carrier']['iata'],
                        'name' => $flight['marketing_carrier']['name'],
                    ];
                }
            }
        }

        // Store available airlines in the cache with TTL
        Cache::put($cacheKeyPrefix . '_available_airlines', collect($airlines)->unique('id')->values()->all(), now()->addHour());

        // Initialize filtered flights for Sabre
        // $filteredSabreFlights = $sabreFlights;

        // Apply filters for Sabre flights
        // if ($request->airline || $request->stops !== null || $request->price_min !== null || $request->price_max !== null) {
        //     $filteredItineraries = collect($sabreFlights['itineraries'])->filter(function ($itinerary) use ($request) {
        //         $valid = true;

        //         // Filter by airline IDs or IATA codes
        //         if ($request->airline) {
        //             $requestAirlines = (array) $request->airline;
        //             $valid = collect($itinerary['legs'])->flatMap(function ($leg) {
        //                 return $leg['stops'];
        //             })->contains(function ($stop) use ($requestAirlines) {
        //                 return isset($stop['airline']) && (
        //                     in_array($stop['airline']['id'], $requestAirlines) ||
        //                     in_array($stop['airline']['iata_code'], $requestAirlines)
        //                 );
        //             });
        //         }

        //         // Filter by stops count
        //         if ($valid && $request->stops !== null) {
        //             $valid = false;
        //             foreach ($itinerary['legs'] as $leg) {
        //                 $stops = count($leg['stops']);
        //                 if (
        //                     ($request->stops == 1 && $stops == 2) ||
        //                     ($request->stops == 2 && $stops == 3) || $request->stops == 'all'
        //                 ) {
        //                     $valid = true;
        //                     break;
        //                 }
        //             }
        //         }

        //         // Filter by price range
        //         if ($valid && ($request->price_min !== null || $request->price_max !== null)) {
        //             $price = $itinerary['pricing']['totalPrice'];
        //             if ($request->price_min !== null && $price < $request->price_min) {
        //                 return false;
        //             }
        //             if ($request->price_max !== null && $price > $request->price_max) {
        //                 return false;
        //             }
        //         }

        //         return $valid;
        //     })->values()->all();

        //     // Update only the itineraries while keeping the original structure
        //     $filteredSabreFlights['itineraries'] = $filteredItineraries;
        // }

        // // Sort Sabre itineraries by price (lowest to highest)
        // usort($filteredSabreFlights['itineraries'], function ($a, $b) {
        //     return $a['pricing']['totalPrice'] <=> $b['pricing']['totalPrice'];
        // });

        // Initialize filtered flights for Sooper
        $filteredSooperFlights = $sooperFlights; // Initialize with the array input

        // Apply filters for Sooper flights
        if ($request['airline'] || $request['stops'] !== null || $request->price_min !== null || $request->price_max !== null) { // Fixed typo: $request-$airlines to $request['airline']
            $filteredSooperData = collect($sooperFlights)->filter(function ($sooperFlight) use ($request) {
                $valid = true;

                // Filter by airline IATA codes
                if ($request['airline']) {
                    $requestAirlines = (array) $request['airline'];
                    $valid = collect($sooperFlight['leg']['flights'])->contains(
                        function ($flight) use ($requestAirlines) {
                            return isset($flight['marketing_carrier']['iata']) &&
                                in_array($flight['marketing_carrier']['iata'], $requestAirlines);
                        }
                    );
                }

                // Filter by stops count (layovers_count in Sooper API)
                if ($valid && $request['stops'] !== null) {
                    $valid = collect($sooperFlight['leg']['flights'])->contains(
                        function ($flight) use ($request) {
                            $stops = $flight['layovers_count'];
                            if (
                                ($request['stops'] == 0 && $stops == 0) ||
                                ($request['stops'] == 1 && $stops == 1) ||
                                ($request['stops'] == 2 && $stops >= 2) ||
                                $request['stops'] == 'all'
                            ) {
                                return true;
                            }
                            return false;
                        }
                    );
                }

                // Filter by price range
                if ($valid && ($request->price_min !== null || $request->price_max !== null)) {
                    $valid = collect($sooperFlight['leg']['flights'])->flatMap(
                        function ($flight) {
                            return $flight['fares'];
                        }
                    )->contains(
                            function ($fare) use ($request) {
                                $price = $fare['total_price'];
                                if ($request->price_min !== null && $price < $request->price_min) {
                                    return false;
                                }
                                if ($request->price_max !== null && $price > $request->price_max) {
                                    return false;
                                }
                                return true;
                            }
                        );
                }

                return $valid;
            })->values()->all();

            // Update only the data while keeping the original structure
            $filteredSooperFlights = $filteredSooperData;
        } else {
            // If no filters are applied, use the original Sooper flights data
            $filteredSooperFlights = $sooperFlights;
        }

        // Sort Sooper flights by price (lowest to highest)
        $filteredSooperFlights = collect($filteredSooperFlights)->sortBy(function ($sooperFlight) {
            return collect($sooperFlight['leg']['flights'])->flatMap(
                function ($flight) {
                    return $flight['fares'];
                }
            )->min('total_price');
        })->values()->all();

        // Get the lowest-priced itinerary for each airline from Sabre flights
        // $cheapestSabreFlightsByAirline = collect($filteredSabreFlights['itineraries'])
        //     ->groupBy(function ($itinerary) {
        //         return $itinerary['legs'][0]['stops'][0]['airline']['id'] ?? 'unknown';
        //     })
        //     ->map(function ($itineraries) {
        //         return $itineraries->sortBy('pricing.totalPrice')->first();
        //     })
        //     ->values()
        //     ->all();

        // Get the lowest-priced flight for each airline from Sooper flights
        $cheapestSooperFlightsByAirline = collect($filteredSooperFlights)
            ->groupBy(function ($sooperFlight) {
                return $sooperFlight['leg']['flights'][0]['marketing_carrier']['iata'] ?? 'unknown';
            })
            ->map(function ($flights) {
                return collect($flights)->sortBy(
                    function ($flight) {
                        return collect($flight['leg']['flights'])->flatMap(
                            function ($f) {
                                return $f['fares'];
                            }
                        )->min('total_price');
                    }
                )->first();
            })
            ->values()
            ->all();

        // Merge cheapest flights from both Sabre and Sooper
        // $cheapestFlightsByAirline = array_merge($cheapestSabreFlightsByAirline, $cheapestSooperFlightsByAirline);
        $cheapestFlightsByAirline = array_merge($cheapestSooperFlightsByAirline);
        // Log::info($filteredSooperFlights);
        return [
            // 'flights' => $filteredSabreFlights,
            'sooper_flights' => $filteredSooperFlights,
            'cheapest_flights_by_airline' => $cheapestFlightsByAirline,
            'previous_search' => Cache::get($cacheKeyPrefix . '_previous_search'),
            'available_airlines' => Cache::get($cacheKeyPrefix . '_available_airlines'),
        ];
    }

    public function getBookingDetails()
    {
        return [
            'Booking Details' => $this->flightAggregator->getBookingDetails()
        ];
    }
}
