<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Pool;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SooperApiService
{
    protected $client;
    protected $apiUrl;
    protected $accessToken;
    protected $tokenExpiry;

    public function __construct()
    {
        $this->client = new Client([
            // 'timeout' => 10, // Set a timeout for requests
            // 'connect_timeout' => 5,
        ]);
        $this->apiUrl = config('sooper.url', 'https://www.sooperfare.com/api/v1');
    }

    /**
     * Get OAuth Access Token with Caching
     */
    public function getAccessToken()
    {
        // Check if token is cached and not expired
        if ($this->accessToken && $this->tokenExpiry > now()) {
            return $this->accessToken;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $body = json_encode([
            'username' => config('sooper.client_id'),
            'password' => config('sooper.secret'),
        ]);

        try {
            $request = new Request('POST', "$this->apiUrl/partner/auth/login", $headers, $body);
            $response = $this->client->send($request); // Synchronous for token
            $body = json_decode($response->getBody(), true);

            $this->accessToken = $body['data']['access_token']['token'] ?? null;
            // Assume token expires in 1 hour (adjust based on API docs)
            $this->tokenExpiry = now()->addHour();

            // Cache token
            Cache::put('sooper_api_token', $this->accessToken, $this->tokenExpiry);

            return $this->accessToken;
        } catch (RequestException $e) {
            Log::error('Sooper API Access Token Error: ', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get Providers with Caching
     */
    public function getProviders($params)
    {
        // $cacheKey = 'sooper_providers_' . md5(json_encode($params));
        // $cachedProviders = Cache::get($cacheKey);

        // if ($cachedProviders) {
        //     return $cachedProviders;
        // }
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-SF-Authorization' => $this->getAccessToken(),
        ];

        $tripType = $params['flight_type'];
        // Multi-city support
        if (isset($params['flight_type']) && $params['flight_type'] === 'multi-city' && !empty($params['trips'])) {
            $legs = [];
            foreach ($params['trips'] as $leg) {
            $legs[] = [
                'from' => $leg['origin'],
                'to' => $leg['destination'],
                'date' => $leg['date'],
            ];
            }
        } else {
            // One-way or return
            $legs = [
            [
                'from' => $params['origin'],
                'to' => $params['destination'],
                'date' => $params['departure_date'],
            ]
            ];
            if ($tripType === 'return' && !empty($params['return_date'])) {
            $legs[] = [
                'from' => $params['destination'],
                'to' => $params['origin'],
                'date' => $params['return_date'],
            ];
            }
        }

        $body = [
            'legs' => $legs,
            'cabin_class' => $params['cabin_class'] === 'Y' ? 'economy' :
            ($params['cabin_class'] === 'S' ? 'premium_economy' :
                ($params['cabin_class'] === 'C' ? 'business' :
                ($params['cabin_class'] === 'J' ? 'premium_business' :
                    ($params['cabin_class'] === 'F' ? 'first' :
                    ($params['cabin_class'] === 'P' ? 'premium_first' : 'unknown'))))),
            'trip_type' => $tripType === 'one-way' ? 'one_way' : ($tripType === 'multi-city' ? 'multi_city' : 'return'),
            'adults' => (int) $params['adults'],
            'children' => (int) $params['children'],
            'infants' => (int) $params['infants'],
        ];

        

        $body = json_encode($body);
        Log::info($body);
        try {
            $request = new Request('GET', "$this->apiUrl/airline/availability", $headers, $body);
            $response = $this->client->send($request); // Synchronous for simplicity
            $responseBody = json_decode($response->getBody(), true);
            Log::info('Sooper API Providers Response: ', $responseBody);
            // Cache providers for 1 hour
            // Cache::put($cacheKey, $responseBody, now()->addHour());

            return $responseBody;
        } catch (RequestException $e) {
            Log::error('Sooper API Providers Error: ', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Search Flights with Concurrent Requests
     */

    public function searchFlights($params)
    {
        Log::info('Search Flights Input Params: ', $params);


        // Convert params to array if string
        if (is_string($params)) {
            $params = json_decode($params, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON in params: ', ['error' => json_last_error_msg()]);
                return [];
            }
        }

        $allResults = [];
        $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
        $currencyCode = Cache::get($cacheKeyPrefix . '_currency_code', 'PKR'); // Default to PKR

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => '*/*',
            'X-SF-Authorization' => $this->getAccessToken(),
            'X-SF-Client-Currency' => $currencyCode,
        ];



        // Prepare request body
        $bodyArray = [
            'provider' => $params['airline'] ?? null,
            'flexible_dates' => false,
            'trip_type' => $params['flight_type'] === 'one-way' ? 'one_way' : ($params['flight_type'] === 'multi-city' ? 'multi_city' : 'return'),
            'cabin_class' => $params['cabin_class'] === 'Y' ? 'economy' :
                ($params['cabin_class'] === 'S' ? 'premium_economy' :
                    ($params['cabin_class'] === 'C' ? 'business' :
                        ($params['cabin_class'] === 'J' ? 'premium_business' :
                            ($params['cabin_class'] === 'F' ? 'first' :
                                ($params['cabin_class'] === 'P' ? 'premium_first' : 'unknown'))))),
            'legs' => [
                [
                    'from' => $params['origin'] ?? null,
                    'to' => $params['destination'] ?? null,
                    'date' => $params['departure_date'] ?? null,
                ],
            ],
            'travelers' => [
                ['type' => 'adult', 'qty' => (int) ($params['adults'] ?? 1)],
                ['type' => 'child', 'qty' => (int) ($params['children'] ?? 0)],
                ['type' => 'infant', 'qty' => (int) ($params['infants'] ?? 0)],
            ],
        ];

        // Add return leg for return trips
        if ($params['flight_type'] === 'return' && !empty($params['return_date'])) {
            $bodyArray['legs'][] = [
            'from' => $params['destination'],
            'to' => $params['origin'],
            'date' => $params['return_date'],
            ];
        } elseif ($params['flight_type'] === 'multi-city' && !empty($params['trips']) && is_array($params['trips'])) {
            // Remove the default leg if present
            $bodyArray['legs'] = [];
            foreach ($params['trips'] as $trip) {
            $bodyArray['legs'][] = [
                'from' => $trip['origin'],
                'to' => $trip['destination'],
                'date' => $trip['date'],
            ];
            }
        }

        // Validate required parameters
        if (empty($bodyArray['provider']) || empty($bodyArray['legs'][0]['from']) || empty($bodyArray['legs'][0]['to']) || empty($bodyArray['legs'][0]['date'])) {
            //Log::error('Missing required parameters', ['body' => $bodyArray]);
            return [];
        }

        $body = json_encode($bodyArray);
        if ($body === false) {
            Log::error('Failed to encode request body to JSON', ['bodyArray' => $bodyArray]);
            return [];
        }
// return;
        // Create and send request
        $request = new Request('POST', "{$this->apiUrl}/airline/search", $headers, $body);

        try {
            $response = $this->client->send($request);
            $responseBody = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $allResults[$params['airline']] = $responseBody;
        } catch (RequestException $e) {
            Log::error('Sooper API Flight Search Error: ', [
                'provider' => $params['airline'],
                'error' => $e->getMessage(),
            ]);
            $allResults[$params['airline']] = null;
        }

        return $allResults;
    }
    //     public function searchFlights($params)
//     {
//         Log::info('Sooper API Search Flights Params: ', $params);
//         if (is_string($params)) {
//             $params = json_decode($params, true);
//         }

    //         $providersData = $this->getProviders($params);
//         $providers = collect($providersData['data']['providers'] ?? [])->pluck('identifier');
//         $allResults = [];

    //         if (empty($providers)) {
//             return $allResults;
//         }

    //         $headers = [
//             'Content-Type' => 'application/json',
//             'Accept' => '*/*',
//             'X-SF-Authorization' => $this->getAccessToken(),
//             'X-SF-Client-Currency' => 'AED'
//         ];

    //         Log::info('Sooper API Search Flights Params: ', [
//             'X-SF-Authorization' => $this->getAccessToken(),
//         ]);


    //         // Create request generator for Pool
//         $requests = function () use ($providers, $params, $headers) {
//             foreach ($providers as $provider) {
//                 $bodyArray = [
//                     'provider' => $provider,
//                     'flexible_dates' => false,
//                     'trip_type' => $params['flight_type'] === 'one-way' ? 'one_way' : 'return',
// 'cabin_class' => $params['cabin_class'] === 'Y' ? 'economy' : 
//                  ($params['cabin_class'] === 'S' ? 'premium_economy' : 
//                  ($params['cabin_class'] === 'C' ? 'business' : 
//                  ($params['cabin_class'] === 'J' ? 'premium_business' : 
//                  ($params['cabin_class'] === 'F' ? 'first' : 
//                  ($params['cabin_class'] === 'P' ? 'premium_first' : 'unknown'))))),
//                     'legs' => [
//                         [
//                             'from' => $params['origin'],
//                             'to' => $params['destination'],
//                             'date' => $params['departure_date'],
//                         ],
//                     ],
//                     'travelers' => [
//                         ['type' => 'adult', 'qty' => (int) $params['adults']],
//                         ['type' => 'child', 'qty' => (int) $params['children']],
//                         ['type' => 'infant', 'qty' => (int) $params['infants']],
//                     ],
//                 ];

    //                 if ($params['flight_type'] === 'return' && !empty($params['return_date'])) {
//                     $bodyArray['legs'][] = [
//                         'from' => $params['destination'],
//                         'to' => $params['origin'],
//                         'date' => $params['return_date'],
//                     ];
//                 }

    //                 $body = json_encode($bodyArray);
//                 yield new Request('POST', "{$this->apiUrl}/airline/search", $headers, $body);
//             }
//         };

    //         // Configure Guzzle Pool
//         $pool = new Pool($this->client, $requests(), [
//             'concurrency' => 5, // Adjust based on API rate limits
//             'fulfilled' => function ($response, $index) use (&$allResults, $providers) {
//                 $provider = $providers[$index];
//                 $responseBody = json_decode($response->getBody(), true, 512);
//                 $allResults[$provider] = $responseBody;
//             },
//             'rejected' => function ($reason, $index) use (&$allResults, $providers) {
//                 $provider = $providers[$index];
//                 Log::error("Sooper API Flight Search Error for provider {$provider}: ", [
//                     'error' => $reason->getMessage(),
//                 ]);
//                 $allResults[$provider] = null;
//             },
//         ]);

    //         // Execute all requests concurrently
//         $promise = $pool->promise();
//         $promise->wait();

    //         return $allResults;
//     }

    public function sooperQuote($request, $passengers)
    {

        Log::info("Sooper Quote Request: " . $request);
        $accessToken = $this->getAccessToken();
        $body = [
            'ref_id' => $request['ref_id'],
            'legs' => []
        ];

        foreach ($request['legs'] as $leg) {
            $body['legs'][] = [
                'ref_id' => $leg['ref_id'],
                'flight' => [
                    'ref_id' => $leg['flight']['ref_id'],
                    'fare' => [
                        'ref_id' => $leg['flight']['fare']['ref_id']
                    ]
                ]
            ];
        }
        $jsonBody = json_encode($body);
$cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
        $currencyCode = Cache::get($cacheKeyPrefix . '_currency_code', 'PKR'); // Default to PKR

        $request = new Request('POST', "$this->apiUrl/airline/quote", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SF-Authorization' => $this->getAccessToken(),
            'X-SF-Client-Currency' => $currencyCode,
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            // Log::info("Sooper Quote Response: " . $res->getBody());
            // $reservation = $this->sooperReservation(json_decode($res->getBody(), true, 512), $passengers);
            return $res->getBody();
        } catch (\Exception $e) {
            Log::error("Error creating PNR: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function fetchAncillaries($request)
    {
        // Log::info("Sooper Ancillaries Request: " . json_encode($request, JSON_PRETTY_PRINT));

        $accessToken = $this->getAccessToken();
        $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
        $currencyCode = Cache::get($cacheKeyPrefix . '_currency_code'); // Default to PKR

        $jsonBody = json_encode([
            'ref_id' => $request['ref_id'],
        ]);

        Log::info($currencyCode);

        // Log::info("Sooper Ancillaries Request: " . $jsonBody);

        $request = new Request('POST', "$this->apiUrl/airline/ancillary", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SF-Authorization' => $accessToken,
            'X-SF-Client-Currency' => $currencyCode,
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            return json_decode($res->getBody(), true, 512);
        } catch (\Exception $e) {
            Log::error("Error fetching ancillaries: " . $e->getMessage());
            return null;
        }
    }

public function patchAncillaries($request)
{
    Log::info($request);

    $input = $request;

    $output = [
        'ref_id' => $input['ref_id'] ?? '5449b3b3-3d65-4cfc-a723-8957673df137',
        'ancillaries' => [
            'segments' => []
        ]
    ];

    // Temporary map to merge data by segment_ref_id + passenger_ref_id
    $segmentsMap = [];

    foreach ($input['extraCharges'] ?? [] as $chargeGroup) {
        // ---- Baggage ----
        foreach ($chargeGroup['baggage'] ?? [] as $baggageGroup) {
            foreach ($baggageGroup as $baggage) {
                $segId = $baggage['segment_ref_id'];
                $paxId = $baggage['passenger_ref_id'];

                if (!isset($segmentsMap[$segId][$paxId])) {
                    $segmentsMap[$segId][$paxId] = [
                        'ref_id' => $segId,
                        'passengers' => [
                            [
                                'ref_id' => $paxId,
                                'baggages' => [],
                                'seats' => [],
                                'meals' => [],
                                'ssrs' => [],
                            ]
                        ]
                    ];
                }

                $segmentsMap[$segId][$paxId]['passengers'][0]['baggages'][] = [
                    'ref_id' => $baggage['ref_id'],
                    'qty' => $baggage['qty'] ?? 1,
                ];
            }
        }

        // ---- Seats ----
        foreach ($chargeGroup['seat'] ?? [] as $seatGroup) {
            foreach ($seatGroup as $seat) {
                $segId = $seat['segment_ref_id'];
                $paxId = $seat['passenger_ref_id'];

                if (!isset($segmentsMap[$segId][$paxId])) {
                    $segmentsMap[$segId][$paxId] = [
                        'ref_id' => $segId,
                        'passengers' => [
                            [
                                'ref_id' => $paxId,
                                'baggages' => [],
                                'seats' => [],
                                'meals' => [],
                                'ssrs' => [],
                            ]
                        ]
                    ];
                }

                $segmentsMap[$segId][$paxId]['passengers'][0]['seats'][] = [
                    'ref_id' => $seat['ref_id'],
                    'qty' => $seat['qty'] ?? 1,
                ];
            }
        }

        // ---- Meals ----
        foreach ($chargeGroup['meal'] ?? [] as $mealGroup) {
            foreach ($mealGroup as $meal) {
                $segId = $meal['segment_ref_id'];
                $paxId = $meal['passenger_ref_id'];

                if (!isset($segmentsMap[$segId][$paxId])) {
                    $segmentsMap[$segId][$paxId] = [
                        'ref_id' => $segId,
                        'passengers' => [
                            [
                                'ref_id' => $paxId,
                                'baggages' => [],
                                'seats' => [],
                                'meals' => [],
                                'ssrs' => [],
                            ]
                        ]
                    ];
                }

                $segmentsMap[$segId][$paxId]['passengers'][0]['meals'][] = [
                    'ref_id' => $meal['ref_id'],
                    'qty' => $meal['qty'] ?? 1,
                ];
            }
        }
    }

    // Flatten segments map into final output
    foreach ($segmentsMap as $segId => $passengers) {
        foreach ($passengers as $paxId => $segmentData) {
            $output['ancillaries']['segments'][] = $segmentData;
        }
    }

    // Sort segments by ref_id for consistency
    usort($output['ancillaries']['segments'], function ($a, $b) {
        return strcmp($a['ref_id'], $b['ref_id']);
    });

    $accessToken = $this->getAccessToken();
    $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
    $currencyCode = Cache::get($cacheKeyPrefix . '_currency_code', 'PKR'); // Default PKR

    $requestBody = json_encode($output);
    Log::info('request body');
    Log::info($requestBody);

    $request = new Request('POST', "{$this->apiUrl}/airline/ancillary", [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'X-SF-Authorization' => $accessToken,
        'X-SF-Client-Currency' => $currencyCode,
    ], $requestBody);

    try {
        $res = $this->client->send($request);
        return json_decode($res->getBody(), true, 512);
    } catch (\Exception $e) {
        Log::error("Error sending ancillary request: " . $e->getMessage());
        return null;
    }
}


    public function sooperReservation($params, $passengers)
    {
        Log::info("Sooper Reservation Params: " . json_encode($params, JSON_PRETTY_PRINT));
        Log::info("Sooper Reservation Passengers: " . json_encode($passengers, JSON_PRETTY_PRINT));

        $ref_id = $params['ref_id'] ?? null;

        $dynamic_passengers = [];

        foreach ($passengers as $index => $passenger) {
            $input_passenger = $passengers[$index];

            $dynamic_passengers[] = [
                'ref_id' => $params['passengers'][$index]['ref_id'],
                'title' => strtolower($input_passenger['title']),
                'first_name' => strtolower($input_passenger['firstName']),
                'last_name' => strtolower($input_passenger['lastName']),
                'gender' => strtolower($input_passenger['gender'] == 'M' ? 'male' : 'female'),
                'dob' => strtolower($input_passenger['dob']),
                'nationality' => strtolower($input_passenger['nationality']),
                'nic' => null,
                'passport_number' => strtolower($input_passenger['documentNo']),
                'passport_country' => strtolower($input_passenger['issueCountry']),
                'passport_expiry' => date('d-m-Y', strtotime($input_passenger['expiryDate'])),
                'type' => strtolower(strtolower($input_passenger['type']))
            ];


        }
        Log::info("Sooper Reservation Dynamic Passengers: " . json_encode($dynamic_passengers, JSON_PRETTY_PRINT));

        $body = [
            'ref_id' => $ref_id,
            'is_ancillary' => true,
            'contact_info' => [
                'first_name' => 'air',
                'last_name' => 'byte',
                'email_address' => 'airbytetravel@gmail.com',
                'phone_number' => '+923267469874',
                'phone_number_country' => 'pk'
            ],
            'passengers' => $dynamic_passengers
        ];

        Log::info("Sooper Reservation Body: " . json_encode($body, JSON_PRETTY_PRINT));

        $jsonBody = json_encode($body);
        $request = new Request('POST', "$this->apiUrl/airline/reservation", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SF-Client-Currency' => 'AED',
            'X-SF-Authorization' => $this->getAccessToken(),

            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            Log::info("Sooper Reservation Response: " . $res->getBody());
            return $res->getBody();
        } catch (\Exception $e) {
            Log::error("Error creating PNR: " . $e->getMessage());
            return null;
        }
    }

    public function cancelSooperBooking($params)
    {
        Log::info("Sooper Cancel Booking Params: " . json_encode($params, JSON_PRETTY_PRINT));
        $accessToken = $this->getAccessToken();
        $jsonBody = json_encode([
            'booking_ref_id' => $params['booking_uuid'],
            'txn_ref_id' => $params['booking_uuid'],
            'amount' => $params['billable_price'],
            'currency' => $params['currency'],
            'remarks' => 'Test Booking Cancellation',
        ]);

        $request = new Request('POST', "$this->apiUrl/booking/cancel", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SF-Authorization' => $accessToken,
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            Log::info("Sooper Cancel Booking Response: " . $res->getBody());
            return json_decode($res->getBody(), true, 512);

        } catch (\Exception $e) {
            Log::error("Error canceling booking: " . $e->getMessage());
            return null;
        }

    }

    public function confrimSooperBooking($params)
    {
        $accessToken = $this->getAccessToken();
        $body = [
            'booking_ref_id' => $params->booking_uuid,
            'txn_ref_id' => $params->booking_uuid,
            'amount' => $params->amount,
            'currency' => 'AED',
            'remarks' => 'Test Booking Confirmation',
        ];

        $jsonBody = json_encode($body);
        Log::info("Sooper Booking Confirmation Request: " . $jsonBody);

        $request = new Request('POST', "$this->apiUrl/booking/confirm", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SF-Authorization' => $accessToken,
            'X-SF-Client-Currency' => 'AED',
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            Log::info("Sooper Booking Confirmation Response: " . $res->getBody());
            return json_decode($res->getBody(), true, 512);

        } catch (\Exception $e) {
            Log::error("Error confirming booking: " . $e->getMessage());
            return null;
        }

    }
    public function voidSooperBooking($params)
    {
        Log::info("Sooper Void Booking Params: " . json_encode($params, JSON_PRETTY_PRINT));
        $accessToken = $this->getAccessToken();
        $jsonBody = json_encode([
            'booking_ref_id' => $params['booking_uuid'],
            'txn_ref_id' => $params['booking_uuid'],
            'amount' => $params['billable_price'],
            'currency' => $params['currency'],
            'remarks' => 'Test Booking Cancellation',
        ]);

        $request = new Request('POST', "$this->apiUrl/booking/void", [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SF-Authorization' => $accessToken,
            'Conversation-ID' => uniqid('conv_' . true)
        ], $jsonBody);

        try {
            $res = $this->client->send($request);
            Log::info("Sooper Void Booking Response: " . $res->getBody());
            return json_decode($res->getBody(), true, 512);

        } catch (\Exception $e) {
            Log::error("Error voiding booking: " . $e->getMessage());
            return null;
        }

    }
}