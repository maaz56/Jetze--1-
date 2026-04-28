<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Log;

class FlyDubaiApiService
{
    protected $client;
    protected $headers;

    protected $apiUrl;
    protected $username;
    protected $password;
    protected $client_id;
    protected $client_secret;
    protected $iata_number;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = config('flydubai.url');
        $this->username = config('flydubai.username');
        $this->password = config('flydubai.password');
        $this->client_id = config('flydubai.client_id');
        $this->client_secret = config('flydubai.client_secret');
        $this->iata_number = config('flydubai.iata_number');

    }

    public function getAccessToken()
    {
        try {
            $tokenURL = "{$this->apiUrl}/res/v3/authenticate";

            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            $body = [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'password',
                'password' => $this->password,
                'scope' => 'res',
                'username' => $this->username,
            ];

            Log::info("FlyDubai Auth Request Body (x-www-form-urlencoded): " . http_build_query($body));

            // Create request for x-www-form-urlencoded
            $request = new Request(
                'POST',
                $tokenURL,
                $headers,
                http_build_query($body) // <-- FORM ENCODING
            );

            // Send async request and wait for response
            $response = $this->client->sendAsync($request)->wait();

            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("Flydubai Access Token Response: " . json_encode($data));

            $token = $data['access_token'] ?? null;

            if (!$token) {
                Log::warning("FlyDubai token missing in response");
            }

            return $token;

        } catch (\Exception $e) {
            Log::error("FlyDubai Auth Error: " . $e->getMessage());
            return null;
        }

    }

    public function getMultiCityAvail($params)
    {
        try {
            Log::info("Searching Flydubai Multi-City Flights with params: " . json_encode($params));

            $accessToken = $this->getAccessToken();

            // 🧩 Build availRequest directly here
            $availRequest = [
                'availRequest' => [
                    'requestorDetails' => [
                        'channel' => 'TPAPI',
                        'pointOfSale' => [
                            'country' => 'AE',
                        ],
                        'filter' => 'CHEAPESTCOMBO',
                        'POCCity' => $params['currencyCode'] ?? 'AED',
                        'POCDate' => now()->toDateString(),
                        'currencyCode' => $params['currencyCode'] ?? 'AED',
                    ],
                    'passengerDetails' => [
                        'pax' => [],
                    ],
                    'journeyData' => [
                        'originDestination' => [],
                    ],
                ],
            ];

            // 👨‍👩‍👧 Add passengers
            if (!empty($params['adults']) && $params['adults'] > 0) {
                $availRequest['availRequest']['passengerDetails']['pax'][] = [
                    'type' => 'ADT',
                    'count' => (int) $params['adults'],
                ];
            }
            if (!empty($params['children']) && $params['children'] > 0) {
                $availRequest['availRequest']['passengerDetails']['pax'][] = [
                    'type' => 'CHD',
                    'count' => (int) $params['children'],
                ];
            }
            if (!empty($params['infants']) && $params['infants'] > 0) {
                $availRequest['availRequest']['passengerDetails']['pax'][] = [
                    'type' => 'INF',
                    'count' => (int) $params['infants'],
                ];
            }

            // 🧭 Add journey data
            if (!empty($params['trips']) && is_array($params['trips'])) {
                foreach ($params['trips'] as $index => $trip) {
                    $date = $trip['date'];
                    $availRequest['availRequest']['journeyData']['originDestination'][] = [
                        'ID' => (string) ($index + 1),
                        'origin' => $trip['origin'],
                        'destination' => $trip['destination'],
                        'originAirport' => $trip['origin'],
                        'destinationAirport' => $trip['destination'],
                        'departureDateRange' => [
                            'startDate' => $date . "T00:00:00",
                            'endDate' => $date . "T23:59:59",
                        ],
                        'carriers' => [
                            $params['airline'] ?? 'FZ',
                        ],
                    ];
                }
            }

            // 🧾 Log the complete formatted request
            Log::info("Generated FlyDubai Avail Request Body:\n" . json_encode($availRequest, JSON_PRETTY_PRINT));

            $request = new Request(
                'POST',
                "{$this->apiUrl}/res/v3/offers/multicity/availability",
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer $accessToken",
                ],
                json_encode($availRequest)
            );
            $response = $this->client->sendAsync($request)->wait();
            Log::info("FlyDubai Multi-City Avail Response: " . $response->getBody()->getContents());
            $response = json_decode($response->getBody(), true);
            return $response;

        } catch (\Exception $e) {
            Log::error("FlyDubai Search Error: " . $e->getMessage());
            return null;
        }
    }


    public function searchMultiCityFlights($params)
    {
        try {
            Log::info("Searching Flydubai Multi-City Flights with params: " . json_encode($params));

            $accessToken = $this->getAccessToken();
            Log::info("Access Token: " . $accessToken);

            $availResponse = $this->getMultiCityAvail($params);

            // Base request structure
            $fareQuoteRequest = [
                "fareQuoteRequest" => [
                    "passengers" => [
                        "passenger" => [
                            [
                                "passengerID" => 1,
                                "DOB" => "",
                                "PTCID" => 1
                            ]
                        ]
                    ],
                    "bookingCurrency" => "AED",
                    "GUID" => "",
                    "bookingIATA" => $this->iata_number ?? "",
                    "subChannel" => "",
                    "pointOfSale" => "AED",
                    "originDestinations" => [
                        "originDestination" => []
                    ],
                    "channel" => "OTA",
                    "bookingCountryCode" => "AE",
                    "promoCode" => ""
                ]
            ];

            // Ensure structure consistency
            $originDestinations = [];
            $odIndex = 1;

            // Loop through all Origin-Destination results
            foreach ($availResponse['availResponse']['originDestination'] as $od) {

                // Get flight groups (can be single object or array)
                $flightGroups = $od['flightGroups'] ?? [];
                if (isset($flightGroups['flights'])) {
                    $flightGroups = [$flightGroups];
                }

                foreach ($flightGroups as $fg) {
                    // Handle single/multiple flights per group
                    $flights = $fg['flights'] ?? [];
                    if (isset($flights['ID'])) {
                        $flights = [$flights];
                    }

                    // If no flights, skip
                    if (empty($flights))
                        continue;

                    // Build leg details for all flights in this group
                    $legDetails = [];
                    foreach ($flights as $flight) {
                        $legDetails[] = [
                            "marketingFlt" => [
                                "flightNum" => $flight['mrktFlight']['flightNum'],
                                "carrierCode" => $flight['mrktFlight']['carrier']
                            ],
                            "depDateTime" => $flight['depDateTime'],
                            "origin" => $flight['origin'],
                            "operatingFlt" => [
                                "flightNum" => $flight['operFlight']['flightNum'],
                                "carrierCode" => $flight['operFlight']['carrier']
                            ],
                            "destination" => $flight['destination']
                        ];
                    }

                    // Determine start and end details
                    $firstFlight = $flights[0];
                    $lastFlight = $flights[count($flights) - 1];

                    // Build originDestination entry
                    $originDestinations[] = [
                        "paxFareDetails" => [
                            "fare" => [
                                [
                                    "cabin" => ucfirst(strtolower($fg['cabinClass'] ?? 'Economy')),
                                    "pax" => [1]
                                ]
                            ]
                        ],
                        "id" => $odIndex,
                        "arrivalTime" => $lastFlight['arrDateTime'],
                        "depDateTime" => $firstFlight['depDateTime'],
                        "origin" => $firstFlight['origin'],
                        "legDetails" => [
                            "legDetail" => $legDetails
                        ],
                        "destination" => $lastFlight['destination']
                    ];

                    $odIndex++;
                }
            }

            // Assign all built ODs to the final request
            $fareQuoteRequest['fareQuoteRequest']['originDestinations']['originDestination'] = $originDestinations;

            Log::info("Generated Fare Quote Request: " . json_encode($fareQuoteRequest, JSON_PRETTY_PRINT));
            $request = new Request(
                'POST',
                "{$this->apiUrl}/res/v3/offers/multicity/fares",
                [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Bearer {$accessToken}",
                ],
                json_encode($fareQuoteRequest)
            );
            $response = $this->client->sendAsync($request)->wait();
            Log::info("FlyDubai Fare Quote Response: " . $response->getBody()->getContents());
            $response = json_decode($response->getBody(), true);
            return $response;

        } catch (\Exception $e) {
            Log::error("FlyDubai Search Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Helper to detect associative array
     */
    function is_assoc(array $array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
    public function searchFlights($params)
    {
        try {
            Log::info("Searching Flydubai Flights with params: " . json_encode($params));

            $accessToken = $this->getAccessToken();
            Log::info("Access Token: " . $accessToken);

            // Step 1: Build passenger list dynamically
            $fareQuoteRequestInfos = [];

            $departureDate = Carbon::parse($params['departure_date'])->format('d/m/Y');
            $returnDate = isset($params['return_date']) && !empty($params['return_date'])
                ? Carbon::parse($params['return_date'])->format('d/m/Y')
                : $departureDate;

            $paxMapping = [
                'adults' => 1,
                'children' => 6,
                'infants' => 5,
            ];

            foreach ($paxMapping as $key => $typeId) {
                $count = (int) ($params[$key] ?? 0);
                if ($count > 0) {
                    $fareQuoteRequestInfos[] = [
                        'PassengerTypeID' => $typeId,
                        'TotalSeatsRequired' => $count,
                    ];
                }
            }

            // Step 2: Common segment (for both one-way & round-trip)
            $segment = [
                "Origin" => $params['origin'],
                "Destination" => $params['destination'],
                "PartyConfig" => "",
                "UseAirportsNotMetroGroups" => "true",
                "UseAirportsNotMetroGroupsAsRule" => "true",
                "UseAirportsNotMetroGroupsForFrom" => "true",
                "UseAirportsNotMetroGroupsForTo" => "true",
                "DateOfDepartureStart" => $params['departure_date'] . "T20:00:00",
                "DateOfDepartureEnd" => $params['departure_date'] . "T23:59:59",
                "FareQuoteRequestInfos" => [
                    "FareQuoteRequestInfo" => $fareQuoteRequestInfos,
                ],
                "FareTypeCategory" => "1",
            ];

            $fareQuoteDetails = ["FareQuoteDetailDateRange" => [$segment]];

            // Step 3: Add return leg if round-trip
            if (($params['flight_type'] ?? '') === 'return' && !empty($params['return_date'])) {
                $returnSegment = $segment;
                $returnSegment['Origin'] = $params['destination'];
                $returnSegment['Destination'] = $params['origin'];
                $returnSegment['DateOfDepartureStart'] = $params['return_date'] . "T20:00:00";
                $returnSegment['DateOfDepartureEnd'] = $params['return_date'] . "T23:59:59";
                $fareQuoteDetails['FareQuoteDetailDateRange'][] = $returnSegment;
            }

            // Step 4: Build main request body
            $body = [
                "RetrieveFareQuoteDateRange" => [
                    "RetrieveFareQuoteDateRangeRequest" => [
                        "SecurityGUID" => "",
                        "CarrierCodes" => [
                            "CarrierCode" => [
                                [
                                    "AccessibleCarrierCode" => "FZ",
                                ]
                            ]
                        ],
                        "ChannelID" => "OTA",
                        "CountryCode" => "PK",
                        "ClientIPAddress" => "13.234.242.73",
                        "HistoricUserName" => $this->username,
                        "CurrencyOfFareQuote" => "PKR",
                        "PromotionalCode" => "FAREBRANDS",
                        "IataNumberOfRequestor" => $this->iata_number ?? "0000000",
                        "FullInBoundDate" => $returnDate,
                        "FullOutBoundDate" => $departureDate,
                        "CorporationID" => "-2147483648",
                        "FareFilterMethod" => "NoCombinabilityRoundtripLowestFarePerFareType",
                        "FareGroupMethod" => "WebFareTypes",
                        "InventoryFilterMethod" => "Available",
                        "FareQuoteDetails" => $fareQuoteDetails,
                    ]
                ]
            ];

            $header = [
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$accessToken}",
            ];

            Log::info("FlyDubai Search Request Body: " . json_encode($body, ));

            // Send request
            $request = new Request('POST', "{$this->apiUrl}/res/v3/pricing/flightswithfares", $header, json_encode($body));
            $response = $this->client->sendAsync($request)->wait();

            // Decode response
            $response = json_decode($response->getBody(), true);
            Log::info("FlyDubai Search Response: " . json_encode($response));

            return $response;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Log API request exceptions (network or server issues)
            Log::error('FlyDubai API Request Exception: ' . $e->getMessage(), [
                'request' => (string) $e->getRequest()->getBody(),
                'response' => $e->hasResponse() ? (string) $e->getResponse()->getBody() : null,
            ]);

            return null;

        } catch (\Exception $e) {
            // Catch any other PHP or logic errors
            Log::error('FlyDubai Search General Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
    public function AddToCart($requestData, $fareReference)
    {
        Log::info(json_encode($requestData['flight']));
        Log::info(json_encode($fareReference));

        // Extract main contact information
        $mainContact = $requestData['main_contact'];
        $travellers = $requestData['travellers'];
        $flightData = $requestData['flight'];

        // Find all selected fares from the flight data
        $selectedFares = [];
        foreach ($fareReference as $fareRef) {
            foreach ($flightData['leg']['flights'] as $flight) {
                foreach ($flight['fares'] as $fare) {
                    if ($fare['ref_id'] === $fareRef) {
                        $selectedFares[] = $fare;
                        break 2; // Break out of both inner loops
                    }
                }
            }
        }

        if (empty($selectedFares)) {
            throw new Exception("No selected fares found: " . implode(', ', $fareReference));
        }

        // Build the request body
        $requestBody = [
            'currency' => $selectedFares[0]['currency'] ?? 'PKR',
            'IATA' => $this->iata_number ?? '',
            'inventoryFilterMethod' => 0,
            'securityGUID' => null,
            'originDestinations' => [],
            
        ];

        // Build originDestinations from flight segments
        foreach ($flightData['leg']['flights'] as $flight) {
            $originDestination = [
                'odID' => $flight['ref_id'],
                'origin' => $flight['from']['iata'],
                'destination' => $flight['to']['iata'],
                'flightNum' => $this->buildFlightNumber($flight),
                'depDate' => $flight['departure_at'],
                'isPromoApplied' => false,
                'fareBrand' => [],
                'segmentDetails' => []
            ];

            // Add fare brands for selected fares
            foreach ($selectedFares as $selectedFare) {
                $fareBrand = [
                    'fareBrandID' => $selectedFare['fare_type_id'],
                    'fareBrandName' => $selectedFare['name_class'],
                    'fareInfos' => []
                ];

                // Add passenger fare information
                foreach ($selectedFare['passenger_fares'] as $passengerFare) {
                    $fareInfo = [
                        'paxFareInfos' => []
                    ];

                    $paxFareInfo = [
                        'applicableTaxDetails' => [],
                        'fareID' => $passengerFare['FareID'],
                        'ID' => (string) $passengerFare['ID'],
                        'FBC' => $passengerFare['FBCode'] ?? 'R',
                        'fareClass' => $selectedFare['fare_segments'][0]['fare_class'] ?? 'R',
                        'cabin' => strtoupper($selectedFare['class']),
                        'baseFare' => $passengerFare['base_price'],
                        'ruleID' => $passengerFare['RuleId'],
                        'originalFare' => $passengerFare['OriginalFare'],
                        'totalFare' => $passengerFare['total_price'],
                        'PTC' => $passengerFare['PTCID'],
                        'seatAvailability' => $selectedFare['available_seats'],
                        'infantAvailability' => $passengerFare['InfantSeatsAvailable'],
                        'secureHash' => $passengerFare['hashCode'] ?? '',
                        'fareCarrier' => $flightData['provider']['code']
                    ];

                    // Add tax details - map tax codes properly
                    foreach ($passengerFare['applicableTaxes'] as $tax) {
                            $paxFareInfo['applicableTaxDetails'][] = [
                                'Amt' => $tax['Amt'],
                                'TaxCode' => $tax['TaxCode'],
                                'TaxID' => $tax['TaxID']
                            ];
                        
                    }

                    $fareInfo['paxFareInfos'][] = $paxFareInfo;
                    $fareBrand['fareInfos'][] = $fareInfo;
                }

                $originDestination['fareBrand'][] = $fareBrand;
            }

            // Add segment details
            foreach ($flight['segments'] as $segment) {
                $segmentDetail = [
                    'segmentID' => $segment['ref_id'],
                    'origin' => $segment['from']['iata'],
                    'destination' => $segment['to']['iata'],
                    'depDate' => $segment['departure_at'],
                    'arrDate' => $segment['arrival_at'],
                    'bookingCodes' => [],
                    'OAFlight' => false,
                    'operCarrier' => $segment['operating_carrier']['iata'],
                    'operFlightNum' => $segment['operating_flight_number'],
                    'mrktCarrier' => $segment['marketing_carrier'],
                    'mrktFlightNum' => $segment['flight_number']
                ];

                // Add booking codes for all selected fares
                foreach ($selectedFares as $selectedFare) {
                    $bookingCode = [
                        'fareClass' => $selectedFare['fare_segments'][0]['fare_class'] ?? 'R',
                        'cabin' => strtoupper($selectedFare['class']),
                        'paxID' => array_map('intval', array_column($selectedFare['passenger_fares'], 'ID'))
                    ];
                    $segmentDetail['bookingCodes'][] = $bookingCode;
                }

                $originDestination['segmentDetails'][] = $segmentDetail;
            }

            $requestBody['originDestinations'][] = $originDestination;
        }

        Log::info("FlyDubai Create Booking Request Body: " . json_encode($requestBody));
        return $requestBody;
    }

    /**
     * Build flight number string from flight data
     */
    private function buildFlightNumber($flight)
    {
        $flightNumbers = [];
        foreach ($flight['segments'] as $segment) {
            $flightNumbers[] = $segment['flight_number'];
        }
        return implode('/', $flightNumbers);
    }

    /**
     * Map tax IDs to tax codes
     */
    private function mapTaxCode($taxId)
    {
        $taxMap = [
            4247 => 'YQ', // Common tax codes mapping
            7364 => 'YR',
            8025 => 'OM',
            10386 => 'ZR',
            11066 => 'F6',
            11107 => 'S6',
            11947 => 'I2',
            // Add more mappings as needed based on your tax data
        ];

        return $taxMap[$taxId] ?? 'TX'; // Default to 'TX' if not mapped
    }





}
