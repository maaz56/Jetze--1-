<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AtApiService;
use App\Services\AncillaryPricingService;
use App\Services\FlightAggregationService;
use App\Services\PriceQuoteService;
use App\Services\SooperApiService;
use App\Transformers\AtAncillaryTransformer;
use App\Transformers\AtFareBreakdownTransformer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class FlightController extends Controller
{
    protected $flightAggregator;
    protected $atApiService;
    protected $sooperApiService;
    protected $priceQuoteService;
    protected $ancillaryPricingService;
    protected $atAncillaryTransformer;
    protected $atFareBreakdownTransformer;

    public function __construct(
        FlightAggregationService $flightAggregationService,
        AtApiService $atApiService,
        SooperApiService $sooperApiService,
        PriceQuoteService $priceQuoteService,
        AncillaryPricingService $ancillaryPricingService,
        AtAncillaryTransformer $atAncillaryTransformer,
        AtFareBreakdownTransformer $atFareBreakdownTransformer,
    )
    {
        $this->flightAggregator = $flightAggregationService;
        $this->atApiService = $atApiService;
        $this->sooperApiService = $sooperApiService;
        $this->priceQuoteService = $priceQuoteService;
        $this->ancillaryPricingService = $ancillaryPricingService;
        $this->atAncillaryTransformer = $atAncillaryTransformer;
        $this->atFareBreakdownTransformer = $atFareBreakdownTransformer;
    }
    public function fetchProviders()
    {



        $apiProviders = [
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
        $currencyCode = $this->currencyCodeForRequest($request);
        // Log::info($request->airline);
        // Initialize params array
        $params = [
            'airline' => $request->airline,
            'cabin_class' => $request->cabin_class,
            'adults' => $request->adults ?? 1,
            'children' => $request->children ?? 0,
            'infants' => $request->infants ?? 0,
            'flight_type' => $flightType,
            'currency_code' => $currencyCode,
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

        // Use the Sanctum-authenticated user so search and checkout share one cache key.
        $cacheKeyPrefix = $this->flightCacheKey($request);

        Cache::forget($cacheKeyPrefix . '_previous_search');
        Cache::forget($cacheKeyPrefix . '_flights');
        Cache::forget($cacheKeyPrefix . '_sooper_flights');
        Cache::forget($cacheKeyPrefix . '_available_airlines');
        Cache::forget($cacheKeyPrefix . '_currency_code');
        // Store previous search parameters in the cache with TTL
        Cache::put($cacheKeyPrefix . '_previous_search', $params, now()->addHour());
        Cache::put($cacheKeyPrefix . '_currency_code', $currencyCode, now()->addHour());
        // Fetch flights from the aggregator
        $flights = $this->flightAggregator->getFlights($params);
        // Log::info('Flights Data: ' . json_encode($flights, JSON_PRETTY_PRINT));
        $sabreFlights = $flights['results'];
        // $sooperFlights = $flights['sooperFlights'];

        // A search token works for logged-in users and guests because API sessions
        // are not reliable as a cache identity for public search requests.
        $searchToken = (string) Str::uuid();

        // Cache every selectable flight separately for the 15-minute quote window.
        foreach ($sabreFlights as &$flight) {
            $flightReference = data_get($flight, 'leg.ref_id');

            if ($flightReference) {
                $flight['quote_search_token'] = $searchToken;

                Cache::put(
                    $this->quoteFlightCacheKey($searchToken, $flightReference),
                    $flight,
                    now()->addMinutes(15),
                );
            }
        }
        unset($flight);

        // Keep the existing legacy flight-detail cache behaviour unchanged.
        Cache::put($cacheKeyPrefix . '_sooper_flights', $sabreFlights, now()->addMinutes(15));


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

    /**
     * Create a server-side quote from selected fares in the current search result.
     */
    public function createPriceQuote(Request $request)
    {
        $validated = $request->validate([
            'flight_ref_id' => ['required', 'string'],
            'fare_references' => ['required', 'array', 'min:1'],
            'fare_references.*' => ['required', 'string'],
            'currency_code' => ['required', 'string', 'size:3'],
            'search_token' => ['required', 'uuid'],
        ]);

        $flight = Cache::get(
            $this->quoteFlightCacheKey(
                $validated['search_token'],
                $validated['flight_ref_id'],
            ),
        );

        if (!is_array($flight)) {
            return response()->json([
                'message' => 'Your search has expired. Please search again before continuing.',
            ], 422);
        }

        $displayCurrency = $this->currencyCodeForRequest($request, $validated['currency_code']);

        if (strtoupper((string) data_get($flight, 'provider.identifier')) === 'AT') {
            $providerPricing = $this->atApiService->priceQuote(
                $flight,
                $validated['fare_references'],
            );
            $quote = $this->priceQuoteService->createAt(
                $request->user(),
                $flight,
                $validated['fare_references'],
                $displayCurrency,
                $providerPricing,
            );
        } else {
            $quote = $this->priceQuoteService->create(
                $request->user(),
                $flight,
                $validated['fare_references'],
                $displayCurrency,
            );
        }

        return response()->json($this->quoteResponse($quote), 201);
    }

    /**
     * Request raw AT FlightInfo for the fares selected in the active server-cached search.
     */
    public function fetchAtFareBreakdown(Request $request)
    {
        $validated = $request->validate([
            'flight_ref_id' => ['required', 'string'],
            'fare_references' => ['required', 'array', 'min:1'],
            'fare_references.*' => ['required', 'string'],
            'search_token' => ['required', 'uuid'],
        ]);

        $flight = Cache::get($this->quoteFlightCacheKey(
            $validated['search_token'],
            $validated['flight_ref_id'],
        ));

        if (!is_array($flight)) {
            return response()->json([
                'message' => 'Your search has expired. Please search again before viewing fare details.',
            ], 422);
        }

        if (strtolower((string) data_get($flight, 'provider.name')) !== 'at') {
            return response()->json(['message' => 'FlightInfo is only available for AT flights.'], 422);
        }

        $trips = $this->atFlightInfoTrips($flight, $validated['fare_references']);
        if (count($trips) !== count($validated['fare_references'])) {
            return response()->json([
                'message' => 'One or more selected fares are no longer available in this search.',
            ], 422);
        }

        $tripType = strtoupper((string) data_get($flight, 'provider.fare_type', 'ON'));
        $response = $this->atApiService->fetchFlightInfo($trips, $tripType);

        if ($response === null) {
            return response()->json(['message' => 'Unable to retrieve AT fare details.'], 422);
        }

        return response()->json([
            'message' => 'AT fare details fetched successfully.',
            'fare_breakdown' => $this->atFareBreakdownTransformer->transform(
                $response,
                $flight,
                $validated['fare_references'],
            ),
        ]);
    }

    /**
     * Return an active quote for the current user and checkout screen.
     */
    public function showPriceQuote(Request $request, string $quoteUuid)
    {
        $quote = $this->priceQuoteService->findActive($quoteUuid, $request->user());

        return response()->json($this->quoteResponse($quote));
    }

    /**
     * Load ancillary options from AT using the quote's locked fare and display currency.
     */
    public function showQuoteAncillaries(Request $request, string $quoteUuid)
    {
        $quote = $this->priceQuoteService->findActive($quoteUuid, $request->user());
        $ancillaries = $this->atAncillaryResponse($quote);

        return response()->json([
            'message' => 'Ancillaries fetched successfully',
            'ancillaries' => $ancillaries,
            'quote' => $this->quoteResponse($quote),
        ]);
    }

    /**
     * Validate browser selection references against AT options and lock them on the quote.
     */
    public function updateQuoteAncillaries(Request $request, string $quoteUuid)
    {
        $validated = $request->validate([
            'selections' => ['present', 'array'],
            'selections.*.type' => ['required', 'string', 'in:baggage,meal,seat'],
            'selections.*.trip_index' => ['required', 'integer', 'min:0'],
            'selections.*.journey_index' => ['required', 'integer', 'min:0'],
            'selections.*.segment_index' => ['required', 'integer', 'min:0'],
            'selections.*.passenger_id' => ['required', 'integer', 'min:1'],
            'selections.*.ssid' => ['required', 'integer', 'min:1'],
        ]);
        $quote = $this->priceQuoteService->findActive($quoteUuid, $request->user());
        $ancillaries = $this->atAncillaryResponse($quote);
        $selections = array_map(
            fn (array $selection) => $this->validatedAtSelection($ancillaries, $selection),
            $validated['selections'],
        );
        $quote = $this->ancillaryPricingService->replaceSelections($quote, $selections);

        return response()->json([
            'message' => 'Ancillaries updated successfully',
            'quote' => $this->quoteResponse($quote),
        ]);
    }

    // Helper functions

    /**
     * Build the per-user search cache key used by search and checkout.
     */
    private function flightCacheKey(Request $request): string
    {
        return $request->user()
            ? 'flights_' . $request->user()->id
            : 'flights_' . session()->getId();
    }

    /** Resolve public-domain currency, with the frontend Origin taking priority for a shared API host. */
    private function currencyCodeForRequest(Request $request, ?string $fallback = null): string
    {
        $originHost = parse_url((string) $request->header('Origin'), PHP_URL_HOST);
        $host = strtolower((string) ($originHost ?: $request->getHost()));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === 'ae' || str_ends_with($host, '.ae')) {
            return 'AED';
        }

        if ($host === 'pk' || str_ends_with($host, '.pk')) {
            return 'PKR';
        }

        return strtoupper($fallback ?: $request->input('currencyCode', 'AED'));
    }

    /**
     * Build the cache key for one selectable flight in a specific search.
     */
    private function quoteFlightCacheKey(string $searchToken, string $flightReference): string
    {
        return 'flight_quote_' . $searchToken . '_' . $flightReference;
    }

    /** Build AT FlightInfo trips from trusted cached fares, never browser prices or TUI values. */
    private function atFlightInfoTrips(array $flight, array $fareReferences): array
    {
        $selectedFareReferences = array_flip($fareReferences);
        $tuis = data_get($flight, 'provider.TUI');
        $trips = [];

        foreach (data_get($flight, 'leg.flights', []) as $flightIndex => $leg) {
            foreach ($leg['fares'] ?? [] as $fare) {
                if (!isset($selectedFareReferences[$fare['ref_id'] ?? ''])) {
                    continue;
                }

                $tui = is_array($tuis) ? ($tuis[$flightIndex] ?? $tuis[0] ?? null) : $tuis;
                $amount = data_get($fare, 'provider_booking_money.amount') ?? $fare['billable_price'] ?? null;

                if (!$tui || $amount === null || !isset($fare['index'])) {
                    continue;
                }

                $trips[] = [
                    'TUI' => $tui,
                    'Amount' => (string) $amount,
                    'OrderID' => $flightIndex + 1,
                    'Index' => (string) $fare['index'],
                ];
            }
        }

        return $trips;
    }

    /**
     * Return only the quote data required by checkout.
     */
    private function quoteResponse($quote): array
    {
        $totals = $this->ancillaryPricingService->ancillaryTotals($quote);

        return [
            'quote_id' => $quote->uuid,
            'provider_money' => [
                'amount' => $quote->provider_amount,
                'currency' => $quote->provider_currency,
            ],
            'base_money' => [
                'amount' => $quote->aed_amount,
                'currency' => 'AED',
            ],
            'display_money' => [
                'amount' => $quote->display_amount,
                'currency' => $quote->display_currency,
            ],
            'ancillary_money' => $totals,
            'ancillary_totals_by_trip' => $this->ancillaryPricingService->ancillaryTotalsByTrip($quote),
            'ancillary_items' => $quote->items()
                ->active()
                ->orderBy('trip_index')
                ->orderBy('journey_index')
                ->orderBy('segment_index')
                ->orderBy('passenger_id')
                ->get()
                ->map(fn ($item) => [
                    'type' => $item->type,
                    'title' => $item->title,
                    'trip_index' => $item->trip_index,
                    'journey_index' => $item->journey_index,
                    'segment_index' => $item->segment_index,
                    'passenger_id' => $item->passenger_id,
                    'provider_references' => $item->provider_references,
                    'provider_money' => [
                        'amount' => $item->provider_amount,
                        'currency' => $item->provider_currency,
                    ],
                    'base_money' => [
                        'amount' => $item->aed_amount,
                        'currency' => 'AED',
                    ],
                    'display_money' => [
                        'amount' => $item->display_amount,
                        'currency' => $item->display_currency,
                    ],
                ])
                ->values(),
            'provider_pricing' => $quote->provider_pricing_data,
            'expires_at' => $quote->expires_at->toIso8601String(),
            'server_now' => now()->toIso8601String(),
        ];
    }

    /** Fetch and map AT ancillary options using only the current quote's trusted fare data. */
    private function atAncillaryResponse($quote): array
    {
        if (strtoupper($quote->provider) !== 'AT') {
            abort(422, 'Ancillaries are currently available for AT quotes only.');
        }

        $rawResponse = $this->atApiService->fetchAncillaries($this->atAncillaryRequestData($quote));
        $ancillaries = $this->atAncillaryTransformer->transform(
            $rawResponse,
            $quote->display_currency,
            $quote->provider_currency,
        );

        return $this->ancillaryPricingService->applyQuoteRates($ancillaries, $quote);
    }

    /** Build AT's ancillary request from server-side quote data, never from browser fares or TUI. */
    private function atAncillaryRequestData($quote): array
    {
        $legs = [];

        foreach (data_get($quote->flight_data, 'leg.flights', []) as $flight) {
            $selectedFare = collect($flight['fares'] ?? [])
                ->first(fn (array $fare) => in_array(
                    $fare['ref_id'] ?? null,
                    $quote->selected_fare_references,
                    true,
                ));

            if (!$selectedFare) {
                continue;
            }

            $legs[] = [
                'Index' => $flight['flight_index'] ?? null,
                'selectedFare' => $selectedFare,
            ];
        }

        if (count($legs) !== count($quote->selected_fare_references)) {
            abort(422, 'The selected fare is no longer available for ancillary pricing.');
        }

        $tui = data_get($quote->provider_pricing_data, 'tui');

        if (!$tui) {
            abort(422, 'The latest provider price is unavailable for ancillary pricing.');
        }

        return [
            'ref_id' => $tui,
            'fareType' => data_get($quote->provider_pricing_data, 'fare_type')
                ?? data_get($quote->flight_data, 'provider.fare_type'),
            'legs' => $legs,
        ];
    }

    /** Match one browser reference with a currently available AT option and derive trusted money. */
    private function validatedAtSelection(array $ancillaries, array $selection): array
    {
        $isSeat = $selection['type'] === 'seat';
        $segmentPath = $isSeat ? 'data.seatLayout.Trips' : 'data.ssrData.Trips';
        $segment = data_get(
            $ancillaries,
            $segmentPath . '.' . $selection['trip_index']
                . '.Journey.' . $selection['journey_index']
                . '.Segments.' . $selection['segment_index'],
        );

        if (!is_array($segment)) {
            throw ValidationException::withMessages([
                'selections' => 'One selected ancillary no longer belongs to this flight segment.',
            ]);
        }

        $options = $isSeat ? ($segment['Seats'] ?? []) : ($segment['SSR'] ?? []);
        $option = collect($options)->first(function (array $option) use ($selection, $isSeat) {
            $optionId = $isSeat ? ($option['SSID'] ?? null) : ($option['ID'] ?? null);

            if ((int) $optionId !== (int) $selection['ssid']) {
                return false;
            }

            if ($isSeat) {
                return ($option['AvailStatus'] ?? false) && ($option['SeatStatus'] ?? null) === 'Open';
            }

            return match ($selection['type']) {
                'baggage' => ($option['Type'] ?? null) === '2',
                'meal' => ($option['Type'] ?? null) === '1',
                default => false,
            };
        });

        if (!$option) {
            throw ValidationException::withMessages([
                'selections' => 'One selected ancillary is unavailable or its price has changed. Please choose again.',
            ]);
        }

        $fuid = $segment['FUID'] ?? data_get(
            $ancillaries,
            'data.ssrData.Trips.' . $selection['trip_index']
                . '.Journey.' . $selection['journey_index']
                . '.Segments.' . $selection['segment_index'] . '.FUID',
        );

        if (!$fuid) {
            throw ValidationException::withMessages([
                'selections' => 'The provider reference for one selected ancillary is missing. Please refresh the options.',
            ]);
        }

        return [
            'type' => $selection['type'],
            'trip_index' => $selection['trip_index'],
            'journey_index' => $selection['journey_index'],
            'segment_index' => $selection['segment_index'],
            'passenger_id' => $selection['passenger_id'],
            'fuid' => (string) $fuid,
            'ssid' => (int) $selection['ssid'],
            'title' => $option['Description'] ?? $option['SeatNumber'] ?? $selection['type'],
            'provider_references' => [
                'fuid' => (string) $fuid,
                'pax_id' => $selection['passenger_id'],
                'ssid' => (int) $selection['ssid'],
            ],
            'provider_money' => $option['provider_money'],
            'base_money' => $option['base_money'],
            'display_money' => $option['display_money'],
            'provider_item_data' => $option,
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
        // Use the same authenticated-user cache key used during the search.
        $cacheKeyPrefix = $this->flightCacheKey($request);

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
        $cacheKeyPrefix = $this->flightCacheKey($request);
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
