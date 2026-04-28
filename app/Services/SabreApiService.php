<?php

namespace App\Services;

use App\Models\Airline;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use Number;

class SabreApiService
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $apiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = config('app.sabre.base_url');
        $this->clientSecret = config('app.sabre.client_secret');
    }

    /**
     * Get OAuth Access Token
     */
    public function getAccessToken()
    {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => "Basic $this->clientSecret",
        ];
        $body = 'grant_type=client_credentials';
        $tokenURL = "$this->apiUrl/v2/auth/token";
        Log::info("Token URL: $tokenURL");
        $request = new Request('POST', $tokenURL, $headers, $body);
        $response = $this->client->sendAsync($request)->wait();
        $body = json_decode($response->getBody(), true);
        Log::info("Sabre Access Token Response: " . json_encode($body));
        return $body['access_token'] ?? null;
    }

    /**
     * Search Flights
     */

    public function searchFlights($params)
    {
        Log::info("Sabre Search Flights Params: " . json_encode($params));
        $accessToken = $this->getAccessToken();
        $body = [
            'OTA_AirLowFareSearchRQ' => [
                'Version' => '6.8.0',
                'POS' => [
                    'Source' => [
                        [
                            'PseudoCityCode' => '8BBD',
                            'RequestorID' => [
                                'Type' => '1',
                                'ID' => '1',
                                'CompanyName' => [
                                    'Code' => 'TN',
                                    'content' => 'TN'
                                ]
                            ]
                        ]
                    ]
                ],
                "AvailableFlightsOnly" => true,
                'OriginDestinationInformation' => [],
                'TravelPreferences' => [
                    "VendorPref" => [
                        // ["Code" => "FZ", "PreferLevel" => "Unacceptable"],
                        // ["Code" => "OV", "PreferLevel" => "Preferred"],
                        //     ["Code" => "OD", "PreferLevel" => "Preferred"],
                        //     ["Code" => "3O", "PreferLevel" => "Preferred"],
                        //     ["Code" => "VS", "PreferLevel" => "Preferred"],
                        //     ["Code" => "HY", "PreferLevel" => "Preferred"],
                        //     ["Code" => "TG", "PreferLevel" => "Preferred"],
                        //     ["Code" => "6Q", "PreferLevel" => "Preferred"],
                        //     ["Code" => "SV", "PreferLevel" => "Preferred"],
                        //     ["Code" => "UL", "PreferLevel" => "Preferred"],
                        //     ["Code" => "SA", "PreferLevel" => "Preferred"],
                        //     ["Code" => "QR", "PreferLevel" => "Preferred"],
                        //     ["Code" => "WY", "PreferLevel" => "Preferred"],
                        //     ["Code" => "XY", "PreferLevel" => "Preferred"],
                        //     ["Code" => "KU", "PreferLevel" => "Preferred"],
                        //     ["Code" => "KQ", "PreferLevel" => "Preferred"],
                        //     ["Code" => "KL", "PreferLevel" => "Preferred"],
                        //     ["Code" => "GF", "PreferLevel" => "Preferred"],
                        //     ["Code" => "EY", "PreferLevel" => "Preferred"],
                        //     ["Code" => "ET", "PreferLevel" => "Preferred"],
                        //     ["Code" => "CZ", "PreferLevel" => "Preferred"],
                        //     ["Code" => "BA", "PreferLevel" => "Preferred"],
                        //     ["Code" => "PC", "PreferLevel" => "Preferred"],
                        //     ["Code" => "ER", "PreferLevel" => "Preferred"],
                        //     ["Code" => "CA", "PreferLevel" => "Preferred"],
                        //     ["Code" => "AC", "PreferLevel" => "Preferred"],
                        //     ["Code" => "J2", "PreferLevel" => "Preferred"],
                        //     ["Code" => "AA", "PreferLevel" => "Preferred"],
                    ],
                    "TPA_Extensions" => [
                        // "PreferNDCSourceOnTie" => ['Value' => true],
                        "DataSources" => [
                            "NDC" => "Enable",
                            "ATPCO" => "Enable",
                            "LCC" => "Disable",
                        ],
                        "NDCIndicators" => [
                            "MultipleBrandedFares" => [
                                "Value" => true
                            ]
                        ],
                    ],
                    "Baggage" => [
                        "RequestType" => "A",
                        // "CarryOnInfo" => true,
                        // "FreeCarryOn" => true,
                        "Description" => true,
                        // "FreePieceRequired" => true,
                    ],
                    "ETicketDesired" => true,
                    "ValidInterlineTicket" => true,
                ],
                'TravelerInfoSummary' => [


                    'AirTravelerAvail' => [
                        [
                            "PassengerTypeQuantity" => [


                            ]
                        ]
                    ],
                    "PriceRequestInformation" => [
                        "TPA_Extensions" => [
                            "Priority" => [
                                "Price" => [
                                    "Priority" => 1
                                ],
                                "DirectFlights" => [
                                    "Priority" => 2
                                ],
                                "Time" => [
                                    "Priority" => 3
                                ],
                                "Vendor" => [
                                    "Priority" => 4
                                ]
                            ],
                            "BrandedFareIndicators" => [
                                "SingleBrandedFare" => true,
                                "MultipleBrandedFares" => true,
                                "ReturnBrandAncillaries" => true
                            ],
                            "Indicators" => [
                                "RefundPenalty" => [
                                    "Ind" => true
                                ]
                            ]
                        ],
                        "CurrencyCode" => "PKR"
                    ]
                ],
                'TPA_Extensions' => [
                    "IntelliSellTransaction" => [
                        "RequestType" => [
                            "Name" => "50ITINS"
                        ]
                    ]
                ]
            ]
        ];

        // Handle OriginDestinationInformation based on flight type
        if ($params['flight_type'] === 'multi-city' && !empty($params['trips'])) {
            // Multi-city: Build OriginDestinationInformation from trips array
            foreach ($params['trips'] as $trip) {
                $body['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'][] = [
                    'DepartureDateTime' => $trip['date'] . "T20:00:00",
                    'OriginLocation' => [
                        'LocationCode' => $trip['origin']
                    ],
                    'DestinationLocation' => [
                        'LocationCode' => $trip['destination']
                    ]
                ];
            }
        } else {
            // One-way or Round-trip: Existing logic
            $body['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'][] = [
                'DepartureDateTime' => $params['departure_date'] . "T20:00:00",
                'OriginLocation' => [
                    'LocationCode' => $params['origin']
                ],
                'DestinationLocation' => [
                    'LocationCode' => $params['destination']
                ]
            ];

            // Add return flight details if available (round-trip)
            if (!empty($params['return_date']) && $params['flight_type'] === 'return') {
                $body['OTA_AirLowFareSearchRQ']['OriginDestinationInformation'][] = [
                    'DepartureDateTime' => $params['return_date'] . "T20:00:00",
                    'OriginLocation' => [
                        'LocationCode' => $params['destination']
                    ],
                    'DestinationLocation' => [
                        'LocationCode' => $params['origin']
                    ]
                ];
            }
        }
        if (!empty($params['adults'])) {
            $body['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'][] = [
                "Code" => "ADT",
                "Quantity" => (int) $params['adults']
            ];
        }
        // Add children if available
        if (!empty($params['children'])) {
            $body['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'][] = [
                "Code" => "CNN",
                "Quantity" => (int) $params['children']
            ];
        }

        // Add infants if available
        if (!empty($params['infants'])) {
            $body['OTA_AirLowFareSearchRQ']['TravelerInfoSummary']['AirTravelerAvail'][0]['PassengerTypeQuantity'][] = [
                "Code" => "INF",
                "Quantity" => (int) $params['infants']
            ];
        }

        // Add cabin class if available
        if (!empty($params['cabin_class'])) {
            $body['OTA_AirLowFareSearchRQ']['TravelPreferences']['CabinPref'][] = [
                "Cabin" => $params['cabin_class'] === 'Y' ? 'Economy' :
                    ($params['cabin_class'] === 'S' ? 'Premium Economy' :
                        ($params['cabin_class'] === 'C' ? 'Business' :
                            ($params['cabin_class'] === 'J' ? 'Premium Business' :
                                ($params['cabin_class'] === 'F' ? 'First' :
                                    ($params['cabin_class'] === 'P' ? 'Premium First' : 'Unknown'))))),
                "PreferLevel" => 'Preferred',
            ];
        }

        // Convert the body array to JSON
        $jsonBody = json_encode($body);
        Log::info("Search Request Body: " . $jsonBody);
        $url = "$this->apiUrl/v5/offers/shop";
        Log::info("Search Request URL: $url");
        $request = new Request('POST', "$this->apiUrl/v5/offers/shop", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $accessToken"
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            $this->processSabre($res->getBody());
            return json_decode($res->getBody(), true);
        } catch (RequestException $e) {
            Log::error("Error searching flights: " . $e->getResponse()->getBody()->getContents());
            return $e->getResponse()->getBody()->getContents();
        }
    }



    public function validateFlightData($flightData)
    {
        // Extract origin/destination information
        $originDestInfo = [];

        if (isset($flightData['legs'])) {
            foreach ($flightData['legs'] as $legIndex => $leg) {
                if (!empty($leg['stops'])) {
                    $firstStop = reset($leg['stops']);
                    $lastStop = end($leg['stops']);

                    $departureDateTime = null;
                    $arrivalDateTime = null;

                    if (isset($firstStop['departure']['time'])) {
                        $departureDateTime = $this->formatDateTime($firstStop['departure']['time']);
                    }

                    if (isset($lastStop['arrival']['time'])) {
                        $arrivalDateTime = $this->formatDateTime($lastStop['arrival']['time']);
                    }

                    $originLocation = $firstStop['departure']['city'] ?? null;
                    $destinationLocation = $lastStop['arrival']['city'] ?? null;

                    // Build flight segments
                    $flightSegments = [];
                    foreach ($leg['stops'] as $stopIndex => $stop) {
                        if (isset($stop['airline'], $stop['departure'], $stop['arrival'], $stop['flightNumber'])) {
                            $flightSegments[] = [
                                'Airline' => [
                                    'Marketing' => $stop['airline']->iata_code ?? '',
                                    'Operating' => $stop['airline']->iata_code ?? ''
                                ],
                                'Number' => $stop['flightNumber'],
                                'ClassOfService' => 'E', // Default to economy
                                'OriginLocation' => [
                                    'LocationCode' => $stop['departure']['city'] ?? ''
                                ],
                                'DestinationLocation' => [
                                    'LocationCode' => $stop['arrival']['city'] ?? ''
                                ],
                                'DepartureDateTime' => $this->formatDateTime($stop['departure']['time'] ?? ''),
                                'ArrivalDateTime' => $this->formatDateTime($stop['arrival']['time'] ?? ''),
                                'Type' => 'A'
                            ];
                        }
                    }

                    $originDestInfo[] = [
                        'RPH' => (string) ($legIndex + 1),
                        'DepartureDateTime' => $departureDateTime,
                        'OriginLocation' => [
                            'LocationCode' => $originLocation
                        ],
                        'DestinationLocation' => [
                            'LocationCode' => $destinationLocation
                        ],
                        'TPA_Extensions' => [
                            'Flight' => $flightSegments
                        ]
                    ];
                }
            }
        }

        // Build the complete request
        $request = [
            'OTA_AirLowFareSearchRQ' => [
                'Version' => '6.8.0',
                'POS' => [
                    'Source' => [
                        [
                            'PseudoCityCode' => 'D4NL',
                            'RequestorID' => [
                                'Type' => '1',
                                'ID' => '1',
                                'CompanyName' => [
                                    'Code' => 'TN',
                                    'content' => 'TN'
                                ]
                            ]
                        ]
                    ]
                ],
                'OriginDestinationInformation' => $originDestInfo,
                'TravelPreferences' => [
                    'TPA_Extensions' => [
                        'VerificationItinCallLogic' => [
                            'Value' => 'M',
                            'AlwaysCheckAvailability' => true
                        ]
                    ],
                    'Baggage' => [
                        'RequestType' => 'A',
                        'Description' => true
                    ]
                ],
                'TravelerInfoSummary' => [
                    'SeatsRequested' => [1],
                    'AirTravelerAvail' => [
                        [
                            'PassengerTypeQuantity' => [
                                [
                                    'Quantity' => 1,
                                    'Code' => 'ADT',
                                    'TPA_Extensions' => [
                                        'VoluntaryChanges' => [
                                            'Match' => 'Info'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'TPA_Extensions' => [
                    'IntelliSellTransaction' => [
                        'RequestType' => [
                            'Name' => 'REVALIDATE'
                        ],
                        'ServiceTag' => [
                            'Name' => 'REVALIDATE'
                        ]
                    ]
                ]
            ]
        ];
        // Log::info("Revalidation Request: " . json_encode($request, JSON_PRETTY_PRINT));
        return $request;



    }
    private function formatDateTime(string $dateTime): string
    {
        if (empty($dateTime)) {
            return date('Y-m-d\TH:i:s');
        }

        try {
            // Handle various date formats
            if (strpos($dateTime, '+') !== false || strpos($dateTime, '-') !== false) {
                // Already has timezone information
                $dt = new \DateTime($dateTime);
                return $dt->format('Y-m-d\TH:i:s');
            } else {
                // No timezone information
                $dt = new \DateTime($dateTime);
                return $dt->format('Y-m-d\TH:i:s');
            }
        } catch (\Exception $e) {
            // Return current date time if parsing fails
            return date('Y-m-d\TH:i:s');
        }
    }

    public function createPNR($customer, $flightData, $fareRefernce)
    {
        Log::info($customer);
        // foreach ($flightData['dates'] as $date) {
        //     $departureDate = $date['departureDate']; // Base date for the flight
        //     Log::info($departureDate);
        // }
        // Build SegmentSelect array for AirPrice->PriceRequestInformation->OptionalQualifiers->PricingQualifiers->SegmentSelection
        $segmentSelect = [];
        // Build SegmentSelect array based on unique segments across all legs
        $segmentSelect = [];
        $segmentNumber = 1;
        $seenSegments = [];


        $fareReference = $customer['fare_reference'];
        $brandCodes = [];

        // Use a global segment counter to ensure unique RPH values across all legs
        $globalSegmentNumber = 1;

        foreach ($flightData['leg']['flights'] as $legIndex => $flight) {
            if (!empty($flight['segments'])) {
                foreach ($flight['segments'] as $segmentIndex => $segment) {

                    // Default to no brand code
                    $brandCode = null;

                    // Now find the brand code from fares that match the selected references
                    if (!empty($flight['fares'])) {
                        foreach ($flight['fares'] as $fare) {
                            if (in_array($fare['ref_id'], $fareReference)) {
                                $brandCode = $fare['brand_code'] ?? null;
                                break; // Found the matching fare, no need to continue
                            }
                        }
                    }

                    // If we found a brand code, assign it to this segment
                    if ($brandCode) {
                        $brandCodes[] = [
                            "RPH" => (int) $globalSegmentNumber,
                            "content" => $brandCode,
                        ];
                    }

                    Log::info("Segment #{$globalSegmentNumber} → Brand: " . ($brandCode ?? 'none'));
                    $globalSegmentNumber++;
                }
            }
        }

        if (!empty($flightData['leg']['flights']) && $brandCodes) {
            foreach ($flightData['leg']['flights'] as $legIndex => $flight) {
                if (!empty($flight['segments'])) {
                    foreach ($flight['segments'] as $segmentIndex => $segment) {
                        // Create a unique key for each segment (e.g., by from/to/flight_number/departure_at)
                        $segmentKey = $segment['from']['iata'] . '-' . $segment['to']['iata'] . '-' . $segment['flight_number'] . '-' . $segment['departure_at'];
                        if (!isset($seenSegments[$segmentKey])) {
                            $segmentSelect[] = [
                                "Number" => (string) $segmentNumber,
                                "RPH" => (string) $segmentNumber
                            ];
                            $seenSegments[$segmentKey] = true;
                            $segmentNumber++;
                        }
                    }
                }
            }
        }

        $passengerTypes = [];
        foreach ($customer['travellers'] as $traveler) {
            $type = $traveler['type'];

            if (!isset($passengerTypes[$type])) {
                $passengerTypes[$type] = 0;
            }
            $passengerTypes[$type]++;
        }

        // Convert to required format
        $passengerTypeArray = array_map(function ($code, $quantity) {
            return ["Code" => $code, "Quantity" => (string) $quantity];
        }, array_keys($passengerTypes), $passengerTypes);

        $accessToken = $this->getAccessToken();
        Log::info("Access Token: " . $accessToken);
        // Initialize the PNR request body
        $farePrice = 0;
        foreach ($fareRefernce as $fareRefId) {
            foreach ($flightData['leg']['flights'] as $flight) {
                if (!empty($flight['fares'])) {
                    foreach ($flight['fares'] as $fare) {
                        if ($fare['ref_id'] === $fareRefId) {
                            $farePrice += $fare['billable_price'] ?? 0;
                        }
                    }
                }
            }
        }

        // Build PricingQualifiers structure as required
        $pricingQualifiers = [];

        if (!empty($segmentSelect)) {
            $pricingQualifiers['ItineraryOptions'] = [
                'SegmentSelect' => $segmentSelect
            ];
        }

        $pricingQualifiers['PassengerType'] = $passengerTypeArray;

        if (!empty($brandCodes)) {
            $pricingQualifiers['Brand'] = $brandCodes;
        }


        if ($flightData['provider']['source'] == 'SB') {

            $body = [
                "CreatePassengerNameRecordRQ" => [
                    "version" => "2.5.0",
                    "targetCity" => "8BBD",
                    "haltOnAirPriceError" => false,
                    "TravelItineraryAddInfo" => [
                        "AgencyInfo" => [
                            "Address" => [
                                "AddressLine" => "Fly Unique",
                                "CityName" => "Lahore", // Change if necessary
                                "CountryCode" => "PK",
                                "PostalCode" => "59300",
                                "StateCountyProv" => ["StateCode" => "PB"],
                                "StreetNmbr" => "65-B Gulfishan",
                                "VendorPrefs" => ["Airline" => ["Hosted" => false]]
                            ],
                            "Ticketing" => ["TicketType" => "7TAW"]
                            // "Ticketing" => ["TicketType" => "7T-A"]
                        ],
                        "CustomerInfo" => [
                            "ContactNumbers" => [
                                "ContactNumber" => [
                                    [
                                        "NameNumber" => "1.1",
                                        "Phone" => $customer['main_contact']['phone'], // Main contact phone
                                        "PhoneUseType" => "H" // Home
                                    ]
                                ]
                            ],
                            "PersonName" => []
                        ]
                    ],
                    "AirBook" => [
                        "HaltOnStatus" => [
                            ["Code" => "HL"],
                            ["Code" => "KK"],
                            ["Code" => "LL"],
                            ["Code" => "NO"],
                            ["Code" => "UC"],
                            ["Code" => "US"]
                        ],

                        "OriginDestinationInformation" => ["FlightSegment" => []],
                        "RedisplayReservation" => [
                            "NumAttempts" => 3,
                            "WaitInterval" => 3000,
                        ]
                    ],

                    "AirPrice" => [

                        [

                            "PriceComparison" => [
                                "AmountSpecified" => $farePrice,
                                "AcceptablePriceIncrease" => [
                                    "HaltOnNonAcceptablePrice" => true,
                                    "Amount" => 100
                                ]
                            ],

                            "PriceRequestInformation" => [
                                "Retain" => true,
                                "OptionalQualifiers" => [
                                    "FOP_Qualifiers" => [
                                        "BasicFOP" => [
                                            "Type" => "CA"
                                        ]
                                    ],
                                    "PricingQualifiers" => $pricingQualifiers
                                ]
                            ]
                        ]
                    ],
                    "SpecialReqDetails" => [
                        "AddRemark" => [
                            "RemarkInfo" => ["FOP_Remark" => [["Type" => "CASH"]]]
                        ],
                        "SpecialService" => [
                            "SpecialServiceInfo" => [
                                "SecureFlight" => [],
                                "Service" => []
                            ]
                        ]
                    ],
                    // 'MiscQualifiers' => [
                    //         'Commission' => [
                    //             'Percent' => 7,
                    //         ],
                    //     ],
                    "PostProcessing" => [
                        "EndTransaction" => ["Source" => ["ReceivedFrom" => "Jetze-DEV"]],
                        "RedisplayReservation" => (object) [],
                        "PricingInterval" => ["waitInterval" => 100]

                    ]
                ]
            ];
        } else if ($flightData['provider']['source'] == 'SB-NDC') {
            $createOrders = [];

            $passengers = [];
            foreach ($customer['travellers'] ?? [] as $index => $traveler) {
                $identityDocuments = [
                    [
                        "id" => "ID-1",
                        "documentNumber" => strtoupper($traveler['documentNo']),
                        "documentTypeCode" => 'PT',
                        "issuingCountryCode" => strtoupper($traveler['issueCountry']),
                        "placeOfIssue" => strtoupper($traveler['issueCountry']),
                        "citizenshipCountryCode" => strtoupper($traveler['nationality']),
                        "residenceCountryCode" => strtoupper($traveler['nationality']),
                        "titleName" => strtoupper($traveler['title']),
                        "givenName" => strtoupper($traveler['firstName']),
                        "surname" => strtoupper($traveler['lastName']),
                        "birthdate" => strtoupper($traveler['dob']),
                        "genderCode" => strtoupper($traveler['gender']),
                        "expiryDate" => strtoupper($traveler['expiryDate']),
                        "hostCountryCode" => strtoupper($traveler['nationality']),
                    ]
                ];


                $passenger = [
                    "id" => $traveler['id'] ?? "PAX" . ($index + 1),
                    "typeCode" => $traveler['type'] === 'CHD' ? 'CNN' : $traveler['type'],
                    "contactInfoRefId" => "CI-1",
                    "birthdate" => $traveler['dob'],
                    "givenName" => $traveler['firstName'],
                    "surname" => $traveler['lastName'],
                    "identityDocuments" => $identityDocuments,

                ];

                if (!empty($traveler['contactInfoRefId'])) {
                    $passenger["contactInfoRefId"] = $traveler['contactInfoRefId'];
                }
                if (!empty($traveler['passengerReference'])) {
                    $passenger["passengerReference"] = $traveler['passengerReference'];
                }

                $passengers[] = $passenger;
            }

            // Agency contact (optional, only if exists)

            // Collect offers based on fareReference(s)
            foreach ($fareRefernce as $fareRefId) {
                foreach ($flightData['leg']['flights'] as $flight) {
                    if (!empty($flight['fares'])) {
                        foreach ($flight['fares'] as $fare) {
                            if ($fare['ref_id'] === $fareRefId) {
                                $selectedOfferItems = [];

                                if (!empty($fare['passenger_fares'])) {
                                    // 🔍 Step 1: Check if any passenger_fares have offerItemId
                                    $hasPassengerOfferItem = false;
                                    foreach ($fare['passenger_fares'] as $pi) {
                                        if (!empty($pi['offerItemId'])) {
                                            $hasPassengerOfferItem = true;
                                            break;
                                        }
                                    }

                                    // ✅ Step 2: If passenger_fares contain offerItemId, process them
                                    if ($hasPassengerOfferItem) {
                                        foreach ($fare['passenger_fares'] as $pi) {
                                            $offerItemId = $pi['offerItemId'] ?? null;
                                            if (!$offerItemId) {
                                                continue;
                                            }

                                            Log::info("Processing passenger offerItemId: " . $offerItemId);

                                            $priceBody = json_encode([
                                                "query" => [
                                                    [
                                                        "offerItemId" => [$offerItemId]
                                                    ]
                                                ]
                                            ]);

                                            $priceRequest = new Request(
                                                'POST',
                                                "{$this->apiUrl}/v1/offers/price",
                                                [
                                                    'Accept' => 'application/json',
                                                    'Content-Type' => 'application/json',
                                                    'Authorization' => "Bearer $accessToken"
                                                ],
                                                $priceBody
                                            );

                                            try {
                                                $priceResponse = $this->client->send($priceRequest);
                                                $priceResult = json_decode($priceResponse->getBody(), true);
                                                $offers = $priceResult['response']['offers'] ?? [];

                                                Log::info("Offers received for passenger fare: " . json_encode($offers, JSON_PRETTY_PRINT));

                                                foreach ($offers as $offer) {
                                                    $offerId = $offer['id'] ?? null;
                                                    $selectedOfferItems = [];

                                                    foreach ($offer['offerItems'] as $item) {
                                                        $offerItemId = $item['id'] ?? null;
                                                        if ($offerItemId) {
                                                            $selectedOfferItems[] = ["id" => $offerItemId];
                                                        }

                                                        // 🎯 Passenger mapping
                                                        $passengersItems = $item['passengers'] ?? [];
                                                        $typeCounters = [];

                                                        foreach ($passengers as $index => $mainPassenger) {
                                                            $mainPassengerType = $mainPassenger['typeCode'] ?? 'N/A';

                                                            if (!isset($typeCounters[$mainPassengerType])) {
                                                                $typeCounters[$mainPassengerType] = 0;
                                                            }

                                                            foreach ($passengersItems as &$offerPassenger) {
                                                                $offerPassengerId = $offerPassenger['id'] ?? null;
                                                                $offerPassengerType = strtoupper($offerPassenger['ptc'] ?? '');
                                                                $mainPassengerType = strtoupper($mainPassenger['typeCode'] ?? '');

                                                                $typesMatch =
                                                                    $mainPassengerType === $offerPassengerType ||
                                                                    (in_array($mainPassengerType, ['CHD', 'CNN']) && in_array($offerPassengerType, ['CHD', 'CNN']));

                                                                if ($typesMatch && $offerPassengerId && empty($offerPassenger['__used'])) {
                                                                    $passengers[$index]['id'] = $offerPassengerId;
                                                                    $offerPassenger['__used'] = true;
                                                                    $typeCounters[$mainPassengerType]++;
                                                                    Log::info("Mapped {$mainPassengerType} passenger → OfferPassenger {$offerPassengerType} (ID: {$offerPassengerId})");
                                                                    break;
                                                                }
                                                            }

                                                        }
                                                    }

                                                    if ($offerId && !empty($selectedOfferItems)) {
                                                        $createOrders[] = [
                                                            "offerId" => $offerId,
                                                            "selectedOfferItems" => $selectedOfferItems
                                                        ];
                                                    }
                                                }

                                                Log::info("Offer Price Response (passenger fare): " . json_encode($priceResult));
                                            } catch (\Exception $e) {
                                                Log::error("Error pricing passenger offerItemId {$offerItemId}: " . $e->getMessage());
                                                return null;
                                            }
                                        }
                                    }

                                    // 🚨 Step 3: If no passenger_fares have offerItemId, fall back to fare-level offer_item_id
                                    else {
                                        $offerItemId = $fare['offer_item_id'] ?? null;

                                        if ($offerItemId) {
                                            Log::info("Fallback using fare-level offerItemId: " . $offerItemId);

                                            $priceBody = json_encode([
                                                "query" => [
                                                    [
                                                        "offerItemId" => [$offerItemId]
                                                    ]
                                                ]
                                            ]);

                                            $priceRequest = new Request(
                                                'POST',
                                                "{$this->apiUrl}/v1/offers/price",
                                                [
                                                    'Accept' => 'application/json',
                                                    'Content-Type' => 'application/json',
                                                    'Authorization' => "Bearer $accessToken"
                                                ],
                                                $priceBody
                                            );

                                            try {
                                                $priceResponse = $this->client->send($priceRequest);
                                                $priceResult = json_decode($priceResponse->getBody(), true);
                                                $offers = $priceResult['response']['offers'] ?? [];

                                                Log::info("Offers received (fare-level fallback): " . json_encode($offers, JSON_PRETTY_PRINT));

                                                foreach ($offers as $offer) {
                                                    $offerId = $offer['id'] ?? null;
                                                    $selectedOfferItems = [];

                                                    foreach ($offer['offerItems'] as $item) {
                                                        $itemId = $item['id'] ?? null;
                                                        if ($itemId) {
                                                            $selectedOfferItems[] = ["id" => $itemId];
                                                        }

                                                        // ✅ Passenger mapping (only one offer item expected)
                                                        $passengersItems = $item['passengers'] ?? [];
                                                        $typeCounters = [];

                                                        foreach ($passengers as $index => $mainPassenger) {
                                                            $mainPassengerType = $mainPassenger['typeCode'] ?? 'N/A';

                                                            if (!isset($typeCounters[$mainPassengerType])) {
                                                                $typeCounters[$mainPassengerType] = 0;
                                                            }

                                                            foreach ($passengersItems as &$offerPassenger) {
                                                                $offerPassengerId = $offerPassenger['id'] ?? null;
                                                                $offerPassengerType = $offerPassenger['ptc'] ?? null;

                                                                if (
                                                                    (
                                                                        $mainPassengerType === $offerPassengerType ||
                                                                        ($mainPassengerType === 'CHD' && $offerPassengerType === 'CNN') ||
                                                                        ($mainPassengerType === 'CNN' && $offerPassengerType === 'CHD')
                                                                    )
                                                                    && $offerPassengerId
                                                                    && empty($offerPassenger['__used'])
                                                                ) {
                                                                    $passengers[$index]['id'] = $offerPassengerId;
                                                                    $offerPassenger['__used'] = true;
                                                                    $typeCounters[$mainPassengerType]++;
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                    }

                                                    if ($offerId && !empty($selectedOfferItems)) {
                                                        $createOrders[] = [
                                                            "offerId" => $offerId,
                                                            "selectedOfferItems" => $selectedOfferItems
                                                        ];
                                                    }
                                                }

                                                Log::info("Offer Price Response (fare fallback + passenger map): " . json_encode($priceResult));
                                            } catch (\Exception $e) {
                                                Log::error("Error pricing fallback offerItemId {$offerItemId}: " . $e->getMessage());
                                                return null;
                                            }
                                        }
                                    }
                                }




                            }
                        }
                    }
                    break;
                }
            }

            $contactInfo = [
                [
                    "id" => "CI-1",

                    "phones" => [
                        [
                            "number" => $customer['agency_contact']['phone'] ?? $customer['main_contact']['phone'],
                        ]
                    ]
                ]
            ];
            Log::info("Passengers before linking INF to ADT: " . json_encode($passengers, JSON_PRETTY_PRINT));
            // Link INF passengers to an ADT passenger via passengerReference
            $adtIds = array_values(array_map(function ($p) {
                return $p['id'];
            }, array_filter($passengers, function ($p) {
                return isset($p['typeCode']) && $p['typeCode'] === 'ADT';
            })));

            $adtCounter = 0;
            if (!empty($adtIds)) {
                foreach ($passengers as $idx => &$p) {
                    if (isset($p['typeCode']) && $p['typeCode'] === 'INF') {
                        // assign passengerReference to an ADT id (round-robin if multiple ADT)
                        $p['passengerReference'] = $adtIds[$adtCounter % count($adtIds)];
                        $adtCounter++;
                    }
                }
                unset($p);
            } else {
                // No ADT found — ensure INF passengers still have the field (null)
                foreach ($passengers as $idx => &$p) {
                    if (isset($p['typeCode']) && $p['typeCode'] === 'INF') {
                        $p['passengerReference'] = null;
                    }
                }
                unset($p);
            }

            $body = [
                "transactionOptions" => [
                    "requestType" => "STATEFUL",
                    "commitTransaction" => true,
                    "movePassengerDetails" => true,
                    "intialIgnore" => false
                ],
                "createOrders" => $createOrders,
                "contactInfos" => $contactInfo,



                "passengers" => $passengers,

            ];
        }
        // $cabinCode = $flightData['passengerInfo'][0]['fareComponents'][0]['segments'][0]['segment']['bookingCode'] ?? '0'; // Default to 'Y' if not provided

        $segmentIndex = 0;
        $legsIndex = 0;

        if (!empty($flightData['leg']['flights']) && $flightData['provider']['source'] == 'SB') {
            foreach ($flightData['leg']['flights'] as $flight) {
                foreach ($flight['segments'] as $segment) {
                    // $cabinCode = $segment['cabin_class'] ?? 'Y'; // Default 'Y'
                    $bookingCode = null;
                    foreach ($flight['fares'] as $fare) {
                        if (in_array($fare['ref_id'], $fareReference)) {
                            foreach ($fare['booking_codes'] as $bc) {
                                if ($bc['segment_ref_id'] == $segment['ref_id']) {
                                    $bookingCode = $bc['booking_code'];
                                }
                            }
                        }
                    }

                    $flightNumber = (string) $segment['flight_number'];

                    // Parse ISO8601 times directly
                    $departureDateTime = Carbon::parse($segment['departure_at']);
                    $arrivalDateTime = Carbon::parse($segment['arrival_at']);

                    // Ensure arrival > departure
                    if ($arrivalDateTime->lessThanOrEqualTo($departureDateTime)) {
                        $arrivalDateTime->addDay();
                    }

                    // Only ADT + CNN travellers
                    $adtAndCnnTravelers = array_filter($customer['travellers'], function ($traveler) {
                        return in_array($traveler['type'], ['ADT', 'CNN', 'CHD']);
                    });

                    // Build segment
                    $body['CreatePassengerNameRecordRQ']['AirBook']['OriginDestinationInformation']['FlightSegment'][] = [
                        "DepartureDateTime" => $departureDateTime->format('Y-m-d\TH:i:s'),
                        "FlightNumber" => $flightNumber,
                        "NumberInParty" => (string) count($adtAndCnnTravelers),
                        "ResBookDesigCode" => $bookingCode ?? null,
                        "Status" => "NN",
                        "DestinationLocation" => ["LocationCode" => $segment['to']['iata']],
                        "MarketingAirline" => [
                            "Code" => $segment['operating_carrier']['iata'],
                            "FlightNumber" => $flightNumber
                        ],
                        "MarriageGrp" => "O",
                        "OriginLocation" => ["LocationCode" => $segment['from']['iata']]
                    ];

                    $segmentIndex++;
                }
            }
        }


        $adultNameNumbers = [];
        if ($flightData['provider']['source'] == 'SB') {
            foreach ($customer['travellers'] as $index => $traveler) {
                $nameNumber = ($index + 1) . ".1";

                if ($traveler['type'] === 'ADT') {
                    $adultNameNumbers[] = $nameNumber;
                }

                // Add passenger details
                $body['CreatePassengerNameRecordRQ']['TravelItineraryAddInfo']['CustomerInfo']['PersonName'][] = [
                    "NameNumber" => $nameNumber, // Incrementing index to ensure uniqueness
                    "NameReference" => $traveler['type'] === 'ADT' ? "" : (($traveler['type'] === 'CNN' || $traveler['type'] === 'CHD') ? 'C6' : 'I10'),
                    "PassengerType" => $traveler['type'] === 'CHD' ? 'CNN' : $traveler['type'],
                    "GivenName" => $traveler['firstName'] . " " . $traveler['title'],
                    "Surname" => $traveler['lastName'],
                    "Infant" => ($traveler['type'] === 'INF') ? true : false,
                ];

                // Add SecureFlight details
                $secureFlight = [
                    "SegmentNumber" => "A",
                    "PersonName" => [
                        "DateOfBirth" => Carbon::parse($traveler['dob'])->format('Y-m-d'),
                        "NameNumber" => ($traveler['type'] === 'INF') ? $adultNameNumbers[0] : $nameNumber,
                        "GivenName" => $traveler['firstName'],
                        "Surname" => $traveler['lastName'],
                        "Gender" => $traveler['gender'] ?? 'M'
                    ],
                    "VendorPrefs" => [
                        "Airline" => ["Hosted" => true]
                    ]
                ];
                $body['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['SecureFlight'][] = $secureFlight;

                // Handle Passenger Type Specific Rules
                if ($traveler['type'] === 'CNN' || $traveler['type'] === 'CHD') {
                    // Add CHLD SSR with age format
                    $age = Carbon::now()->diffInYears(Carbon::parse($traveler['dob']));
                    $body['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['Service'][] = [
                        "SSR_Code" => "CHLD",
                        "PersonName" => ["NameNumber" => $nameNumber],
                        // "Text" => Carbon::parse($traveler['dob'])->format('dMy')
                        "Text" => strtoupper(Carbon::parse($traveler['dob'])->format('dMy'))
                    ];
                } elseif ($traveler['type'] === 'INF') {

                    $adultIndex = min(count($adultNameNumbers) - 1, 0);
                    $adultNameNumber = $adultNameNumbers[$adultIndex] ?? "1.1";
                    $dob = Carbon::parse($traveler['dob']);
                    $infantText = strtoupper("{$traveler['lastName']}/{$traveler['firstName']}/" . Carbon::parse($traveler['dob'])->format('dMy'));
                    $body['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['Service'][] = [
                        "SSR_Code" => "INFT",
                        "PersonName" => ["NameNumber" => $adultNameNumber],
                        "Text" => $infantText
                    ];

                } elseif ($traveler['type'] === 'INS') {
                    // Infant with Seat - Uses own NameNumber
                    $body['CreatePassengerNameRecordRQ']['SpecialReqDetails']['SpecialService']['SpecialServiceInfo']['Service'][] = [
                        "SSR_Code" => "INFT",
                        "PersonName" => ["NameNumber" => $nameNumber],
                        "Text" => "{$traveler['lastName']}/{$traveler['firstName']}/" . Carbon::parse($traveler['dob'])->format('dMy') . "/OS"
                    ];
                }
            }
        }


        $jsonBody = json_encode($body, JSON_PRETTY_PRINT);
        Log::info("PNR Creation Request: " . $jsonBody);

        if ($flightData['provider']['source'] == 'SB-NDC') {
            $request = new Request('POST', "$this->apiUrl/v1/orders/create", [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer $accessToken",
                'Conversation-ID' => uniqid('conv_' . true)
            ], $jsonBody);
        } else if ($flightData['provider']['source'] == 'SB') {
            return;
            $request = new Request('POST', "$this->apiUrl/v2.5.0/passenger/records?mode=create", [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer $accessToken",
                'Conversation-ID' => uniqid('conv_' . true)
            ], $jsonBody);
        } else {
            return;
        }
        try {
            $res = null;
            $res = $this->client->send($request);
            Log::info("PNR Creation Response: " . json_encode(json_decode($res->getBody(), true), JSON_PRETTY_PRINT));
            return $res->getBody();
        } catch (RequestException $e) {
            Log::error("Error creating PNR: " . $e->getResponse()->getBody()->getContents());
            return null;
        }
    }


    public function confirmTicket($pnr)
    {
        $params = [
            "AirTicketRQ" => [
                "Itinerary" => [
                    "ID" => $pnr,
                ],
                "DesignatePrinter" => [
                    "Printers" => [
                        "Ticket" => [
                            "CountryCode" => 'PK'
                        ],
                        "Hardcopy" => [
                            "LNIATA" => config('airline.sabre.printer'),
                        ]
                    ]
                ],
                "Ticketing" => [
                    [
                        "FOP_Qualifiers" => [
                            "BasicFOP" => [
                                "Type" => "CA",
                            ]
                        ],
                        // 'MiscQualifiers' => [
                        //     'Commission' => [
                        //         'Percent' => 7,
                        //     ],
                        // ],
                        "PricingQualifiers" => [
                            "PriceQuote" => [
                                [
                                    "Record" => [
                                        [
                                            "Number" => 1,
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "PostProcessing" => [
                    "EndTransaction" => [
                        "Source" => [
                            "ReceivedFrom" => 'Jetze'
                        ],
                        "Email" => [
                            "Itinerary" => [
                                "PDF" => [
                                    "Ind" => true
                                ],
                                "Ind" => true
                            ],
                            "Ind" => true
                        ]
                    ]
                ]
            ]
        ];

        $accessToken = $this->getAccessToken();
        $jsonBody = json_encode($params);
        Log::info("Ticketing Request: " . $jsonBody);
        $request = new Request('POST', "{$this->apiUrl}/v1.3.0/air/ticket", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $accessToken"
        ], $jsonBody);
        try {
            $res = $this->client->send($request);
            $response = json_decode($res->getBody(), false);


            return $response->getBody();
        } catch (Exception $e) {
            Log::error("Error creating PNR: " . $e->getMessage());
            return null;
        }
    }

    public function voidBooking($bookingId)
    {
        $params = [
            "confirmationId" => $bookingId,
            "targetPcc" => '8BBD',
            "errorHandlingPolicy" => "HALT_ON_ERROR",
            "voidNonElectronicTickets" => true,
            "DesignatePrinter" => [
                "Printers" => [
                    "Ticket" => [
                        "CountryCode" => 'PK'
                    ],
                    "Hardcopy" => [
                        "LNIATA" => config('airline.sabre.printer'),
                    ]
                ]
            ],
        ];

        $accessToken = $this->getAccessToken();
        $jsonBody = json_encode($params);
        $request = new Request('POST', "{$this->apiUrl}/v1/trip/orders/voidFlightTickets", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $accessToken",
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            $response = json_decode($res->getBody(), true);
            Log::info('Void Response:');
            Log::info($response);
            return $response;
        } catch (\Exception $e) {
            Log::error("Error voiding flight tickets: " . $e->getMessage());
            return null;
        }
    }

    public function getBookingDetails($pnr)
    {
        $accessToken = $this->getAccessToken();
        // Log::info($pnr->pnr);
        $body = [
            "confirmationId" => $pnr->pnr,

        ];

        $jsonBody = json_encode($body);

        $request = new Request('POST', "$this->apiUrl/v1/trip/orders/getBooking", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $accessToken",
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            Log::info($res->getBody());
            return $res->getBody();
        } catch (\Exception $e) {
            Log::error("Error creating PNR: " . $e->getMessage());
            return null;
        }
    }

    public function cancelBooking($pnr)
    {
        $accessToken = $this->getAccessToken();

        $body = [
            "confirmationId" => $pnr,
            "bookingSource" => "SABRE",
            "retrieveBooking" => true,
            "cancelAll" => true
        ];
        $jsonBody = json_encode($body);
        $request = new Request('POST', "$this->apiUrl/v1/trip/orders/cancelBooking", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $accessToken",
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            // Log::info("Cancelling Sabre");
            // Log::info($res->getBody());
            return $res->getBody();
        } catch (\Exception $e) {
            Log::error("Error creating PNR: " . $e->getMessage());
            return null;
        }
    }

    public function cancelNDCOrder($orderId)
    {
        Log::info("Cancelling Sabre NDC Order with ID: " . $orderId);
        $accessToken = $this->getAccessToken();

        $body = [
            "id" => $orderId,
        ];
        $jsonBody = json_encode($body);
        $request = new Request('POST', "$this->apiUrl/v1/orders/cancel", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $accessToken",
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            Log::info("Cancelling Sabre NDC Order");
            Log::info($res->getBody());
            return $res->getBody();
        } catch (\Exception $e) {
            Log::error("Error cancelling NDC Order: " . $e->getMessage());
            return null;
        }
    }


    private function processSabre($sabreResponse)
    {
        $sabreResponse = json_decode($sabreResponse, true);
        // Log::info("Processing Sabre Response: " . json_encode($sabreResponse, JSON_PRETTY_PRINT));
        $processedSabreFlights = [];

        $schedules = collect($sabreResponse['groupedItineraryResponse']['scheduleDescs'] ?? [])
            ->keyBy('id');
        $legs = collect($sabreResponse['groupedItineraryResponse']['legDescs'] ?? [])
            ->keyBy('id');
        $fares = collect($sabreResponse['groupedItineraryResponse']['fareComponentDescs'] ?? [])
            ->keyBy('id');

        foreach ($legs as $legId => $legData) {
            $flights = [];

            foreach ($legData['schedules'] as $scheduleRef) {
                $schedule = $schedules[$scheduleRef['ref']] ?? null;
                if (!$schedule)
                    continue;

                $airline = Airline::where('iata_code', $schedule['carrier']['marketing'] ?? null)->first();
                $margin_amount = $airline ? $airline->margin_amount : 0;
                $amount_type = $airline ? $airline->amount_type : null;
                $margin_type = $airline ? $airline->margin_type : null;

                $fareInfo = $fares->firstWhere('governingCarrier', $schedule['carrier']['marketing'] ?? null);

                $flights[] = [
                    'from' => $schedule['departure']['airport'] ?? null,
                    'to' => $schedule['arrival']['airport'] ?? null,
                    'departure_time' => $schedule['departure']['time'] ?? null,
                    'arrival_time' => $schedule['arrival']['time'] ?? null,
                    'marketing_carrier' => [
                        'iata' => $schedule['carrier']['marketing'] ?? null,
                        'flight_number' => $schedule['carrier']['marketingFlightNumber'] ?? null,
                    ],
                    'fares' => [
                        [
                            'amount' => $fareInfo['publishedFareAmount'] ?? null,
                            'currency' => $fareInfo['publishedFareCurrency'] ?? null,
                            'margin_amount' => $margin_amount,
                            'amount_type' => $amount_type,
                            'margin_type' => $margin_type,
                        ]
                    ]
                ];
            }

            $processedSabreFlights[] = [
                'ref_id' => $legId,
                'provider' => 'Sabre',
                'leg' => [
                    'flights' => $flights
                ],
            ];
        }
        // Log::info("Processed Sabre Flights: " . json_encode($processedSabreFlights, JSON_PRETTY_PRINT));
        return response()->json([
            'status' => true,
            'data' => $processedSabreFlights
        ]);
    }

}
