<?php

namespace App\Services;

use Cache;
use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use function Laravel\Prompts\select;

class OneApiService
{

    protected $authUrl;
    protected $baseUrl;
    protected $priceUrl;
    protected $username;
    protected $password;
    protected $agentCode;
    protected $accessToken;
    protected $journeyType;
    protected $clientUserName;
    protected $environment;


    public function __construct()
    {
        $this->authUrl = config('oneapi.auth_url');
        $this->baseUrl = config('oneapi.base_url');
        $this->priceUrl = config('oneapi.price_url');
        $this->username = config('oneapi.username');
        $this->clientUserName = config('oneapi.client_username');
        $this->password = config('oneapi.password');
        $this->agentCode = config('oneapi.agent_code');
        $this->environment = 'OTA';



    }

    private function getSalesChannel(): string
    {
        return 'OTA';
    }

    public function setJsessionId($jsessionId)
    {
        Cache::put('one_api_jsession_id', $jsessionId, now()->addHours(1));
    }
    public function getJsessionId()
    {
        return Cache::get('one_api_jsession_id');
    }

    private function extractSessionCookieFromHeaders(array $headers): ?string
    {
        $setCookieHeaders = $headers['set-cookie'] ?? $headers['Set-Cookie'] ?? [];

        foreach ($setCookieHeaders as $cookieHeader) {
            if (stripos($cookieHeader, 'JSESSIONID=') !== false) {
                $cookieParts = explode(';', $cookieHeader);
                return trim($cookieParts[0]);
            }
        }

        return null;
    }

    private function getAccessToken()
    {
        try {
            $body = [
                'login' => $this->username,
                'password' => $this->password,
            ];
            $request = new Client();
            $response = $request->post($this->authUrl, [
                'json' => $body
            ]);
            // $this->setJsessionId($headers['set-cookie'][0]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['tokenPair']['accessToken'];
        } catch (RequestException $e) {
            Log::error('Error fetching access token: ' . $e->getMessage());
            return null;
        }
    }


    public function searchFlights($params)
    {
        Log::info($params);

        $this->accessToken = $this->getAccessToken();
        // Log::info('Access Token: ' . $this->accessToken);

        // Extract params
        $origin = $params['origin'];
        $destination = $params['destination'];
        $departureDate = $params['departure_date'];
        $returnDate = $params['return_date'] ?? null;
        $cabinClass = $params['cabin_class'];
        $currency = 'PKR';

        $adults = (int) $params['adults'];
        $children = (int) $params['children'];
        $infants = (int) $params['infants'];

        $isReturn = ($params['flight_type'] === 'return');
        $journeyType = $isReturn ? 'RETURN' : 'ONEWAY';
        $this->journeyType = $journeyType;
        // Build ONDs
        $onds = [];

        // First OND (departure)
        $onds[] = [
            'origin' => [
                'code' => $origin,
                'locationType' => 'AIRPORT'
            ],
            'destination' => [
                'code' => $destination,
                'locationType' => 'AIRPORT'
            ],
            'searchStartDate' => $departureDate,
            'searchEndDate' => $departureDate,
            'preferredDate' => null,
            'bookingType' => 'NORMAL',
            'cabinClass' => $cabinClass,
            'ondRef' => $origin . '/' . $destination,
            'interlineQuoteDetails' => null
        ];

        if ($isReturn && $returnDate) {
            $onds[] = [
                'origin' => [
                    'code' => $destination,
                    'locationType' => 'AIRPORT'
                ],
                'destination' => [
                    'code' => $origin,
                    'locationType' => 'AIRPORT'
                ],
                'searchStartDate' => $returnDate,
                'searchEndDate' => $returnDate,
                'preferredDate' => null,
                'bookingType' => 'NORMAL',
                'cabinClass' => $cabinClass,
                'ondRef' => $destination . '/' . $origin,
                'interlineQuoteDetails' => null
            ];
        }

        // Build pax array
        $paxCounts = [
            ['count' => $adults, 'paxType' => 'ADT'],
            ['count' => $children, 'paxType' => 'CHD'],
            ['count' => $infants, 'paxType' => 'INF'],
        ];

        // Final request body
        $requestBody = [
            'searchOnds' => $onds,
            'paxCounts' => $paxCounts,
            'return' => $isReturn,
            'currencyCode' => $currency,
            'cabinClass' => $cabinClass,
            'metaData' => [
                'agentCode' => $this->agentCode,
                'country' => 'PK',
                'station' => 'PK',
                'salesChannel' => 'TravelAgent',
                'otherMetaData' => [
                    [
                        'metaDataKey' => 'FLIGHT_CUTOVER_TIME',
                        'metaDataValue' => now()->toISOString(),
                    ],
                    [
                        'metaDataKey' => 'SKIP_OND_MERGE',
                        'metaDataValue' => 'true',
                    ],
                ],
            ],
        ];

        $client = new Client();
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->accessToken,
                'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                'X-AERO-JOURNEY-TYPE' => $journeyType,
                'X-AERO-USERID' => $this->username,
                'X-AERO-AGENT-CODE' => $this->agentCode,
            ];
            // $headers['Cookie'] = $this->getJsessionId();
            $response = $client->post($this->baseUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                    'X-AERO-JOURNEY-TYPE' => $journeyType,
                    'X-AERO-USERID' => $this->username,
                    'X-AERO-AGENT-CODE' => $this->agentCode,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
            ]);
            $header = $response->getHeaders();
            Log::info($header['set-cookie']);
            // $this->setJsessionId($header['set-cookie'][0]);
            $responseData = json_decode($response->getBody()->getContents(), true);
            $origin = $params['origin'];
            $destination = $params['destination'];
            $deptDate = $params['departure_date'];

            // if ($params['flight_type'] === 'one-way') {
            //     foreach (
            //         $responseData['ondWiseFlightCombinations']["$origin/$destination"]
            //         ['dateWiseFlightCombinations'][$deptDate]['flightOptions']
            //         as &$combination
            //     ) {
            //         $priceResponse = $this->getPrice($combination, $params);
            //         $combination['pricing'] = json_decode($priceResponse, true);
            //     }
            //     unset($combination);
            // }
            // if ($params['flight_type'] === 'return') {

            //     $returnDate = $params['return_date'];

            //     $outboundFlights = $responseData['ondWiseFlightCombinations']["$origin/$destination"]
            //     ['dateWiseFlightCombinations'][$deptDate]['flightOptions'] ?? [];

            //     $returnFlights = $responseData['ondWiseFlightCombinations']["$destination/$origin"]
            //     ['dateWiseFlightCombinations'][$returnDate]['flightOptions'] ?? [];

            //     foreach ($outboundFlights as &$outbound) {
            //         foreach ($returnFlights as &$return) {

            //             $combinedSegments = array_merge(
            //                 $outbound['flightSegments'] ?? [],
            //                 $return['flightSegments'] ?? []
            //             );

            //             $combinedFlights = $outbound;
            //             $combinedFlights['flightSegments'] = $combinedSegments;

            //             $priceResponse = $this->getPrice($combinedFlights, $params);
            //             $priceResponse = json_decode($priceResponse, true);

            //             // ✅ Attach pricing
            //             $outbound['pricing'] = $priceResponse;
            //             $return['pricing'] = $priceResponse;
            //         }
            //     }

            //     unset($outbound, $return);

            //     // ✅ THIS IS THE MISSING PART
            //     $responseData['ondWiseFlightCombinations']["$origin/$destination"]
            //     ['dateWiseFlightCombinations'][$deptDate]['flightOptions'] = $outboundFlights;

            //     $responseData['ondWiseFlightCombinations']["$destination/$origin"]
            //     ['dateWiseFlightCombinations'][$returnDate]['flightOptions'] = $returnFlights;
            // }


            Log::info("OneAPI Response:\n" . json_encode($responseData, JSON_PRETTY_PRINT));
            return $responseData;
        } catch (RequestException $e) {
            Log::error('Error fetching flights from OneAPI: ' . $e->getMessage());
            return null;
        }

    }

    public function getPrice($request, $params)
    {
        $flightSegments = $request;

        //--------------------------------------------------------------
        // 🚀 CREATE SOAP REQUEST USING DOMDocument
        //--------------------------------------------------------------

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // <soap:Envelope>
        $envelope = $dom->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soap:Envelope");
        $dom->appendChild($envelope);

        // Namespaces
        $envelope->setAttribute("xmlns:xsd", "http://www.w3.org/2001/XMLSchema");
        $envelope->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");

        // <soap:Header>
        $header = $dom->createElement("soap:Header");
        $envelope->appendChild($header);

        //--------------------------------------------------------------
        // 🔐 WS Security Header
        //--------------------------------------------------------------
        $security = $dom->createElementNS(
            "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd",
            "wsse:Security"
        );
        $security->setAttribute("soap:mustUnderstand", "1");
        $header->appendChild($security);

        $usernameToken = $dom->createElement("wsse:UsernameToken");
        $usernameToken->setAttributeNS(
            "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd",
            "wsu:Id",
            "UsernameToken-32124385"
        );
        $security->appendChild($usernameToken);

        $usernameToken->appendChild($dom->createElement("wsse:Username", $this->clientUserName));
        $password = $dom->createElement("wsse:Password", $this->password);
        $password->setAttribute("Type", "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText");
        $usernameToken->appendChild($password);


        //--------------------------------------------------------------
        // 📦 SOAP BODY
        //--------------------------------------------------------------
        $body = $dom->createElementNS("http://schemas.xmlsoap.org/soap/envelope/", "soap:Body");
        $body->setAttribute("xmlns:ns1", "http://www.opentravel.org/OTA/2003/05");
        $envelope->appendChild($body);

        $ns1_airPriceRQ = $dom->createElement("ns1:OTA_AirPriceRQ");
        $ns1_airPriceRQ->setAttribute("EchoToken", uniqid());
        $ns1_airPriceRQ->setAttribute("PrimaryLangID", "en-us");
        $ns1_airPriceRQ->setAttribute("SequenceNmbr", "1");
        $ns1_airPriceRQ->setAttribute("TimeStamp", now()->toIso8601String());
        $ns1_airPriceRQ->setAttribute("Version", "20061.00");

        $body->appendChild($ns1_airPriceRQ);

        //--------------------------------------------------------------
        // 🧍 POS
        //--------------------------------------------------------------
        $pos = $dom->createElement("ns1:POS");
        $ns1_airPriceRQ->appendChild($pos);

        $source = $dom->createElement("ns1:Source");
        $source->setAttribute("TerminalID", "{$this->username}/{$this->getSalesChannel()}");
        $pos->appendChild($source);

        $reqID = $dom->createElement("ns1:RequestorID");
        $reqID->setAttribute("ID", $this->clientUserName);
        $reqID->setAttribute("Type", "4");
        $source->appendChild($reqID);

        $bookingChannel = $dom->createElement("ns1:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);


        //--------------------------------------------------------------
        // ✈️ AIR ITINERARY
        //--------------------------------------------------------------
        $direction = $params['flight_type'] == 'one-way' ? 'OneWay' : 'Return';
        $airItinerary = $dom->createElement("ns1:AirItinerary");
        $airItinerary->setAttribute("DirectionInd", $direction);
        $ns1_airPriceRQ->appendChild($airItinerary);

        $originDestOptions = $dom->createElement("ns1:OriginDestinationOptions");
        $airItinerary->appendChild($originDestOptions);


        // ------------------------
        // INSERT FLIGHT SEGMENTS
        // ------------------------
        foreach ($flightSegments as $flightSegment) {
            $originDestOption = $dom->createElement("ns1:OriginDestinationOption");
            $originDestOptions->appendChild($originDestOption);
            foreach ($flightSegment['segments'] as $seg) {

                $fs = $dom->createElement("ns1:FlightSegment");

                $fs->setAttribute("ArrivalDateTime", $seg["arrivalDateTimeLocal"]);
                $fs->setAttribute("DepartureDateTime", $seg["departureDateTimeLocal"]);
                $fs->setAttribute("FlightNumber", $seg["flightNumber"]);

                $dep = $dom->createElement("ns1:DepartureAirport");
                $dep->setAttribute("LocationCode", $seg["origin"]["airportCode"]);
                $originDestOption->appendChild($fs);
                $fs->appendChild($dep);

                $arr = $dom->createElement("ns1:ArrivalAirport");
                $arr->setAttribute("LocationCode", $seg["destination"]["airportCode"]);
                $fs->appendChild($arr);

                $opAirline = $dom->createElement("ns1:OperatingAirline");
                $opAirline->setAttribute("Code", substr($seg["flightNumber"], 0, 2));
                $fs->appendChild($opAirline);
            }
        }


        //--------------------------------------------------------------
        // 👤 TRAVELERS
        //--------------------------------------------------------------
        $traveler = $dom->createElement("ns1:TravelerInfoSummary");
        $ns1_airPriceRQ->appendChild($traveler);

        $avail = $dom->createElement("ns1:AirTravelerAvail");
        $traveler->appendChild($avail);

        if ((int) $params['adults'] > 0) {
            $avail->appendChild($this->makePTQ($dom, "ADT", $params['adults']));
        }
        if ((int) $params['children'] > 0) {
            $avail->appendChild($this->makePTQ($dom, "CHD", $params['children']));
        }
        if ((int) $params['infants'] > 0) {
            $avail->appendChild($this->makePTQ($dom, "INF", $params['infants']));
        }
        $specialReqDetails = $dom->createElement("ns1:SpecialReqDetails");
        $ssrRequests = $dom->createElement("ns1:SSRRequests");
        $specialReqDetails->appendChild($ssrRequests);
        $ns1_airPriceRQ->appendChild($specialReqDetails);
        //--------------------------------------------------------------
        // RETURN SOAP XML STRING
        //--------------------------------------------------------------
        $isReturn = ($params['flight_type'] === 'return');
        $journeyType = $isReturn ? 'RETURN' : 'ONEWAY';
        $soapRequestXml = $dom->saveXML();
        Log::info($soapRequestXml);
        $client = new Client();
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'Authorization' => 'Bearer ' . $this->accessToken,
            'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
            'X-AERO-JOURNEY-TYPE' => $journeyType,
            'X-AERO-USERID' => $this->username,
            'X-AERO-AGENT-CODE' => $this->agentCode,
        ];
        // Log::info('Before JSESSIONID: ' . $this->getJsessionId());
        $headers['Cookie'] = $this->getJsessionId();
        try {
            $response = $client->post($this->priceUrl, [
                'headers' => $headers,
                'body' => $soapRequestXml,
            ]);

            $responseXml = $response->getBody()->getContents();
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseXml);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xml);
            $header = $response->getHeaders();
            $cookieHeader = $header['set-cookie'][0] ?? '';
            Log::info($cookieHeader);
            $jsonData = json_decode($json, true);
            $jsonData['Body']['SetCookie'] = $cookieHeader;
            $json = json_encode($jsonData);
            // Log::info($json);
            // $this->setJsessionId($cookieHeader);
            return $json;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneAPi Booking API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract XML <Error> message if available
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneAPi Error XML", ['error_parsed' => $arrayError]);

                    // Try to detect standard OneAPi <Errors> or <Error> nodes
                    $errorMsg = $arrayError['Errors']['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['Message'] ??
                        json_encode($arrayError);

                    return response()->json([
                        'error' => 'OneAPi API Error',
                        'message' => $errorMsg,
                        'raw' => $arrayError
                    ], 502);
                }
            }

            return response()->json([
                'error' => 'OneAPi API Request Failed',
                'message' => $e->getMessage(),
            ], 502);

        }

    }
    public function validatePriceWithBundle($request)
    {
        //--------------------------------------------------------------
        // 🔍 NORMALIZE REQUEST DATA
        //--------------------------------------------------------------
        $quoteData = $request['quote'] ?? [];
        $flight = $request['flight'] ?? [];
        $leg = $flight['leg'] ?? [];
        $flights = $leg['flights'] ?? [];
        $selectedFares = $request['selectedFares'] ?? [];

        $adults = (int) ($request['adults'] ?? 0);
        $children = (int) ($request['children'] ?? 0);
        $infants = (int) ($request['infants'] ?? 0);

        //--------------------------------------------------------------
        // ✈️ DETERMINE JOURNEY TYPE
        //--------------------------------------------------------------
        $flightCount = count($flights);

        if ($flightCount === 1) {
            $journeyType = 'ONEWAY';
            $directionInd = 'OneWay';
        } elseif ($flightCount === 2) {
            $journeyType = 'RETURN';
            $directionInd = 'Return';
        } else {
            $journeyType = 'MULTICITY';
            $directionInd = 'MultiCity';
        }

        //--------------------------------------------------------------
        // 🚀 CREATE SOAP REQUEST
        //--------------------------------------------------------------
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $envelope = $dom->createElementNS(
            "http://schemas.xmlsoap.org/soap/envelope/",
            "soap:Envelope"
        );
        $dom->appendChild($envelope);

        $envelope->setAttribute("xmlns:xsd", "http://www.w3.org/2001/XMLSchema");
        $envelope->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");

        //--------------------------------------------------------------
        // 🔐 SOAP HEADER (WS SECURITY)
        //--------------------------------------------------------------
        $header = $dom->createElement("soap:Header");
        $envelope->appendChild($header);

        $security = $dom->createElementNS(
            "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd",
            "wsse:Security"
        );
        $security->setAttribute("soap:mustUnderstand", "1");
        $header->appendChild($security);

        $usernameToken = $dom->createElement("wsse:UsernameToken");
        $usernameToken->setAttributeNS(
            "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd",
            "wsu:Id",
            "UsernameToken-" . uniqid()
        );
        $security->appendChild($usernameToken);

        $usernameToken->appendChild(
            $dom->createElement("wsse:Username", $this->clientUserName)
        );

        $password = $dom->createElement("wsse:Password", $this->password);
        $password->setAttribute(
            "Type",
            "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText"
        );
        $usernameToken->appendChild($password);

        //--------------------------------------------------------------
        // 📦 SOAP BODY
        //--------------------------------------------------------------
        $body = $dom->createElementNS(
            "http://schemas.xmlsoap.org/soap/envelope/",
            "soap:Body"
        );
        $body->setAttribute("xmlns:ns1", "http://www.opentravel.org/OTA/2003/05");
        $envelope->appendChild($body);

        $airPriceRQ = $dom->createElement("ns1:OTA_AirPriceRQ");
        // Add attributes from request
        $reqSpecific = $flight['req_specific'] ?? [];
        foreach ($reqSpecific as $attr => $value) {
            $airPriceRQ->setAttribute($attr, $value);
        }
        $body->appendChild($airPriceRQ);

        //--------------------------------------------------------------
        // 🧍 POS
        //--------------------------------------------------------------
        $pos = $dom->createElement("ns1:POS");
        $airPriceRQ->appendChild($pos);

        $source = $dom->createElement("ns1:Source");
        $source->setAttribute("TerminalID", "{$this->username}/{$this->getSalesChannel()}");
        $pos->appendChild($source);

        $reqID = $dom->createElement("ns1:RequestorID");
        $reqID->setAttribute("ID", $this->clientUserName);
        $reqID->setAttribute("Type", "4");
        $source->appendChild($reqID);
        $bookingChannel = $dom->createElement("ns1:BookingChannel");
        $bookingChannel->setAttribute("Type", "12");
        $source->appendChild($bookingChannel);

        //--------------------------------------------------------------
        // ✈️ AIR ITINERARY
        //--------------------------------------------------------------
        $airItinerary = $dom->createElement("ns1:AirItinerary");
        $airItinerary->setAttribute("DirectionInd", $directionInd);
        $airPriceRQ->appendChild($airItinerary);

        $originDestOptions = $dom->createElement("ns1:OriginDestinationOptions");
        $airItinerary->appendChild($originDestOptions);

        foreach ($flights as $flight) {
            $originDestOption = $dom->createElement("ns1:OriginDestinationOption");
            $originDestOptions->appendChild($originDestOption);

            foreach ($flight['segments'] as $seg) {
                $fs = $dom->createElement("ns1:FlightSegment");
                $fs->setAttribute("DepartureDateTime", $seg['departure_at']);
                $fs->setAttribute("ArrivalDateTime", $seg['arrival_at']);
                $fs->setAttribute(
                    "FlightNumber",
                    $seg['operating_carrier']['iata'] . $seg['flight_number']
                );

                $originDestOption->appendChild($fs);

                $dep = $dom->createElement("ns1:DepartureAirport");
                $dep->setAttribute("LocationCode", $seg['from']['iata']);
                $fs->appendChild($dep);

                $arr = $dom->createElement("ns1:ArrivalAirport");
                $arr->setAttribute("LocationCode", $seg['to']['iata']);
                $fs->appendChild($arr);

                $opAirline = $dom->createElement("ns1:OperatingAirline");
                $opAirline->setAttribute(
                    "Code",
                    $seg['operating_carrier']['iata']
                );
                $fs->appendChild($opAirline);
            }
        }

        //--------------------------------------------------------------
        // 👤 TRAVELERS
        //--------------------------------------------------------------
        $traveler = $dom->createElement("ns1:TravelerInfoSummary");
        $airPriceRQ->appendChild($traveler);

        $avail = $dom->createElement("ns1:AirTravelerAvail");
        $traveler->appendChild($avail);

        if ($adults > 0)
            $avail->appendChild($this->makePTQ($dom, "ADT", $adults));
        if ($children > 0)
            $avail->appendChild($this->makePTQ($dom, "CHD", $children));
        if ($infants > 0)
            $avail->appendChild($this->makePTQ($dom, "INF", $infants));


        $bundledServices = $dom->createElement("ns1:BundledServiceSelectionOptions");
        $airPriceRQ->appendChild($bundledServices);

        $bundleIds = [];

        foreach ($flights as $index => $flight) {
            foreach ($flight['fares'] as $fare) {

                if (
                    in_array($fare['ref_id'], $selectedFares, true) &&
                    !empty($fare['bundledServiceId'])
                ) {
                    $bundleIds[$index] = $fare['bundledServiceId'];
                }
            }
        }

        if ($journeyType === 'ONEWAY' && isset($bundleIds[0])) {
            $bundledServices->appendChild(
                $dom->createElement("ns1:OutBoundBunldedServiceId", $bundleIds[0])
            );
        }

        if ($journeyType === 'RETURN') {
            if (isset($bundleIds[0])) {
                $bundledServices->appendChild(
                    $dom->createElement("ns1:OutBoundBunldedServiceId", $bundleIds[0])
                );
            }
            if (isset($bundleIds[1])) {
                $bundledServices->appendChild(
                    $dom->createElement("ns1:InBoundBunldedServiceId", $bundleIds[1])
                );
            }
        }

        if ($journeyType === 'MULTICITY') {
            foreach ($bundleIds as $bundleId) {
                $bundledServices->appendChild(
                    $dom->createElement("ns1:BunldedServiceId", $bundleId)
                );
            }
        }

        //--------------------------------------------------------------
        // 🚀 SEND REQUEST
        //--------------------------------------------------------------
        $soapRequestXml = $dom->saveXML();
        Log::info($soapRequestXml);

        $client = new Client();
        $headers = [
            'Content-Type' => 'text/xml; charset=utf-8',
            'Authorization' => 'Bearer ' . $this->accessToken,
            'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
            'X-AERO-JOURNEY-TYPE' => $journeyType,
            'X-AERO-USERID' => $this->username,
            'X-AERO-AGENT-CODE' => $this->agentCode,
        ];
        $headers['Cookie'] = $flight['req_specific']['SetCookie'] ?? '';
        try {
            $response = $client->post($this->priceUrl, [
                'headers' => $headers,
                'body' => $soapRequestXml,
            ]);
            $header = $response->getHeaders();


            $responseXml = $response->getBody()->getContents();
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseXml);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xml);
            Log::info($json);
            return $json;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneAPi Booking API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract XML <Error> message if available
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneAPi Error XML", ['error_parsed' => $arrayError]);

                    // Try to detect standard OneAPi <Errors> or <Error> nodes
                    $errorMsg = $arrayError['Errors']['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['Message'] ??
                        json_encode($arrayError);

                    return response()->json([
                        'error' => 'OneAPi API Error',
                        'message' => $errorMsg,
                        'raw' => $arrayError
                    ], 502);
                }
            }

            return response()->json([
                'error' => 'OneAPi API Request Failed',
                'message' => $e->getMessage(),
            ], 502);

        }

    }


    // Small helper to add PassengerTypeQuantity
    private function makePTQ($dom, $code, $qty)
    {
        $ptq = $dom->createElement("ns1:PassengerTypeQuantity");
        $ptq->setAttribute("Code", $code);
        $ptq->setAttribute("Quantity", $qty);
        return $ptq;
    }

    private function normalizeOneApiList($value): array
    {
        if ($value === null || $value === []) {
            return [];
        }

        if (!is_array($value)) {
            return [$value];
        }

        return array_is_list($value) ? $value : [$value];
    }

    private function extractQuotePassengers(array $quote, array $requestData = []): array
    {
        $breakdowns = $quote['Body']['OTA_AirPriceRS']['PricedItineraries']['PricedItinerary']['AirItineraryPricingInfo']['PTC_FareBreakdowns']['PTC_FareBreakdown'] ?? [];
        $passengers = [];

        foreach ($this->normalizeOneApiList($breakdowns) as $breakdown) {
            $attributes = $breakdown['PassengerTypeQuantity']['@attributes'] ?? [];
            $code = $attributes['Code'] ?? null;
            $quantity = (int) ($attributes['Quantity'] ?? 0);

            if (!$code || $quantity < 1) {
                continue;
            }

            $refs = [];
            foreach ($this->normalizeOneApiList($breakdown['TravelerRefNumber'] ?? []) as $travelerRef) {
                $rph = $travelerRef['@attributes']['RPH'] ?? null;
                if ($rph) {
                    $refs[] = $rph;
                }
            }

            $passengers[] = [
                'code' => $code,
                'quantity' => $quantity,
                'traveler_refs' => $refs,
            ];
        }

        if (!empty($passengers)) {
            return $passengers;
        }

        foreach (['ADT' => 'adults', 'CHD' => 'children', 'INF' => 'infants'] as $code => $requestKey) {
            $quantity = (int) ($requestData[$requestKey] ?? 0);

            if ($quantity > 0) {
                $passengers[] = [
                    'code' => $code,
                    'quantity' => $quantity,
                    'traveler_refs' => [],
                ];
            }
        }

        return $passengers;
    }

    private function flattenTravelerRefs(array $passengers): array
    {
        $travelerRefs = [];

        foreach ($passengers as $passenger) {
            foreach ($passenger['traveler_refs'] ?? [] as $travelerRef) {
                $travelerRefs[] = $travelerRef;
            }
        }

        return $travelerRefs;
    }

    private function resolveTravelerRef(array $selection, $index, array $travelerRefs): string
    {
        $selectedRef = $selection['passenger_ref_id'] ?? null;
        if (!empty($selectedRef)) {
            return $selectedRef;
        }

        $numericIndex = is_numeric($index) ? (int) $index : null;
        if ($numericIndex !== null && isset($travelerRefs[$numericIndex])) {
            return $travelerRefs[$numericIndex];
        }

        return 'A' . ($numericIndex !== null ? $numericIndex + 1 : 1);
    }


   


    public function fetchBaggage($request)
    {
        $flightData = $request['flight'] ?? null;
        $quoteData = $request['quote'] ?? null;
        if (!$flightData) {
            Log::error("No flightData data found.");
            return;
        }

        // Initialize DOM
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Envelope
        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $dom->appendChild($envelope);

        // Declare namespaces
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns', 'http://www.opentravel.org/OTA/2003/05');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');


        // Namespaces
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns', 'http://www.opentravel.org/OTA/2003/05');

        // Header
        $header = $dom->createElement('soapenv:Header');
        $envelope->appendChild($header);

        $security = $dom->createElement('wsse:Security');
        $security->setAttribute('soapenv:mustUnderstand', '1');
        $header->appendChild($security);

        $usernameToken = $dom->createElement('wsse:UsernameToken');
        $usernameToken->setAttribute('wsu:Id', 'UsernameToken-' . uniqid());
        $security->appendChild($usernameToken);

        $username = $dom->createElement('wsse:Username', $this->clientUserName);
        $usernameToken->appendChild($username);

        $password = $dom->createElement('wsse:Password', $this->password);
        $password->setAttribute('Type', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText');
        $usernameToken->appendChild($password);


        // Body
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // Main Request
        $baggageRQ = $dom->createElement('ns:AA_OTA_AirBaggageDetailsRQ');
        $body->appendChild($baggageRQ);
        $reqSpecific = $flightData['req_specific'] ?? [];
        foreach ($reqSpecific as $attr => $value) {
            $baggageRQ->setAttribute($attr, $value);
        }
        // Add attributes from request
        $reqSpecific = $quoteData['Body']['OTA_AirPriceRS']['@attributes'] ?? [];
        foreach ($reqSpecific as $attr => $value) {
            if ($attr !== 'RetransmissionIndicator') {
                $baggageRQ->setAttribute($attr, $value);
            }
        }

        // POS - you can adjust TerminalID, RequestorID etc.
        $pos = $dom->createElement('ns:POS');
        $baggageRQ->appendChild($pos);

        $source = $dom->createElement('ns:Source');
        $source->setAttribute('TerminalID', "{$this->username}/OTA");
        $pos->appendChild($source);

        $requestorID = $dom->createElement('ns:RequestorID');
        $requestorID->setAttribute('ID', $this->clientUserName);
        $requestorID->setAttribute('Type', '4');
        $source->appendChild($requestorID);

        $bookingChannel = $dom->createElement('ns:BookingChannel');
        $bookingChannel->setAttribute('Type', '12');
        $source->appendChild($bookingChannel);

        // BaggageDetailsRequests
        $baggageRequests = $dom->createElement('ns:BaggageDetailsRequests');
        $baggageRQ->appendChild($baggageRequests);

        // Loop through segments
        $legs = $flightData['leg'];
        if (isset($legs['flights'])) {
            foreach ($legs['flights'] as $flight) {

                foreach ($flight['segments'] as $segment) {
                    // Log::info($segment);
                    $baggageRequest = $dom->createElement('ns:BaggageDetailsRequest');
                    $baggageRequests->appendChild($baggageRequest);

                    $segmentInfo = $dom->createElement('ns:FlightSegmentInfo');
                    $segmentInfo->setAttribute('ArrivalDateTime', $segment['arrival_at']);
                    $segmentInfo->setAttribute('DepartureDateTime', $segment['departure_at']);
                    $segmentInfo->setAttribute('FlightNumber', $segment['operating_carrier']['iata'] . $segment['flight_number']);
                    $segmentInfo->setAttribute('RPH', $segment['RPH']);
                    $segmentInfo->setAttribute('returnFlag', 'false');
                    $baggageRequest->appendChild($segmentInfo);

                    // Departure Airport
                    $dep = $dom->createElement('ns:DepartureAirport');
                    $dep->setAttribute('LocationCode', $segment['from']['iata']);
                    $dep->setAttribute('Terminal', '');
                    $segmentInfo->appendChild($dep);

                    // Arrival Airport
                    $arr = $dom->createElement('ns:ArrivalAirport');
                    $arr->setAttribute('LocationCode', $segment['to']['iata']);
                    $arr->setAttribute('Terminal', '');
                    $segmentInfo->appendChild($arr);

                    // Operating Airline
                    $op = $dom->createElement('ns:OperatingAirline');
                    $op->setAttribute('Code', $segment['operating_carrier']['iata'] ?? '');
                    $segmentInfo->appendChild($op);
                }
            }
        }

        // Output XML
        $xml = $dom->saveXML();
        Log::info($xml);
        $journeyType = '';
        $flights = $flightData['leg']['flights'];
        $flightCount = count($flights);

        if ($flightCount === 1) {
            $journeyType = 'ONEWAY';
            $directionInd = 'OneWay';
        } elseif ($flightCount === 2) {
            $journeyType = 'RETURN';
            $directionInd = 'Return';
        } else {
            $journeyType = 'MULTICITY';
            $directionInd = 'MultiCity';
        }

        // -----------------------------------------------------------------
        try {
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                'X-AERO-USERID' => $this->username,
                'X-AERO-AGENT-CODE' => $this->agentCode,
                'X-AERO-JOURNEY-TYPE' => $journeyType,
            ];
            $headers['Cookie'] = $flightData['req_specific']['SetCookie'];
            $client = new Client([
                'base_uri' => $this->priceUrl,
                'verify' => false,
                'headers' => $headers,
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xml]);
            $header = $response->getHeaders();
            // $cookieHeader = $header['set-cookie'][0] ?? '';

            // // Extract JSESSIONID from cookie
            // if (preg_match('/JSESSIONID=([^;]+)/', $cookieHeader, $matches)) {
            //     $jsessionId = $matches[1];
            //     Log::info('Extracted JSESSIONID: ' . $jsessionId);
            //     $this->setJsessionId($jsessionId);
            // }
            $responseBody = (string) $response->getBody();


            // -----------------------------------------------------------------
            // 2. Parse XML
            // -----------------------------------------------------------------
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xml);
            $array = json_decode($json, true);
            // Log::info($array);

            $baggageDetailsResponse =
                $array['Body']['AA_OTA_AirBaggageDetailsRS']['BaggageDetailsResponses']['OnDBaggageDetailsResponse']
                ?? [];

            /**
             * If response is a single object, wrap it into an array
             */
            if (!empty($baggageDetailsResponse) && isset($baggageDetailsResponse['OnDFlightSegmentInfo'])) {
                $baggageDetailsResponse = [$baggageDetailsResponse];
            }

            $array['Body']['AA_OTA_AirBaggageDetailsRS']['BaggageDetailsResponses']['OnDBaggageDetailsResponse']
                = $baggageDetailsResponse;

            return $array;


        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneApi Baggage API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract XML <Error> message if available
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneApi Error XML", ['error_parsed' => $arrayError]);

                    // Try to detect standard OneApi <Errors> or <Error> nodes
                    $errorMsg = $arrayError['Errors']['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['Message'] ??
                        json_encode($arrayError);

                    return response()->json([
                        'error' => 'OneApi API Error',
                        'message' => $errorMsg,
                        'raw' => $arrayError
                    ], 502);
                }
            }

            return response()->json([
                'error' => 'OneApi API Request Failed',
                'message' => $e->getMessage(),
            ], 502);

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("OneApi Booking Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => isset($bookingData) ? json_encode($bookingData) : null,
            ]);

            return response()->json([
                'error' => 'Booking request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function fetchMeals($request)
    {
        $flight = $request['flight'] ?? null;
        $flightData = $request['flight'] ?? null;
        if (!$flight) {
            Log::error("No flight data found.");
            return;
        }

        // Initialize DOM
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Envelope
        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $dom->appendChild($envelope);

        // Declare namespaces
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns', 'http://www.opentravel.org/OTA/2003/05');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');


        // Namespaces
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns', 'http://www.opentravel.org/OTA/2003/05');

        // Header
        $header = $dom->createElement('soapenv:Header');
        $envelope->appendChild($header);

        $security = $dom->createElement('wsse:Security');
        $security->setAttribute('soapenv:mustUnderstand', '1');
        $header->appendChild($security);

        $usernameToken = $dom->createElement('wsse:UsernameToken');
        $usernameToken->setAttribute('wsu:Id', 'UsernameToken-' . uniqid());
        $security->appendChild($usernameToken);

        $username = $dom->createElement('wsse:Username', value: $this->clientUserName);
        $usernameToken->appendChild($username);

        $password = $dom->createElement('wsse:Password', $this->password);
        $password->setAttribute('Type', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText');
        $usernameToken->appendChild($password);


        // Body
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // Main Request
        $baggageRQ = $dom->createElement('ns:AA_OTA_AirMealDetailsRQ');
        $body->appendChild($baggageRQ);

        // Add attributes from request
        $reqSpecific = $flight['req_specific'] ?? [];
        foreach ($reqSpecific as $attr => $value) {
            $baggageRQ->setAttribute($attr, $value);
        }

        // POS - you can adjust TerminalID, RequestorID etc.
        $pos = $dom->createElement('ns:POS');
        $baggageRQ->appendChild($pos);

        $source = $dom->createElement('ns:Source');
        $source->setAttribute('TerminalID', "{$this->username}/OTA");
        $pos->appendChild($source);

        $requestorID = $dom->createElement('ns:RequestorID');
        $requestorID->setAttribute('ID', $this->clientUserName);
        $requestorID->setAttribute('Type', '4');
        $source->appendChild($requestorID);

        $bookingChannel = $dom->createElement('ns:BookingChannel');
        $bookingChannel->setAttribute('Type', '12');
        $source->appendChild($bookingChannel);

        // BaggageDetailsRequests
        $baggageRequests = $dom->createElement('ns:MealDetailsRequests');
        $baggageRQ->appendChild($baggageRequests);

        // Loop through segments
        $legs = $flight['leg'] ?? [];
        if (isset($legs['flights'])) {
            foreach ($legs['flights'] as $flight) {
                foreach ($flight['segments'] as $segment) {
                    $baggageRequest = $dom->createElement('ns:MealDetailsRequest');
                    $baggageRequest->setAttribute('TravelerRefNumberRPHs', '');
                    $baggageRequests->appendChild($baggageRequest);

                    $segmentInfo = $dom->createElement('ns:FlightSegmentInfo');
                    $segmentInfo->setAttribute('ArrivalDateTime', $segment['arrival_at']);
                    $segmentInfo->setAttribute('DepartureDateTime', $segment['departure_at']);
                    $segmentInfo->setAttribute('FlightNumber', $segment['operating_carrier']['iata'] . $segment['flight_number']);
                    $segmentInfo->setAttribute('RPH', $segment['RPH']);
                    $segmentInfo->setAttribute('returnFlag', 'false');
                    $baggageRequest->appendChild($segmentInfo);

                    // Departure Airport
                    $dep = $dom->createElement('ns:DepartureAirport');
                    $dep->setAttribute('LocationCode', $segment['from']['iata']);
                    $dep->setAttribute('Terminal', '');
                    $segmentInfo->appendChild($dep);

                    // Arrival Airport
                    $arr = $dom->createElement('ns:ArrivalAirport');
                    $arr->setAttribute('LocationCode', $segment['to']['iata']);
                    $arr->setAttribute('Terminal', '');
                    $segmentInfo->appendChild($arr);

                    // Operating Airline
                    $op = $dom->createElement('ns:OperatingAirline');
                    $op->setAttribute('Code', $segment['operating_carrier']['iata'] ?? '');
                    $segmentInfo->appendChild($op);
                }
            }
        }

        // Output XML
        $xml = $dom->saveXML();
        Log::info($xml);
        try {
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->accessToken,
                'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                'X-AERO-JOURNEY-TYPE' => $this->journeyType,
                'X-AERO-USERID' => $this->username,
                'X-AERO-AGENT-CODE' => $this->agentCode,
            ];
            $headers['Cookie'] = $flightData['req_specific']['SetCookie'];
            $client = new Client([
                'base_uri' => $this->priceUrl,
                'verify' => false,
                'headers' => $headers,
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xml]);
            $header = $response->getHeaders();
            // $cookieHeader = $header['set-cookie'][0] ?? '';

            // // Extract JSESSIONID from cookie
            // if (preg_match('/JSESSIONID=([^;]+)/', $cookieHeader, $matches)) {
            //     $jsessionId = $matches[1];
            //     Log::info('Extracted JSESSIONID: ' . $jsessionId);
            //     $this->setJsessionId($jsessionId);
            // }
            $responseBody = (string) $response->getBody();

            // Log::info("OneApi Meals Response XML:\n" . $responseBody);

            // -----------------------------------------------------------------
            // 2. Parse XML
            // -----------------------------------------------------------------
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xml);
            $array = json_decode($json, true);


            return $array;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneApi Baggage API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract XML <Error> message if available
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneApi Error XML", ['error_parsed' => $arrayError]);

                    // Try to detect standard OneApi <Errors> or <Error> nodes
                    $errorMsg = $arrayError['Errors']['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['Message'] ??
                        json_encode($arrayError);

                    return response()->json([
                        'error' => 'OneApi API Error',
                        'message' => $errorMsg,
                        'raw' => $arrayError
                    ], 502);
                }
            }

            return response()->json([
                'error' => 'OneApi API Request Failed',
                'message' => $e->getMessage(),
            ], 502);

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("OneApi Booking Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => isset($bookingData) ? json_encode($bookingData) : null,
            ]);

            return response()->json([
                'error' => 'Booking request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function fetchSeats($request)
    {
        $flight = $request['flight'] ?? null;
        $flightData = $flight ?? null;
        if (!$flight) {
            Log::error("No flight data found.");
            return;
        }

        // Initialize DOM
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Envelope
        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $dom->appendChild($envelope);

        // Declare namespaces
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns', 'http://www.opentravel.org/OTA/2003/05');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsse', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:wsu', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd');


        // Namespaces
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns', 'http://www.opentravel.org/OTA/2003/05');

        // Header
        $header = $dom->createElement('soapenv:Header');
        $envelope->appendChild($header);

        $security = $dom->createElement('wsse:Security');
        $security->setAttribute('soapenv:mustUnderstand', '1');
        $header->appendChild($security);

        $usernameToken = $dom->createElement('wsse:UsernameToken');
        $usernameToken->setAttribute('wsu:Id', 'UsernameToken-' . uniqid());
        $security->appendChild($usernameToken);

        $username = $dom->createElement('wsse:Username', value: $this->clientUserName);
        $usernameToken->appendChild($username);

        $password = $dom->createElement('wsse:Password', $this->password);
        $password->setAttribute('Type', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText');
        $usernameToken->appendChild($password);


        // Body
        $body = $dom->createElement('soapenv:Body');
        $envelope->appendChild($body);

        // Main Request
        $baggageRQ = $dom->createElement('ns:OTA_AirSeatMapRQ');
        $body->appendChild($baggageRQ);

        // Add attributes from request
        $reqSpecific = $flight['req_specific'] ?? [];
        foreach ($reqSpecific as $attr => $value) {
            $baggageRQ->setAttribute($attr, $value);
        }

        // POS - you can adjust TerminalID, RequestorID etc.
        $pos = $dom->createElement('ns:POS');
        $baggageRQ->appendChild($pos);

        $source = $dom->createElement('ns:Source');
        $source->setAttribute('TerminalID', "{$this->username}/OTA");
        $pos->appendChild($source);

        $requestorID = $dom->createElement('ns:RequestorID');
        $requestorID->setAttribute('ID', $this->clientUserName);
        $requestorID->setAttribute('Type', '4');
        $source->appendChild($requestorID);

        $bookingChannel = $dom->createElement('ns:BookingChannel');
        $bookingChannel->setAttribute('Type', '12');
        $source->appendChild($bookingChannel);

        // BaggageDetailsRequests
        $baggageRequests = $dom->createElement('ns:SeatMapRequests');
        $baggageRQ->appendChild($baggageRequests);

        // Loop through segments
        $legs = $flight['leg'] ?? [];
        if (isset($legs['flights'])) {
            foreach ($legs['flights'] as $flight) {
                foreach ($flight['segments'] as $segment) {
                    $baggageRequest = $dom->createElement('ns:SeatMapRequest');
                    $baggageRequest->setAttribute('TravelerRefNumberRPHs', '');
                    $baggageRequests->appendChild($baggageRequest);

                    $segmentInfo = $dom->createElement('ns:FlightSegmentInfo');
                    $segmentInfo->setAttribute('ArrivalDateTime', $segment['arrival_at']);
                    $segmentInfo->setAttribute('DepartureDateTime', $segment['departure_at']);
                    $segmentInfo->setAttribute('FlightNumber', $segment['operating_carrier']['iata'] . $segment['flight_number']);
                    $segmentInfo->setAttribute('RPH', $segment['RPH']);
                    $segmentInfo->setAttribute('returnFlag', 'false');
                    $baggageRequest->appendChild($segmentInfo);

                    // Departure Airport
                    $dep = $dom->createElement('ns:DepartureAirport');
                    $dep->setAttribute('LocationCode', $segment['from']['iata']);
                    $dep->setAttribute('Terminal', '');
                    $segmentInfo->appendChild($dep);

                    // Arrival Airport
                    $arr = $dom->createElement('ns:ArrivalAirport');
                    $arr->setAttribute('LocationCode', $segment['to']['iata']);
                    $arr->setAttribute('Terminal', '');
                    $segmentInfo->appendChild($arr);

                    // Operating Airline
                    $op = $dom->createElement('ns:OperatingAirline');
                    $op->setAttribute('Code', $segment['operating_carrier']['iata'] ?? '');
                    $segmentInfo->appendChild($op);
                }
            }
        }

        // Output XML
        $xml = $dom->saveXML();
        // Log::info($xml);
        try {
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->accessToken,
                'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                'X-AERO-JOURNEY-TYPE' => $this->journeyType,
                'X-AERO-USERID' => $this->username,
                'X-AERO-AGENT-CODE' => $this->agentCode,
            ];
            $headers['Cookie'] = $flightData['req_specific']['SetCookie'];
            $client = new Client([
                'base_uri' => $this->priceUrl,
                'verify' => false,
                'headers' => $headers,
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xml]);
            $header = $response->getHeaders();
            // $cookieHeader = $header['set-cookie'][0] ?? '';

            // // Extract JSESSIONID from cookie
            // if (preg_match('/JSESSIONID=([^;]+)/', $cookieHeader, $matches)) {
            //     $jsessionId = $matches[1];
            //     Log::info('Extracted JSESSIONID: ' . $jsessionId);
            //     $this->setJsessionId($jsessionId);
            // }
            $responseBody = (string) $response->getBody();


            // -----------------------------------------------------------------
            // 2. Parse XML
            // -----------------------------------------------------------------
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xml);
            $array = json_decode($json, true);


            return $array;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneApi Baggage API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract XML <Error> message if available
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneApi Error XML", ['error_parsed' => $arrayError]);

                    // Try to detect standard OneApi <Errors> or <Error> nodes
                    $errorMsg = $arrayError['Errors']['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['Message'] ??
                        json_encode($arrayError);

                    return response()->json([
                        'error' => 'OneApi API Error',
                        'message' => $errorMsg,
                        'raw' => $arrayError
                    ], 502);
                }
            }

            return response()->json([
                'error' => 'OneApi API Request Failed',
                'message' => $e->getMessage(),
            ], 502);

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("OneApi Booking Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => isset($bookingData) ? json_encode($bookingData) : null,
            ]);

            return response()->json([
                'error' => 'Booking request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function priceWithBundle_ssr($data)
    {
        Log::info("Starting priceWithBundle_ssr with data: ");
        $quote = $data['quote'];
        if (is_string($quote)) {
            $quote = json_decode($quote, true);
        }
        $journeyType = '';
        $req = $quote['Body']['OTA_AirPriceRS']['@attributes'];
        $flight = $data['flight'];
        $flightData = $flight;
        $legs = $data['flight']['leg']['flights'];
        $extras = $data['selectedExtras'];
        $selectedFares = $data['selectedFares'] ?? [];
        $passengers = $this->extractQuotePassengers($quote, is_array($data) ? $data : []);
        $travelerRefs = $this->flattenTravelerRefs($passengers);
$flightCount = count($legs);

        if ($flightCount === 1) {
            $journeyType = 'ONEWAY';
            $directionInd = 'OneWay';
        } elseif ($flightCount === 2) {
            $journeyType = 'RETURN';
            $directionInd = 'Return';
        } else {
            $journeyType = 'MULTICITY';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        /* ===================== ENVELOPE ===================== */
        $envelope = $doc->createElementNS(
            'http://schemas.xmlsoap.org/soap/envelope/',
            'soap:Envelope'
        );
        $doc->appendChild($envelope);

        $envelope->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:wsse',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'
        );
        $envelope->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:wsu',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd'
        );
        $envelope->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:ns1',
            'http://www.opentravel.org/OTA/2003/05'
        );

        /* ===================== HEADER ===================== */
        // Header
        $header = $doc->createElement('soap:Header');
        $envelope->appendChild($header);

        $security = $doc->createElement('wsse:Security');
        $security->setAttribute('soap:mustUnderstand', '1');
        $header->appendChild($security);

        $usernameToken = $doc->createElement('wsse:UsernameToken');
        $usernameToken->setAttribute('wsu:Id', 'UsernameToken-' . uniqid());
        $security->appendChild($usernameToken);

        $username = $doc->createElement('wsse:Username', $this->clientUserName);
        $usernameToken->appendChild($username);

        $password = $doc->createElement('wsse:Password', $this->password);
        $password->setAttribute('Type', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText');
        $usernameToken->appendChild($password);


        /* ===================== BODY ===================== */
        $body = $doc->createElement('soap:Body');
        $envelope->appendChild($body);

        $rq = $doc->createElement('ns1:OTA_AirPriceRQ');
        $body->appendChild($rq);

        foreach ($req as $k => $v) {
            if ($k !== 'SetCookie') {
                $rq->setAttribute($k, $v);
            }
        }

        /* ===================== POS ===================== */
        $pos = $doc->createElement('ns1:POS');
        $rq->appendChild($pos);

        $source = $doc->createElement('ns1:Source');
        $source->setAttribute('TerminalID', "{$this->username}/OTA");
        $pos->appendChild($source);

        $requestorID = $doc->createElement('ns1:RequestorID');
        $requestorID->setAttribute('ID', $this->clientUserName);
        $requestorID->setAttribute('Type', '4');
        $source->appendChild($requestorID);

        $bookingChannel = $doc->createElement('ns1:BookingChannel');
        $bookingChannel->setAttribute('Type', '12');
        $source->appendChild($bookingChannel);

        /* ===================== AIR ITINERARY ===================== */
        $itin = $doc->createElement('ns1:AirItinerary');
        $itin->setAttribute('DirectionInd', count($legs) > 1 ? 'Return' : 'OneWay');
        $rq->appendChild($itin);

        $ods = $doc->createElement('ns1:OriginDestinationOptions');
        $itin->appendChild($ods);

        foreach ($legs as $flight) {
            $odo = $doc->createElement('ns1:OriginDestinationOption');
            $ods->appendChild($odo);

            foreach ($flight['segments'] as $seg) {
                $fs = $doc->createElement('ns1:FlightSegment');
                $fs->setAttribute('ArrivalDateTime', $seg['arrival_at']);
                $fs->setAttribute('DepartureDateTime', $seg['departure_at']);
                $fs->setAttribute('FlightNumber', $seg['operating_carrier']['iata'] . $seg['flight_number']);
                $fs->setAttribute('RPH', $seg['RPH']);
                $fs->setAttribute('returnFlag', 'false');
                $odo->appendChild($fs);

                $dep = $doc->createElement('ns1:DepartureAirport');
                $dep->setAttribute('LocationCode', $seg['from']['iata']);
                $fs->appendChild($dep);

                $arr = $doc->createElement('ns1:ArrivalAirport');
                $arr->setAttribute('LocationCode', $seg['to']['iata']);
                $fs->appendChild($arr);

                $op = $doc->createElement('ns1:OperatingAirline');
                $op->setAttribute('Code', $seg['operating_carrier']['iata']);
                $fs->appendChild($op);
            }
        }

        /* ===================== TRAVELERS ===================== */
        $tis = $doc->createElement('ns1:TravelerInfoSummary');
        $rq->appendChild($tis);

        $avail = $doc->createElement('ns1:AirTravelerAvail');
        $tis->appendChild($avail);

        foreach ($passengers as $passenger) {
            $avail->appendChild(
                $this->makePTQ($doc, $passenger['code'], (string) $passenger['quantity'])
            );
        }

        $bundledServices = $doc->createElement("ns1:BundledServiceSelectionOptions");
        $rq->appendChild($bundledServices);

        $bundleIds = [];

        foreach ($legs as $index => $flight) {
            foreach ($flight['fares'] as $fare) {

                if (
                    in_array($fare['ref_id'], $selectedFares, true) &&
                    !empty($fare['bundledServiceId'])
                ) {
                    $bundleIds[$index] = $fare['bundledServiceId'];
                }
            }
        }

        if ($journeyType === 'ONEWAY' && isset($bundleIds[0])) {
            $bundledServices->appendChild(
                $doc->createElement("ns1:OutBoundBunldedServiceId", $bundleIds[0])
            );
        }

        if ($journeyType === 'RETURN') {
            if (isset($bundleIds[0])) {
                $bundledServices->appendChild(
                    $doc->createElement("ns1:OutBoundBunldedServiceId", $bundleIds[0])
                );
            }
            if (isset($bundleIds[1])) {
                $bundledServices->appendChild(
                    $doc->createElement("ns1:InBoundBunldedServiceId", $bundleIds[1])
                );
            }
        }

        if ($journeyType === 'MULTICITY') {
            foreach ($bundleIds as $bundleId) {
                $bundledServices->appendChild(
                    $doc->createElement("ns1:BunldedServiceId", $bundleId)
                );
            }
        }

        $special = $doc->createElement('ns1:SpecialReqDetails');
        $tis->appendChild($special);

        /* ===================== BAGGAGE ===================== */
        if (!empty($extras)) {
            $bagReqs = $doc->createElement('ns1:BaggageRequests');
            $special->appendChild($bagReqs);

            foreach ($extras as $leg) {
                foreach ($leg['baggage'] ?? [] as $rph => $bags) {
                    foreach ($bags as $bgindex => $bag) {
                        $br = $doc->createElement('ns1:BaggageRequest');
                        $br->setAttribute('baggageCode', $bag['baggageCode']);
                        $br->setAttribute('TravelerRefNumberRPHList', $this->resolveTravelerRef($bag, $bgindex, $travelerRefs));
                        $br->setAttribute('FlightRefNumberRPHList', $rph);
                        $bagReqs->appendChild($br);
                    }
                }
            }
        }

        /* ===================== SEATS ===================== */
        $seatReqs = $doc->createElement('ns1:SeatRequests');
        $special->appendChild($seatReqs);

        foreach ($extras as $leg) {
            foreach ($leg['seat'] ?? [] as $rph => $seats) {
                foreach ($seats as $sIndex => $seat) {
                    $sr = $doc->createElement('ns1:SeatRequest');
                    $sr->setAttribute('SeatNumber', $seat['row'] . $seat['seatNumber']);
                    $sr->setAttribute('TravelerRefNumberRPHList', $this->resolveTravelerRef($seat, $sIndex, $travelerRefs));
                    $sr->setAttribute('FlightRefNumberRPHList', $rph);
                    $seatReqs->appendChild($sr);
                }
            }
        }
        /* ===================== MEALS ===================== */
        if (!empty($extras)) {

            $mealReqs = $doc->createElement('ns1:MealRequests');
            $special->appendChild($mealReqs);

            foreach ($extras as $leg) {

                foreach ($leg['meal'] ?? [] as $rph => $meals) {

                    // Parse RPH
                    $parts = explode('$', $rph);

                    $flightNumber = $parts[2] ?? '';
                    $departureRaw = $parts[3] ?? '';

                    // Convert departure date
                    $departureDate = '';
                    if ($departureRaw) {
                        $departureDate = \Carbon\Carbon::createFromFormat('YmdHis', $departureRaw)
                            ->format('Y-m-d\TH:i:s');
                    }

                    foreach ($meals as $index => $meal) {

                        $mr = $doc->createElement('ns1:MealRequest');

                        $mr->setAttribute('mealCode', $meal['mealCode']);
                        $mr->setAttribute('mealQuantity', $meal['quantity'] ?? 1);
                        $mr->setAttribute('TravelerRefNumberRPHList', $this->resolveTravelerRef($meal, $index, $travelerRefs));
                        $mr->setAttribute('FlightRefNumberRPHList', $rph);

                        $mealReqs->appendChild($mr);
                    }
                }
            }
        }
        
        $xml = $doc->saveXML();
        Log::info("OneApi Price with Bundle SSR Request XML:\n" . $xml);
        try {
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
            ];
            $headers['Cookie'] = $flightData['req_specific']['SetCookie'];
            $client = new Client([
                'base_uri' => $this->priceUrl,
                'verify' => false,
                'headers' => $headers,
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xml]);
            $header = $response->getHeaders();
            // $cookieHeader = $header['set-cookie'][0] ?? '';

            // // Extract JSESSIONID from cookie
            // if (preg_match('/JSESSIONID=([^;]+)/', $cookieHeader, $matches)) {
            //     $jsessionId = $matches[1];
            //     Log::info('Extracted JSESSIONID: ' . $jsessionId);
            //     $this->setJsessionId($jsessionId);
            // }
            $responseBody = (string) $response->getBody();


            // -----------------------------------------------------------------
            // 2. Parse XML
            // -----------------------------------------------------------------
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xml);
            $array = json_decode($json, true);

            Log::info($array);
            if (
                
                isset($array['Errors']['Error']) 
            ) {
                return [
                    'error' => 'OneApi API Error',
                    'message' => $this->extractErrorMessage($array),
                    'raw' => $array,
                ];
            }
            return $array;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneApi API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract XML <Error> message if available
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneApi Error XML", ['error_parsed' => $arrayError]);

                    return [
                        'error' => 'OneApi API Error',
                        'message' => $this->extractErrorMessage($arrayError),
                        'raw' => $arrayError
                    ];
                }
            }

            return [
                'error' => 'OneApi API Request Failed',
                'message' => $e->getMessage(),
            ];

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("OneApi Booking Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => isset($bookingData) ? json_encode($bookingData) : null,
            ]);

            return [
                'error' => 'Booking request failed',
                'message' => $e->getMessage(),
            ];
        }
    }

     public function bookFlight($request)
    {
        Log::info($request);
        $flight = $request['flight'];
        $req = $flight['req_specific'];
        $ancillariesResponse = $request['ancillariesResponse'] ?? [];

        if (is_string($ancillariesResponse)) {
            $ancillariesResponse = json_decode($ancillariesResponse, true) ?? [];
        }

        $ancillaryPassengers = is_array($ancillariesResponse)
            ? $this->extractQuotePassengers($ancillariesResponse, is_array($request) ? $request : [])
            : [];
        $travelerRefs = $this->flattenTravelerRefs($ancillaryPassengers);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        /* ===================== SOAP ENVELOPE ===================== */
        $env = $dom->createElementNS(
            'http://schemas.xmlsoap.org/soap/envelope/',
            'soap:Envelope'
        );
        $dom->appendChild($env);

        // Do NOT declare ns1 & ns2 here — declare them on Body instead

        /* ===================== HEADER ===================== */
        $header = $dom->createElement('soap:Header');
        $env->appendChild($header);

        $sec = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'wsse:Security'
        );
        $sec->setAttribute('soap:mustUnderstand', '1');
        $header->appendChild($sec);

        $token = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'wsse:UsernameToken'
        );
        $token->setAttributeNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
            'wsu:Id',
            'UsernameToken-' . uniqid()
        );
        $sec->appendChild($token);

        $token->appendChild($dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'wsse:Username',
            $this->clientUserName // Use your actual username variable
        ));

        $pwd = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'wsse:Password',
            $this->password
        );
        $pwd->setAttribute(
            'Type',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText'
        );
        $token->appendChild($pwd);

        /* ===================== BODY ===================== */
        $body = $dom->createElement('soap:Body');

        // Declare namespaces on Body (matches working example)
        $body->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns2', 'http://www.opentravel.org/OTA/2003/05');
        $body->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ns1', 'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05');
        $env->appendChild($body);

        $bookRQ = $dom->createElementNS(
            'http://www.opentravel.org/OTA/2003/05',
            'ns2:OTA_AirBookRQ'
        );

        // Copy all attributes from $req (EchoToken, TransactionIdentifier, etc.)
        foreach ($req as $k => $v) {
            if ($k !== 'SetCookie' && $k !== 'RetransmissionIndicator') {
                $bookRQ->setAttribute($k, $v);
            }
        }
        // Add required TimeStamp if missing
        if (!$bookRQ->hasAttribute('TimeStamp')) {
            $bookRQ->setAttribute('TimeStamp', date('c'));
        }

        $body->appendChild($bookRQ);

        /* ===================== POS ===================== */
        $pos = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:POS');
        $bookRQ->appendChild($pos);

        $src = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:Source');
        $src->setAttribute('TerminalID', $this->clientUserName . '/' . $this->getSalesChannel()); // Important: matches working example
        $pos->appendChild($src);

        $rid = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:RequestorID');
        $rid->setAttribute('ID', $this->clientUserName); // Use actual username
        $rid->setAttribute('Type', '4');
        $src->appendChild($rid);

        $bc = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:BookingChannel');
        $bc->setAttribute('Type', '12');
        $src->appendChild($bc);

        /* ===================== AIR ITINERARY ===================== */
        $itin = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:AirItinerary');
        $bookRQ->appendChild($itin);

        $ods = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:OriginDestinationOptions');
        $itin->appendChild($ods);

        foreach ($flight['leg']['flights'] as $flightItem) {
            $odo = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:OriginDestinationOption');
            $ods->appendChild($odo);

            foreach ($flightItem['segments'] as $seg) {
                $fs = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:FlightSegment');
                $fs->setAttribute('DepartureDateTime', $seg['departure_at']);
                $fs->setAttribute('ArrivalDateTime', $seg['arrival_at']);
                $fs->setAttribute('FlightNumber', $seg['operating_carrier']['iata'] . $seg['flight_number']);
                $fs->setAttribute('RPH', $seg['RPH']);
                $fs->setAttribute('returnFlag', 'false');

                $dep = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:DepartureAirport');
                $dep->setAttribute('LocationCode', $seg['from']['iata']);
                $fs->appendChild($dep);

                $arr = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:ArrivalAirport');
                $arr->setAttribute('LocationCode', $seg['to']['iata']);
                $fs->appendChild($arr);

                $op = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:OperatingAirline');
                $op->setAttribute('Code', $seg['operating_carrier']['iata']);
                $fs->appendChild($op);

                $odo->appendChild($fs);
            }
        }

        /* ===================== TRAVELER INFO ===================== */
        $travInfo = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:TravelerInfo');
        $bookRQ->appendChild($travInfo);

        foreach ($request['travellers'] as $i => $t) {
            $trav = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:AirTraveler');
            $trav->setAttribute('PassengerTypeCode', $t['type'] == 'CNN' ? 'CHD' : $t['type']);
            $trav->setAttribute('BirthDate', $t['dob'] . 'T00:00:00');
            $travelerRef = $travelerRefs[$i] ?? ('A' . ($i + 1));

            // PersonName
            $pn = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:PersonName');
            $pn->appendChild($dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:GivenName', $t['firstName']));
            $pn->appendChild($dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:Surname', $t['lastName']));
            $pn->appendChild($dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:NameTitle', strtoupper($t['title'])));
            $trav->appendChild($pn);

            // Telephone (required in working example)
            $tel = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:Telephone');
            $tel->setAttribute('CountryAccessCode', '92'); // Adjust if needed
            $tel->setAttribute('PhoneNumber', $request['main_contact']['phone']);
            $trav->appendChild($tel);

            // Address (recommended)
            $addr = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'Address');
            $country = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'CountryName');
            $country->setAttribute('Code', 'PK'); // Pakistan
            $addr->appendChild($country);
            $trav->appendChild($addr);

            // Document (passport/NIC – required in working example)
            $doc = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:Document');
            $doc->setAttribute('DocHolderNationality', 'PK');
            $docHolder = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:DocHolderName', $t['firstName'] . ' ' . $t['lastName']);
            $doc->appendChild($docHolder);
            // Add DocID, DocType, ExpireDate if you have them
            $trav->appendChild($doc);

            $ref = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns2:TravelerRefNumber');
            $ref->setAttribute('RPH', $travelerRef);
            $trav->appendChild($ref);

            $travInfo->appendChild($trav);
        }

        /* ===================== AAAirBookRQExt ===================== */
        $ext = $dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns1:AAAirBookRQExt'
        );
        $body->appendChild($ext);

        $ci = $dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:ContactInfo');
        $ext->appendChild($ci);

        // Contact Person (usually first traveler)
        $pn = $dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:PersonName');
        $pn->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:Title', strtoupper($request['travellers'][0]['title'])));
        $pn->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:FirstName', $request['travellers'][0]['firstName']));
        $pn->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:LastName', $request['travellers'][0]['lastName']));
        $ci->appendChild($pn);

        // Telephone
        $tel = $dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:Telephone');
        $tel->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:PhoneNumber', $request['main_contact']['phone']));
        $tel->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:CountryCode', '92'));
        $ci->appendChild($tel);

        // Email
        $ci->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:Email', $request['main_contact']['email']));

        // Address (required in working example)
        $addr = $dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:Address');
        $countryName = $dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:CountryName');
        $countryName->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:CountryName', 'Pakistan'));
        $countryName->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:CountryCode', 'PK'));
        $addr->appendChild($countryName);
        $addr->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:StateCode', 'Sindh')); // Adjust
        $addr->appendChild($dom->createElementNS('http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05', 'ns1:CityName', 'Karachi')); // Adjust
        $ci->appendChild($addr);

        /* ===================== FINAL XML ===================== */
        $xml = $dom->saveXML();
        Log::info("SOAP BOOK REQUEST:");
        Log::info($xml);
        $journeyType = '';
        $flights = $flight['leg']['flights'];
        $flightCount = count($flights);

        if ($flightCount === 1) {
            $journeyType = 'ONEWAY';
            $directionInd = 'OneWay';
        } elseif ($flightCount === 2) {
            $journeyType = 'RETURN';
            $directionInd = 'Return';
        } else {
            $journeyType = 'MULTICITY';
            $directionInd = 'MultiCity';
        }

        // -----------------------------------------------------------------
        try {
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                'X-AERO-USERID' => $this->username,
                'X-AERO-AGENT-CODE' => $this->agentCode,
                'X-AERO-JOURNEY-TYPE' => $journeyType,
            ];
            $headers['Cookie'] = $flight['req_specific']['SetCookie'] ?? '';
            Log::info(json_encode($headers));

            $client = new Client([
                'base_uri' => $this->priceUrl,
                'verify' => false,
                'headers' => $headers,
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xml]);
            $responseBody = (string) $response->getBody();

            Log::info("OneApi Booking Response XML:\n" . $responseBody);

            // -----------------------------------------------------------------
            // 2. Parse XML
            // -----------------------------------------------------------------
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xml);
            $array = json_decode($json, true);

            Log::info($json);
            if (
                                isset($array['Errors']['Error']) 

            ) {
                return [
                    'error' => 'OneApi Booking Error',
                    'message' => $this->extractErrorMessage($array),
                    'raw' => $array,
                ];
            }
            return $array;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneApi Booking API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract XML <Error> message if available
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneApi Error XML", ['error_parsed' => $arrayError]);

                    return [
                        'error' => 'OneApi API Error',
                        'message' => $this->extractErrorMessage($arrayError),
                        'raw' => $arrayError
                    ];
                }
            }

            return [
                'error' => 'OneApi API Request Failed',
                'message' => $e->getMessage(),
            ];

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("OneApi Booking Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => isset($bookingData) ? json_encode($bookingData) : null,
            ]);

            return [
                'error' => 'Booking request failed',
                'message' => $e->getMessage(),
            ];
        }
    }
    public function confirmTicket($request)
    {
        Log::info($request);

        $pnrData = $request['pnrData']['Body']['OTA_AirBookRS'] ?? [];
        $bookingReferenceID = $pnrData['AirReservation']['BookingReferenceID']['@attributes']['ID'] ?? $request['pnr'] ?? '';
        $readBookingResponse = $this->readBooking($bookingReferenceID);
        Log::info("Read Booking Response for PNR $bookingReferenceID:".json_encode($readBookingResponse));
        $pnrData = $readBookingResponse['Body']['OTA_AirBookRS'] ?? [];
        $totalFare = $pnrData['AirReservation']['PriceInfo']['ItinTotalFare']['TotalFare']['@attributes'] ?? [];
        $readBookingData = is_array($readBookingResponse) ? ($readBookingResponse['data'] ?? []) : [];
        $refreshedSessionCookie = is_array($readBookingResponse) ? ($readBookingResponse['session_cookie'] ?? null) : null;

        if (!empty($readBookingData['Body']['OTA_AirBookRS'])) {
            $pnrData = $readBookingData['Body']['OTA_AirBookRS'];
        }

        // Extract payment info from booking data
        $totalFare = $pnrData['AirReservation']['PriceInfo']['ItinTotalFare']['TotalFare']['@attributes'] ?? $totalFare;
        $paymentAmount = $totalFare['Amount'] ?? '0';
        $currencyCode = $totalFare['CurrencyCode'] ?? 'PKR';
        $decimalPlaces = $totalFare['DecimalPlaces'] ?? '2';

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        /* ===================== SOAP ENVELOPE ===================== */
        $env = $dom->createElementNS(
            'http://schemas.xmlsoap.org/soap/envelope/',
            'soapenv:Envelope'
        );
        $dom->appendChild($env);

        /* ===================== HEADER ===================== */
        $header = $dom->createElement('soapenv:Header');
        $env->appendChild($header);

        $sec = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'ns2:Security'
        );
        $header->appendChild($sec);

        $token = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'ns2:UsernameToken'
        );
        $token->setAttributeNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
            'ns3:Id',
            'UsernameToken-' . uniqid()
        );
        $sec->appendChild($token);

        $token->appendChild($dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'ns2:Username',
            $this->clientUserName
        ));

        $pwd = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'ns2:Password',
            $this->password
        );
        $token->appendChild($pwd);

        /* ===================== BODY ===================== */
        $body = $dom->createElement('soapenv:Body');
        $env->appendChild($body);

        /* ===================== OTA_AirBookModifyRQ ===================== */
        $bookModifyRQ = $dom->createElementNS(
            'http://www.opentravel.org/OTA/2003/05',
            'ns8:OTA_AirBookModifyRQ'
        );
        // Set attributes from the original booking request
        $originalReq = $pnrData['@attributes'] ?? [];
        $bookModifyRQ->setAttribute('EchoToken', $originalReq['EchoToken'] ?? uniqid());
        $bookModifyRQ->setAttribute('SequenceNmbr', $originalReq['SequenceNmbr'] ?? '1');
        $bookModifyRQ->setAttribute('TransactionIdentifier', $originalReq['TransactionIdentifier']);
        $bookModifyRQ->setAttribute('Version', $originalReq['Version'] ?? '20061.0');

        $body->appendChild($bookModifyRQ);

        /* ===================== POS ===================== */
        $pos = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:POS');
        $bookModifyRQ->appendChild($pos);

        $source = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:Source');
        $pos->appendChild($source);

        $requestorID = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:RequestorID');
        $requestorID->setAttribute('ID', $this->clientUserName);
        $requestorID->setAttribute('Type', '4');
        $source->appendChild($requestorID);

        $bookingChannel = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:BookingChannel');
        $bookingChannel->setAttribute('Type', '12');
        $source->appendChild($bookingChannel);

        /* ===================== AirBookModifyRQ ===================== */
        $airBookModify = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:AirBookModifyRQ');
        $airBookModify->setAttribute('ModificationType', '9'); // 9 = Issue Ticket
        $bookModifyRQ->appendChild($airBookModify);

        /* ===================== Fulfillment ===================== */
        $fulfillment = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:Fulfillment');
        $airBookModify->appendChild($fulfillment);

        $paymentDetails = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:PaymentDetails');
        $fulfillment->appendChild($paymentDetails);

        $paymentDetail = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:PaymentDetail');
        $paymentDetails->appendChild($paymentDetail);

        $directBill = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:DirectBill');
        $paymentDetail->appendChild($directBill);

        $companyName = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:CompanyName');
        $companyName->setAttribute('Code', $this->agentCode);
        $directBill->appendChild($companyName);

        $paymentAmountElem = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:PaymentAmount');
        $paymentAmountElem->setAttribute('Amount', number_format($paymentAmount, 2, '.', ''));
        $paymentAmountElem->setAttribute('CurrencyCode', $currencyCode);
        $paymentAmountElem->setAttribute('DecimalPlaces', $decimalPlaces);
        $paymentDetail->appendChild($paymentAmountElem);

        /* ===================== BookingReferenceID ===================== */
        $bookingRef = $dom->createElementNS('http://www.opentravel.org/OTA/2003/05', 'ns8:BookingReferenceID');
        $bookingRef->setAttribute('ID', $bookingReferenceID);
        $bookingRef->setAttribute('Type', '14'); // 14 = Booking Reference
        $airBookModify->appendChild($bookingRef);

        /* ===================== AAAirBookModifyRQExt ===================== */
        $ext = $dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns7:AAAirBookModifyRQExt'
        );
        $body->appendChild($ext);

        $loadDataOptions = $dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns7:AALoadDataOptions'
        );
        $ext->appendChild($loadDataOptions);

        $loadDataOptions->appendChild($dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns7:LoadTravelerInfo',
            'true'
        ));

        $loadDataOptions->appendChild($dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns7:LoadAirItinery',
            'true'
        ));

        $loadDataOptions->appendChild($dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns7:LoadPriceInfoTotals',
            'true'
        ));

        $loadDataOptions->appendChild($dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns7:LoadFullFilment',
            'true'
        ));

        /* ===================== FINAL XML ===================== */
        $xml = $dom->saveXML();
        Log::info("SOAP BOOK MODIFY REQUEST (Ticket Issuance):");
        Log::info($xml);

        // Determine journey type
        $journeyType = '';
        $flightLegs = $pnrData['AirReservation']['AirItinerary']['OriginDestinationOptions']['OriginDestinationOption'] ?? [];

        if (isset($flightLegs['FlightSegment']) && !isset($flightLegs[0])) {
            // Single flight segment
            $journeyType = 'ONEWAY';
        } elseif (is_array($flightLegs) && count($flightLegs) > 1) {
            // Check if it's return or multi-city
            $journeyType = 'RETURN';
        } else {
            $journeyType = 'ONEWAY';
        }
        // -----------------------------------------------------------------
        try {
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                'X-AERO-USERID' => $this->username,
                'X-AERO-AGENT-CODE' => $this->agentCode,
                'X-AERO-JOURNEY-TYPE' => $journeyType,
                'SOAPAction' => 'modifyReservation',
            ];

            // Add cookie if available from original request
            if ($refreshedSessionCookie) {
                $headers['Cookie'] = $refreshedSessionCookie;
            } elseif ($this->getJsessionId()) {
                $headers['Cookie'] = $this->getJsessionId();
            } elseif (isset($request['pnrData']['Body']['OTA_AirBookRS']['@attributes']['SetCookie'])) {
                $headers['Cookie'] = $request['pnrData']['Body']['OTA_AirBookRS']['@attributes']['SetCookie'];
            }

            Log::info('Request Headers:', $headers);

            $client = new Client([
                'base_uri' => $this->priceUrl,
                'verify' => false,
                'headers' => $headers,
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xml]);
            $responseHeaders = $response->getHeaders();
            $confirmSessionCookie = $this->extractSessionCookieFromHeaders($responseHeaders);
            if ($confirmSessionCookie) {
                $this->setJsessionId($confirmSessionCookie);
            }
            $responseBody = (string) $response->getBody();

            Log::info("OneApi Booking Modify Response XML (Ticket Issuance):\n" . $responseBody);

            // -----------------------------------------------------------------
            // 2. Parse XML
            // -----------------------------------------------------------------
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);

            if ($xml === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return null;
            }

            $json = json_encode($xml);
            $array = json_decode($json, true);

            Log::info("Parsed Booking Modify Response:", $array);

            // Some OneApi failures come back as OTA_AirBookRS even during confirm/modify.
            if (
                isset($array['Body']['OTA_AirBookRS']['Errors']) ||
                isset($array['Body']['OTA_AirBookRS']['Errors']['Error'])
            ) {
                return null;
            }



            return $array;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("OneApi Booking Modify API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            // Try to extract error message from XML response
            if ($errorBody) {
                $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $errorBody);
                libxml_use_internal_errors(true);
                $xmlError = simplexml_load_string($cleanBody);

                if ($xmlError !== false) {
                    $jsonError = json_encode($xmlError);
                    $arrayError = json_decode($jsonError, true);

                    Log::error("Parsed OneApi Error XML", ['error_parsed' => $arrayError]);

                    $errorMsg = $this->extractErrorMessage($arrayError);

                    return  null;
                }
            }

            return null;

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("OneApi Booking Modify Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => json_encode($request),
            ]);

            return null;
        }
    }

    /**
     * Extract error message from response array
     */
    private function extractErrorMessage($array)
    {
        $error = $array['Body']['OTA_AirPriceRS']['Errors']['Error'] ??
            $array['Body']['OTA_AirBookModifyRS']['Errors']['Error'] ??
            $array['Body']['OTA_AirBookRS']['Errors']['Error'] ??
            $array['Errors']['Error'] ??
            $array['Error'] ??
            null;

        if (is_array($error) && array_is_list($error)) {
            $error = $error[0] ?? null;
        }

        return $array['Body']['OTA_AirBookModifyRS']['Errors']['Error']['@attributes']['ShortText'] ??
            $array['Body']['OTA_AirBookModifyRS']['Errors']['Error']['@attributes']['Message'] ??
            $array['Body']['OTA_AirPriceRS']['Errors']['Error']['@attributes']['ShortText'] ??
            $array['Body']['OTA_AirPriceRS']['Errors']['Error']['@attributes']['Message'] ??
            $array['Body']['OTA_AirBookRS']['Errors']['Error']['@attributes']['ShortText'] ??
            $array['Body']['OTA_AirBookRS']['Errors']['Error']['@attributes']['Message'] ??
            $array['Body']['Fault']['faultstring'] ??
            $error['@attributes']['ShortText'] ??
            $error['@attributes']['Message'] ??
            'Unknown error occurred';
    }

    /**
     * Update booking status in database
     */
    public function readBooking($pnr)
    {
        Log::info("Read Booking Request for PNR: " . $pnr);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        /* ===================== SOAP ENVELOPE ===================== */
        $env = $dom->createElementNS(
            'http://schemas.xmlsoap.org/soap/envelope/',
            'soapenv:Envelope'
        );
        $dom->appendChild($env);

        /* ===================== HEADER ===================== */
        $header = $dom->createElement('soapenv:Header');
        $env->appendChild($header);

        $security = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'wsse:Security'
        );
        $security->setAttribute('soapenv:mustUnderstand', '1');
        $header->appendChild($security);

        $token = $dom->createElementNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd',
            'wsse:UsernameToken'
        );
        $token->setAttributeNS(
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd',
            'wsu:Id',
            'UsernameToken-' . rand(10000000, 99999999)
        );
        $security->appendChild($token);

        $token->appendChild($dom->createElement('wsse:Username', $this->clientUserName));

        $password = $dom->createElement('wsse:Password', $this->password);
        $password->setAttribute(
            'Type',
            'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText'
        );
        $token->appendChild($password);

        /* ===================== BODY ===================== */
        $body = $dom->createElement('soapenv:Body');
        $env->appendChild($body);

        /* ===================== OTA_ReadRQ ===================== */
        $readRQ = $dom->createElementNS(
            'http://www.opentravel.org/OTA/2003/05',
            'ns2:OTA_ReadRQ'
        );

        $readRQ->setAttribute('EchoToken', uniqid());
        $readRQ->setAttribute('PrimaryLangID', 'en-us');
        $readRQ->setAttribute('SequenceNmbr', '1');
        $readRQ->setAttribute('TimeStamp', now()->toIso8601String());
        $readRQ->setAttribute('Version', '20061.00');

        $body->appendChild($readRQ);

        /* ===================== POS ===================== */
        $pos = $dom->createElement('ns2:POS');
        $readRQ->appendChild($pos);

        $source = $dom->createElement('ns2:Source');
        $source->setAttribute('TerminalID', $this->username . '/' . $this->getSalesChannel());
        $pos->appendChild($source);

        $requestorID = $dom->createElement('ns2:RequestorID');
        $requestorID->setAttribute('ID', $this->clientUserName);
        $requestorID->setAttribute('Type', '4');
        $source->appendChild($requestorID);

        $bookingChannel = $dom->createElement('ns2:BookingChannel');
        $bookingChannel->setAttribute('Type', '12');
        $source->appendChild($bookingChannel);

        /* ===================== ReadRequests ===================== */
        $readRequests = $dom->createElement('ns2:ReadRequests');
        $readRQ->appendChild($readRequests);

        $readRequest = $dom->createElement('ns2:ReadRequest');
        $readRequests->appendChild($readRequest);

        $uniqueID = $dom->createElement('ns2:UniqueID');
        $uniqueID->setAttribute('ID', $pnr);
        $uniqueID->setAttribute('Type', '14');
        $readRequest->appendChild($uniqueID);

        /* ===================== Extension ===================== */
        $ext = $dom->createElementNS(
            'http://www.isaaviation.com/thinair/webservices/OTA/Extensions/2003/05',
            'ns1:AAReadRQExt'
        );
        $body->appendChild($ext);

        $loadDataOptions = $dom->createElement('ns1:AALoadDataOptions');
        $ext->appendChild($loadDataOptions);

        $loadDataOptions->appendChild($dom->createElement('ns1:LoadTravelerInfo', 'true'));
        $loadDataOptions->appendChild($dom->createElement('ns1:LoadAirItinery', 'true'));
        $loadDataOptions->appendChild($dom->createElement('ns1:LoadPriceInfoTotals', 'true'));
        $loadDataOptions->appendChild($dom->createElement('ns1:LoadFullFilment', 'true'));

        /* ===================== FINAL XML ===================== */
        $xml = $dom->saveXML();
        Log::info("SOAP READ REQUEST:");
        Log::info($xml);

        try {
            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'X-AERO-SALES-CHANNEL' => $this->getSalesChannel(),
                'X-AERO-USERID' => $this->username,
                'X-AERO-AGENT-CODE' => $this->agentCode,
            ];

            $client = new Client([
                'base_uri' => $this->priceUrl,
                'verify' => false,
                'headers' => $headers,
            ]);

            $response = $client->post('', ['body' => $xml]);
            $responseHeaders = $response->getHeaders();
            $sessionCookie = $this->extractSessionCookieFromHeaders($responseHeaders);
            if ($sessionCookie) {
                $this->setJsessionId($sessionCookie);
            }
            $responseBody = (string) $response->getBody();

            Log::info("Read Booking Response XML:\n" . $responseBody);

            /* ===================== Parse XML ===================== */
            $cleanBody = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            $xml = simplexml_load_string($cleanBody);

            $json = json_encode($xml);
            $array = json_decode($json, true);

            Log::info("Parsed Read Booking Response:". json_encode($array));
           
             return [
                'success' => true,
                'data' => $array,
                'session_cookie' => $sessionCookie,
            ];
        

        } catch (\Exception $e) {
            Log::error("Read Booking Error: " . $e->getMessage());

            return response()->json([
                'error' => 'Failed to read booking',
                'message' => $e->getMessage()
            ], 500);
        }
    }



}
