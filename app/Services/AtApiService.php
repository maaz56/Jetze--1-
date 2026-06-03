<?php
namespace App\Services;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;



class AtApiService
{
    protected $signBaseUrl;
    protected $flightBaseUrl;
    protected $merchantId;
    protected $apiKey;
    protected $clientId;
    protected $client;
    protected $password;
    protected $agentCode;
    protected $browserKey;
    private bool $useMockApi = false;


    public function __construct()
    {

        $this->client = new Client();
        $this->signBaseUrl = config('at.sign_base_url');
        $this->flightBaseUrl = config('at.flight_base_url');
        $this->merchantId = config('at.merchant_id');
        $this->apiKey = config('at.api_key');
        $this->clientId = config('at.client_id');
        $this->password = config('at.password');
        $this->agentCode = config('at.agent_code');
        $this->browserKey = config('at.browser_key');
    }



    protected function getAccessToken()
    {

        $headers = [
            'Content-Type' => 'application/json',
        ];
        $signBaseUrl = "$this->signBaseUrl/Utils/Signature";
        $signaturePayload = [
            'MerchantID' => $this->merchantId,
            'ApiKey' => $this->apiKey,
            'ClientID' => $this->clientId,
            'Password' => $this->password,
            'AgentCode' => $this->agentCode,
            'BrowserKey' => $this->browserKey,
        ];
        // Example method to get an access token from the AT API
        $response = new Request(
            'POST',
            $signBaseUrl,
            $headers,
            json_encode($signaturePayload)
        );

        $response = $this->client->send($response);
        $response = json_decode($response->getBody(), true);

        return $response;
    }

    public function searchFlights($params)
    {
        Log::info('Searching flights with params: ', $params);

        // ✅ MOCK MODE
        if ($this->useMockApi) {
            Log::warning('Flight API is OFF. Using MOCK search response.');

            // simulate real response structure
            $mockResponse = [
                'Msg' => ['Success'],
                'TUI' => 'MOCK_TUI_123456'
            ];


            // still call result function (important for flow)

            return $this->getSearchFlightsRes($mockResponse['TUI']);

        }

        // 🔴 REAL API CODE (UNCHANGED)
        $accessToken = $this->getAccessToken();
        Log::info($accessToken);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token']
        ];

        $searchUrl = "$this->flightBaseUrl/flights/ExpressSearch";
        $fareType = $params['flight_type'] === 'return' ? 'RS' : 'ON';

        $payload = [
            'FareType' => $fareType,
            'ADT' => $params['adults'] ?? 1,
            'CHD' => $params['children'] ?? 0,
            'INF' => $params['infants'] ?? 0,
            'Cabin' => 'E',
            'Source' => 'LV',
            'Mode' => 'AS',
            'ClientID' => $this->clientId,
            "MoreFltKey" => "",
            "ONFltNo" => "",
            "RTFltNo" => "",
            'IsMultipleCarrier' => false,
            'IsRefundable' => false,
            'preferedAirlines' => [],
            'TUI' => "",
            'SecType' => 'I',
            'Trips' => [
                [
                    'From' => $params['origin'],
                    'To' => $params['destination'],
                    'ReturnDate' => $params['flight_type'] === 'return' ? $params['return_date'] : '',
                    'OnwardDate' => $params['departure_date'],

                ],
            ],
            'Parameters' => [
                'IsDirect' => false,
                'IsNearbyAirport' => false,
                'IsStudentFare' => false,
                "IsSeniorCitizen" => null,
                'Airlines' => '',
                'GroupType' => '',
                "IsGDSSearch" => true,
                "IsLCCSearch" => true,
                'Refundable' => '',
                'IsExtendedSearch' => false,

            ],
        ];
        try {
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($payload)
            );

            Log::info('Flight search request payload: ', $payload);

            $response = $this->client->send($request);
            $responseBody = json_decode($response->getBody(), true);
            Log::info('Flight search response: ', $responseBody);
            if ($responseBody['Msg'][0] === "Success") {
                // $this->getWebSettings($responseBody['TUI']);
                return $this->getSearchFlightsRes($responseBody['TUI']);
            }

            Log::warning('Flight search returned non-success message');
            return null;

        } catch (RequestException $e) {
            Log::error('Error searching flights: ' . $e->getMessage());
            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }
            return null;
        }
    }


    public function getSearchFlightsRes($tui)
    {
        // ✅ MOCK MODE
        if ($this->useMockApi) {
            Log::warning('Using MOCK GetExpSearch response.');

            // stored JSON file (already using 👍)
            return json_decode(Storage::get('ATResponse.json'), true);
        }

        // 🔴 REAL API CODE (UNCHANGED)
        $accessToken = $this->getAccessToken();
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token']
        ];

        $searchResUrl = "$this->flightBaseUrl/flights/GetExpSearch";
        $payload = [
            'ClientID' => $this->clientId,
            'TUI' => $tui,
        ];

        try {





            // =========================
            // LIVE API MODE
            // =========================

            $request = new Request(
                'POST',
                $searchResUrl,
                $headers,
                json_encode($payload)
            );

            Log::info('GetExpSearch request payload: ', $payload);

            $response = $this->client->send($request);

            $body = json_decode($response->getBody(), true);
            $isComplete = strtolower($body['Completed'] ?? 'false');

            // 🔁 Retry until completed
            while ($isComplete !== 'true') {

                Log::info('Search not ready. Retrying GetExpSearch… Status: ' . $isComplete);

                sleep(2);

                $response = $this->client->send($request);
                $body = json_decode($response->getBody(), true);
                $isComplete = strtolower($body['Completed'] ?? 'false');
            }

            return $body;

        } catch (RequestException $e) {

            Log::error('Error getting search flights result: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }

            return null;
        }

    }
    public function getWebSettings($tui)
    {

        $guzzleClient = new Client();
        $response = $guzzleClient->post($this->signBaseUrl . '/Utils/WebSettings', [
            'json' => [
                'ClientID' => $this->clientId,
                'TUI' => $tui,
            ],
        ]);
        $responseBody = json_decode($response->getBody()->getContents(), true);
        Log::info('GetWebSettings response: ', $responseBody);



    }

    public function sendPriceRequest($request)
    {
        Log::info('Sending Price Request with data: ', $request->all());

        $accessToken = $this->getAccessToken();

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];

        $priceRequestUrl = "{$this->flightBaseUrl}/Flights/SmartPricer";

        /*
        |--------------------------------------------------------------------------
        | BUILD SUPPLIER PAYLOAD
        |--------------------------------------------------------------------------
        */

        $tui = $request->input('ref_id'); // main ref_id
        $legs = $request->input('legs', []);

        $trips = [];

        foreach ($legs as $index => $leg) {
            if (!isset($leg['selectedFare']['billable_price'])) {
                continue;
            }

            $trips[] = [
                'Amount' => (float) $leg['selectedFare']['billable_price'],
                'Index' => $leg['selectedFare']['index'], // 1, 2, 3...
                'OrderID' => $index + 1, // keep static or change if needed
                'TUI' => $tui,
            ];
        }

        $payload = [
            'Trips' => $trips,
            'ClientID' => $accessToken['ClientID'], // you said you'll add this
            'Mode' => 'AS',
            'Options' => 'A',
            'Source' => 'SF',
            'TripType' => count($trips) > 1 ? 'RS' : 'ON',
        ];

        Log::info('Price Request payload (final): ', $payload);

        try {
            $req = new \GuzzleHttp\Psr7\Request(
                'POST',
                $priceRequestUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($req);
            $responseBody = json_decode($response->getBody(), true);

            Log::info('Price Request response: ', $responseBody);
            $response = $this->getPricer($responseBody);
            return $response;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error sending Price Request: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }

            return null;
        }
    }

    public function getPricer($request)
    {
        $accessToken = $this->getAccessToken();
        Log::info($accessToken);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];

        $priceRequestUrl = "{$this->flightBaseUrl}/Flights/GetSPricer";




        $payload = [
            'TUI' => $request['TUI'],

        ];

        Log::info('Get Price payload (final): ', $payload);

        try {
            $req = new \GuzzleHttp\Psr7\Request(
                'POST',
                $priceRequestUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($req);
            $responseBody = json_decode($response->getBody(), true);

            Log::info('Get Price response: ', $responseBody);
            if (!isset($responseBody['TUI']) && !$responseBody['TUI']) {
                Log::warning('Price request did not return TUI. Response: ');
                return null;
            }
            $this->travelerCheckList($responseBody);
            return $responseBody;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error sending Price Request: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }

            return null;
        }
    }
    public function travelerCheckList($tui)
    {


        Log::info('Getting Traveler Checklist with TUI: ', $tui);
        $accessToken = $this->getAccessToken();

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];
        Log::info('Getting Traveler Checklist Payload: ' . $tui['TUI']);
        $guzzleClient = new Client();
        $response = $guzzleClient->post($this->flightBaseUrl . '/Utils/GetTravelCheckList', [
            'headers' => $headers,
            'body' => json_encode([
                'ClientID' => $this->clientId,
                'TUI' => $tui['TUI'],
            ]),
        ]);
        $responseBody = json_decode($response->getBody()->getContents(), true);
        Log::info('Traveler Checklist response: ', $responseBody);
    }

    public function atBooking($params)
    {
        $accessToken = $this->getAccessToken();
        $clientId = $accessToken['ClientID'] ?? null;
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];

        $bookingUrl = "{$this->flightBaseUrl}/Flights/CreateItinerary";

        /*
        |--------------------------------------------------------------------------
        | CONTACT INFO
        |--------------------------------------------------------------------------
        */
        $contact = $params['main_contact'] ?? [];
        $firstTraveller = $params['travellers'][0] ?? [];

        $contactInfo = [
            'Title' => $firstTraveller['title'] ?? 'Mr',
            'FName' => $firstTraveller['firstName'] ?? 'Guest',
            'LName' => $firstTraveller['lastName'] ?? 'User',
            'Mobile' => str_replace(' ', '', $contact['phone']) ?? '',
            'Phone' => '',
            'Email' => $contact['email'] ?? '',
            'Address' => 'N/A',
            'CountryCode' => 'PK',
            'State' => '',
            'City' => '',
            'PIN' => substr($contact['phone'], -6),
            'GSTCompanyName' => '',
            'GSTTIN' => '',
            'GSTMobile' => '',
            'GSTEmail' => '',
            'UpdateProfile' => false,
            'IsGuest' => false,
        ];

        /*
        |--------------------------------------------------------------------------
        | TRAVELLERS
        |--------------------------------------------------------------------------
        */
        $travellers = [];

        foreach ($params['travellers'] as $i => $traveller) {

            $dob = $traveller['dob'];
            $age = \Carbon\Carbon::parse($dob)->age;

            $travellers[] = [
                'ID' => $i + 1,
                'Title' => $traveller['title'],
                'FName' => $traveller['firstName'],
                'LName' => $traveller['lastName'],
                'Age' => $age,
                'DOB' => $dob,
                'Gender' => strtoupper($traveller['gender']),
                'PTC' => ($traveller['type'] == 'ADT') ? 'ADT' : (($traveller['type'] == 'CNN') ? 'CHD' : 'INF'), // ADT
                'Nationality' => strtoupper($traveller['nationality']),
                'PassportNo' => $traveller['documentNo'],
                'PLI' => strtoupper($traveller['issueCountry']),
                'PDOE' => $traveller['expiryDate'],
                'VisaType' => 'Visiting Visa',
            ];
        }

        $SSR = [];
        $totalAmount = 0;

        $selectedExtras = $params['selectedExtras'] ?? [];

        if (is_array($selectedExtras)) {
            foreach ($selectedExtras as $flightExtras) {
                if (!is_array($flightExtras))
                    continue;

                foreach ($flightExtras as $group => $segments) { // baggage / seat / meal
                    if (!is_array($segments))
                        continue;

                    foreach ($segments as $segment) {
                        if (!is_array($segment))
                            continue;

                        foreach ($segment as $pax) {
                            if (!is_array($pax))
                                continue;

                            foreach ($pax as $item) {
                                if (
                                    !is_array($item) ||
                                    !isset($item['FUID'], $item['PaxID'], $item['SSID'])
                                ) {
                                    continue;
                                }

                                // Calculate amount
                                $price = $item['Charge']
                                    ?? $item['SSRNetAmount']
                                    ?? 0;

                                $totalAmount += (float) $price;

                                // Build SSR payload
                                $SSR[] = [
                                    'FUID' => (int) $item['FUID'],
                                    'PaxID' => (int) $item['PaxID'],
                                    'SSID' => (int) $item['SSID'],
                                ];
                            }
                        }
                    }
                }
            }
        }



        /*
        |--------------------------------------------------------------------------
        | FINAL PAYLOAD
        |--------------------------------------------------------------------------
        */
        $payload = [
            'TUI' => $params['TUI'],
            'ContactInfo' => $contactInfo,
            'Travellers' => $travellers,
            'PLP' => [],
            'SSR' => $SSR,
            'CrossSell' => [],
            'NetAmount' => $params['NetAmount'],
            'SSRAmount' => $totalAmount,
            'ClientID' => $clientId,
            'DeviceID' => '',
            'AppVersion' => '',
            'CrossSellAmount' => 0,
        ];

        Log::info('AT Booking Payload (final):', $payload);

        try {
            $request = new \GuzzleHttp\Psr7\Request(
                'POST',
                $bookingUrl,
                $headers,
                json_encode($payload)
            );

            $responseBody = $this->client->send($request);
            $responseBody = json_decode($responseBody->getBody(), true);
            $responseBody = json_encode($responseBody);
            Log::info('AT Booking Response:' . $responseBody);

            return $responseBody;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('AT Booking Error: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('AT Booking Response:', [
                    'body' => (string) $e->getResponse()->getBody()
                ]);
            }

            return null;
        }
    }

}