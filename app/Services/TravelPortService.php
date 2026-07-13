<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Log;

class TravelPortService
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

    protected $sessionId;
    protected $confirmationId;
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

    private function resolveSegmentValue($map, $segmentRef, $index, $sequence = null)
    {
        if (is_array($map)) {
            if ($segmentRef !== null) {
                $segmentRefKey = (string) $segmentRef;
                if (array_key_exists($segmentRefKey, $map)) {
                    return $map[$segmentRefKey];
                }
                if (array_key_exists($segmentRef, $map)) {
                    return $map[$segmentRef];
                }
            }

            if ($sequence !== null) {
                $sequenceInt = (int) $sequence;
                if (array_key_exists($sequenceInt, $map)) {
                    return $map[$sequenceInt];
                }
                $sequenceKey = (string) $sequenceInt;
                if (array_key_exists($sequenceKey, $map)) {
                    return $map[$sequenceKey];
                }
            }
            if (array_key_exists($index, $map)) {
                return $map[$index];
            }
            if (array_key_exists(0, $map)) {
                return $map[0];
            }
        }
        return $map;
    }

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
            $usePremiumFlex = filter_var($params['flexible_plus_minus_3'] ?? false, FILTER_VALIDATE_BOOLEAN);
            // Prepare API endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/catalog/search/catalogproductofferings';
            if ($usePremiumFlex && ($params['airline'] === 'TravelPort-GDS')) {
                Log::info("Using Premium Flex search criteria for TravelPort");
                $searchUrl .= '/premiumflex';
            }

                Log::info($searchUrl);
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

            // Map cabin codes to full names
            $cabinMap = [
                'Y' => 'Economy',
                'S' => 'PremiumEconomy',
                'C' => 'Business',
                'F' => 'First',
            ];

            $cabinCode = $params['cabin_class'] ?? 'Y';
            $cabinName = $cabinMap[$cabinCode] ?? 'Economy';
            $searchCriteriaFlight = [];
            if ($params['flight_type'] === 'one-way' || $params['flight_type'] === 'return') {

                $searchCriteriaFlight = [
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
                ];
                
            }



            $contentSourceList = ['GDS', 'NDC'];
            $requestedSource = strtoupper($params['travelport_content_source'] ?? '');
            if ($requestedSource === 'GDS') {
                $contentSourceList = ['GDS'];
            } elseif ($requestedSource === 'NDC') {
                $contentSourceList = ['NDC'];
            }

            $body = [
                '@type' => 'CatalogProductOfferingsQueryRequest',
                'CatalogProductOfferingsRequest' => [
                    '@type' => 'CatalogProductOfferingsRequestAir',
                    'maxNumberOfUpsellsToReturn' => 10,
                    "offersPerPage" => 300,

                    'contentSourceList' => $contentSourceList,
                    'PassengerCriteria' => [
                        [
                            '@type' => 'PassengerCriteria',
                            'number' => (int) ($params['adults'] ?? 1),
                            'passengerTypeCode' => 'ADT'
                        ],
                        [
                            '@type' => 'PassengerCriteria',
                            'number' => (int) ($params['children'] ?? 0),
                            'passengerTypeCode' => 'CHD'
                        ],
                        [
                            '@type' => 'PassengerCriteria',
                            'number' => (int) ($params['infants'] ?? 0),
                            'passengerTypeCode' => 'INF'
                        ],
                    ],
                    'SearchCriteriaFlight' => $searchCriteriaFlight,
                    'SearchModifiersAir' => [
                        '@type' => 'SearchModifiersAir',
                        "CabinPreference" => [
                            [
                                "@type" => "CabinPreference",
                                "preferenceType" => "PreferredWithUpgrade",
                                "cabins" => [
                                    $cabinName
                                ]
                            ]
                        ]
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
            


if ($usePremiumFlex && ($params['airline'] === 'TravelPort-GDS')) {
    foreach ($body['CatalogProductOfferingsRequest']['SearchCriteriaFlight'] as &$flightCriteria) {
        $flightCriteria['daysBeforeDeparture'] = 3;
        $flightCriteria['daysAfterDeparture'] = 3;
    }

    unset($flightCriteria);
}

            if ($params['flight_type'] === 'multi-city') {
                // Initialize array if not set
                $body['CatalogProductOfferingsRequest']['SearchCriteriaFlight'] = [];

                foreach ($params['trips'] as $trip) {
                    $body['CatalogProductOfferingsRequest']['SearchCriteriaFlight'][] = [
                        '@type' => 'SearchCriteriaFlight',
                        'departureDate' => $trip['date'],
                        'From' => [
                            'value' => $trip['origin']
                        ],
                        'To' => [
                            'value' => $trip['destination']
                        ]
                    ];
                }
            }
            if ($params['flight_type'] === 'return') {
                $body['CatalogProductOfferingsRequest']['CustomResponseModifiersAir'] = [
                    '@type' => 'CustomResponseModifiersAir',
                    "SearchRepresentation" => "Journey"
                ];
            }

            Log::info(json_encode($body, JSON_PRETTY_PRINT));
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response

            $response = $this->client->sendAsync($request)->wait();

            // Decode the JSON response
            $data = json_decode($response->getBody()->getContents(), true);
            return $data;

        } catch (\Exception $e) {
            Log::error("Travelport Flight Search Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function travelportNDCPRriceRequest($data)
    {

        $flightData = $data['flight_data']['leg']['flights'];
        $selectedFares = $data['selectedFares']; // This contains ['QR__CC01', 'QR__CC01']

        $catalogProductOfferingSelection = [];

        foreach ($flightData as $index => $flight) {
            // Find the fare that matches the selectedFare for this index
            $selectedFareRefId = $selectedFares[$index];

            // Find the fare with matching ref_id
            $selectedFare = null;
            foreach ($flight['fares'] as $fare) {
                if ($fare['ref_id'] === $selectedFareRefId) {
                    $selectedFare = $fare;
                    break;
                }
            }

            // If found, use its identifiers
            if ($selectedFare) {
                $catalogProductOfferingSelection[] = [
                    "CatalogProductOfferingIdentifier" => [
                        "Identifier" => [
                            "value" => $selectedFare['offer_identifier']
                        ]
                    ],
                    "ProductIdentifier" => [
                        [
                            "Identifier" => [
                                "value" => $selectedFare['product_identifier']
                            ]
                        ]
                    ]
                ];
            }
        }

        $request = [
            "@type" => "OfferQueryBuildFromCatalogProductOfferings",
            "BuildFromCatalogProductOfferingsRequest" => [
                "@type" => "BuildFromCatalogProductOfferingsRequestAir",
                "CatalogProductOfferingsIdentifier" => [
                    "Identifier" => [
                        "value" => $data['flight_data']['provider']['catalogueRefId']
                    ]
                ],
                "CatalogProductOfferingSelection" => $catalogProductOfferingSelection
            ]
        ];

        Log::info("TravelPort NDC Price Request Created", ['request' => $request]);
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("TravelPort Token not retrieved");
                return ['error' => 'Unable to get access token'];
            }
            // Prepare API endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/price/offers/buildfromcatalogproductofferings';

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
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($request)
            );

            // Send async request and wait for response

            $response = $this->client->sendAsync($request)->wait();

            // Decode the JSON response
            //  Log::info("TravelPort Flight Price Response: ", [$response]);
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("TravelPort Flight Price Response: " . json_encode($data));

            return $data;
        } catch (\Exception $e) {
            Log::error("Travelport Price Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    public function travelportPrice($data)
    {
        $productCriteriaAir = [];
        $brandTier = null;
        $cabin = null;
        $rbd = null;
        $rbd = null;
        if (!isset($data['flight_data']['provider']['contentSource']) || $data['flight_data']['provider']['contentSource'] !== 'GDS') {
            $response = $this->travelportNDCPRriceRequest($data);


            return $response;
        }


        foreach ($data['flight_data']['leg']['flights'] as $flightIdx => $flight) {
            $specificSegments = [];

            foreach ($flight['segments'] as $index => $segment) {
                foreach ($flight['fares'] as $fareIndex => $fare) {
                    if ($fare['ref_id'] === $data['selectedFares'][$flightIdx]) {
                        // Travelport brandTier starts from 1. Skip Economy/empty.
                        $brandTierRaw = $fare['brand_tier'] ?? null;
                        if ($brandTierRaw === 'Economy' || $brandTierRaw === null || $brandTierRaw === '') {
                            $brandTier = null;
                        } elseif (is_numeric($brandTierRaw) && (int) $brandTierRaw > 0) {
                            $brandTier = (int) $brandTierRaw;
                        } else {
                            $brandTier = null;
                        }
                        $cabin = $fare['cabin_class'] ?? null;
                        $rbd = $fare['rbd_code'] ?? null;
                        
                    }
                }
                $segmentRef = $segment['segment_ref'] ?? null;
                $segmentSequence = $index + 1;
                $segmentCabin = $this->resolveSegmentValue($cabin, $segmentRef, $index, $segmentSequence);
                $segmentRbd = $this->resolveSegmentValue($rbd, $segmentRef, $index, $segmentSequence);
                $segmentPayload = [
                    'flightNumber' => $segment['flight_number'],
                    'carrier' => $segment['operating_carrier']['iata'],
                    'departureDate' => substr($segment['departure_at'], 0, 10),
                    'departureTime' => substr($segment['departure_at'], 11, 8),
                    'arrivalDate' => substr($segment['arrival_at'], 0, 10),
                    'arrivalTime' => substr($segment['arrival_at'], 11, 8),
                    'from' => $segment['from']['iata'],
                    'to' => $segment['to']['iata'],
                    'classOfService' => $segmentRbd,
                    'cabin' => is_array($segmentCabin) ? ($segmentCabin[0] ?? null) : $segmentCabin,
                    'segmentSequence' => $segmentSequence,
                    'AvailabilitySourceCode' => $segment['availability_source_code'] ?? '',
                    'ContentSource' => 'GDS',
                ];
                if ($brandTier !== null) {
                    $segmentPayload['brandTier'] = $brandTier;
                }
                $specificSegments[] = $segmentPayload;
            }

            $productCriteriaAir[] = [
                'SpecificFlightCriteria' => $specificSegments,
                'sequence' => $flightIdx + 1,
            ];
        }
        $passengerCriteriaAir = [];
        if ($data['adults'] > 1) {
            $passengerCriteriaAir['PassengerCriteria'][] = [
                '@type' => 'PassengerCriteria',
                'number' => $data['adults'],
                'passengerTypeCode' => 'ADT'
            ];
        }
        if ($data['infants'] > 0) {
            $passengerCriteriaAir['PassengerCriteria'][] = [
                '@type' => 'PassengerCriteria',
                'number' => $data['infants'],
                'passengerTypeCode' => 'INF'
            ];
        }
        if ($data['children'] > 0) {
            $passengerCriteriaAir['PassengerCriteria'][] = [
                '@type' => 'PassengerCriteria',
                'number' => $data['children'],
                'passengerTypeCode' => 'CHD'
            ];
        }
        $body = [
            '@type' => 'OfferQueryBuildFromProducts',
            'BuildFromProductsRequest' => [
                '@type' => 'BuildFromProductsRequestAir',
                'PassengerCriteria' => $passengerCriteriaAir['PassengerCriteria'] ?? [
                    [
                        '@type' => 'PassengerCriteria',
                        'number' => 1,
                        'passengerTypeCode' => 'ADT'
                    ]
                ],
                'ProductCriteriaAir' => $productCriteriaAir
            ],
            'validateInventoryInd' => true
        ];

        Log::info("TravelPort Price Request Body: " . json_encode($body, JSON_PRETTY_PRINT));
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("TravelPort Token not retrieved");
                return ['error' => 'Unable to get access token'];
            }
            // Prepare API endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/price/offers/buildfromproducts';

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
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response

            $response = $this->client->sendAsync($request)->wait();

            // Decode the JSON response
            //  Log::info("TravelPort Flight Price Response: ", [$response]);
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("TravelPort Flight Price Response: " . json_encode($data));

            return $data;
        } catch (\Exception $e) {
            Log::error("Travelport Price Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }

    }
    public function getFareRules($priceIdentifier)
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("TravelPort Token not retrieved");
                return ['error' => 'Unable to get access token'];
            }
            // Prepare API endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/farerule/farerules/fromoffer?fareRuleType=ShortText&offerIdentifier=' . $priceIdentifier;

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
            $request = new Request(
                'GET',
                $searchUrl,
                $headers
            );

            // Send async request and wait for response

            $response = $this->client->sendAsync($request)->wait();

            // Decode the JSON response
            //Log::info("TravelPort Fare Rules Response: ", [$response]);
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("TravelPort Fare Rules Response: " . json_encode($data));

            return $data;
        } catch (\Exception $e) {
            Log::error("Travelport Fare Rules Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function createWorkbenchSession()
    {
        try {
            // Get token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/book/session/reservationworkbench';

            $body = [
                '@type' => 'ReservationID',
                'ReservationID' => [],
            ];

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

            $request = new Request('POST', $searchUrl, $headers, json_encode($body));

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate HTTP response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);
                return ['error' => 'Invalid response from TravelPort'];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            // Validate JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('TravelPort: JSON decode failed', [
                    'error' => json_last_error_msg(),
                ]);
                return ['error' => 'Invalid response format'];
            }

            // API-level error
            if (!empty($data['Errors'])) {
                Log::error('TravelPort: API error', $data['Errors']);
                return ['error' => $data['Errors'][0]['Message'] ?? 'TravelPort API error'];
            }

            $sessionId = $data['ReservationResponse']['Reservation']['Identifier']['value'] ?? null;

            if (!$sessionId) {
                Log::warning('TravelPort: Session ID not found', $data);
                return ['error' => 'Session ID not returned'];
            }
            $this->sessionId = $sessionId;
            return ['success' => true, 'sessionId' => $sessionId];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('TravelPort: Request exception', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : null,
            ]);

            return ['error' => 'TravelPort request failed'];

        } catch (\Throwable $e) {
            Log::error('TravelPort: Unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected server error'];
        }
    }

    public function addNDCOffer($data){
         $flightData = $data['flight']['leg']['flights'];
        $selectedFares = $data['fare_reference']; // This contains ['QR__CC01', 'QR__CC01']

        $catalogProductOfferingSelection = [];

        foreach ($flightData as $index => $flight) {
            // Find the fare that matches the selectedFare for this index
            $selectedFareRefId = $selectedFares[$index];

            // Find the fare with matching ref_id
            $selectedFare = null;
            foreach ($flight['fares'] as $fare) {
                if ($fare['ref_id'] === $selectedFareRefId) {
                    $selectedFare = $fare;
                    break;
                }
            }

            // If found, use its identifiers
            if ($selectedFare) {
                $catalogProductOfferingSelection[] = [
                    "CatalogProductOfferingIdentifier" => [
                        "Identifier" => [
                            "value" => $selectedFare['offer_identifier']
                        ]
                    ],
                    "ProductIdentifier" => [
                        [
                            "Identifier" => [
                                "value" => $selectedFare['product_identifier']
                            ]
                        ]
                    ]
                ];
            }
        }

        $request = [
            "@type" => "OfferQueryBuildFromCatalogProductOfferings",
            "BuildFromCatalogProductOfferingsRequest" => [
                "@type" => "BuildFromCatalogProductOfferingsRequestAir",
                "CatalogProductOfferingsIdentifier" => [
                    "Identifier" => [
                        "value" => $data['flight']['provider']['catalogueRefId']
                    ]
                ],
                "CatalogProductOfferingSelection" => $catalogProductOfferingSelection
            ]
        ];
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("TravelPort Token not retrieved");
                return ['error' => 'Unable to get access token'];
            }
            // Prepare API endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/book/airoffer/reservationworkbench/' . $this->sessionId . '/offers/buildfromcatalogproductofferings';

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
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($request)
            );

            // Send async request and wait for response

            $response = $this->client->sendAsync($request)->wait();

            // Decode the JSON response
            //  Log::info("TravelPort Flight Price Response: ", [$response]);
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("TravelPort Flight Offer Response: " . json_encode($data));

            return $data;
        } catch (\Exception $e) {
            Log::error("Travelport Offer Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    public function addOffer($data)
    {

        $productCriteriaAir = [];
        $specificSegments = [];
        $brandTier = null;
        $cabin = null;

          if (!isset($data['flight']['provider']['contentSource']) || $data['flight']['provider']['contentSource'] !== 'GDS') {
            $response = $this->addNDCOffer($data);


            return $response;
        }

        foreach ($data['flight']['leg']['flights'] as $flightIdx => $flight) {
            $specificSegments = [];

            foreach ($flight['segments'] as $index => $segment) {
                foreach ($flight['fares'] as $fareIndex => $fare) {
                    if ($fare['ref_id'] === $data['fare_reference'][$flightIdx]) {
                        // Travelport brandTier starts from 1. Skip Economy/empty.
                        $brandTierRaw = $fare['brand_tier'] ?? null;
                        if ($brandTierRaw === 'Economy' || $brandTierRaw === null || $brandTierRaw === '') {
                            $brandTier = null;
                        } elseif (is_numeric($brandTierRaw) && (int) $brandTierRaw > 0) {
                            $brandTier = (int) $brandTierRaw;
                        } else {
                            $brandTier = null;
                        }
                        $cabin = $fare['cabin_class'] ?? null;
                        $rbd = $fare['rbd_code'] ?? null;
                    }
                }
                $segmentRef = $segment['segment_ref'] ?? null;
                $segmentSequence = $index + 1;
                $segmentCabin = $this->resolveSegmentValue($cabin, $segmentRef, $index, $segmentSequence);
                $segmentRbd = $this->resolveSegmentValue($rbd, $segmentRef, $index, $segmentSequence);
                $segmentPayload = [
                    'flightNumber' => $segment['flight_number'],
                    'carrier' => $segment['operating_carrier']['iata'],
                    'departureDate' => substr($segment['departure_at'], 0, 10),
                    'departureTime' => substr($segment['departure_at'], 11, 8),
                    'arrivalDate' => substr($segment['arrival_at'], 0, 10),
                    'arrivalTime' => substr($segment['arrival_at'], 11, 8),
                    'from' => $segment['from']['iata'],
                    'to' => $segment['to']['iata'],
                    'classOfService' => $segmentRbd,
                    'cabin' => is_array($segmentCabin) ? ($segmentCabin[0] ?? null) : $segmentCabin,
                    'segmentSequence' => $segmentSequence,
                    'AvailabilitySourceCode' => $segment['availability_source_code'] ?? '',
                    'ContentSource' => 'GDS',
                ];
                if ($brandTier !== null) {
                    $segmentPayload['brandTier'] = $brandTier;
                }
                $specificSegments[] = $segmentPayload;
            }

            $productCriteriaAir[] = [
                'SpecificFlightCriteria' => $specificSegments,
                'sequence' => $flightIdx + 1,
            ];
        }
        $passengerCriteriaAir = [];
        if ($data['adults'] > 0) {
            $passengerCriteriaAir['PassengerCriteria'][] = [
                '@type' => 'PassengerCriteria',
                'number' => $data['adults'],
                'passengerTypeCode' => 'ADT'
            ];
        }
        if ($data['infants'] > 0) {
            $passengerCriteriaAir['PassengerCriteria'][] = [
                '@type' => 'PassengerCriteria',
                'number' => $data['infants'],
                'passengerTypeCode' => 'INF'
            ];
        }
        if ($data['children'] > 0) {
            $passengerCriteriaAir['PassengerCriteria'][] = [
                '@type' => 'PassengerCriteria',
                'number' => $data['children'],
                'passengerTypeCode' => 'CHD'
            ];
        }
        $body = [
            '@type' => 'OfferQueryBuildFromProducts',
            'BuildFromProductsRequest' => [
                '@type' => 'BuildFromProductsRequestAir',
                'PassengerCriteria' => $passengerCriteriaAir['PassengerCriteria'] ?? [
                    [
                        '@type' => 'PassengerCriteria',
                        'number' => 1,
                        'passengerTypeCode' => 'ADT'
                    ]
                ],
                'ProductCriteriaAir' => $productCriteriaAir
            ],
            'validateInventoryInd' => true
        ];
        Log::info("TravelPort Offer Request Body: " . json_encode($body, JSON_PRETTY_PRINT));
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("TravelPort Token not retrieved");
                return ['error' => 'Unable to get access token'];
            }
            if ($this->sessionId == null) {
                $wresponse = $this->createWorkbenchSession();
                if ($wresponse['success'] ?? false) {
                    $this->sessionId = $wresponse['sessionId'];
                } else {
                    return $wresponse;
                }
            }
            // Prepare API endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/book/airoffer/reservationworkbench/' . $this->sessionId . '/offers/buildfromproducts';

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
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response

            $response = $this->client->sendAsync($request)->wait();

            // Decode the JSON response
            $data = json_decode($response->getBody()->getContents(), true);
            Log::info("TravelPort offer Response: " . json_encode($data));
            if (isset($data['OfferListResponse']['Result']['Error'])) {
                if (count($data['OfferListResponse']['Result']['Error']) > 0) {
                    return ['error' => $data['OfferListResponse']['Result']['Error'][0]['Message']];
                }
                //return ['error' => $data['OfferListResponse']['Result']['Error'][0]['Message']];
            }
            return [
                'success' => true,
                'data' => $data
            ];
        } catch (\Exception $e) {
            Log::error("Travelport offer Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    public function addTravelers($data)
    {
        $responses = [];

        $contactEmail = $data['main_contact']['email'] ?? null;
        $contactPhone = $data['main_contact']['phone'] ?? null;
        $contactCountry = $data['main_contact']['country'] ?? 'PK';

        try {
            // --------------------------------------------------
            // 1. Get Token
            // --------------------------------------------------
            $token = $this->getAccessToken();
            if (!$token) {
                throw new \Exception('Unable to get access token');
            }

            // --------------------------------------------------
            // 2. Ensure Workbench Session
            // --------------------------------------------------
            if ($this->sessionId === null) {
                $wresponse = $this->createWorkbenchSession();
                if (!($wresponse['success'] ?? false)) {
                    return $wresponse;
                }
                $this->sessionId = $wresponse['sessionId'];
            }

            // --------------------------------------------------
            // 3. Endpoint + Headers
            // --------------------------------------------------
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion .
                '/air/book/traveler/reservationworkbench/' .
                $this->sessionId . '/travelers';

            $headers = [
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'XAUTH_TRAVELPORT_ACCESSGROUP' => $this->accessGroup,
                'Accept-Version' => $this->apiVersion,
                'Content-Version' => $this->apiVersion,
                'Authorization' => 'Bearer ' . $token,
            ];

            // --------------------------------------------------
            // 4. Loop each traveler → send individually
            // --------------------------------------------------
            foreach ($data['travellers'] as $index => $traveller) {

                $gender = $traveller['gender'] === 'M' ? 'Male' : 'Female';

                $payload = [
                    '@type' => 'Traveler',
                    'id' => 'trav_' . ($index + 1),
                    'passengerTypeCode' => $traveller['type'] === 'CNN' ? 'CHD' : $traveller['type'],
                    'gender' => $gender,
                    'birthDate' => $traveller['dob'],

                    'PersonName' => [
                        '@type' => 'PersonNameDetail',
                        'Prefix' => $traveller['title'],
                        'Given' => $traveller['firstName'],
                        'Surname' => $traveller['lastName'],
                    ],

                    'Telephone' => $contactPhone ? [
                        [
                            '@type' => 'Telephone',
                            'countryAccessCode' => $this->getCountryCode($contactCountry),
                            'phoneNumber' => $contactPhone,
                            'role' => 'Home',
                        ]
                    ] : [],

                    'Email' => $contactEmail ? [
                        [
                            'value' => $contactEmail
                        ]
                    ] : [],

                    'TravelDocument' => [
                        [
                            '@type' => 'TravelDocumentDetail',
                            'docNumber' => $traveller['documentNo'],
                            'docType' => ucfirst($traveller['documentType']),
                            'expireDate' => $traveller['expiryDate'],
                            'issueCountry' => strtoupper($traveller['issueCountry']),
                            'birthDate' => $traveller['dob'],
                            'Gender' => $gender,
                            'PersonName' => [
                                '@type' => 'PersonNameDetail',
                                'Prefix' => $traveller['title'],
                                'Given' => $traveller['firstName'],
                                'Surname' => $traveller['lastName'],
                            ],
                        ]
                    ]
                ];

                Log::info('TravelPort Traveler Payload', $payload);

                $request = new Request(
                    'POST',
                    $searchUrl,
                    $headers,
                    json_encode($payload)
                );

                try {
                    $response = $this->client->send($request);
                    $result = json_decode($response->getBody()->getContents(), true);

                    Log::info('Traveler Added Successfully', $result);

                    $responses[] = [
                        'traveler_id' => $payload['id'],
                        'success' => true,
                        'response' => $result
                    ];

                } catch (\Throwable $e) {
                    Log::error('Traveler Add Failed', [
                        'traveler_id' => $payload['id'],
                        'message' => $e->getMessage()
                    ]);

                    $responses[] = [
                        'traveler_id' => $payload['id'],
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // --------------------------------------------------
            // 5. Final response
            // --------------------------------------------------
            return [
                'success' => true,
                'travelers' => $responses
            ];

        } catch (\Throwable $e) {
            Log::error('TravelPort Traveler Error', [
                'message' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }


    private function getCountryCode($country)
    {
        return match (strtolower($country)) {
            'pakistan', 'pk' => '92',
            'usa', 'us' => '1',
            'uk' => '44',
            default => '92',
        };
    }


    public function bookFlight($data)
    {
        $wresponse = $this->createWorkbenchSession();
        if ($wresponse['success'] ?? false) {
            $this->sessionId = $wresponse['sessionId'];
        } else {
            return $wresponse;
        }
        $this->addTravelers($data);
        $response = $this->addOffer($data);
        if ($response['error'] ?? false) {
            return $response;
        }
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort Token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            $searchUrl = $this->searchUrl . '/' . $this->apiVersion .
                '/air/book/reservation/reservations/' .
                $this->sessionId;

            $headers = [
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'XAUTH_TRAVELPORT_ACCESSGROUP' => $this->accessGroup,
                'Accept-Version' => $this->apiVersion,
                'Content-Version' => $this->apiVersion,
                'Authorization' => 'Bearer ' . $token,
            ];
            $payload = [
                "@type" => "ReservationQueryCommitReservation"
            ];

            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->sendAsync($request)->wait();

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('TravelPort Booking Response' . json_encode($result, JSON_PRETTY_PRINT));

            return $result;
        } catch (\Throwable $e) {
            Log::error('TravelPort Traveler Error', [
                'message' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }

    }

    public function startConfirmationSession($pnr)
    {
        try {
            // Get token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/book/session/reservationworkbench/buildfromlocator?Locator=' . $pnr;

            $body = [
                '@type' => 'ReservationID',
                'ReservationID' => [],
            ];

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

            $request = new Request('POST', $searchUrl, $headers, json_encode($body));

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate HTTP response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);
                return ['error' => 'Invalid response from TravelPort'];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            // Validate JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('TravelPort: JSON decode failed', [
                    'error' => json_last_error_msg(),
                ]);
                return ['error' => 'Invalid response format'];
            }

            // API-level error
            if (!empty($data['Errors'])) {
                Log::error('TravelPort: API error', $data['Errors']);
                return ['error' => $data['Errors'][0]['Message'] ?? 'TravelPort API error'];
            }

            $sessionId = $data['ReservationResponse']['Reservation']['Identifier']['value'] ?? null;

            if (!$sessionId) {
                Log::warning('TravelPort: Session ID not found', $data);
                return ['error' => 'Session ID not returned'];
            }
            $this->confirmationId = $sessionId;
            return ['success' => true, 'sessionId' => $sessionId];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('TravelPort: Request exception', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : null,
            ]);

            return ['error' => 'TravelPort request failed'];

        } catch (\Throwable $e) {
            Log::error('TravelPort: Unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected server error'];
        }
    }

    public function FOPReservation($data)
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort Token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            $searchUrl = $this->searchUrl . '/' . $this->apiVersion .
                '/air/payment/reservationworkbench/' .
                $this->confirmationId . '/formofpayment';
            $headers = [
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'XAUTH_TRAVELPORT_ACCESSGROUP' => $this->accessGroup,
                'Accept-Version' => $this->apiVersion,
                'Content-Version' => $this->apiVersion,
                'Authorization' => 'Bearer ' . $token,
            ];
            $payload = [
                "@type" => "FormOfPaymentCash",
                "id" => "formOfPayment_1",
                "FormOfPaymentRef" => "formOfPayment_1"
            ];

            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->sendAsync($request)->wait();

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('TravelPort FOP Response' . json_encode($result, JSON_PRETTY_PRINT));
            if ($result['FormOfPaymentResponse']['Result']['Error'] ?? false) {
                return ['status' => false, 'error' => $result['FormOfPaymentResponse']['Result']['Error'][0]['Message']];
            }
            return ['success' => true, 'result' => $result];
        } catch (\Throwable $e) {
            Log::error('TravelPort Traveler Error', [
                'message' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }


    }

    public function applyPayment($data, $FOPResponse)
    {
        try {
            Log::info('Applying payment');
            $fopIdentifier = $FOPResponse['result']['FormOfPaymentResponse']['FormOfPayment']['Identifier']['value'];
            $pnrData = $data['pnrData'];

            $paymentPayload = [
                '@type' => 'Payment',
                'id' => 'payment_1',

                'Amount' => [
                    'code' => $pnrData['ReservationResponse']['Reservation']['Offer'][0]['Price']['CurrencyCode']['value'],
                    'minorUnit' => 2,
                    'currencySource' => 'Charged',
                    'value' => $pnrData['ReservationResponse']['Reservation']['Offer'][0]['Price']['TotalPrice'],
                ],

                'FormOfPaymentIdentifier' => [
                    'id' => 'formOfPayment_1',
                    'FormOfPaymentRef' => 'formOfPayment_1',
                    'Identifier' => [
                        'authority' => 'Travelport',
                        'value' => $fopIdentifier,
                    ]
                ],

                'OfferIdentifier' => [
                    [
                        'id' => $pnrData['ReservationResponse']['Reservation']['Offer'][0]['id'],
                        'offerRef' => $pnrData['ReservationResponse']['Reservation']['Offer'][0]['id'],
                        'Identifier' => [
                            'authority' => $pnrData['ReservationResponse']['Reservation']['Offer'][0]['Identifier']['authority'],
                            'value' => $pnrData['ReservationResponse']['Reservation']['Offer'][0]['Identifier']['value'],
                        ]
                    ]
                ]
            ];

            Log::info('Travelport Payment Payload', $paymentPayload);

            $token = $this->getAccessToken();
            if (!$token) {
                return ['error' => 'Unable to get access token'];
            }

            $url = $this->searchUrl . '/' . $this->apiVersion .
                '//air/paymentoffer/reservationworkbench/' .
                $this->confirmationId . '/payments';

            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'XAUTH_TRAVELPORT_ACCESSGROUP' => $this->accessGroup,
                'Accept-Version' => $this->apiVersion,
                'Content-Version' => $this->apiVersion,
                'Authorization' => 'Bearer ' . $token,
            ];

            $request = new Request('POST', $url, $headers, json_encode($paymentPayload));
            $response = $this->client->sendAsync($request)->wait();

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('Travelport Payment Response', $result);
            if ($result['PaymentResponse']['Payment']['Error'] ?? false) {
                return ['error' => $result['PaymentResponse']['Payment']['Error'][0]['Message']];
            }
            return $result;

        } catch (\Throwable $e) {
            Log::error('Travelport Apply Payment Error', [
                'message' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    public function confirmTicket($data)
    {

        $session = $this->startConfirmationSession($data->pnr);
        if ($session['success'] ?? false) {
            $this->confirmationId = $session['sessionId'];
            Log::info("Confirmation Session ID: " . $this->confirmationId);
            $FOPResponse = $this->FOPReservation($data);
            Log::info("Response", $FOPResponse);
            if ($FOPResponse['error'] ?? false) {
                return ['status' => false, 'error' => $FOPResponse['error']];
            } else {
                $paymentResponse = $this->applyPayment($data, $FOPResponse);
                if ($paymentResponse['error'] ?? false) {
                    return ['status' => false, 'error' => $paymentResponse['error']];
                } else {
                    $token = $this->getAccessToken();
                    if (!$token) {
                        return ['status' => false, 'error' => 'Unable to get access token'];
                    }

                    $url = $this->searchUrl . '/' . $this->apiVersion .
                        '/air/book/reservation/reservations/' .
                        $this->confirmationId;
                    $body = [
                        '@type' => 'ReservationQueryCommitReservation',
                    ];
                    $headers = [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'XAUTH_TRAVELPORT_ACCESSGROUP' => $this->accessGroup,
                        'Accept-Version' => $this->apiVersion,
                        'Content-Version' => $this->apiVersion,
                        'Authorization' => 'Bearer ' . $token,
                    ];
                    try {

                        $request = new Request('POST', $url, $headers, json_encode($body));
                        $response = $this->client->sendAsync($request)->wait();

                        $result = json_decode($response->getBody()->getContents(), true);
                        Log::info('Travelport Confirm Ticket Response', $result);
                        if (isset($result['ReservationResponse']['Reservation']['Offer'])) {
                            return [
                                'status' => true,
                                'result' => $result
                            ];

                        } else {
                            return [
                                'status' => false,
                                'result' => $result
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::error('Travelport Confirm Ticket Error', [
                            'message' => $e->getMessage()
                        ]);
                        return ['status' => false, 'error' => $e->getMessage()];

                    }

                }
            }


        } else {
            return ['success' => false, 'result' => $session];
        }
        // Implement ticket confirmation logic here
        // For now, just return a dummy response
        return [
            'status' => 'success',
            'message' => 'Ticket confirmed for PNR: ' . $data
        ];
    }


    public function cancelReservation($pnr)
    {
        try {
            // Get token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/receipt/reservations/' . $pnr . '/receipts';

            $body = [
                '@type' => 'ReservationID',
                'ReservationID' => [],
            ];

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

            $request = new Request('POST', $searchUrl, $headers, json_encode($body));

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate HTTP response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);
                return ['error' => 'Invalid response from TravelPort'];
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if ($data['ReceiptListResponse']['Result']['Error'] ?? false) {
                Log::error('TravelPort: API error', $data['ReceiptListResponse']['Result']['Error']);
                return null;
            }
            // Validate JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('TravelPort: JSON decode failed', [
                    'error' => json_last_error_msg(),
                ]);
                return ['error' => 'Invalid response format'];
            }
            Log::info('TravelPort Cancel Reservation Response', $data);
            return ['success' => true, 'data' => $data];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('TravelPort: Request exception', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : null,
            ]);

            return ['error' => 'TravelPort request failed'];

        } catch (\Throwable $e) {
            Log::error('TravelPort: Unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected server error'];
        }

    }

  

    public function initiateRefundWorkbench($pnr)
    {
    try {
            // Get token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/book/session/reservationworkbench/buildfromlocator?Locator=' . $pnr;

            $body = [
                '@type' => 'ReservationID',
                'ReservationID' => [],
            ];

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

            $request = new Request('POST', $searchUrl, $headers, json_encode($body));

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate HTTP response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);
                return ['error' => 'Invalid response from TravelPort'];
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $apiError = $this->extractTravelportError($data);
            if ($apiError) {
                Log::error('TravelPort: API error', $apiError);
                return ['success' => false, 'error' => $apiError['message'], 'data' => $apiError];
            }
            // Validate JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('TravelPort: JSON decode failed', [
                    'error' => json_last_error_msg(),
                ]);
                return ['error' => 'Invalid response format'];
            }
            Log::info('TravelPort Void Reservation Response', $data);
            return ['success' => true, 'data' => $data];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('TravelPort: Request exception', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : null,
            ]);

            return ['error' => 'TravelPort request failed'];

        } catch (\Throwable $e) {
            Log::error('TravelPort: Unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected server error'];
        }

    }
      public function refundQuote($voidIdentifier ,$reservationId){
        try {
            Log::info('Initiating refund quote for Reservation ID: ' . $reservationId);
            Log::info('Void Identifier: ' . $voidIdentifier);
            // Get token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/book/airoffer/reservationworkbench/' . $voidIdentifier.'/offers/canceloffer';

            $body = [
                '@type' => 'OfferQueryCancelOffer',
                'BuildFromOffer' => [
                    '@type' => 'BuildFromOfferAir',
                    'OfferIdentifier' => [
                        'Identifier' => [
                            'value' => $reservationId
                        ]
                    ]
                ]
            ];
            Log::info('Refund Quote Request Body', $body);
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

            $request = new Request('POST', $searchUrl, $headers, json_encode($body));

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate HTTP response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);
                return ['error' => 'Invalid response from TravelPort'];
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $apiError = $this->extractTravelportError($data);
            if ($apiError) {
                Log::error('TravelPort: API error', $apiError);
                return ['success' => false, 'error' => $apiError['message'], 'data' => $apiError];
            }
            // Validate JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('TravelPort: JSON decode failed', [
                    'error' => json_last_error_msg(),
                ]);
                return ['error' => 'Invalid response format'];
            }
            Log::info('TravelPort Void refund quote Response', $data);
            return ['success' => true, 'data' => $data];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('TravelPort: Request exception', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : null,
            ]);

            return ['error' => 'TravelPort request failed'];

        }
    }

    public function commitRefund($voidIdentifier ,$offerIdentifier){
        try {
            Log::info('Void Identifier: ' . $voidIdentifier);
            // Get token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/receipt/reservations/' . $voidIdentifier.'/receipts?OfferIdentifier='.$offerIdentifier;

            
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
                'RetainFlag' => 'false'
            ];

            $request = new Request('POST', $searchUrl, $headers);

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate HTTP response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);
                return ['error' => 'Invalid response from TravelPort'];
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $apiError = $this->extractTravelportError($data);
            if ($apiError) {
                Log::error('TravelPort: API error', $apiError);
                return ['success' => false, 'error' => $apiError['message'], 'data' => $apiError];
            }
            // Validate JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('TravelPort: JSON decode failed', [
                    'error' => json_last_error_msg(),
                ]);
                return ['error' => 'Invalid response format'];
            }
            Log::info('TravelPort Void refund commit Response'. json_encode($data));
            return ['success' => true, 'data' => $data];    
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('TravelPort: Request exception', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()  ? $e->getResponse()->getBody()->getContents()
                    : null,
            ]); 
            return ['error' => 'TravelPort request failed'];
        } catch (\Throwable $e) {   
            Log::error('TravelPort: Unexpected error', [
                'message' => $e->getMessage(),
            ]);
            return ['error' => 'Unexpected server error'];
        }
    }
    public function voidReservation($pnr)
    {
        Log::info('Initiating void reservation for PNR: ' . $pnr);
        $session = $this->initiateRefundWorkbench($pnr);
        Log::info('Refund Workbench Session Response', $session);
        if ($session['success'] ?? false) {
            $refundQuote = $this->refundQuote($session['data']['ReservationResponse']['Identifier']['value'],$session['data']['ReservationResponse']['Reservation']['Offer'][0]['Identifier']['value']);
            Log::info('Refund Quote Response', $refundQuote);
            if($refundQuote['success'] ?? false){
                $commitRefund = $this->commitRefund($session['data']['ReservationResponse']['Identifier']['value'],$session['data']['ReservationResponse']['Reservation']['Offer'][0]['Identifier']['value']);
                Log::info('Commit Refund Response', $commitRefund);
                if($commitRefund['success'] ?? false){
                    return ['success' => true, 'data' => $commitRefund['data']];
                } else {
                    return ['error' => $commitRefund['error'] ?? 'Unknown error during refund commit'];
                }
            } else {
                return ['error' => $refundQuote['error'] ?? 'Unknown error during refund quote'];

            }
            
        }

        return [
            'success' => false,
            'error' => $session['error'] ?? 'Unable to initiate refund workbench',
            'data' => $session['data'] ?? null,
        ];
    }

    public function ticketDisplayNumber($pnr){
        Log::info('Initiating ticket display for PNR: ' . $pnr);
        try{

        $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint
            $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/receipt/reservations/' . $pnr.'/receipts';

            
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
                'RetainFlag' => 'false'
            ];

            $request = new Request('GET', $searchUrl, $headers);

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate HTTP response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);
                return ['error' => 'Invalid response from TravelPort'];
            }
            $data = json_decode($response->getBody()->getContents(), true);
            if ($data['ReceiptListResponse']['Result']['Error'] ?? false) {
                Log::error('TravelPort: API error', $data['ReceiptListResponse']['Result']['Error']);
                return null;
            }

             Log::info('TravelPort ticket display Response'. json_encode($data));
                return ['success' => true, 'data' => $data];   

        } catch (\Throwable $e) {
            Log::error('TravelPort: Ticket Display Number Error', [
                'message' => $e->getMessage(),
            ]);
            return ['error' => 'Unexpected server error during ticket display'];
        }
    }
    public function voidGDSReservation($pnr)
    {
        Log::info('Initiating GDS void reservation for PNR: ' . $pnr);
        try {
            $response = $this->ticketDisplayNumber($pnr);
            Log::info('TravelPort ticket display response for GDS void', (array) $response);

            if (!($response['success'] ?? false)) {
                return ['error' => $response['error'] ?? 'Unable to fetch ticket numbers for void'];
            }

            $receiptIds = data_get($response, 'data.ReceiptListResponse.ReceiptID', []);
            if (!is_array($receiptIds)) {
                $receiptIds = [$receiptIds];
            }

            $ticketNumbers = [];
            foreach ($receiptIds as $receipt) {
                $documents = $receipt['Document'] ?? [];
                if (!is_array($documents)) {
                    $documents = [$documents];
                }

                foreach ($documents as $document) {
                    $number = $document['Number'] ?? null;
                    if (!empty($number)) {
                        $ticketNumbers[] = (string) $number;
                    }
                }
            }

            $ticketNumbers = array_values(array_unique($ticketNumbers));
            if (empty($ticketNumbers)) {
                return ['error' => 'No ticket numbers found to update status'];
            }

            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }
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
                'RetainFlag' => 'false'
            ];
            $results = [];
            foreach ($ticketNumbers as $ticketNumber) {
                $searchUrl = $this->searchUrl . '/' . $this->apiVersion . '/air/ticket/tickets/updatestatus/' . $ticketNumber;
                $body = [
                    'TicketQueryUpdateTicket' => (object) [],
                ];
                Log::info(json_encode($body));
                try {
                    $request = new Request('PUT', $searchUrl, $headers, json_encode($body));
                    $apiResponse = $this->client->sendAsync($request)->wait();
                    $rawBody = $apiResponse->getBody()->getContents();
                    $payload = json_decode($rawBody, true);

                    $resultErrors = data_get($payload, 'Result.Error', []);
                    if (!is_array($resultErrors)) {
                        $resultErrors = [$resultErrors];
                    }

                    $alreadyVoid = collect($resultErrors)->contains(function ($err) {
                        $message = strtoupper((string) ($err['Message'] ?? ''));
                        return str_contains($message, 'DOCUMENT IS ALREADY IN VOID STATUS');
                    });

                    $hasApiError = !empty($payload['ErrorResponse']) || (!empty($resultErrors) && !$alreadyVoid);
                    $isSuccess = $apiResponse->getStatusCode() === 200 && !empty($payload) && (!$hasApiError || $alreadyVoid);

                    $results[] = [
                        'ticket_number' => $ticketNumber,
                        'success' => $isSuccess,
                        'status_code' => $apiResponse->getStatusCode(),
                        'response' => $payload,
                        'raw_response' => $rawBody,
                        'already_void' => $alreadyVoid,
                    ];
                } catch (\GuzzleHttp\Exception\RequestException $e) {
                    $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
                    $results[] = [
                        'ticket_number' => $ticketNumber,
                        'success' => false,
                        'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500,
                        'response' => $errorBody ? json_decode($errorBody, true) : null,
                        'raw_response' => $errorBody,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('TravelPort GDS update-status results', $results);
            $hasFailure = collect($results)->contains(fn($r) => !($r['success'] ?? false));
            if ($hasFailure) {
                return [
                    'success' => false,
                    'error' => 'One or more ticket status updates failed',
                    'data' => $results,
                    'ticket_numbers' => $ticketNumbers,
                ];
            }

            return ['success' => true, 'data' => $results, 'ticket_numbers' => $ticketNumbers];

        } catch (\Throwable $e) {
            Log::info($e->getMessage());
            Log::error('TravelPort: Void GDS Reservation Error', [
                'message' => $e->getMessage(),
            ]);
            return ['error' => 'Unexpected server error during GDS void'];
        }
    }
    public function getBookingDetails($pnr)
    {
        Log::info('Fetching booking details for PNR: ' . $pnr);
        try {
            // Get token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error('TravelPort: Access token not retrieved');
                return ['error' => 'Unable to get access token'];
            }

            // Endpoint for retrieving reservation
            $url = $this->searchUrl . '/' . $this->apiVersion . '/air/book/reservation/reservations/' . $pnr;

            $headers = [
                'Accept-Encoding' => 'gzip, deflate',
                'Cache-Control' => 'no-cache',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'XAUTH_TRAVELPORT_ACCESSGROUP' => $this->accessGroup,
                'Accept-Version' => $this->apiVersion,
                'Content-Version' => $this->apiVersion,
                'Authorization' => 'Bearer ' . $token,
            ];

            $request = new Request('GET', $url, $headers);

            // Send request
            $response = $this->client->sendAsync($request)->wait();

            // Validate response
            if ($response->getStatusCode() !== 200) {
                Log::error('TravelPort: Invalid status code', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getBody()->getContents(),
                ]);

                return ['error' => 'Invalid response from TravelPort'];
            }

            $data = json_decode($response->getBody()->getContents(), true);

            // Validate JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('TravelPort: JSON decode failed', [
                    'error' => json_last_error_msg(),
                ]);

                return ['error' => 'Invalid response format'];
            }


            return $data;
        

        } catch (\GuzzleHttp\Exception\RequestException $e) {

            Log::error('TravelPort: Request exception', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse()
                    ? $e->getResponse()->getBody()->getContents()
                    : null,
            ]);

            return ['error' => 'TravelPort request failed'];

        } catch (\Throwable $e) {

            Log::error('TravelPort: Unexpected error', [
                'message' => $e->getMessage(),
            ]);

            return ['error' => 'Unexpected server error'];
        }
    }

    private function extractTravelportError(array $data): ?array
    {
        $paths = [
            'ReservationResponse.Result.Error',
            'ReceiptListResponse.Result.Error',
            'ErrorResponse.Result.Error',
            'Result.Error',
        ];

        foreach ($paths as $path) {
            $error = data_get($data, $path);
            if (empty($error)) {
                continue;
            }

            $errors = is_array($error) && array_is_list($error) ? $error : [$error];
            $first = $errors[0] ?? [];
            $message = $first['Message'] ?? $first['message'] ?? 'Travelport API returned an error';

            return [
                'message' => $message,
                'errors' => $errors,
            ];
        }

        return null;
    }
}
