<?php
namespace App\Services;
use App\Transformers\AtFlightTransformer;
use Cache;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;



class AtApiService
{
    private const DM_VALIDATION_CACHE_PREFIX = 'AT_DM_VALIDATION_';

    protected $signBaseUrl;
    protected $flightBaseUrl;
    protected $merchantId;
    protected $apiKey;
    protected $clientId;
    protected $client;
    protected $password;
    protected $agentCode;
    protected $browserKey;
    protected $atFlightTransformer;
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
        $this->atFlightTransformer = new AtFlightTransformer();
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
        $fareType = $this->atFlightTransformer->determineFareType($params);
        $this->cacheFareType($fareType);

        // Build trips array based on flight type and resolved FareType
        $trips = $this->buildTripsArray($params, $fareType);

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

        if ($fareType === 'DM' && !empty($trips)) {
            return $this->searchTripsOneByOne($payload, $trips, $headers, $searchUrl);
        }

        try {
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($payload)
            );

            Log::info('Express Search request payload: ', $payload);

            $response = $this->client->send($request);
            $responseBody = json_decode($response->getBody(), true);
            Log::info('Express search response: ', $responseBody);



            if (isset($responseBody['Msg'][0]) && $responseBody['Msg'][0] === "Success") {
                $this->getWebSettings($responseBody['TUI']);

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
     * Store the resolved fare type in cache for later use in the AT flow.
     */
    private function cacheFareType(string $fareType): void
    {
        $cacheKey = $this->getFareTypeCacheKey();
        Cache::put($cacheKey, $fareType, now()->addHour());
        Log::info('Cached AT fare type: ' . $fareType, ['cache_key' => $cacheKey]);
    }

    private function getFareTypeCacheKey(): string
    {
        return $this->getCacheKeyPrefix() . '_at_fare_type';
    }

    private function resolveFareType(array $params = []): string
    {
        return strtoupper((string) (
            $params['fareType']
            ?? $params['FareType']
            ?? $params['fare_type']
            ?? Cache::get($this->getFareTypeCacheKey(), 'ON')
        ));
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
     * Build trips array based on flight type
     */
    private function buildTripsArray(array $params, string $fareType): array
    {
        $flightType = $params['flightType'] ?? $params['flight_type'] ?? 'one-way';
        $trips = [];

        if ($fareType === 'DM' && isset($params['trips']) && is_array($params['trips'])) {
            return $this->buildMultiCityTrips($params);
        }

        if ($flightType === 'multi-city' && isset($params['trips']) && is_array($params['trips'])) {
            return $this->buildMultiCityTrips($params);
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
            Log::info('GetExpSearch completed successfully. Response: ', $body);
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
        Log::info('getwebSettinfs Request:' . json_encode([
            'ClientID' => $this->clientId,
            'TUI' => $tui,
        ], JSON_PRETTY_PRINT));
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

        $tripType = $this->resolveFareType($request->all());

        if ($tripType === 'DM') {
            return $this->sendDmMultiCityPriceRequests($request, $accessToken, $headers, $priceRequestUrl, $tripType);
        }

        $tui = $request->input('ref_id'); // main ref_id
        if (is_array($tui)) {
            $tui = $tui[0] ?? '';
        }
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
        Log::info('Constructed trips for Price Request: ', $trips);
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
        // Log::info($accessToken);
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


        // Log::info('Getting Traveler Checklist with TUI: ', $tui);
        $accessToken = $this->getAccessToken();

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];
        Log::info('Getting Traveler Checklist Payload: ' . json_encode(
            [
                'ClientID' => $this->clientId,
                'TUI' => $tui['TUI'],
            ],
            JSON_PRETTY_PRINT
        ));

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
        $cachedValidationResponse = $this->getCachedDmValidationResponse($params['TUI'] ?? null);
        $fareType = strtoupper((string) (
            $params['fareType']
            ?? $params['FareType']
            ?? $params['fare_type']
            ?? $cachedValidationResponse['FareType']
            ?? ''
        ));
        $holdInfo = $params['flight']['leg']['flights'][0]['hold_info'] ?? null;
        $bookingTui = $this->resolveActualTui($params['TUI'] ?? '', $cachedValidationResponse);
        $netAmount = $cachedValidationResponse['NetAmount'] ?? $params['NetAmount'] ?? 0;

        /*
        |--------------------------------------------------------------------------
        | CONTACT INFO
        |--------------------------------------------------------------------------
        */
        $contact = $params['main_contact'] ?? [];
        $firstTraveller = $params['travellers'][0] ?? [];
        $mobile = preg_replace('/\D+/', '', (string) (
            $contact['phoneNationalNumber'] ?? $contact['phone'] ?? ''
        ));
        $mobileCountryCode = ltrim((string) ($contact['mobileCountryCode'] ?? '92'), '+');

        $contactInfo = [
            'Title' => $firstTraveller['title'] ?? 'Mr',
            'FName' => $firstTraveller['firstName'] ?? 'Guest',
            'LName' => $firstTraveller['lastName'] ?? 'User',
            'Mobile' => $mobile,
            'Phone' => '',
            'Email' => $contact['email'] ?? '',
            'Address' => 'N/A',
            'CountryCode' => strtoupper((string) ($contact['phoneCountryCode'] ?? 'PK')),
            'MobileCountryCode' => '+' . $mobileCountryCode,
            'State' => $firstTraveller['state'] ?? '',
            'City' => $firstTraveller['city'] ?? '',
            'PIN' => substr($mobile, -6),
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

        if ($fareType === 'DM') {
            return $this->bookDmMultiCityTrips(
                $params,
                $cachedValidationResponse,
                $headers,
                $bookingUrl,
                $clientId,
                $contactInfo,
                $travellers
            );
        }



        /*
        |--------------------------------------------------------------------------
        | FINAL PAYLOAD
        |--------------------------------------------------------------------------
        */
        $payload = [
            'TUI' => $bookingTui,
            'ContactInfo' => $contactInfo,
            'Travellers' => $travellers,
            'PLP' => [],
            'SSR' => $SSR,
            'CrossSell' => [],
            'NetAmount' => $netAmount,
            'SSRAmount' => $totalAmount,
            'ClientID' => $clientId,
            'HoldInfo' => $holdInfo,
            'BookingType' => 'HB',
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
        // Log::info('Fetching ancillaries with data: ', $request);
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
        $cachedValidationResponse = $this->getCachedDmValidationResponse($tui);
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
                'TUI' => $this->resolveActualTui($tui, $cachedValidationResponse, $index),
            ];
        }

        $tripType = $this->resolveFareType($request);

        if ($tripType === 'DM') {
            return $this->fetchDmAncillaryTrips($priceRequestUrl, $headers, $accessToken['ClientID'], $trips, $tripType, 'SSR');
        }

        $payload = $this->buildAncillaryPayload($trips, $accessToken['ClientID'], $tripType);

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
        // Log::info('Getting Seat Layout with data: ', $request);
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
        $cachedValidationResponse = $this->getCachedDmValidationResponse($tui);
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
                'TUI' => $this->resolveActualTui($tui, $cachedValidationResponse, $index),
            ];
        }

        $tripType = $this->resolveFareType($request);

        if ($tripType === 'DM') {
            return $this->fetchDmAncillaryTrips($priceRequestUrl, $headers, $accessToken['ClientID'], $trips, $tripType, 'SeatLayout');
        }

        $payload = $this->buildAncillaryPayload($trips, $accessToken['ClientID'], $tripType);

        // Log::info('Seat request payload (final): ', $payload);

        try {
            $req = new \GuzzleHttp\Psr7\Request(
                'POST',
                $priceRequestUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($req);
            $responseBody = json_decode($response->getBody(), true);

            // Log::info($responseBody);
            return $responseBody;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error sending SSR request: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }

            return null;
        }
    }

    private function buildAncillaryPayload(array $trips, $clientId, string $tripType): array
    {
        return [
            'Trips' => $trips,
            'ClientID' => $clientId,
            'PaidSSR' => 'true',
            'Source' => 'LV',
            'TripType' => $tripType,
        ];
    }

    /**
     * DM uses an independent TUI for each searched trip, so SSR and seat-layout
     * are requested one trip at a time and merged back in their original order.
     */
    private function fetchDmAncillaryTrips(string $url, array $headers, $clientId, array $trips, string $tripType, string $resource): ?array
    {
        $combined = null;
        $combinedTrips = [];

        foreach ($trips as $index => $trip) {
            $supplierTrip = array_merge($trip, ['OrderID' => 1]);
            $payload = $this->buildAncillaryPayload([$supplierTrip], $clientId, $tripType);

            try {
                Log::info("AT DM {$resource} request for trip " . ($index + 1), $payload);
                $req = new Request('POST', $url, $headers, json_encode($payload));
                $response = json_decode($this->client->send($req)->getBody(), true);
                Log::info("AT DM {$resource} response for trip " . ($index + 1), $response ?? []);

                if (!is_array($response)) {
                    return null;
                }

                $combined ??= $response;
                foreach (($response['Trips'] ?? []) as $responseTrip) {
                    $combinedTrips[] = $responseTrip;
                }
            } catch (RequestException $e) {
                Log::error("AT DM {$resource} failed for trip " . ($index + 1) . ': ' . $e->getMessage());
                if ($e->hasResponse()) {
                    Log::error('Response: ' . $e->getResponse()->getBody());
                }
                return null;
            }
        }

        if ($combined === null) {
            return null;
        }

        $combined['Trips'] = $combinedTrips;
        $combined['FareType'] = $tripType;

        return $combined;
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
        $transactionID = $params['pnrData']['TransactionID'] ?? null;
        $TUI = $params['FareType'] === 'DM' ? $params['pnrData']['BookingResponses'][0]['TUI'] ?? null : $params['pnrData']['TUI'] ?? null;
        /*
        |--------------------------------------------------------------------------
        | BUILD PAYMENT PAYLOAD
        |--------------------------------------------------------------------------
        */
        $payload = [
            'TransactionID' => $transactionID,
            'PaymentAmount' => 0,
            'NetAmount' => (int) $params['net_amount'],
            'BrowserKey' => $this->browserKey,
            'ClientID' => $clientId,
            'TUI' => $TUI,
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
    public function getItineraryStatus($paymentResponse)
    {
        try {

            // 🔹 Extract required values
            $tui = $paymentResponse['TUI'] ?? null;
            $transactionId = $paymentResponse['TransactionID'] ?? null;

            if (!$tui || !$transactionId) {
                Log::error('Missing TUI or TransactionID for itinerary status', $paymentResponse);
                return null;
            }

            // 🔹 Build payload
            $payload = [
                "TUI" => $tui,
                "TransactionID" => $transactionId
            ];

            Log::info('Get Itinerary Request Payload:', $payload);

            $url = "{$this->flightBaseUrl}/Payment/GetItineraryStatus";
            $accessToken = $this->getAccessToken();
            $clientId = $accessToken['ClientID'] ?? null;
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => $accessToken['Token'],
            ];

            $request = new \GuzzleHttp\Psr7\Request(
                'POST',
                $url,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($request);

            $body = json_decode($response->getBody(), true);

            Log::info('Get Itinerary Response:', $body);

            return $body;

        } catch (\Exception $e) {

            Log::error('Get Itinerary Status Error: ' . $e->getMessage());
            return null;
        }
    }
    public function getBookingDetails($request)
    {
        Log::info('Getting booking details with PNR: ' . $request->pnr);
        $accessToken = $this->getAccessToken();
        Log::info($accessToken);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken['Token'],
        ];

        $bookingDetailsUrl = "{$this->flightBaseUrl}/Utils/RetrieveBooking";

        $payload = [
            "ReferenceType" => "T",
            "TUI" => "",
            "ReferenceNumber" => $request->pnr ?? "",
            'ClientID' => $accessToken['ClientID'],
        ];

        Log::info('Get Booking Details payload (final): ', $payload);

        try {
            $req = new \GuzzleHttp\Psr7\Request(
                'POST',
                $bookingDetailsUrl,
                $headers,
                json_encode($payload)
            );

            $response = $this->client->send($req);
            $responseBody = json_decode($response->getBody(), true);

            Log::info('Get Booking Details response: ', $responseBody);
            $statusResponse = $this->getItineraryStatus($responseBody);

            return [
                'bookingDetails' => $responseBody,
                'itineraryStatus' => $statusResponse
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error getting Booking Details: ' . $e->getMessage());

            if ($e->hasResponse()) {
                Log::error('Response: ' . $e->getResponse()->getBody());
            }

            return null;
        }
    }

    public function cancelBooking($request)
    {
        Log::info('Cancelling booking with PNR: ' . $request);
    }

    /*
    |--------------------------------------------------------------------------
    |========================= AT DM MULTI-CITY ===============================
    |--------------------------------------------------------------------------
    | Everything below this border belongs to the AT DM multi-city flow.
    | This keeps multi-city search, validation cache, and pricing separate
    | from the standard one-way / return AT service flow above.
    |--------------------------------------------------------------------------
    */

    private function buildMultiCityTrips(array $params): array
    {
        $trips = [];

        foreach ($params['trips'] as $trip) {
            $trips[] = [
                'From' => $trip['origin'],
                'To' => $trip['destination'],
                'ReturnDate' => '',
                'OnwardDate' => $trip['date'],
                'TUI' => $trip['TUI'] ?? $params['TUI'] ?? ''
            ];
        }

        return $trips;
    }

    private function searchTripsOneByOne(array $payload, array $trips, array $headers, string $searchUrl): ?array
    {
        $combinedResponse = null;
        $combinedTrips = [];
        $tuis = [];

        foreach ($trips as $index => $trip) {
            $tripPayload = $payload;
            $tripPayload['Trips'] = [$trip];

            try {
                $request = new Request(
                    'POST',
                    $searchUrl,
                    $headers,
                    json_encode($tripPayload)
                );

                Log::info('DM flight search request payload for trip ' . ($index + 1) . ': ', $tripPayload);

                $response = $this->client->send($request);
                $responseBody = json_decode($response->getBody(), true);
                Log::info('DM flight search response for trip ' . ($index + 1) . ': ', $responseBody);

                if (!isset($responseBody['Msg'][0]) || $responseBody['Msg'][0] !== "Success") {
                    Log::warning('DM flight search returned non-success message for trip ' . ($index + 1));
                    return null;
                }

                $tuis[] = $responseBody['TUI'];
                $tripResult = $this->getSearchFlightsRes($responseBody['TUI']);

                if (empty($tripResult) || !is_array($tripResult)) {
                    Log::warning('DM flight search result is empty for trip ' . ($index + 1));
                    return null;
                }

                if ($combinedResponse === null) {
                    $combinedResponse = $tripResult;
                }

                foreach ($tripResult['Trips'] ?? [] as $resultTrip) {
                    $combinedTrips[] = $resultTrip;
                }
            } catch (RequestException $e) {
                Log::error('Error searching DM flight trip ' . ($index + 1) . ': ' . $e->getMessage());
                if ($e->hasResponse()) {
                    Log::error('Response: ' . $e->getResponse()->getBody());
                }
                return null;
            }
        }

        if ($combinedResponse === null) {
            return null;
        }

        $combinedResponse['Trips'] = $combinedTrips;
        $combinedResponse['TUI'] = $tuis[0] ?? ($combinedResponse['TUI'] ?? null);
        $combinedResponse['TUIList'] = $tuis;

        Log::info('Combined DM flight search response: ', $combinedResponse);

        return $combinedResponse;
    }

    private function getDmValidationCacheKey(string $frontendTui): string
    {
        return self::DM_VALIDATION_CACHE_PREFIX . $frontendTui;
    }

    private function generateDmValidationFrontendTui(): string
    {
        return 'ATDM-' . Str::upper(Str::random(32));
    }

    private function cacheDmValidationCombinedResponse(array $combinedResponse): array
    {
        $frontendTui = $this->generateDmValidationFrontendTui();
        $cacheKey = $this->getDmValidationCacheKey($frontendTui);

        Cache::put($cacheKey, $combinedResponse, now()->addHour());

        Log::info('Cached AT DM validation combined response.', [
            'cache_key' => $cacheKey,
            'frontend_tui' => $frontendTui,
            'original_tuis' => $combinedResponse['TUIList'] ?? [],
        ]);

        return [
            'TUI' => $frontendTui,
            'NetAmount' => $combinedResponse['NetAmount'] ?? 0,
            'FareType' => $combinedResponse['FareType'] ?? 'DM',
            'Msg' => $combinedResponse['Msg'] ?? ['Success'],
            'IsCachedValidationResponse' => true,
        ];
    }

    private function getCachedDmValidationResponse($frontendTui): ?array
    {
        if (!is_string($frontendTui) || !Str::startsWith($frontendTui, 'ATDM-')) {
            return null;
        }

        $cacheKey = $this->getDmValidationCacheKey($frontendTui);
        $cachedResponse = Cache::get($cacheKey);

        if (!$cachedResponse) {
            Log::warning('AT DM validation cache key was not found.', [
                'frontend_tui' => $frontendTui,
                'cache_key' => $cacheKey,
            ]);
            return null;
        }

        return $cachedResponse;
    }

    private function resolveActualTui($frontendTui, ?array $cachedResponse = null, int $index = 0): string
    {
        if (is_array($frontendTui)) {
            return (string) ($frontendTui[$index] ?? $frontendTui[0] ?? '');
        }

        $cachedResponse ??= $this->getCachedDmValidationResponse($frontendTui);

        if ($cachedResponse) {
            return (string) (
                $cachedResponse['TUIList'][$index]
                ?? $cachedResponse['TUI']
                ?? ''
            );
        }

        return (string) ($frontendTui ?? '');
    }

    private function buildDmSsrPayload($params, int $tripIndex): array
    {
        $SSR = [];
        $totalAmount = 0;
        $selectedExtras = $params['selectedExtras'][$tripIndex] ?? [];

        if (!is_array($selectedExtras)) {
            return ['SSR' => $SSR, 'SSRAmount' => $totalAmount];
        }

        foreach ($selectedExtras as $group => $segments) {
            if (!is_array($segments)) {
                continue;
            }

            foreach ($segments as $journey) {
                if (!is_array($journey)) {
                    continue;
                }

                foreach ($journey as $segment) {
                    if (!is_array($segment)) {
                        continue;
                    }

                    foreach ($segment as $pax) {
                        if (!is_array($pax)) {
                            continue;
                        }

                        if (isset($pax['FUID'], $pax['PaxID'], $pax['SSID'])) {
                            $items = [$pax];
                        } else {
                            $items = $pax;
                        }

                        foreach ($items as $item) {
                            if (!is_array($item) || !isset($item['FUID'], $item['PaxID'], $item['SSID'])) {
                                continue;
                            }

                            $totalAmount += (float) ($item['Charge'] ?? $item['SSRNetAmount'] ?? $item['Fare'] ?? 0);
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

        return ['SSR' => $SSR, 'SSRAmount' => $totalAmount];
    }

    private function bookDmMultiCityTrips($params, ?array $cachedValidationResponse, array $headers, string $bookingUrl, $clientId, array $contactInfo, array $travellers): ?array
    {
        if (!$cachedValidationResponse) {
            Log::warning('Cannot book AT DM multi-city without cached validation response.', [
                'frontend_tui' => $params['TUI'] ?? null,
            ]);
            return null;
        }

        $pricedResponses = $cachedValidationResponse['PricedResponses'] ?? [];

        if (empty($pricedResponses)) {
            Log::warning('AT DM cached validation response does not include priced responses.', [
                'frontend_tui' => $params['TUI'] ?? null,
                'cached_response' => $cachedValidationResponse,
            ]);
            return null;
        }

        $bookingResponses = [];
        $transactionIds = [];

        foreach ($pricedResponses as $index => $validatedResponse) {
            $tui = $validatedResponse['TUI'] ?? $cachedValidationResponse['TUIList'][$index] ?? null;
            $netAmount = $validatedResponse['NetAmount'] ?? 0;

            if (empty($tui)) {
                Log::warning('Skipping AT DM booking request because validated TUI is missing.', [
                    'trip_index' => $index,
                    'validated_response' => $validatedResponse,
                ]);
                return null;
            }

            $ssrPayload = $this->buildDmSsrPayload($params, $index);
            $payload = [
                'TUI' => $tui,
                'SequenceID' => $index + 1,
                'ContactInfo' => $contactInfo,
                'Travellers' => $travellers,
                'PLP' => [],
                'SSR' => $ssrPayload['SSR'],
                'CrossSell' => [],
                'NetAmount' => $netAmount + $ssrPayload['SSRAmount'],
                'SSRAmount' => $ssrPayload['SSRAmount'],
                'ClientID' => $clientId,
                'DeviceID' => '',
                'AppVersion' => '',
                'CrossSellAmount' => 0,
            ];

            if ($index === 1) {
                $refTransactionId = $bookingResponses[0]['TransactionID'] ?? null;

                if (empty($refTransactionId)) {
                    Log::warning('Cannot book the second AT DM multi-city trip without the first trip transaction ID.', [
                        'first_booking_response' => $bookingResponses[0] ?? null,
                    ]);
                    return null;
                }

                $payload['RefTransactionID'] = $refTransactionId;
            }

            Log::info('AT DM Booking Payload for trip ' . ($index + 1) . ':', $payload);

            try {
                $request = new \GuzzleHttp\Psr7\Request(
                    'POST',
                    $bookingUrl,
                    $headers,
                    json_encode($payload)
                );

                $responseBody = $this->client->send($request);
                $responseBody = json_decode($responseBody->getBody(), true);
                Log::info('AT DM Booking Response for trip ' . ($index + 1) . ':', $responseBody);

                $bookingResponses[] = $responseBody;

                if (!empty($responseBody['TransactionID'])) {
                    $transactionIds[] = $responseBody['TransactionID'];
                }
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                Log::error('AT DM Booking Error for trip ' . ($index + 1) . ': ' . $e->getMessage());

                if ($e->hasResponse()) {
                    Log::error('AT DM Booking Response:', [
                        'body' => (string) $e->getResponse()->getBody()
                    ]);
                }

                return null;
            }
        }

        if (empty($transactionIds)) {
            Log::warning('AT DM booking did not return any transaction IDs.', [
                'booking_responses' => $bookingResponses,
            ]);
            return [
                'TransactionID' => 0,
                'FareType' => 'DM',
                'BookingResponses' => $bookingResponses,
            ];
        }

        return [
            'TransactionID' => $transactionIds[0],
            'TransactionIDList' => $transactionIds,
            'FareType' => 'DM',
            'TUI' => $params['TUI'] ?? null,
            'TUIList' => $cachedValidationResponse['TUIList'] ?? [],
            'NetAmount' => $cachedValidationResponse['NetAmount'] ?? 0,
            'BookingResponses' => $bookingResponses,
        ];
    }

    private function sendDmMultiCityPriceRequests($request, array $accessToken, array $headers, string $priceRequestUrl, string $tripType): ?array
    {
        $legs = $request->input('legs', []);
        $tuis = $this->normalizeDmTuiList($request->input('ref_id'));

        if (empty($legs) || empty($tuis)) {
            Log::warning('DM price request missing legs or TUI list.', [
                'legs' => $legs,
                'tuis' => $tuis,
            ]);
            return null;
        }

        $pricedResponses = [];
        $smartPricerResponses = [];

        foreach ($legs as $index => $leg) {
            $selectedFare = $leg['selectedFare'] ?? [];
            $tui = $tuis[$index] ?? null;

            if (empty($tui) || !isset($selectedFare['billable_price'])) {
                Log::warning('Skipping invalid DM selected fare pricing item.', [
                    'leg_index' => $index,
                    'tui' => $tui,
                    'selected_fare' => $selectedFare,
                ]);
                return null;
            }

            $payload = [
                'Trips' => [
                    [
                        'Amount' => (float) $selectedFare['billable_price'],
                        'Index' => $selectedFare['index'] ?? ($leg['Index'] ?? ''),
                        'OrderID' => $index + 1,
                        'TUI' => $tui,
                    ]
                ],
                'ClientID' => $accessToken['ClientID'],
                'Mode' => 'AS',
                'Options' => 'A',
                'Source' => 'SF',
                'TripType' => $tripType,
            ];

            Log::info('DM SmartPricer payload for selected fare ' . ($index + 1) . ': ', $payload);

            try {
                $req = new \GuzzleHttp\Psr7\Request(
                    'POST',
                    $priceRequestUrl,
                    $headers,
                    json_encode($payload)
                );

                $response = $this->client->send($req);
                $responseBody = json_decode($response->getBody(), true);
                Log::info('DM SmartPricer response for selected fare ' . ($index + 1) . ': ', $responseBody);

                $smartPricerResponses[] = $responseBody;
                $pricedResponse = $this->getPricer($responseBody);

                if (!$pricedResponse) {
                    Log::warning('DM GetSPricer returned empty response for selected fare ' . ($index + 1));
                    return null;
                }

                $pricedResponses[] = $pricedResponse;
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                Log::error('Error sending DM SmartPricer request for selected fare ' . ($index + 1) . ': ' . $e->getMessage());

                if ($e->hasResponse()) {
                    Log::error('Response: ' . $e->getResponse()->getBody());
                }

                return null;
            }
        }

        return $this->combineDmPricerResponses($pricedResponses, $smartPricerResponses, $tripType);
    }

    private function normalizeDmTuiList($refIds): array
    {
        if (is_array($refIds)) {
            return array_values($refIds);
        }

        if (is_string($refIds) && $refIds !== '') {
            return [$refIds];
        }

        return [];
    }

    private function combineDmPricerResponses(array $pricedResponses, array $smartPricerResponses, string $tripType): array
    {
        $combined = $pricedResponses[0] ?? [];
        $tuis = [];
        $trips = [];
        $netAmount = 0;

        foreach ($pricedResponses as $pricedResponse) {
            if (!empty($pricedResponse['TUI'])) {
                $tuis[] = $pricedResponse['TUI'];
            }

            foreach ($pricedResponse['Trips'] ?? [] as $trip) {
                $trips[] = $trip;
            }

            $netAmount += (float) ($pricedResponse['NetAmount'] ?? 0);
        }

        $combined['FareType'] = $tripType;
        $combined['TUI'] = $tuis[0] ?? ($combined['TUI'] ?? null);
        $combined['TUIList'] = $tuis;
        $combined['Trips'] = $trips;
        $combined['NetAmount'] = $netAmount ?: ($combined['NetAmount'] ?? 0);
        $combined['PricedResponses'] = $pricedResponses;
        $combined['SmartPricerResponses'] = $smartPricerResponses;

        Log::info('Combined DM GetSPricer response: ', $combined);

        return $this->cacheDmValidationCombinedResponse($combined);
    }

}
