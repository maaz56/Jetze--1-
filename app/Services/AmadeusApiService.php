<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmadeusApiService
{
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;
    protected $apiVersion;
    protected $accessToken;
    protected $httpClient;

    public function __construct()
    {
        $this->apiKey = config('services.amadeus.key');
        $this->apiSecret = config('services.amadeus.secret');
        $this->baseUrl = config('services.amadeus.url');
        $this->apiVersion = config('services.amadeus.version');

        // Initialize Guzzle HTTP client
        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10, // Adjust as per your needs
            'verify' => false, // Set to true if you have a valid CA certificate bundle
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        // Get access token on instantiation
        $this->accessToken = $this->getAccessToken();
    }

    private function getAccessToken()
    {
        try {
            $response = $this->httpClient->post('/v1/security/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->apiKey,
                    'client_secret' => $this->apiSecret,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['access_token'];
        } catch (RequestException $e) {
            Log::error('Error fetching access token from Amadeus: ' . $e->getMessage());
            return null;
        }
    }

    public function searchFlights($params)
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/shopping/flight-offers";

        $requestData = [
            "currencyCode" => "USD",
            "originDestinations" => [
                [
                    "id" => "1",
                    "originLocationCode" => $params['origin'],
                    "destinationLocationCode" => $params['destination'],
                    "departureDateTimeRange" => [
                        "date" => $params['departure_date'],
                    ]
                ]
            ],
            "travellers" => [
                [
                    "id" => "1",
                    "travellerType" => "ADULT"
                ]
            ],
            "sources" => ["GDS"],
            "searchCriteria" => [
                "flightFilters" => [
                    "cabinRestrictions" => [
                        [
                            "cabin" => $params['class_type'] ?? 'BUSINESS',
                            "coverage" => "MOST_SEGMENTS",
                            "originDestinationIds" => ["1"]
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
        ])->post($url, $requestData);

        return $response->json();
    }

    public function getFlightById($flightId)
    {
        $url = "{$this->baseUrl}/{$this->apiVersion}/shopping/flight-offers";

        $flights = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->accessToken,
            'Accept' => 'application/json',
        ])->post($url);

        dd($flights->body())    ;

        if ($flights->successful()) {
            foreach ($flights['data'] as $flight) {
                if ($flight['id'] === $flightId) {
                    return $flight;
                }
            }
        }
    }
}
