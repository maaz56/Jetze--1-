<?php
namespace App\Services;
use App\Models\Airport;
use Cache;
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

        // 🔴 REAL API CODE
        $accessToken = $this->getAccessToken();
        Log::info($accessToken);

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token']
        ];

        $searchUrl = "$this->flightBaseUrl/flights/ExpressSearch";

        // Determine FareType based on flight type and trip details
        $fareType = $this->determineFareType($params);
        $this->cacheFareType($fareType);

        // Build trips array based on flight type
        $trips = $this->buildTripsArray($params);

        $payload = [
            'FareType' => $fareType,
            'ADT' => (int) ($params['adults'] ?? 1),
            'CHD' => (int) ($params['children'] ?? 0),
            'INF' => (int) ($params['infants'] ?? 0),
            'Cabin' => $this->mapCabinClass($params['cabin_class'] ?? 'Y'),
            'Source' => 'CF', // Changed from 'LV' to 'CF' for consistency
            'Mode' => 'AS',
            'ClientID' => $this->clientId,
            "MoreFltKey" => "",
            "ONFltNo" => "",
            "RTFltNo" => "",
            'IsMultipleCarrier' => false,
            'IsRefundable' => false,
            'preferedAirlines' => $params['preferedAirlines'] ?? [],
            'TUI' => $params['TUI'] ?? "",
            'SecType' => $params['SecType'] ?? 'I',
            'Trips' => $trips,
            'Parameters' => [
                'IsDirect' => $params['is_direct'] ?? false,
                'IsNearbyAirport' => $params['is_nearby_airport'] ?? true,
                'IsStudentFare' => $params['is_student_fare'] ?? false,
                "IsSeniorCitizen" => $params['is_senior_citizen'] ?? null,
                'Airlines' => $params['airlines'] ?? '',
                'GroupType' => $params['group_type'] ?? '',
                "IsGDSSearch" => $params['is_gds_search'] ?? true,
                "IsLCCSearch" => $params['is_lcc_search'] ?? true,
                'Refundable' => $params['refundable'] ?? '',
                'IsExtendedSearch' => $params['is_extended_search'] ?? false,
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

            if (isset($responseBody['Msg'][0]) && $responseBody['Msg'][0] === "Success") {
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

    /**
     * Determine FareType based on flight type and trip details
     */
    private function determineFareType(array $params): string
    {
        $flightType = $params['flightType'] ?? $params['flight_type'] ?? 'one-way';

        // If explicitly set to multi-city
        if ($flightType === 'multi-city') {
            // Check if it's international or domestic
            $trips = $params['trips'] ?? [];
            if (!empty($trips)) {
                $isInternational = $this->isInternationalMultiCity($trips);
                return $isInternational ? 'DM' : 'DM';
            }
            return 'IM'; // Default to International Multi-city
        }

        // Handle return flights
        if ($flightType === 'return') {
            return 'RS';
        }

        // Handle one-way flights
        return 'ON';
    }

    /**
     * Store the resolved fare type in cache for later use in the AT flow.
     */
    private function cacheFareType(string $fareType): void
    {
        $cacheKey = "AT_Fare_type";
        Cache::put($cacheKey, $fareType, now()->addHour());
        Log::info('Cached AT fare type: ' . $fareType, ['cache_key' => $cacheKey]);
    }

    /**
     * Build the same cache prefix used by the flight search controller.
     */
    private function getCacheKeyPrefix(): string
    {
        return auth()->id()
            ? 'flights_' . auth()->id()
            : 'flights_' . session()->getId();
    }

    /**
     * Check if multi-city trip is international
     */
    private function isInternationalMultiCity(array $trips): bool
    {
        // Get all unique airport codes from all trips
        $allAirports = [];
        foreach ($trips as $trip) {
            $allAirports[] = $trip['origin'];
            $allAirports[] = $trip['destination'];
        }
        $allAirports = array_unique($allAirports);

        if (empty($allAirports)) {
            return true; // Default to international if no airports
        }

        // Get Pakistani airports from cache
        $pakistaniAirports = $this->getPakistaniAirports();

        // Check each airport - if ANY is not Pakistani, it's international
        foreach ($allAirports as $airport) {
            $airport = strtoupper($airport);

            // If airport is NOT in Pakistani airports list, it's international
            if (!in_array($airport, $pakistaniAirports)) {
                return true; // International found
            }
        }

        // All airports are in Pakistan → domestic multi-city
        return false;
    }
    private function getPakistaniAirports(): array
    {
        // Cache for 24 hours to avoid repeated DB queries
        return Cache::remember('pakistani_airports', 86400, function () {
            return Airport::where('iata_country_code', 'PK')
                ->pluck('iata_code')
                ->map(fn($code) => strtoupper($code))
                ->toArray();
        });
    }


    /**
     * Build trips array based on flight type
     */
    private function buildTripsArray(array $params): array
    {
        $flightType = $params['flightType'] ?? $params['flight_type'] ?? 'one-way';
        $trips = [];

        // Handle multi-city flights
        if ($flightType === 'multi-city' && isset($params['trips']) && is_array($params['trips'])) {
            foreach ($params['trips'] as $trip) {
                $trips[] = [
                    'From' => $trip['origin'],
                    'To' => $trip['destination'],
                    'ReturnDate' => '', // Empty for multi-city
                    'OnwardDate' => $trip['date'],
                    'TUI' => $trip['TUI'] ?? $params['TUI'] ?? ''
                ];
            }
            return $trips;
        }

        // Handle return flights
        if ($flightType === 'return') {
            $trips[] = [
                'From' => $params['origin'],
                'To' => $params['destination'],
                'ReturnDate' => $params['return_date'] ?? '',
                'OnwardDate' => $params['departure_date'],
                'TUI' => $params['TUI'] ?? ''
            ];
            return $trips;
        }

        // Handle one-way flights
        $trips[] = [
            'From' => $params['origin'],
            'To' => $params['destination'],
            'ReturnDate' => '',
            'OnwardDate' => $params['departure_date'],
            'TUI' => $params['TUI'] ?? ''
        ];

        return $trips;
    }

    /**
     * Map cabin class to API expected format
     */
    private function mapCabinClass(string $cabinClass): string
    {
        $cabinMap = [
            'Y' => 'E',      // Economy
            'E' => 'E',      // Economy
            'Economy' => 'E',
            'economy' => 'E',
            'C' => 'B',      // Business
            'J' => 'B',      // Business
            'Business' => 'B',
            'business' => 'B',
            'F' => 'F',      // First
            'First' => 'F',
            'first' => 'F',
        ];

        return $cabinMap[$cabinClass] ?? 'E';
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
        $flightType = $request->input('flight_type', $request->input('flightType', 'one-way'));

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
        Log::info('Constructed trips for Price Request: ', $trips);
       $tripType = Cache::get('AT_Fare_type', 'ON'); // Default to 'ON' if not set
        $payload = [
            'Trips' => $trips,
            'ClientID' => $accessToken['ClientID'], // you said you'll add this
            'Mode' => 'AS',
            'Options' => 'A',
            'Source' => 'SF',
            'TripType' => $tripType,
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
            $response = json_encode($responseBody);
            Log::info('AT Booking Response:' . $response);

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
  public function fetchAncillaries($request)
    {
        Log::info('Fetching ancillaries with data: ', $request);
        $ssrData = $this->getSSR($request);
        $seatLayout = $this->getSeatLayout($request);
        $data = [
            'ssrData' => $ssrData,
            'seatLayout' => $seatLayout,
        ];
        Log::info('Fetched ancillaries data: ', $data);
        return [
            'data' => $data
        ];


    }
    public function getSSR($request)
    {
        Log::info('Getting SSR with data: ', $request);
        $accessToken = $this->getAccessToken();
        Log::info($accessToken);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];

        $priceRequestUrl = "{$this->flightBaseUrl}/Flights/SSR";

        /*
        |--------------------------------------------------------------------------
        | BUILD SUPPLIER PAYLOAD
        |--------------------------------------------------------------------------
        */

        $tui = $request['ref_id']; // main ref_id
        $legs = $request['legs'] ?? [];

        $trips = [];

        foreach ($legs as $index => $leg) {
            if (!isset($leg['selectedFare']['billable_price'])) {
                continue;
            }

            $trips[] = [
                'Amount' => 0,
                'Index' => "", // 1, 2, 3...
                'OrderID' => $index + 1, // keep static or change if needed
                'TUI' => $tui,
            ];
        }

        $payload = [
            'Trips' => $trips,
            'ClientID' => $accessToken['ClientID'], // you said you'll add this
            'PaidSSR' => 'true',
            'Source' => 'LV',
            'TripType' => count($trips) > 1 ? 'RS' : 'ON',
        ];

        Log::info('SSR request payload (final): ' . json_encode($payload, JSON_PRETTY_PRINT));

        try {
            $req = new \GuzzleHttp\Psr7\Request(
                'POST',
                $priceRequestUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($req);
            $responseBody = json_decode($response->getBody(), true);

            Log::info('SSR request payload response: ' . json_encode($responseBody, JSON_PRETTY_PRINT));
            return $responseBody;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error sending SSR request: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }

            return null;
        }
    }
    public function getSeatLayout($request)
    {
        Log::info('Getting Seat Layout with data: ', $request);
        $accessToken = $this->getAccessToken();
        Log::info($accessToken);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];

        $priceRequestUrl = "{$this->flightBaseUrl}/Flights/SeatLayout";

        /*
        |--------------------------------------------------------------------------
        | BUILD SUPPLIER PAYLOAD
        |--------------------------------------------------------------------------
        */

        $tui = $request['ref_id']; // main ref_id
        $legs = $request['legs'] ?? [];

        $trips = [];

        foreach ($legs as $index => $leg) {
            if (!isset($leg['selectedFare']['billable_price'])) {
                continue;
            }

            $trips[] = [
                'Amount' => 0,
                'Index' => "", // 1, 2, 3...
                'OrderID' => $index + 1, // keep static or change if needed
                'TUI' => $tui,
            ];
        }

        $payload = [
            'Trips' => $trips,
            'ClientID' => $accessToken['ClientID'], // you said you'll add this
            'PaidSSR' => 'true',
            'Source' => 'LV',
            'TripType' => count($trips) > 1 ? 'RS' : 'ON',
        ];

        Log::info('SSR request payload (final): ', $payload);

        try {
            $req = new \GuzzleHttp\Psr7\Request(
                'POST',
                $priceRequestUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($req);
            $responseBody = json_decode($response->getBody(), true);

            Log::info($responseBody);
            return $responseBody;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error sending SSR request: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }

            return null;
        }
    }

     public function atPaymentRequest($params)
    {

        $accessToken = $this->getAccessToken();
        $clientId = $accessToken['ClientID'] ?? null;
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];

        $paymentUrl = "{$this->flightBaseUrl}/Payment/StartPay";

        /*
        |--------------------------------------------------------------------------
        | BUILD PAYMENT PAYLOAD
        |--------------------------------------------------------------------------
        */
        $payload = [
            'TransactionID' => (int) $params['TransactionID'],
            'PaymentAmount' => 0,
            'NetAmount' => (int) $params['net_amount'],
            'BrowserKey' => $this->browserKey,
            'ClientID' => $clientId,
            'TUI' => $params['TUI'],
            'Hold' => false,
            'Promo' => null,
            'PaymentType' => '',
            'BankCode' => '',
            'GateWayCode' => '',
            'MerchantID' => '',
            'PaymentCharge' => 0,
            'ReleaseDate' => '',
            'OnlinePayment' => false,
            'DepositPayment' => true,

            'Card' => [
                'Number' => '',
                'Expiry' => '',
                'CVV' => '',
                'CHName' => '',
                'Address' => '',
                'City' => '',
                'State' => '',
                'Country' => '',
                'PIN' => '',
                'International' => false,
                'SaveCard' => false,
                'FName' => '',
                'LName' => '',
                'EMIMonths' => '0',
            ],

            'VPA' => '',
            'CardAlias' => '',
            'QuickPay' => null,
            'RMSSignature' => '',
            'TargetCurrency' => '',
            'TargetAmount' => 0,
            'ServiceType' => 'ITI',
            'BookingType' => 'HB', // HB = Hold Booking
        ];

        Log::info('AT Payment Payload (final):', $payload);

        try {
            $request = new \GuzzleHttp\Psr7\Request(
                'POST',
                $paymentUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($request);
            $responseBody = $response->getBody();
            $responseBodyString = (string) $responseBody;
            $responseData = json_decode($responseBodyString, true);

            Log::info('AT Payment Response:' . $responseBodyString);

            $message = $responseData['Msg'][0] ?? null;
            if (in_array($message, ['Payment Failed !', 'Payment Failed'], true)) {
                Log::error('AT Payment failed with API message', [
                    'message' => $message,
                    'response' => $responseData,
                ]);

                return json_encode([
                    'status' => false,
                    'message' => $message,
                    'error' => $message,
                ]);
            }

            return $responseBodyString;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('AT Payment Error: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('AT Payment Response:', [
                    'body' => (string) $e->getResponse()->getBody()
                ]);
            }

            return null;
        }
    }

}
