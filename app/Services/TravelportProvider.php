<?php

namespace App\Services\Suppliers;

use App\Services\Contracts\SupplierInterface;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Supplier;
use Illuminate\Support\Arr;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Request;

class TravelportProvider
{

    protected $client;
    protected $headers;

    protected $apiAuthUrl;
    protected $username;
    protected $password;
    protected $clientId;
    protected $clientSecret;

    protected $searchUrl;
    protected $apiVersion;

    protected $accessGroup;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiAuthUrl = config('travelport.auth_url');
        $this->username = config('travelport.username');
        $this->password = config('travelport.password');
        $this->clientId = config('travelport.client_id');
        $this->clientSecret = config('travelport.client_secret');
        $this->searchUrl = config('travelport.search_url');
        $this->apiVersion = config('travelport.api_version');
        $this->accessGroup = config('travelport.access_group');


    }
    public function code(): string
    {
        return 'travelport';
    }


    /**
     * STEP 5: The Aggregator calls this.
     * This function:
     * - gets token
     * - sends search request to Travelport
     * - returns unified offers
     */
   
    public function searchFlights($params)
    {

        Log::info("Searching TravelPort Flights with params: " . json_encode($params));
        try {

            // First, get the TravelPort token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("TravelPort Token not retrieved");
                return ['error' => 'Unable to get access token'];
            }
            // Prepare API endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/catalog/search/catalogproductofferings';

            // Prepare headers
            $headers = [
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'XAUTH_TRAVELPORT_ACCESSGROUP' => $this->accessGroup,
                'Accept-Version' => $this->apiVersion,
                'Content-Version' => $this->apiVersion,
                'taxBreakDown' => 'true',
                'Authorization' => 'Bearer ' . $token,
            ];

            $body = [
                '@type' => 'CatalogProductOfferingsQueryRequest',
                'CatalogProductOfferingsRequest' => [
                    '@type' => 'CatalogProductOfferingsRequestAir',
                    'maxNumberOfUpsellsToReturn' => 4,
                    'contentSourceList' => ['GDS'],
                    'PassengerCriteria' => [
                        [
                            '@type' => 'PassengerCriteria',
                            'number' => (int) ($params['adults'] ?? 1),
                            'passengerTypeCode' => 'ADT'
                        ],
                        [
                            '@type' => 'PassengerCriteria',
                            'number' => (int) ($params['children'] ?? 0),
                            'passengerTypeCode' => 'CNN'
                        ],
                        [
                            '@type' => 'PassengerCriteria',
                            'number' => (int) ($params['infants'] ?? 0),
                            'passengerTypeCode' => 'INF'
                        ],
                    ],
                    'SearchCriteriaFlight' => [
                        [
                            '@type' => 'SearchCriteriaFlight',
                            'departureDate' => $params['departure_date'],
                            'From' => [
                                'value' => $params['origin']
                            ],
                            'To' => [
                                'value' => $params['destination'],
                            ]
                        ]
                    ],
                    'SearchModifiersAir' => [
                        '@type' => 'SearchModifiersAir',
                        'SearchRe'
                        // 'PreferredCabinClass' => $params['cabin_class'] ?? 'Y',
                        // 'CurrencyCode' => $params['currency_code'] ?? 'AED'
                    ],
                ]
            ];
            if ($params['flight_type'] === 'return') {
                $body['CatalogProductOfferingsRequest']['SearchCriteriaFlight'][] = [
                    '@type' => 'SearchCriteriaFlight',
                    'departureDate' => $params['return_date'],
                    'From' => [
                        'value' => $params['destination']
                    ],
                    'To' => [
                        'value' => $params['origin'],
                    ]
                ];
            }
            if($params['flight_type'] === 'return'){
                $body['CatalogProductOfferingsRequest']['CustomResponseModifiersAir'] = [
                    '@type' => 'CustomResponseModifiersAir',
                    "SearchRepresentation" => "Journey"
                ];
            }
            Log::info("TravelPort Flight Search Request Body: " . json_encode($body));

            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response

            $response = $this->client->sendAsync($request)->wait();

            // Decode the JSON response
            Log::info("TravelPort Flight Search Response: ", [$response]);
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("TravelPort Flight Search Response: " . json_encode($data));

            return $this->transformToCommon($data, $params);

        } catch (\Exception $e) {
            Log::error("Travelport Flight Search Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Fetch token using OAuth (cached)
     */
     public function getAccessToken()
    {
        //Log::info("Getting TravelPort Access Token");
        try {
            $tokenURL = $this->apiAuthUrl . '/oauth/token'; // Adjust endpoint if necessary

            $headers = [
                'Content-Type' => 'application/json',
            ];

            $body = [

                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'openid'

            ];

            // Create Duffel-style Guzzle request
            $request = new Request(
                'POST',
                $tokenURL,
                $headers,
                json_encode($body)
            );

            // Log::info("TravelPort Access Token Request Body: " . $request->getBody());

            // Send async request and wait for response
            $response = $this->client->sendAsync($request)->wait();

            // Decode the response JSON
            $data = json_decode($response->getBody()->getContents(), true);

            //Log::info("TravelPort Access Token Response: " . json_encode($data));

            // Extract the token from nested structure
            $token = $data['access_token'] ?? null;

            if (!$token) {
                Log::warning("TravelPort token missing in response");
            }

            return $token;

        } catch (\Exception $e) {
            Log::error("TravelPort Access Token Error: " . $e->getMessage());
            return null;
        }
    }

    protected function buildRequestBody(array $search): array
    {
        $req = $search; // your search array

        // Build PassengerCriteria dynamically
        $passengers = [];

        if ($req['adults'] > 0) {
            $passengers[] = [
                "@type" => "PassengerCriteria",
                "number" => $req['adults'],
                "passengerTypeCode" => "ADT"
            ];
        }

        if ($req['children'] > 0) {
            $passengers[] = [
                "@type" => "PassengerCriteria",
                "number" => $req['children'],
                "passengerTypeCode" => "CHD"
            ];
        }

        if ($req['infants'] > 0) {
            $passengers[] = [
                "@type" => "PassengerCriteria",
                "number" => $req['infants'],
                "passengerTypeCode" => "INF"
            ];
        }

        // Build Flight Search Array
        $searchCriteriaFlight = [
            [
                "@type" => "SearchCriteriaFlight",
                "departureDate" => $req['departure'],
                "From" => ["value" => $req['from']],
                "To" => ["value" => $req['to']]
            ]
        ];

        // Optional round-trip block
        if ($req['trip_type'] === "round" && $req['return']) {
            $searchCriteriaFlight[] = [
                "@type" => "SearchCriteriaFlight",
                "departureDate" => $req['return'],
                "From" => ["value" => $req['to']],
                "To" => ["value" => $req['from']]
            ];
        }

        return [
            "CatalogProductOfferingsQueryRequest" => [
                "CatalogProductOfferingsRequest" => [
                    "@type" => "CatalogProductOfferingsRequestAir",
                    "offersPerPage" => 15,
                    "maxNumberOfUpsellsToReturn" => 4,
                    "contentSourceList" => ["GDS"],
                    "PassengerCriteria" => $passengers,
                    "SearchCriteriaFlight" => $searchCriteriaFlight
                ]
            ]
        ];
    }


    /**
     * Transform raw Travelport JSON into common format:
     *
     * {
     *   supplier: "travelport",
     *   search_id: "...",
     *   references: { flights, products, brands, terms },
     *   itineraries: [
     *      {
     *        id, offer_id, origin, destination, slice_index, direction, flight_refs[],
     *        summary: {...},
     *        fare_options: [ {...}, {...} ]
     *      }
     *   ]
     * }
     */
    public function transformToCommon(array $response, array $search = []): array
    {
        // ------------------------------------------------------------
        // 0) Detect structure (ReferenceList may be under root or under CatalogProductOfferings)
        // ------------------------------------------------------------
        $root = Arr::get($response, 'CatalogProductOfferingsResponse', []);
        $catalog = Arr::get($root, 'CatalogProductOfferings', []);

        $referenceListA = Arr::get($catalog, 'ReferenceList', []);
        $referenceListB = Arr::get($root, 'ReferenceList', []);
        $referenceList = $referenceListA ?: $referenceListB;

        $searchId = Arr::get($catalog, 'Identifier.value')
            ?? Arr::get($root, 'Identifier.value')
            ?? null;

        $offerings = Arr::get($catalog, 'CatalogProductOffering', [])
            ?: Arr::get($root, 'CatalogProductOffering', []);

        // determine trip_type & slices meta (for slice_index + direction)
        $tripType = $search['trip_type'] ?? 'oneway';
        $slicesMeta = $this->buildSlicesMeta($search);

        // ------------------------------------------------------------
        // 1) Build reference maps (flights, products, brands, terms)
        // ------------------------------------------------------------
        $flights = [];
        $products = [];
        $brands = [];
        $terms = [];

        foreach ($referenceList as $list) {
            $type = $list['@type'] ?? null;

            if ($type === 'ReferenceListFlight') {
                foreach ($list['Flight'] ?? [] as $item) {
                    $flights[$item['id']] = $item;
                }
            }

            if ($type === 'ReferenceListProduct') {
                foreach ($list['Product'] ?? [] as $item) {
                    $products[$item['id']] = $this->normalizeProduct($item);
                }
            }

            if ($type === 'ReferenceListBrand') {
                foreach ($list['Brand'] ?? [] as $item) {
                    $brands[$item['id']] = $item;
                }
            }

            if ($type === 'ReferenceListTermsAndConditions') {
                foreach ($list['TermsAndConditions'] ?? [] as $item) {
                    $terms[$item['id']] = $item;
                }
            }
        }

        // ------------------------------------------------------------
        // 2) Build itineraries = (offering + flight_refs) group
        //    Each itinerary has multiple fare_options (product+brand)
        // ------------------------------------------------------------
        $itinerariesByKey = [];

        foreach ($offerings as $offering) {

            $offerId = Arr::get($offering, 'id');
            $origin = Arr::get($offering, 'Departure');
            $destination = Arr::get($offering, 'Arrival');

            // figure out which slice this (origin, destination) belongs to
            $sliceMeta = $this->findSliceForItinerary($slicesMeta, (string) $origin, (string) $destination);

            foreach (Arr::get($offering, 'ProductBrandOptions', []) as $pbo) {

                $flightRefs = Arr::get($pbo, 'flightRefs', []);
                if (empty($flightRefs)) {
                    continue;
                }

                // key = offering + same flight refs => same physical routing
                $itiKey = $this->buildItineraryKey($offerId, $flightRefs);

                // Create itinerary shell if first time
                if (!isset($itinerariesByKey[$itiKey])) {
                    $stopCount = max(count($flightRefs) - 1, 0);

                    $itinerariesByKey[$itiKey] = [
                        'id' => $itiKey,
                        'supplier' => $this->code(),
                        'search_id' => $searchId,
                        'offer_id' => $offerId,

                        // NEW: these two indexes + trip_type
                        'trip_type' => $tripType,
                        'slice_index' => $sliceMeta['index'] ?? 1,          // 1,2,3...
                        'direction' => $sliceMeta['direction'] ?? 'unknown', // outbound|inbound|multi|unknown

                        'origin' => $origin,
                        'destination' => $destination,
                        'flight_refs' => $flightRefs,

                        'summary' => [
                            'stops' => $stopCount,
                            'is_direct' => $stopCount === 0,
                            'has_stops' => $stopCount > 0,

                            'duration' => null,
                            'duration_minutes' => null,
                            'main_cabin' => null,
                            'main_passenger_type' => null,

                            'departure_time' => null,
                            'arrival_time' => null,

                            'cheapest_price' => null,
                        ],

                        'fare_options' => [],
                    ];
                }

                $itinerary = &$itinerariesByKey[$itiKey];

                // For each brand/product/price variant add a fare_option
                foreach (Arr::get($pbo, 'ProductBrandOffering', []) as $pboffer) {

                    $productRef = Arr::get($pboffer, 'Product.0.productRef');
                    $brandRef = Arr::get($pboffer, 'Brand.BrandRef');
                    $termsRef = Arr::get($pboffer, 'TermsAndConditions.termsAndConditionsRef');
                    $comboCodes = Arr::get($pboffer, 'CombinabilityCode', []);

                    $priceBlock = Arr::get($pboffer, 'BestCombinablePrice', []);
                    $priceSummary = $this->normalizePrice($priceBlock);
                    $passengerPricing = $this->normalizePriceBreakdown(
                        Arr::get($priceBlock, 'PriceBreakdown', [])
                    );

                    $product = $products[$productRef] ?? null;
                    $mainPax = $product['main_pax'] ?? 'ADT';
                    $cabin = Arr::get($product, "passenger_products.$mainPax.cabin");

                    // ---------- fill departure / arrival time (once) ----------
                    if ($itinerary['summary']['departure_time'] === null && isset($flightRefs[0])) {
                        $firstRef = $flightRefs[0];
                        $firstFlight = $flights[$firstRef] ?? null;

                        if ($firstFlight) {
                            $depDate = Arr::get($firstFlight, 'Departure.date');
                            $depTime = Arr::get($firstFlight, 'Departure.time');
                            if ($depDate && $depTime) {
                                $itinerary['summary']['departure_time'] = $depDate . 'T' . $depTime;
                            }
                        }
                    }

                    if ($itinerary['summary']['arrival_time'] === null && !empty($flightRefs)) {
                        $lastRef = $flightRefs[count($flightRefs) - 1];
                        $lastFlight = $flights[$lastRef] ?? null;

                        if ($lastFlight) {
                            $arrDate = Arr::get($lastFlight, 'Arrival.date');
                            $arrTime = Arr::get($lastFlight, 'Arrival.time');
                            if ($arrDate && $arrTime) {
                                $itinerary['summary']['arrival_time'] = $arrDate . 'T' . $arrTime;
                            }
                        }
                    }

                    // ---------- add fare option ----------
                    $fareOption = [
                        'id' => $this->offerId($offerId, $productRef, $brandRef),
                        'product_ref' => $productRef,
                        'brand_ref' => $brandRef,
                        'terms_ref' => $termsRef,
                        'combinability_code' => $comboCodes,

                        'cabin' => $cabin,
                        'main_passenger_type' => $mainPax,

                        'pricing' => [
                            'currency' => $priceSummary['currency'],
                            'base' => $priceSummary['base'],
                            'taxes' => $priceSummary['taxes'],
                            'fees' => $priceSummary['fees'],
                            'surcharges' => $priceSummary['surcharges'],
                            'total' => $priceSummary['total'],
                            'per_passenger' => $passengerPricing,
                        ],
                    ];

                    $itinerary['fare_options'][] = $fareOption;

                    // ---------- fill duration / cabin / main pax ----------
                    if ($itinerary['summary']['duration'] === null && $product) {
                        $durationIso = $product['total_duration'] ?? null;
                        $itinerary['summary']['duration'] = $durationIso;
                        $itinerary['summary']['duration_minutes'] = $this->isoToMinutes($durationIso);
                    }

                    if ($itinerary['summary']['main_passenger_type'] === null) {
                        $itinerary['summary']['main_passenger_type'] = $mainPax;
                    }

                    if ($itinerary['summary']['main_cabin'] === null && $cabin) {
                        $itinerary['summary']['main_cabin'] = $cabin;
                    }

                    // ---------- track cheapest fare for card price ----------
                    $total = $priceSummary['total'] ?? null;
                    if ($total !== null) {
                        $cheapest = $itinerary['summary']['cheapest_price'];
                        if ($cheapest === null || $total < $cheapest['total']) {
                            $itinerary['summary']['cheapest_price'] = [
                                'currency' => $priceSummary['currency'],
                                'base' => $priceSummary['base'],
                                'taxes' => $priceSummary['taxes'],
                                'fees' => $priceSummary['fees'],
                                'surcharges' => $priceSummary['surcharges'],
                                'total' => $total,
                            ];
                        }
                    }
                }
            }
        }

        // ------------------------------------------------------------
        // Final output
        // ------------------------------------------------------------
        return [
            'supplier' => $this->code(),
            'search_id' => $searchId,

            'references' => [
                'flights' => $flights,
                'products' => $products,
                'brands' => $brands,
                'terms' => $terms,
            ],

            'itineraries' => array_values($itinerariesByKey),
        ];
    }

    // ========================= Helpers ==============================

    /**
     * Build slice meta from the original search:
     * index: 1,2,3...
     * direction: outbound|inbound|multi|unknown
     */
    protected function buildSlicesMeta(array $search): array
    {
        $tripType = $search['trip_type'] ?? 'oneway';
        $slices = [];

        if ($tripType === 'oneway') {
            $slices[] = [
                'index' => 1,
                'from' => $search['from'] ?? null,
                'to' => $search['to'] ?? null,
                'direction' => 'outbound',
            ];
        } elseif (in_array($tripType, ['round', 'roundtrip'], true)) {
            $from = $search['from'] ?? null;
            $to = $search['to'] ?? null;

            $slices[] = [
                'index' => 1,
                'from' => $from,
                'to' => $to,
                'direction' => 'outbound',
            ];
            $slices[] = [
                'index' => 2,
                'from' => $to,
                'to' => $from,
                'direction' => 'inbound',
            ];
        } elseif ($tripType === 'multicity') {
            foreach ($search['slices'] ?? [] as $i => $slice) {
                $slices[] = [
                    'index' => $i + 1,
                    'from' => $slice['from'] ?? null,
                    'to' => $slice['to'] ?? null,
                    'direction' => 'multi',
                ];
            }
        }

        // fallback if nothing built
        if (empty($slices)) {
            $slices[] = [
                'index' => 1,
                'from' => $search['from'] ?? null,
                'to' => $search['to'] ?? null,
                'direction' => 'unknown',
            ];
        }

        return $slices;
    }

    /**
     * Match an itinerary (origin/destination) to a slice.
     */
    protected function findSliceForItinerary(array $slicesMeta, ?string $origin, ?string $destination): array
    {
        foreach ($slicesMeta as $slice) {
            if (
                ($slice['from'] ?? null) === $origin &&
                ($slice['to'] ?? null) === $destination
            ) {
                return $slice;
            }
        }

        // default if no exact match
        return [
            'index' => 1,
            'direction' => 'unknown',
        ];
    }

    private function normalizeProduct(array $p): array
    {
        $id = $p['id'];

        $segments = [];
        foreach ($p['FlightSegment'] ?? [] as $seg) {
            $segments[] = [
                'sequence' => $seg['sequence'] ?? null,
                'flight_ref' => Arr::get($seg, 'Flight.FlightRef'),
                'duration' => $seg['duration'] ?? null,
                'connection' => $seg['connectionDuration'] ?? null,
            ];
        }

        $pax = [];
        foreach ($p['PassengerFlight'] ?? [] as $pf) {
            $type = $pf['passengerTypeCode'] ?? null;
            if (!$type) {
                continue;
            }

            foreach ($pf['FlightProduct'] ?? [] as $fp) {
                $pax[$type] = [
                    'class_of_service' => $fp['classOfService'] ?? null,
                    'cabin' => $fp['cabin'] ?? null,
                    'fare_basis_code' => $fp['fareBasisCode'] ?? null,
                    'fare_type' => $fp['fareType'] ?? null,
                    'fare_type_code' => $fp['fareTypeCode'] ?? null,
                    'brand_ref' => Arr::get($fp, 'Brand.BrandRef'),
                ];
            }
        }

        $mainPax = array_key_exists('ADT', $pax)
            ? 'ADT'
            : (array_key_first($pax) ?: null);

        return [
            'id' => $id,
            'total_duration' => $p['totalDuration'] ?? null,
            'segments' => $segments,
            'passenger_products' => $pax,
            'main_pax' => $mainPax,
        ];
    }

    private function normalizePrice(array $price): array
    {
        $currency = Arr::get($price, 'CurrencyCode.value');
        $base = Arr::get($price, 'Base', 0);
        $taxes = Arr::get($price, 'TotalTaxes', 0);
        $fees = Arr::get($price, 'TotalFees', 0);
        $total = Arr::get($price, 'TotalPrice', 0);

        $surcharges = 0;
        foreach (Arr::get($price, 'PriceBreakdown', []) as $pb) {
            foreach (Arr::get($pb, 'Surcharges.Surcharge', []) as $s) {
                $surcharges += Arr::get($s, 'value', 0);
            }
        }

        return compact('currency', 'base', 'taxes', 'fees', 'surcharges', 'total');
    }

    private function normalizePriceBreakdown(array $breakdown): array
    {
        $res = [];

        foreach ($breakdown as $pb) {
            $amount = $pb['Amount'] ?? [];

            $currency = Arr::get($amount, 'CurrencyCode.value');
            $base = Arr::get($amount, 'Base', 0);
            $taxes = Arr::get($amount, 'Taxes.TotalTaxes', 0);
            $fees = Arr::get($amount, 'Fees.TotalFees', 0);
            $total = Arr::get($amount, 'Total', 0);

            $surcharges = 0;
            foreach (Arr::get($pb, 'Surcharges.Surcharge', []) as $s) {
                $surcharges += Arr::get($s, 'value', 0);
            }

            $res[] = [
                'type' => Arr::get($pb, 'requestedPassengerType'),
                'quantity' => Arr::get($pb, 'quantity', 1),
                'currency' => $currency,
                'base' => $base,
                'taxes' => $taxes,
                'fees' => $fees,
                'surcharges' => $surcharges,
                'total' => $total,
            ];
        }

        return $res;
    }

    private function offerId(?string $offeringId, ?string $productRef, ?string $brandRef): string
    {
        return implode('_', array_filter([$offeringId, $productRef, $brandRef]));
    }

    private function buildItineraryKey(string $offerId, array $flightRefs): string
    {
        return $offerId . ':' . implode('-', $flightRefs);
    }

    private function isoToMinutes(?string $iso): ?int
    {
        if (!$iso || !str_starts_with($iso, 'PT')) {
            return null;
        }

        $hours = 0;
        $mins = 0;

        if (preg_match('/(\d+)H/', $iso, $h)) {
            $hours = (int) $h[1];
        }

        if (preg_match('/(\d+)M/', $iso, $m)) {
            $mins = (int) $m[1];
        }

        return $hours * 60 + $mins;
    }
}