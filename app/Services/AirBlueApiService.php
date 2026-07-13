<?php

namespace App\Services;

use Cache;
use Carbon\Carbon;
use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Log;
use Noki\XmlConverter\Convert;
use SimpleXMLElement;
use SoapBox\Formatter\Formatter;

class AirBlueApiService
{
    protected string $endpoint;
    protected string $target;
    protected string $version;
    protected string $clientId;
    protected string $clientKey;
    protected string $agentType;
    protected string $agentId;
    protected string $agentPassword;
    protected string $certPassword;
    protected string $certPath;

    public function __construct()
    {
        $config = config('airblue');

        $this->endpoint = $config['endpoint']['search'];
        $this->target = $config['service']['target'];
        $this->version = $config['service']['version'];

        $this->clientId = $config['api_client']['id'];
        $this->clientKey = $config['api_client']['key'];

        $this->agentType = $config['agent']['type'];
        $this->agentId = $config['agent']['id'];
        $this->agentPassword = $config['agent']['password'];


        $this->certPath = storage_path('certs/cert.pem');  // centralized here
    }





    public function searchFlights($params): string
    {

        $paxMapping = [
            'adults' => 'ADT',
            'children' => 'CHD',
            'infants' => 'INF',
        ];

        // ✅ Build passenger info XML
        $passengerXML = '';
        foreach ($paxMapping as $key => $code) {
            $count = (int) ($params[$key] ?? 0);
            if ($count > 0) {
                $passengerXML .= "<PassengerTypeQuantity Code=\"{$code}\" Quantity=\"{$count}\"/>";
            }
        }

        $originDestXML = '';

        // ✅ Handle Multi-City flights
        if (!empty($params['flight_type']) && $params['flight_type'] === 'multi-city' && !empty($params['trips'])) {
            $rph = 1;
            foreach ($params['trips'] as $trip) {
                $date = $trip['date'] ?? null;
                $origin = $trip['origin'] ?? null;
                $destination = $trip['destination'] ?? null;

                if ($date && $origin && $destination) {
                    $originDestXML .= <<<XML
                    <OriginDestinationInformation RPH="{$rph}">
                        <DepartureDateTime>{$date}T00:00:00Z</DepartureDateTime>
                        <OriginLocation LocationCode="{$origin}"/>
                        <DestinationLocation LocationCode="{$destination}"/>
                    </OriginDestinationInformation>
                XML;
                    $rph++;
                }
            }
        }
        // ✅ Handle Return flight
        elseif (!empty($params['flight_type']) && $params['flight_type'] === 'return') {
            $originDestXML .= <<<XML
            <OriginDestinationInformation RPH="1">
                <DepartureDateTime>{$params['departure_date']}T00:00:00Z</DepartureDateTime>
                <OriginLocation LocationCode="{$params['origin']}"/>
                <DestinationLocation LocationCode="{$params['destination']}"/>
            </OriginDestinationInformation>
        XML;

            $returnDate = $params['return_date'] ?? null;
            if ($returnDate) {
                $originDestXML .= <<<XML
                <OriginDestinationInformation RPH="2">
                    <DepartureDateTime>{$returnDate}T00:00:00Z</DepartureDateTime>
                    <OriginLocation LocationCode="{$params['destination']}"/>
                    <DestinationLocation LocationCode="{$params['origin']}"/>
                </OriginDestinationInformation>
            XML;
            }
        }
        // ✅ Default: One-way
        else {
            $originDestXML .= <<<XML
            <OriginDestinationInformation RPH="1">
                <DepartureDateTime>{$params['departure_date']}T00:00:00Z</DepartureDateTime>
                <OriginLocation LocationCode="{$params['origin']}"/>
                <DestinationLocation LocationCode="{$params['destination']}"/>
            </OriginDestinationInformation>
        XML;
        }

        // ✅ Build the full XML request
        $airBlueRequestXML = <<<XML
    <Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/">
        <Header/>
        <Body>
            <AirLowFareSearch xmlns="http://zapways.com/air/ota/3.0">
                <airLowFareSearchRQ EchoToken="{$this->generateToken()}" Target="{$this->target}" Version="{$this->version}" xmlns="http://www.opentravel.org/OTA/2003/05">
                    <POS>
                        <Source ERSP_UserID="{$this->clientId}/{$this->clientKey}">
                            <RequestorID Type="{$this->agentType}" ID="{$this->agentId}" MessagePassword="{$this->agentPassword}" />
                        </Source>
                    </POS>
                    {$originDestXML}
                    <TravelerInfoSummary>
                        <AirTravelerAvail>
                            {$passengerXML}
                        </AirTravelerAvail>
                    </TravelerInfoSummary>
                </airLowFareSearchRQ>
            </AirLowFareSearch>
        </Body>
    </Envelope>
    XML;


        $airBlueResponseXML = $this->sendRequest($airBlueRequestXML);


        return $airBlueResponseXML;
    }



    public function sendRequest(string $xml)
    {
        $certPath = storage_path('certs/cert.pem');
        $keyPath = storage_path('certs/key.pem');

        $client = new Client([
            'base_uri' => $this->endpoint,
            'verify' => false,
            'cert' => $certPath,
            'ssl_key' => $keyPath,
            'headers' => [
                "Content-Type" => "text/xml; charset=utf-8",
            ]
        ]);
        $response = $client->post('', [
            'body' => $xml
        ]);

        $body = (string) $response->getBody();
        // Remove   namespace prefixes like S:, ns2:
        $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $body);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);

        if ($xml === false) {
            foreach (libxml_get_errors() as $error) {
                Log::error("XML parse error: " . $error->message);
            }
            return response()->json(['error' => 'Invalid XML'], 500);
        }

        $json = json_encode($xml);
        // Log::info("AirBlue Search Response XML:\n" . $json);
        $array = json_decode($json, true);

        return json_encode($array);
    }

    private function generateToken(): string
    {
        return (string) rand(100000000000, 999999999999);
    }

    public function bookFlight($bookingData)
    {

        // -----------------------------------------------------------------
        // 1. Extract & validate core parts
        // -----------------------------------------------------------------
        $mainContact = $bookingData['main_contact'] ?? [];
        $travellers = $bookingData['travellers'] ?? [];
        $flightLeg = $bookingData['flight']['leg'] ?? [];
        $flights = $flightLeg['flights'] ?? [];
        $fareReferences = $bookingData['fare_reference'] ?? [];

        if (empty($flights) || empty($fareReferences)) {
            throw new \RuntimeException('Flights or fare references missing');
        }

        // -----------------------------------------------------------------
        // 2. Match selected fares per flight
        // -----------------------------------------------------------------
        $selectedFares = [];
        foreach ($flights as $index => $flight) {
            $refId = $fareReferences[$index] ?? null;
            if (!$refId)
                continue;

            $selectedFare = collect($flight['fares'] ?? [])
                ->firstWhere('ref_id', $refId);

            if (!$selectedFare) {
                throw new \RuntimeException("Selected fare not found for flight index {$index}");
            }

            $selectedFares[$index] = $selectedFare;
        }

        // -----------------------------------------------------------------
        // 3. Build initial data container
        // -----------------------------------------------------------------
        $data = [
            'Service' => [
                'Target' => $this->target,
                'Version' => $this->version,
            ],
            'APIClient' => [
                'ID' => config('airblue.api_client.id'),
                'Key' => config('airblue.api_client.key'),
            ],
            'Agent' => [
                'ID' => config('airblue.agent.id'),
                'Password' => config('airblue.agent.password'),
            ],
            'Flights' => [],
            'Passengers' => [],
            'FareDetails' => [],
        ];

        // -----------------------------------------------------------------
        // 4. Loop over flights and build flight segments
        // -----------------------------------------------------------------
        foreach ($flights as $flightIndex => $flight) {
            $segments = $flight['segments'] ?? [];
            $selectedFare = $selectedFares[$flightIndex] ?? null;
            if (!$selectedFare)
                continue;

            foreach ($segments as $segmentIndex => $segment) {
                $data['Flights'][] = [
                    'FlightIndex' => $flightIndex + 1,
                    'FlightRPH' => $flight['RPH'] ?? '',
                    'SegmentRPH' => $segment['RPH'] ?? '',
                    'SegmentIndex' => $segmentIndex + 1,
                    'DepartureDateTime' => $segment['departure_at'] ?? '',
                    'ArrivalDateTime' => $segment['arrival_at'] ?? '',
                    'StopQuantity' => $segment['stop_quantity'] ?? '0',
                    'FlightNumber' => $segment['flight_number'] ?? '',
                    'FareType' => $selectedFare['name_class'] ?? '',
                    'ResBookDesigCode' => $segment['class_code'] ?? '',
                    'CabinClass' => $segment['cabin_class'] ?? 'Y',
                    'Status' => $segment['status'] ?? 'ONTIME',
                    'DepartureAirport' => $segment['from']['iata'] ?? '',
                    'ArrivalAirport' => $segment['to']['iata'] ?? '',
                    'OperatingAirline' => $segment['operating_carrier']['iata'] ?? '',
                    'Equipment' => $segment['aircraft']['model'] ?? '',
                    'MarketingAirline' => $flight['marketing_carrier']['iata'] ?? '',
                    'DepartureDateOnly' => substr($segment['departure_at'] ?? '', 0, 10) . 'T00:00:00',
                ];
            }

            // -----------------------------------------------------------------
            // 5. Add fare details per flight
            // -----------------------------------------------------------------
            $fareDetails = [
                'Currency' => $selectedFare['currency']['code'] ?? 'PKR',
                'Base' => $selectedFare['base_price'] ?? 0,
                'TaxesTotal' => $selectedFare['taxes'] ?? 0,
                'Taxes' => $this->extractTaxes($selectedFare),
                'FeesTotal' => $selectedFare['fees'] ?? 0,
                'fare_basis_code' => $selectedFare['fare_basis_code'] ?? '',
                'name_class' => $selectedFare['name_class'] ?? '',
                'passenger_fares' => $selectedFare['passenger_fares'] ?? [],
                'Fees' => $this->extractFees($selectedFare),
                'Total' => $selectedFare['total_price'] ?? 0,
                'Penalties' => $this->getDefaultPenalties($selectedFare),
                'Baggage' => [], // filled below
            ];

            // Attach baggage per traveller type
            foreach ($travellers as $traveller) {
                $travType = $traveller['type'] ?? 'ADT';
                $baggagePolicy = collect($selectedFare['baggage_policies'] ?? [])
                    ->firstWhere('traveler_type', $travType);
                $fareDetails['Baggage'][] = [
                    'Type' => $travType,
                    'Weight' => $baggagePolicy['weight'] ?? 0,
                    'Unit' => 'KGS',
                ];
            }

            $data['FareDetails'][] = $fareDetails;
        }

        // -----------------------------------------------------------------
        // 6. Add passengers dynamically
        // -----------------------------------------------------------------
        $groupedByType = collect($travellers)->groupBy('type');
        foreach ($groupedByType as $type => $travList) {
            $data['Passengers'][] = [
                'Type' => $type,
                'Quantity' => (string) count($travList),
                'Details' => $travList, // Optional — used in XML
            ];
        }

        // -----------------------------------------------------------------
        // 7. Build XML using loops
        // -----------------------------------------------------------------
        $xmlBody = $this->buildXml($data, $travellers, $mainContact);
        Log::info("Generated XML Body:\n" . $xmlBody);
        // -----------------------------------------------------------------
        // 8. Mock or send booking request
        // -----------------------------------------------------------------
        try {
            $certPath = storage_path('certs/cert.pem');
            $keyPath = storage_path('certs/key.pem');

            $client = new Client([
                'base_uri' => $this->endpoint,
                'verify' => false,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8'],
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xmlBody]);
            $responseBody = (string) $response->getBody();

            Log::info("AirBlue Booking Response XML:\n" . $responseBody);

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

            Log::error("AirBlue Booking API HTTP Error", [
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

                    Log::error("Parsed AirBlue Error XML", ['error_parsed' => $arrayError]);

                    // Try to detect standard AirBlue <Errors> or <Error> nodes
                    $errorMsg = $arrayError['Errors']['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['Message'] ??
                        json_encode($arrayError);

                    return response()->json([
                        'error' => 'AirBlue API Error',
                        'message' => $errorMsg,
                        'raw' => $arrayError
                    ], 502);
                }
            }

            return response()->json([
                'error' => 'AirBlue API Request Failed',
                'message' => $e->getMessage(),
            ], 502);

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("AirBlue Booking Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => isset($bookingData) ? json_encode($bookingData) : null,
            ]);

            return response()->json([
                'error' => 'Booking request failed',
                'message' => $e->getMessage(),
            ], 500);
        }

    }



    // -------------------------------------------------------------------------
    // Helper: map fare class name → OTA FareType
    // -------------------------------------------------------------------------
    private function mapFareType(string $nameClass): string
    {
        return match (strtoupper($nameClass)) {
            'EF' => 'EF',
            'EX' => 'EX',
            'EV' => 'EV',
            default => 'EF',
        };
    }

    // -------------------------------------------------------------------------
    // Helper: extract taxes (fallback to total if not broken down)
    // -------------------------------------------------------------------------
    private function extractTaxes(array $fare): array
    {

        $taxes = [];
        $total = $fare['taxes'] ?? 0;
        $currency = $fare['currency']['code'] ?? 'PKR';

        // If provider gives breakdown, use it
        if (!empty($fare['tax_breakdown'])) {
            foreach ($fare['tax_breakdown'] as $t) {
                $taxes[] = [
                    'Code' => $t['code'] ?? 'ZP',
                    'Currency' => $t['currency'] ?? $currency,
                    'Amount' => $t['amount'] ?? 0,
                ];
            }
        } else {
            $taxes[] = ['Code' => 'ZP', 'Currency' => $currency, 'Amount' => $total];
        }

        return $taxes;
    }

    // -------------------------------------------------------------------------
    // Helper: extract fees
    // -------------------------------------------------------------------------
    private function extractFees(array $fare): array
    {


        $fees = [];
        $total = $fare['fees'] ?? 0;
        $currency = $fare['currency']['code'] ?? 'PKR';

        if (!empty($fare['fee_breakdown'])) {
            foreach ($fare['fee_breakdown'] as $f) {
                $fees[] = [
                    'Code' => $f['code'] ?? 'XF',
                    'Currency' => $f['currency'] ?? $currency,
                    'Amount' => $f['amount'] ?? 0,
                ];
            }
        } else {
            $fees[] = ['Code' => 'XF', 'Currency' => $currency, 'Amount' => $total];
        }

        return $fees;
    }

    // -------------------------------------------------------------------------
    // Default penalties (customize per airline if needed)
    // -------------------------------------------------------------------------
    private function getDefaultPenalties(array $fare): array
    {
        $currency = $fare['currency']['code'] ?? 'PKR';
        return [
            ['HoursBeforeDeparture' => '<1', 'Currency' => $currency, 'Amount' => '5000'],
            ['HoursBeforeDeparture' => '>1', 'Currency' => $currency, 'Amount' => '2500'],
        ];
    }

    // -------------------------------------------------------------------------
    // Main XML Builder – uses loops only
    // -------------------------------------------------------------------------
    private function buildXml(array $data, array $travellers, array $mainContact)
    {

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // === SOAP Envelope ===
        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $dom->appendChild($envelope);
        $envelope->appendChild($dom->createElement('Header'));
        $body = $dom->createElement('Body');
        $envelope->appendChild($body);

        // === AirBook Root ===
        $airBook = $dom->createElementNS('http://zapways.com/air/ota/3.0', 'AirBook');
        $body->appendChild($airBook);

        $airBookRQ = $dom->createElement('airBookRQ');
        $airBookRQ->setAttribute('Target', $data['Service']['Target']);
        $airBookRQ->setAttribute('Version', $data['Service']['Version']);
        $airBookRQ->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.opentravel.org/OTA/2003/05');
        $airBook->appendChild($airBookRQ);

        // === POS (Authentication) ===
        $pos = $dom->createElement('POS');
        $source = $dom->createElement('Source');
        $source->setAttribute('ERSP_UserID', $data['APIClient']['ID'] . '/' . $data['APIClient']['Key']);
        $requestor = $dom->createElement('RequestorID');
        $requestor->setAttribute('Type', '29');
        $requestor->setAttribute('ID', $data['Agent']['ID']);
        $requestor->setAttribute('MessagePassword', $data['Agent']['Password']);
        $source->appendChild($requestor);
        $pos->appendChild($source);
        $airBookRQ->appendChild($pos);

        // === AirItinerary (Flights + Segments) ===
        $airItinerary = $dom->createElement('AirItinerary');
        $originDestOptions = $dom->createElement('OriginDestinationOptions');

        foreach ($data['Flights'] as $flightIndex => $flight) {
            $originDestOption = $dom->createElement('OriginDestinationOption');
            $originDestOption->setAttribute('RPH', $flight['FlightRPH']);
            // $originDestOption->setAttribute('StopQuantity', $flight['StopQuantity'] ?? '0');

            $flightSegment = $dom->createElement('FlightSegment');
            $flightSegment->setAttribute('RPH', $flight['SegmentRPH']);
            $flightSegment->setAttribute('DepartureDateTime', $flight['DepartureDateTime'] ?? '');
            $flightSegment->setAttribute('ArrivalDateTime', $flight['ArrivalDateTime'] ?? '');
            $flightSegment->setAttribute('StopQuantity', $flight['StopQuantity'] ?? '0');
            $flightSegment->setAttribute('FlightNumber', $flight['FlightNumber'] ?? '');
            $flightSegment->setAttribute('FareType', $flight['FareType'] ?? '');
            $flightSegment->setAttribute('ResBookDesigCode', $flight['ResBookDesigCode'] ?? '');
            $flightSegment->setAttribute('CabinClass', $flight['CabinClass'] ?? 'Y');
            $flightSegment->setAttribute('Status', $flight['Status'] ?? 'ONTIME');

            $flightSegment->appendChild($this->el($dom, 'DepartureAirport', '', [
                'LocationCode' => $flight['DepartureAirport'] ?? ''
            ]));
            $flightSegment->appendChild($this->el($dom, 'ArrivalAirport', '', [
                'LocationCode' => $flight['ArrivalAirport'] ?? ''
            ]));
            $flightSegment->appendChild($this->el($dom, 'OperatingAirline', '', [
                'Code' => $flight['OperatingAirline'] ?? ''
            ]));
            $flightSegment->appendChild($this->el($dom, 'Equipment', '', [
                'AirEquipType' => $flight['Equipment'] ?? ''
            ]));
            $flightSegment->appendChild($this->el($dom, 'MarketingAirline', '', [
                'Code' => $flight['MarketingAirline'] ?? ''
            ]));

            $originDestOption->appendChild($flightSegment);
            $originDestOptions->appendChild($originDestOption);
        }

        $airItinerary->appendChild($originDestOptions);
        $airBookRQ->appendChild($airItinerary);

        // === PriceInfo (Fare Details) ===
        $priceInfo = $dom->createElement('PriceInfo');
        $ptcBreakdowns = $dom->createElement('PTC_FareBreakdowns');
        foreach ($data['Flights'] as $index => $flight) {
            $fareDetail = $data['FareDetails'][$index] ?? [];
            foreach ($fareDetail['passenger_fares'] ?? [] as $travelerFare) {
                $ptcBreakdown = $dom->createElement('PTC_FareBreakdown');

                $ptcBreakdown->appendChild($this->el($dom, 'PassengerTypeQuantity', '', [
                    'Code' => $travelerFare['traveler_type'] ?? 'ADT',
                    'Quantity' => $travelerFare['total_passenger'] ?? '1'
                ]));

                $passengerFare = $dom->createElement('PassengerFare');
                $passengerFare->appendChild($this->el($dom, 'BaseFare', '', [
                    'CurrencyCode' => $fareDetail['Currency'] ?? 'PKR',
                    'Amount' => $travelerFare['base_price'] ?? 0
                ]));

                $taxes = $dom->createElement('Taxes');
                $taxes->setAttribute('Amount', $travelerFare['taxes'] ?? 0);
                if (isset($travelerFare['taxCodes']) && is_array($travelerFare['taxCodes'])) {
                    foreach ($travelerFare['taxCodes'] ?? [] as $tax) {
                        $taxes->appendChild($this->el($dom, 'Tax', '', [
                            'TaxCode' => $tax['@attributes']['TaxCode'] ?? '',
                            'CurrencyCode' => $tax['@attributes']['CurrencyCode'] ?? $fareDetail['Currency'] ?? 'PKR',
                            'Amount' => $tax['@attributes']['Amount'] ?? 0
                        ]));
                    }
                }
                $passengerFare->appendChild($taxes);
            
                $fees = $dom->createElement('Fees');
                if (isset($travelerFare['fees']) && (float)$travelerFare['fees'] > 0) {
                $fees->setAttribute('Amount', $travelerFare['fees'] ?? 0);
                foreach ($travelerFare['feeCodes'] ?? [] as $fee) {
                    $fees->appendChild($this->el($dom, 'Fee', '', [
                        'FeeCode' => $fee['@attributes']['FeeCode'] ?? '',
                        'CurrencyCode' => $fee['@attributes']['CurrencyCode'] ?? $fareDetail['Currency'] ?? 'PKR',
                        'Amount' => $fee['@attributes']['Amount'] ?? 0
                    ]));
                }
                
                $passengerFare->appendChild($fees);
                }
                $passengerFare->appendChild($this->el($dom, 'TotalFare', '', [
                    'CurrencyCode' => $fareDetail['Currency'] ?? 'PKR',
                    'Amount' => $travelerFare['total_price'] ?? 0
                ]));

                $ptcBreakdown->appendChild($passengerFare);

                // === Fare Info ===
                $fareInfo = $dom->createElement('FareInfo');
                $fareInfo->appendChild($this->el($dom, 'DepartureDate', $flight['DepartureDateTime'] ?? ''));
                $departureAirport = $dom->createElement('DepartureAirport');
                $departureAirport->setAttribute('LocationCode', $flight['DepartureAirport'] ?? '');
                $fareInfo->appendChild($departureAirport);
                $ArrivalAirport = $dom->createElement('ArrivalAirport');
                $ArrivalAirport->setAttribute('LocationCode', $flight['ArrivalAirport'] ?? '');
                $fareInfo->appendChild($ArrivalAirport);

                $fareInfo->appendChild($this->el($dom, 'FareInfo', '', [
                    'FareBasisCode' => $fareDetail['fare_basis_code'] ?? '',
                    'FareType' => $flight['FareType'] ?? '',
                ]));
                $fareInfo->appendChild($passengerFare->cloneNode(true));
                $ptcBreakdown->appendChild($fareInfo);

                // === Penalties ===
                $fareInfoRules = $dom->createElement('FareInfo');
                $fareInfoRules->appendChild($this->el($dom, 'DepartureDate', $flight['DepartureDateTime'] ?? ''));
                $ruleInfo = $dom->createElement('RuleInfo');
                $chargesRules = $dom->createElement('ChargesRules');
                $voluntaryChanges = $dom->createElement('VoluntaryChanges');
                foreach ($fareDetail['Penalties'] ?? [] as $penalty) {
                    $penaltyEl = $dom->createElement('Penalty');
                    foreach ($penalty as $key => $value) {
                        if (in_array($key, ['Currency', 'Amount']))
                            continue;
                        $penaltyEl->setAttribute($key, $value);
                    }
                    $penaltyEl->setAttribute('CurrencyCode', $penalty['Currency'] ?? 'PKR');
                    $penaltyEl->setAttribute('Amount', $penalty['Amount'] ?? 0);
                    $voluntaryChanges->appendChild($penaltyEl);
                }
                $chargesRules->appendChild($voluntaryChanges);
                $ruleInfo->appendChild($chargesRules);
                $fareInfoRules->appendChild($ruleInfo);
                $ptcBreakdown->appendChild($fareInfoRules);
                $departureAirport = $dom->createElement('DepartureAirport');
                $departureAirport->setAttribute('LocationCode', $flight['DepartureAirport'] ?? '');
                $fareInfoRules->appendChild($departureAirport);
                $ArrivalAirport = $dom->createElement('ArrivalAirport');
                $ArrivalAirport->setAttribute('LocationCode', $flight['ArrivalAirport'] ?? '');
                $fareInfoRules->appendChild($ArrivalAirport);
                $passengerFare = $dom->createElement('PassengerFare');

                // === Baggage ===
                $travType = strtoupper($travelerFare['traveler_type'] ?? 'ADT');

                $baggageList = $fareDetail['Baggage'] ?? [];

                if (is_array($baggageList)) {
                    foreach ($baggageList as $baggage) {

                        $bagType = strtoupper($baggage['Type'] ?? '');

                        // allow only matching traveler type baggage
                        if ($bagType !== $travType) {
                            continue;
                        }

                        // append baggage allowance (only once)
                        $passengerFare->appendChild(
                            $this->el($dom, 'FareBaggageAllowance', '', [
                                'UnitOfMeasureQuantity' => $baggage['Weight'] ?? 0,
                                'UnitOfMeasure' => $baggage['Unit'] ?? 'KGS',
                            ])
                        );

                        break; // only one entry per traveler type
                    }
                }

                $fareInfoRules->appendChild($passengerFare);

                $ptcBreakdowns->appendChild($ptcBreakdown);

            }
        }


        $priceInfo->appendChild($ptcBreakdowns);
        $airBookRQ->appendChild($priceInfo);

        // === TravelerInfo (Passengers) ===
        $travelerInfo = $dom->createElement('TravelerInfo');
        foreach ($travellers as $index => $traveler) {
            if ($traveler['type'] == 'CNN') {
                $traveler['title'] = 'MSTR';
            } else if ($traveler['type'] == 'INF') {
                $traveler['title'] = '';
            }
            $airTraveler = $dom->createElement('AirTraveler');
            $airTraveler->setAttribute('BirthDate', $traveler['dob'] ?? '');

            $personName = $dom->createElement('PersonName');
            $personName->appendChild($dom->createElement('GivenName', $traveler['firstName'] ?? ''));
            $personName->appendChild($dom->createElement('Surname', $traveler['lastName'] ?? ''));
            if ($traveler['type'] == 'INF') {

            } else {
                $personName->appendChild($dom->createElement('NameTitle', strtoupper($traveler['title'] ?? '')));
            }
            $airTraveler->appendChild($personName);

            if ($traveler['type'] !== 'INF') {
                $airTraveler->appendChild($this->el($dom, 'Telephone', '', [
                    'PhoneLocationType' => '10',
                    'CountryAccessCode' => '92',
                    'PhoneNumber' => preg_replace('/\D/', '', $mainContact['phone'] ?? '')
                ]));
            }
            $airTraveler->appendChild($dom->createElement('Email', $mainContact['email'] ?? ''));

            $doc = $dom->createElement('Document');
            $cnic = $traveler['cnic'] ?? null;
            if ($cnic) {
                $doc->setAttribute('DocID', $cnic);
                $doc->setAttribute('DocType', '5');
            } else {
                $doc->setAttribute('DocID', $traveler['documentNo'] ?? '');
                $doc->setAttribute('DocType', '2');
            }
            $doc->setAttribute('ExpireDate', $traveler['expiryDate'] ?? '');
            $doc->setAttribute('DocIssueCountry', strtoupper($traveler['issueCountry'] ?? ''));
            $doc->setAttribute('DocHolderNationality', strtoupper($traveler['nationality'] ?? ''));
            $airTraveler->appendChild($doc);

            $airTraveler->appendChild($this->el($dom, 'PassengerTypeQuantity', '', [
                'Code' => $traveler['type'] == 'CNN' ? 'CHD' : $traveler['type'] ?? 'ADT',
                'Quantity' => '1'
            ]));

            $airTraveler->appendChild($this->el($dom, 'TravelerRefNumber', '', [
                'RPH' => (string) $index + 1
            ]));

            $travelerInfo->appendChild($airTraveler);
        }
        $airBookRQ->appendChild($travelerInfo);

        return $dom->saveXML();
    }

    // -------------------------------------------------------------------------
    // Helper: create element with attributes
    // -------------------------------------------------------------------------
    private function el(DOMDocument $dom, string $name, string $value = '', array $attrs = []): \DOMElement
    {
        $el = $dom->createElement($name, $value);
        foreach ($attrs as $k => $v) {
            $el->setAttribute($k, $v);
        }
        return $el;
    }


    public function cancelBooking($pnr)
    {
        $data = [
            'Service' => [
                'Target' => $this->target,
                'Version' => $this->version,
            ],
            'APIClient' => [
                'ID' => config('airblue.api_client.id'),
                'Key' => config('airblue.api_client.key'),
            ],
            'Agent' => [
                'ID' => config('airblue.agent.id'),
                'Password' => config('airblue.agent.password'),
            ],
            'PNR' => $pnr,
        ];

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // === SOAP Envelope ===
        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'Envelope');
        $dom->appendChild($envelope);
        $envelope->appendChild($dom->createElement('Header'));
        $body = $dom->createElement('Body');
        $envelope->appendChild($body);

        // === AirBook Root ===
        $cancelRq = $dom->createElementNS('http://zapways.com/air/ota/3.0', 'Cancel');
        $body->appendChild($cancelRq);

        $cancelRQ = $dom->createElement('cancelRQ');
        $cancelRQ->setAttribute('Target', $data['Service']['Target']);
        $cancelRQ->setAttribute('Version', $data['Service']['Version']);
        $cancelRQ->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.opentravel.org/OTA/2003/05');
        $cancelRq->appendChild($cancelRQ);
        // === POS (Authentication) ===
        $pos = $dom->createElement('POS');
        $source = $dom->createElement('Source');
        $source->setAttribute('ERSP_UserID', $data['APIClient']['ID'] . '/' . $data['APIClient']['Key']);
        $requestor = $dom->createElement('RequestorID');
        $requestor->setAttribute('Type', '29');
        $requestor->setAttribute('ID', $data['Agent']['ID']);
        $requestor->setAttribute('MessagePassword', $data['Agent']['Password']);
        $source->appendChild($requestor);
        $pos->appendChild($source);
        $cancelRQ->appendChild($pos);

        $uniqueID = $dom->createElement('UniqueID');
        $uniqueID->setAttribute('ID', $data['PNR']);
        $cancelRQ->appendChild($uniqueID);
        Log::info("Final AirBlue Cancel Booking Request XML:\n" . $dom->saveXML());
        $xmlBody = $dom->saveXML();
        try {
            $certPath = storage_path('certs/cert.pem');
            $keyPath = storage_path('certs/key.pem');

            $client = new Client([
                'base_uri' => $this->endpoint,
                'verify' => false,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8'],
            ]);

            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xmlBody]);
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

            Log::info("Parsed Booking Response Array: " . json_encode($array));

            return $array;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("AirBlue Booking API HTTP Error", [
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

                    Log::error("Parsed AirBlue Error XML", ['error_parsed' => $arrayError]);

                    // Try to detect standard AirBlue <Errors> or <Error> nodes
                    $errorMsg = $arrayError['Errors']['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['ShortText'] ??
                        $arrayError['Error']['@attributes']['Message'] ??
                        json_encode($arrayError);

                    return null;
                }
            }

            return null;

        } catch (\Throwable $e) {
            // -----------------------------------------------------------------
            // 4. Handle unexpected PHP/runtime exceptions
            // -----------------------------------------------------------------
            Log::error("AirBlue Booking Exception: " . $e->getMessage(), [
                'exception' => $e,
                'booking_data_snapshot' => isset($bookingData) ? json_encode($bookingData) : null,
            ]);

            return null;
        }
    }

    public function airSeatMap($request)
    {


        $flightsJson = $request['flight_data'] ?? '[]';
        $selectedFaresJson = $request['fare_reference'] ?? '[]';
        $selectedFares = is_string($selectedFaresJson)
            ? json_decode($selectedFaresJson, true)
            : $selectedFaresJson;

        $flights = is_string($flightsJson)
            ? json_decode($flightsJson, true)
            : $flightsJson;
        $legFlights = $flights['leg']['flights'] ?? [];
        $pnrJson = $request['pnr_response'] ?? '';

        $pnr = is_string($pnrJson)
            ? json_decode($pnrJson, true)
            : $pnrJson;
        $bookingReferenceID = $pnr['Body']['AirBookResponse']['AirBookResult']['AirReservation']['BookingReferenceID'] ?? '';
        $data = [
            'Service' => [
                'Target' => $this->target,
                'Version' => $this->version,
            ],
            'APIClient' => [
                'ID' => config('airblue.api_client.id'),
                'Key' => config('airblue.api_client.key'),
            ],
            'Agent' => [
                'ID' => config('airblue.agent.id'),
                'Password' => config('airblue.agent.password'),
            ],
            'Flights' => [],
            'PNR' => [$bookingReferenceID[0]],
        ];
        foreach ($legFlights as $flightIndex => $flight) {
            $segments = $flight['segments'] ?? [];
            $selectedFare = $selectedFares[$flightIndex] ?? null;
            if (!$selectedFare)
                continue;

            foreach ($segments as $segmentIndex => $segment) {
                $data['Flights'][] = [
                    'FlightIndex' => $flightIndex + 1,
                    'FlightRPH' => $flight['RPH'] ?? '',
                    'SegmentRPH' => $segment['RPH'] ?? '',
                    'SegmentIndex' => $segmentIndex + 1,
                    'DepartureDateTime' => $segment['departure_at'] ?? '',
                    'ArrivalDateTime' => $segment['arrival_at'] ?? '',
                    'StopQuantity' => $segment['stop_quantity'] ?? '0',
                    'FlightNumber' => $segment['flight_number'] ?? '',
                    'FareType' => $this->mapFareType($selectedFare['name_class'] ?? ''),
                    'ResBookDesigCode' => $segment['class_code'] ?? '',
                    'CabinClass' => $segment['cabin_class'] ?? 'Y',
                    'Status' => $segment['status'] ?? 'ONTIME',
                    'DepartureAirport' => $segment['from']['iata'] ?? '',
                    'ArrivalAirport' => $segment['to']['iata'] ?? '',
                    'OperatingAirline' => $segment['operating_carrier']['iata'] ?? '',
                    'Equipment' => $segment['aircraft']['model'] ?? '',
                    'MarketingAirline' => $flight['marketing_carrier']['iata'] ?? '',
                    'DepartureDateOnly' => substr($segment['departure_at'] ?? '', 0, 10) . 'T00:00:00',
                ];
            }

        }
        $xml = new \SimpleXMLElement('<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"/>');
        $body = $xml->addChild('Body');
        $asm = $body->addChild('AirSeatMap', null, 'http://zapways.com/air/ota/3.0');
        $rq = $asm->addChild('airSeatMapRQ', null, 'http://www.opentravel.org/OTA/2003/05');

        $rq->addAttribute('EchoToken', uniqid());
        $rq->addAttribute('Target', $this->target);
        $rq->addAttribute('Version', $this->version);

        // ------------------------------------------------------------
// POS Section
// ------------------------------------------------------------
        $pos = $rq->addChild('POS');
        $src = $pos->addChild('Source');
        $src->addAttribute('ERSP_UserID', $data['APIClient']['ID'] . '/' . $data['APIClient']['Key']);

        $rid = $src->addChild('RequestorID');
        $rid->addAttribute('Type', '29');
        $rid->addAttribute('ID', $data['Agent']['ID']);
        $rid->addAttribute('MessagePassword', $data['Agent']['Password']);

        // ------------------------------------------------------------
// SeatMapRequests
// ------------------------------------------------------------
        $seatReqs = $rq->addChild('SeatMapRequests');

        foreach ($data['Flights'] as $f) {
            $seatReq = $seatReqs->addChild('SeatMapRequest');

            $finfo = $seatReq->addChild('FlightSegmentInfo');
            $finfo->addAttribute('DepartureDateTime', $f['DepartureDateTime']);
            $finfo->addAttribute('FlightNumber', $f['FlightNumber']);
            $finfo->addAttribute('FareType', $f['FareType']);
            $finfo->addAttribute('ResBookDesigCode', $f['ResBookDesigCode']);
            $finfo->addAttribute('CabinClass', $f['CabinClass']);

            $dep = $finfo->addChild('DepartureAirport');
            $dep->addAttribute('LocationCode', $f['DepartureAirport']);

            $arr = $finfo->addChild('ArrivalAirport');
            $arr->addAttribute('LocationCode', $f['ArrivalAirport']);

            $op = $finfo->addChild('OperatingAirline');
            $op->addAttribute('Code', $f['OperatingAirline']);
            $op->addAttribute('FlightNumber', $f['FlightNumber']);
        }

        // ------------------------------------------------------------
// PNR
// ------------------------------------------------------------
        foreach ($data['PNR'] as $pnrEntry) {
            $attrs = $pnrEntry['@attributes'] ?? [];
            $br = $rq->addChild('BookingReferenceID');

            foreach ($attrs as $key => $value) {
                $br->addAttribute($key, $value);
            }
        }

        $xmlBody = $xml->asXML();
        try {
            $certPath = storage_path('certs/cert.pem');
            $keyPath = storage_path('certs/key.pem');

            $client = new Client([
                'base_uri' => $this->endpoint,
                'verify' => false,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8'],
            ]);
            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xmlBody]);
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
                return null;
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
            Log::error("AirBlue Fetch Seats API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

        }


    }
    public function fetchAncillaries($request)
    {
        $flightsJson = $request['flight_data'] ?? '[]';
        $selectedFaresJson = $request['fare_reference'] ?? '[]';
        $selectedFares = is_string($selectedFaresJson)
            ? json_decode($selectedFaresJson, true)
            : $selectedFaresJson;

        $flights = is_string($flightsJson)
            ? json_decode($flightsJson, true)
            : $flightsJson;
        $legFlights = $flights['leg']['flights'] ?? [];
        $pnrJson = $request['pnr_response'] ?? '';

        $pnr = is_string($pnrJson)
            ? json_decode($pnrJson, true)
            : $pnrJson;
        $bookingReferenceID = $pnr['Body']['AirBookResponse']['AirBookResult']['AirReservation']['BookingReferenceID'] ?? '';
        $data = [
            'Service' => [
                'Target' => $this->target,
                'Version' => $this->version,
            ],
            'APIClient' => [
                'ID' => config('airblue.api_client.id'),
                'Key' => config('airblue.api_client.key'),
            ],
            'Agent' => [
                'ID' => config('airblue.agent.id'),
                'Password' => config('airblue.agent.password'),
            ],
            'Flights' => [],
            'PNR' => [$bookingReferenceID[0]],
        ];
        foreach ($legFlights as $flightIndex => $flight) {
            $segments = $flight['segments'] ?? [];
            $selectedFare = $selectedFares[$flightIndex] ?? null;
            if (!$selectedFare)
                continue;

            foreach ($segments as $segmentIndex => $segment) {
                $data['Flights'][] = [
                    'FlightIndex' => $flightIndex + 1,
                    'FlightRPH' => $flight['RPH'] ?? '',
                    'SegmentRPH' => $segment['RPH'] ?? '',
                    'SegmentIndex' => $segmentIndex + 1,
                    'DepartureDateTime' => $segment['departure_at'] ?? '',
                    'ArrivalDateTime' => $segment['arrival_at'] ?? '',
                    'StopQuantity' => $segment['stop_quantity'] ?? '0',
                    'FlightNumber' => $segment['flight_number'] ?? '',
                    'FareType' => $this->mapFareType($selectedFare['name_class'] ?? ''),
                    'ResBookDesigCode' => $segment['class_code'] ?? '',
                    'CabinClass' => $segment['cabin_class'] ?? 'Y',
                    'Status' => $segment['status'] ?? 'ONTIME',
                    'DepartureAirport' => $segment['from']['iata'] ?? '',
                    'ArrivalAirport' => $segment['to']['iata'] ?? '',
                    'OperatingAirline' => $segment['operating_carrier']['iata'] ?? '',
                    'Equipment' => $segment['aircraft']['model'] ?? '',
                    'MarketingAirline' => $flight['marketing_carrier']['iata'] ?? '',
                    'DepartureDateOnly' => substr($segment['departure_at'] ?? '', 0, 10) . 'T00:00:00',
                ];
            }
        }

        $xml = new SimpleXMLElement('<Envelope/>');
        $xml->addAttribute('xmlns', 'http://schemas.xmlsoap.org/soap/envelope/');

        $header = $xml->addChild('Header');

        // Main body
        $body = $xml->addChild('Body');
        $airAncillary = $body->addChild('AirAncillaryItems');
        $airAncillary->addAttribute('xmlns', 'http://zapways.com/air/ota/3.0');

        // Request Root
        $rq = $airAncillary->addChild('airAncillaryItemsRQ');
        $rq->addAttribute('EchoToken', uniqid());
        $rq->addAttribute('Target', $data['Service']['Target']);
        $rq->addAttribute('Version', $data['Service']['Version']);
        $rq->addAttribute('xmlns', 'http://www.opentravel.org/OTA/2003/05');

        // POS Block
        $pos = $rq->addChild('POS');
        $source = $pos->addChild('Source');
        $source->addAttribute('ERSP_UserID', $data['APIClient']['ID'] . '/' . $data['APIClient']['Key']);

        $reqID = $source->addChild('RequestorID');
        $reqID->addAttribute('Type', '29');
        $reqID->addAttribute('ID', $data['Agent']['ID']);
        $reqID->addAttribute('MessagePassword', $data['Agent']['Password']);

        // Ancillary Requests Wrapper
        $ancRequests = $rq->addChild('AncillaryItemRequests');

        // Flights (Loop)
        foreach ($data['Flights'] as $flight) {

            $anc = $ancRequests->addChild('AncillaryItemRequest');

            $segInfo = $anc->addChild('FlightSegmentInfo');
            $segInfo->addAttribute('DepartureDateTime', $flight['DepartureDateTime']);
            $segInfo->addAttribute('FlightNumber', $flight['FlightNumber']);
            $segInfo->addAttribute('FareType', $flight['FareType']);
            $segInfo->addAttribute('ResBookDesigCode', $flight['ResBookDesigCode']);
            $segInfo->addAttribute('CabinClass', $flight['CabinClass']);

            // Departure Airport
            $dep = $segInfo->addChild('DepartureAirport');
            $dep->addAttribute('LocationCode', $flight['DepartureAirport']);

            // Arrival Airport
            $arr = $segInfo->addChild('ArrivalAirport');
            $arr->addAttribute('LocationCode', $flight['ArrivalAirport']);

            // Operating Airline
            $op = $segInfo->addChild('OperatingAirline');
            $op->addAttribute('Code', $flight['OperatingAirline']);
            $op->addAttribute('FlightNumber', $flight['FlightNumber']);
        }

        // Booking Reference ID (Loop PNR)
        foreach ($data['PNR'] as $pnrEntry) {
            $attrs = $pnrEntry['@attributes'] ?? [];
            $br = $rq->addChild('BookingReferenceID');

            foreach ($attrs as $key => $value) {
                $br->addAttribute($key, $value);
            }
        }

        $xmlBody = $xml->asXML();
        try {
            $certPath = storage_path('certs/cert.pem');
            $keyPath = storage_path('certs/key.pem');

            $client = new Client([
                'base_uri' => $this->endpoint,
                'verify' => false,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8'],
            ]);
            // -----------------------------------------------------------------
            // 1. Send request
            // -----------------------------------------------------------------
            $response = $client->post('', ['body' => $xmlBody]);
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
                return null;
            }
            $json = json_encode($xml);
            $array = json_decode($json, true);
            // $array = json_decode($array, true);
            //     $array = '{"Body":{"AirAncillaryItemsResponse":{"AirAncillaryItemsResult":{"@attributes":{"EchoToken":"691ace29c25bf","Version":"1.04"},"Success":[],"AncillaryItemResponses":{"AncillaryItemResponse":{"FlightSegmentInfo":{"@attributes":{"DepartureDateTime":"2026-01-03T13:15:00","ArrivalDateTime":"2026-01-03T15:00:00","StopQuantity":"0","RPH":"1","FlightNumber":"402","FareType":"EF","ResBookDesigCode":"X","CabinClass":"Y"},"DepartureAirport":{"@attributes":{"LocationCode":"KHI"}},"ArrivalAirport":{"@attributes":{"LocationCode":"LHE"}},"OperatingAirline":{"@attributes":{"Code":"PA"}},"Equipment":{"@attributes":{"AirEquipType":"A321"}},"MarketingAirline":{"@attributes":{"Code":"PA"}}},"AncillaryItemSets":{"AncillaryItemSet":[{"@attributes":{"GroupCode":"XBAG","GroupDescription":"Excess baggage must be purchased in advance (not available at airport check-in). Multiple baggage selections (10kg, 20kg, 30kg) can be purchased in one transaction. Important: Once purchased, excess baggage is non-changeable and cannot be upgraded. Please ensure the total baggage required is purchased\u00a0in\u00a0one\u00a0go.","GroupImageURL":"\/content\/filebank?id=b182a94f-fab1-44a4-845c-5fccd6e91107","GroupLevel":"COUPON","GroupTitle":"Checked Baggage","MultipleChoice":"true"},"AncillaryItems":{"AncillaryItem":[{"@attributes":{"ItemCode":"XBAG30","ItemTitle":"30kgs  1 Extra Bag","Available":"true","ChargeCurrency":"PKR","ChargeAmount":"4800","IsRefundable":"true"}},{"@attributes":{"ItemCode":"XBAG20","ItemTitle":"20kgs  1 Extra Bag","Available":"true","ChargeCurrency":"PKR","ChargeAmount":"2900","IsRefundable":"true"}},{"@attributes":{"ItemCode":"XBAG10","ItemTitle":"10kgs  1 Extra Bag","Available":"true","ChargeCurrency":"PKR","ChargeAmount":"1450","IsRefundable":"true"}}]}},{"@attributes":{"GroupCode":"WCHR","GroupDescription":"If the passenger requires wheelchair services, please select the option here.  (Skardu Airport is not equipped with an aerobridge or ambulift. Passengers must be able to embark and disembark via stairs either independently or with minimal assistance.).","GroupLevel":"COUPON","GroupTitle":"Wheelchair Service","MultipleChoice":"false"},"AncillaryItems":{"AncillaryItem":[{"@attributes":{"ItemCode":"WCHR","ItemTitle":"Wheel Chair (can climb stairs)","Description":"Wheelchair assistance required; passenger can walk short distance up or down stairs.","Available":"true","ChargeCurrency":"PKR","IsRefundable":"true"}},{"@attributes":{"ItemCode":"WCHS","ItemTitle":"Wheelchair (cant climb stairs)","Description":"Wheelchair assistance required; passenger can walk short distance, but not up or down stairs.","Available":"false","IsRefundable":"true"}},{"@attributes":{"ItemCode":"WCHC","ItemTitle":"Wheelchair (passenger cannot walk any distance)","Description":"Wheelchair required; passenger cannot walk any distance and will require the aisle chair to board.","Available":"true","ChargeCurrency":"PKR","IsRefundable":"true"}}]}}]}}},"BookingReferenceID":{"@attributes":{"Instance":"PA0041448160","ID":"LVFHSW"}}}}}}  
            // ';
            return $array;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // -----------------------------------------------------------------
            // 3. Handle Guzzle HTTP errors (still may contain XML body)
            // -----------------------------------------------------------------
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';
            Log::error("AirBlue Fetch Ancillaries API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

        }

    }



    public function airBookModify($request)
    {

        // -------------------------------
        // Extract PNR
        // -------------------------------
        $pnr = $request['pnr'] ?? [];
        $bookingReferenceID =
            $pnr['Body']['AirBookResponse']['AirBookResult']['AirReservation']['BookingReferenceID'] ?? [];

        // Ensure array format
        if (!is_array($bookingReferenceID) || isset($bookingReferenceID['@attributes'])) {
            $bookingReferenceID = [$bookingReferenceID];
        }

        // -------------------------------
        // Extract Flight Segments
        // -------------------------------
        $segments =
            $pnr['Body']['AirBookResponse']['AirBookResult']['AirReservation']['AirItinerary']
            ['OriginDestinationOptions']['OriginDestinationOption'] ?? [];

        if (!is_array($segments) || isset($segments['@attributes'])) {
            $segments = [$segments];
        }

        // Build Flights structure (for segment RPH lookup)
        $flights = [];
        foreach ($segments as $flightIndex => $segment) {

            $flightSegments = $segment['FlightSegment'] ?? [];
            if (!is_array($flightSegments) || isset($flightSegments['@attributes'])) {
                $flightSegments = [$flightSegments];
            }

            foreach ($flightSegments as $segmentIndex => $flightSegment) {
                $flights[] = [
                    'FlightIndex' => $flightIndex + 1,
                    'FlightRPH' => $segment['@attributes']['RPH'] ?? '',
                    'SegmentRPH' => $flightSegment['@attributes']['RPH'] ?? '',
                    'SegmentIndex' => $segmentIndex + 1,
                ];
            }
        }

        // -------------------------------
        // Data from Frontend
        // -------------------------------
        $selectedSeats = $request['selectedSeats']['selectedSeats'] ?? [];
        $selectedAncillaries = $request['selectedAncillaries'] ?? [];

        $data = [
            'Service' => [
                'Target' => $this->target,
                'Version' => $this->version,
            ],
            'APIClient' => [
                'ID' => config('airblue.api_client.id'),
                'Key' => config('airblue.api_client.key'),
            ],
            'Agent' => [
                'ID' => config('airblue.agent.id'),
                'Password' => config('airblue.agent.password'),
            ],
            'Flights' => $flights,
            'SelectedSeats' => $selectedSeats,
            'SelectedAncillaries' => $selectedAncillaries,
            'PNR' => [$bookingReferenceID[0]],
        ];


        // -------------------------------
        // Build XML
        //-------------------------------
        $xml = new SimpleXMLElement('<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/"/>');
        $xml->addChild('Header');

        $body = $xml->addChild('Body');
        $airModify = $body->addChild('AirBookModify', null, 'http://zapways.com/air/ota/3.0');
        $rq = $airModify->addChild('airBookModifyRQ', null, 'http://www.opentravel.org/OTA/2003/05');

        $rq->addAttribute('EchoToken', uniqid());
        $rq->addAttribute('Target', $data['Service']['Target']);
        $rq->addAttribute('Version', $data['Service']['Version']);

        // POS
        $pos = $rq->addChild('POS');
        $source = $pos->addChild('Source');
        $source->addAttribute('ERSP_UserID', $data['APIClient']['ID'] . '/' . $data['APIClient']['Key']);

        $reqID = $source->addChild('RequestorID');
        $reqID->addAttribute('Type', '29');
        $reqID->addAttribute('ID', $data['Agent']['ID']);
        $reqID->addAttribute('MessagePassword', $data['Agent']['Password']);

        // Modify Block
        $AirBookModifyRQ = $rq->addChild('AirBookModifyRQ');
        $AirBookModifyRQ->addAttribute('ModificationType', '5');

        $TravelerInfo = $AirBookModifyRQ->addChild('TravelerInfo');
        $SpecialReqDetails = $TravelerInfo->addChild('SpecialReqDetails');

        // =====================================================
        // 1️⃣ SEAT REQUESTS (If any)
        // =====================================================
        if (!empty($data['SelectedSeats'])) {
            $SeatRequests = $SpecialReqDetails->addChild('SeatRequests');

            foreach ($data['SelectedSeats'] as $seat) {

                $SeatRequest = $SeatRequests->addChild('SeatRequest');

                $SeatRequest->addAttribute('SeatNumber', $seat['seat'] ?? '');
                $SeatRequest->addAttribute('RowNumber', $seat['row'] ?? '');
                $SeatRequest->addAttribute(
                    'TravelerRefNumberRPHList',
                    $seat['travelerRefNumber'] ?? ''
                );
                $SeatRequest->addAttribute(
                    'FlightRefNumberRPHList',
                    $seat['segment_rph'] ?? $seat['RPH'] ?? ''
                );
            }
        }

        // =====================================================
        // 2️⃣ ANCILLARY REQUESTS (If any)
        // =====================================================
        if (!empty($data['SelectedAncillaries'])) {

            $SpecialServiceRequests = $SpecialReqDetails->addChild('SpecialServiceRequests');

            foreach ($data['SelectedAncillaries'] as $anc) {

                foreach ($data['Flights'] as $flight) {
                    $ssr = $SpecialServiceRequests->addChild('SpecialServiceRequest');

                    $ssr->addAttribute('ItemCode', $anc['itemCode']);
                    $ssr->addAttribute('TravelerRefNumberRPHList', $anc['TravelerRefNumber']);
                    $ssr->addAttribute('FlightRefNumberRPHList', $flight['SegmentRPH']);
                }
            }
        }

        // =====================================================
        // PNR Block
        // =====================================================
        $AirReservation = $rq->addChild('AirReservation');

        foreach ($data['PNR'] as $pnrItem) {
            $br = $AirReservation->addChild('BookingReferenceID');
            $br->addAttribute('Instance', $pnrItem['@attributes']['Instance'] ?? '');
            $br->addAttribute('ID', $pnrItem['@attributes']['ID'] ?? '');
        }

        $xmlBody = $xml->asXML();

        // -------------------------------
        // Send Request
        // -------------------------------
        try {
            $certPath = storage_path('certs/cert.pem');
            $keyPath = storage_path('certs/key.pem');

            $client = new Client([
                'base_uri' => $this->endpoint,
                'verify' => false,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8'],
            ]);

            $response = $client->post('', ['body' => $xmlBody]);
            $responseBody = (string) $response->getBody();


            // Clean response before parsing
            $clean = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $responseBody);
            libxml_use_internal_errors(true);

            $parsed = simplexml_load_string($clean);

            if (!$parsed) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return null;
            }

            return json_decode(json_encode($parsed), true);

        } catch (\GuzzleHttp\Exception\RequestException $e) {

            Log::error("AirBlue Combined Modify Error", [
                'message' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 'N/A',
                'error_body' => $e->getResponse() ? (string) $e->getResponse()->getBody() : '',
            ]);

            return null;
        }
    }

    public function getBookingDetails($request)
    {
        try {

            // Create DOM Document
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->formatOutput = true;

            // SOAP Envelope
            $envelope = $doc->createElementNS(
                "http://schemas.xmlsoap.org/soap/envelope/",
                "soapenv:Envelope"
            );
            $doc->appendChild($envelope);

            // Header
            $header = $doc->createElement("soapenv:Header");
            $envelope->appendChild($header);

            // Body
            $body = $doc->createElement("soapenv:Body");
            $envelope->appendChild($body);

            // Read Node (Zapways Namespace)
            $read = $doc->createElementNS(
                "http://zapways.com/air/ota/3.0",
                "air:Read"
            );
            $body->appendChild($read);

            // readRQ Node (OTA Namespace)
            $readRQ = $doc->createElementNS(
                "http://www.opentravel.org/OTA/2003/05",
                "ota:readRQ"
            );
            $readRQ->setAttribute("Target", $this->target);
            $readRQ->setAttribute("Version", "1.04");
            $read->appendChild($readRQ);

            // POS
            $pos = $doc->createElement("ota:POS");
            $readRQ->appendChild($pos);

            $source = $doc->createElement("ota:Source");
            $source->setAttribute(
                "ERSP_UserID",
                $this->clientId . "/" . $this->clientKey
            );
            $pos->appendChild($source);

            $requestorId = $doc->createElement("ota:RequestorID");
            $requestorId->setAttribute("Type", $this->agentType);
            $requestorId->setAttribute("ID", $this->agentId);
            $requestorId->setAttribute("MessagePassword", $this->agentPassword);
            $source->appendChild($requestorId);

            // UniqueID (Pass booking reference from $request)
            $uniqueId = $doc->createElement("ota:UniqueID");
            $uniqueId->setAttribute("ID", $request);
            $readRQ->appendChild($uniqueId);

            // Convert XML to string
            $xml = $doc->saveXML();

            // $endpoint = 'https://ota.zapways.com/Read';
            $certPath = storage_path('certs/cert.pem');
            $keyPath = storage_path('certs/key.pem');
            // Create Guzzle Client
            $client = new Client([
                'base_uri' => $this->endpoint,
                'verify' => false,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'headers' => [
                    "Content-Type" => "text/xml; charset=utf-8",
                ]
            ]);

            // Send Request
            $response = $client->post('', [
                'body' => $xml
            ]);

            $body = (string) $response->getBody();

            // Remove namespace prefixes (S:, ns2:, etc.)
            $body = preg_replace('/(<\/?)(\w+):([^>]*>)/', '$1$3', $body);

            libxml_use_internal_errors(true);
            $xmlObj = simplexml_load_string($body);

            if ($xmlObj === false) {
                foreach (libxml_get_errors() as $error) {
                    Log::error("XML parse error: " . $error->message);
                }
                return response()->json(['error' => 'Invalid XML'], 500);
            }

            $json = json_encode($xmlObj);
            Log::info("Zapways Read Response JSON:\n" . $json);

            $array = json_decode($json, true);

            return json_encode($array);

        } catch (\Exception $e) {
            Log::error("Zapways Read Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function demandTicket($request)
    {
        $pnrJson = $request['pnrData'] ?? '';
        $paymentInfoJson = $request['payment_info'] ?? '';

        Log::info("Raw Payment Info:\n" . $paymentInfoJson);

        $pnr = is_string($pnrJson)
            ? json_decode($pnrJson, true)
            : $pnrJson;





        // Extract Booking Reference ID from PNR response
        $bookingReferenceID = $pnr['Body']['AirBookResponse']['AirBookResult']['AirReservation']['BookingReferenceID'] ?? [];
        $amount = $pnr['Body']['AirBookResponse']['AirBookResult']['AirReservation']['PriceInfo']['ItinTotalFare']['TotalFare']['@attributes']['Amount'] ?? null;
        $currencyCode = $pnr['Body']['AirBookResponse']['AirBookResult']['AirReservation']['PriceInfo']['ItinTotalFare']['TotalFare']['@attributes']['CurrencyCode'] ?? null;
        // Prepare data array
        $data = [
            'Service' => [
                'Target' => $this->target,
                'Version' => $this->version,
            ],
            'APIClient' => [
                'ID' => config('airblue.api_client.id'),
                'Key' => config('airblue.api_client.key'),
            ],
            'Agent' => [
                'ID' => config('airblue.agent.id'),
                'Password' => config('airblue.agent.password'),
            ],
            'BookingReference' => $bookingReferenceID[0] ?? [],
        ];

        // Create XML
        $xml = new SimpleXMLElement('<Envelope/>');
        $xml->addAttribute('xmlns', 'http://schemas.xmlsoap.org/soap/envelope/');

        // Add Header
        $header = $xml->addChild('Header');

        // Main body
        $body = $xml->addChild('Body');
        $airDemandTicket = $body->addChild('AirDemandTicket');
        $airDemandTicket->addAttribute('xmlns', 'http://zapways.com/air/ota/3.0');

        // Request Root
        $rq = $airDemandTicket->addChild('airDemandTicketRQ');
        $rq->addAttribute('Target', $data['Service']['Target']);
        $rq->addAttribute('Version', $data['Service']['Version']);
        $rq->addAttribute('xmlns', 'http://www.opentravel.org/OTA/2003/05');

        // POS Block
        $pos = $rq->addChild('POS');
        $source = $pos->addChild('Source');
        $source->addAttribute('ERSP_UserID', $data['APIClient']['ID'] . '/' . $data['APIClient']['Key']);

        $reqID = $source->addChild('RequestorID');
        $reqID->addAttribute('Type', '29');
        $reqID->addAttribute('ID', $data['Agent']['ID']);
        $reqID->addAttribute('MessagePassword', $data['Agent']['Password']);

        // Demand Ticket Detail
        $demandDetail = $rq->addChild('DemandTicketDetail');

        // Booking Reference ID
        if (!empty($data['BookingReference'])) {
            $br = $demandDetail->addChild('BookingReferenceID');
            foreach ($data['BookingReference'] as $key => $value) {
                if ($key !== '@attributes') {
                    $br->addAttribute($key, $value);
                }
            }
            // Also add attributes if they exist in @attributes
            if (isset($data['BookingReference']['@attributes'])) {
                foreach ($data['BookingReference']['@attributes'] as $key => $value) {
                    $br->addAttribute($key, $value);
                }
            }
        }

        // Payment Info
        $payment = $demandDetail->addChild('PaymentInfo');

        // Handle direct attributes
        $payment->addAttribute('PaymentType', 'Cash');

        $payment->addAttribute('CurrencyCode', $currencyCode);

        $payment->addAttribute('Amount', $amount);







        Log::info("Final AirBlue Demand Ticket Request XML:\n" . $xml->asXML());

        $xmlBody = $xml->asXML();
        return;

        try {
            $certPath = storage_path('certs/cert.pem');
            $keyPath = storage_path('certs/key.pem');

            $client = new Client([
                'base_uri' => $this->endpoint,
                'verify' => false,
                'cert' => $certPath,
                'ssl_key' => $keyPath,
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8'],
            ]);

            // Send request
            // $response = $client->post('', ['body' => $xmlBody]);
            $responseBody = (string) $response->getBody();

            Log::info("AirBlue Demand Ticket Response XML:\n" . $responseBody);

            // Parse XML response
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

            Log::info("Parsed Demand Ticket Response Array: " . json_encode($array));

            return $array;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Handle Guzzle HTTP errors
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? (string) $errorResponse->getBody() : '';

            Log::error("AirBlue Demand Ticket API HTTP Error", [
                'message' => $e->getMessage(),
                'status_code' => $errorResponse ? $errorResponse->getStatusCode() : 'N/A',
                'error_body' => $errorBody,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("AirBlue Demand Ticket General Error", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }
}


