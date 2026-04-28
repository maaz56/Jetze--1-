<?php

namespace App\Services;

use Cache;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Log;
use Noki\XmlConverter\Convert;
use SimpleXMLElement;
use SoapBox\Formatter\Formatter;

class PIAApiService
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
        $this->apiUrl = config('pia.url');
        $this->username = config('pia.username');
        $this->password = config('pia.password');
    }





    public function searchFlights($params)
    {
        try {
            Log::info("Searching PIA Flights with params: " . json_encode($params));

            // Step 1: Prepare client info
            $clientInfo = [
                'clientIP' => '101.53.247.143',
                'member' => 'false',
                'password' => 'Bachakhan553@',
                'userName' => 'UNIQUETTB2B',
                'preferredCurrency' => 'PKR',
            ];

            // Step 2: Prepare Passenger Info
            $paxMapping = [
                'adults' => 'ADLT',
                'children' => 'CHLD',
                'infants' => 'INFT',
            ];

            $passengers = '';
            foreach ($paxMapping as $key => $code) {
                $count = (int) ($params[$key] ?? 0);
                if ($count > 0) {
                    $passengers .= "
                    <passengerTypeQuantityList>
                        <hasStrecher/>
                        <passengerType>
                            <code>{$code}</code>
                        </passengerType>
                        <quantity>{$count}</quantity>
                    </passengerTypeQuantityList>";
                }
            }

            // Step 3: Prepare Origin-Destination Info
            $originDestXml = '';
            if ($params['flight_type'] === 'one-way') {
                $originDestXml .= self::buildOriginDestination(
                    $params['origin'],
                    $params['destination'],
                    $params['departure_date']
                );
            } elseif ($params['flight_type'] === 'return') {
                $originDestXml .= self::buildOriginDestination(
                    $params['origin'],
                    $params['destination'],
                    $params['departure_date']
                );
                $originDestXml .= self::buildOriginDestination(
                    $params['destination'],
                    $params['origin'],
                    $params['return_date']
                );
            } elseif ($params['flight_type'] === 'multi-city' && !empty($params['trips'])) {
                foreach ($params['trips'] as $trip) {
                    $originDestXml .= self::buildOriginDestination(
                        $trip['origin'],
                        $trip['destination'],
                        $trip['date']
                    );
                }
            }

            // Step 4: Assemble full XML SOAP Request
            $tripType = strtoupper($params['flight_type'] == 'one-way' ? 'ONE_WAY' : ($params['flight_type'] == 'return' ? 'ROUND_TRIP' : 'MULTI_CITY'));

            $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
                '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" ' .
                'xmlns:impl="http://impl.soap.ws.crane.hititcs.com/">' .
                '<soapenv:Header/>' .
                '<soapenv:Body>' .
                '<impl:GetAvailability>' .
                '<AirAvailabilityRequest>' .
                '<clientInformation>' .
                '<clientIP>' . $clientInfo['clientIP'] . '</clientIP>' .
                '<member>' . $clientInfo['member'] . '</member>' .
                '<password>' . $clientInfo['password'] . '</password>' .
                '<userName>' . $clientInfo['userName'] . '</userName>' .
                '<preferredCurrency>' . $clientInfo['preferredCurrency'] . '</preferredCurrency>' .
                '</clientInformation>' .
                $originDestXml .
                '<travelerInformation>' . $passengers . '</travelerInformation>' .
                '<tripType>' . $tripType . '</tripType>' .
                '</AirAvailabilityRequest>' .
                '</impl:GetAvailability>' .
                '</soapenv:Body>' .
                '</soapenv:Envelope>';

            // Step 5: Log & Return XML
            Log::info("Generated PIA XML Request:\n" . $xml);

            $request = new Request('POST', $this->apiUrl, [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => 'GetAvailability',
            ], $xml);

            $response = $this->client->send($request);
            $body = (string) $response->getBody();
            Log::info("PIA Search Response Raw XML:\n" . $body);
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
            Log::info("PIA Search Response XML:\n" . $json);
            $array = json_decode($json, true);
            $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
            Cache::put($cacheKeyPrefix . '_PIA_flights', $array, now()->addHour());

            return $array;


        } catch (\Exception $e) {
            Log::error('PIA Search Exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred during flight search.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to build <originDestinationInformationList> XML block
     */
    private static function buildOriginDestination($origin, $destination, $date)
    {
        return "
        <originDestinationInformationList>
            <dateOffset>0</dateOffset>
            <departureDateTime>{$date}</departureDateTime>
            <destinationLocation>
                <locationCode>{$destination}</locationCode>
            </destinationLocation>
            <flexibleFaresOnly>false</flexibleFaresOnly>
            <includeInterlineFlights>false</includeInterlineFlights>
            <openFlight>false</openFlight>
            <originLocation>
                <locationCode>{$origin}</locationCode>
            </originLocation>
        </originDestinationInformationList>";
    }


    public function createBooking($requestData, $fareReferences)
{
   

    // Fetch cached flights
    $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
    $cachedFlights = Cache::get($cacheKeyPrefix . '_PIA_flights');
    $response = $cachedFlights['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList'];

    // Filter selected flight(s)
    $filtered = [];
    $optionList = $response['originDestinationOptionList'];
    if (isset($optionList['fareComponentGroupList'])) {
        $optionList = [$optionList];
    }

    foreach ($fareReferences as $internalId) {
        foreach ($optionList as $option) {
            $fareGroups = $option['fareComponentGroupList'];
            if (isset($fareGroups['fareComponentList'])) {
                $fareGroups = [$fareGroups];
            }
            foreach ($fareGroups as $group) {
                $fareComponents = $group['fareComponentList'];
                if (isset($fareComponents['internalID'])) {
                    $fareComponents = [$fareComponents];
                }
                foreach ($fareComponents as $component) {
                    if ($component['internalID'] === $internalId) {
                        $filtered[] = $option;
                    }
                }
            }
        }
    }

    if (empty($filtered)) {
        Log::info('No flight found for given fareReferences.');
        return null;
    }
    Log::info(json_encode($filtered, JSON_PRETTY_PRINT));
    // For simplicity, just use first filtered flight
    $flight = $filtered[0];
    $fare = $flight['fareComponentGroupList']['fareComponentList'];
    $traveller = $requestData['travellers'][0];
    $mainContact = $requestData['main_contact'];

    // Extract dynamic values
    $fareReferenceID = $fare['passengerFareInfoList']['fareInfoList'][0]['fareReferenceID'] ?? '';
    $fareReferenceCode = $fare['fareReferenceCode'] ?? 'OOWSPK';
    $baggageWeight = 30;
 

    // Build traveler info
    $travelerXML = "
        <airTravelerList>
            <gender>{$traveller['gender']}</gender>
            <birthDate>{$traveller['dob']}</birthDate>
            <passengerTypeCode>{$traveller['type']}</passengerTypeCode>
            <personName>
                <givenName>{$traveller['firstName']}</givenName>
                <surname>{$traveller['lastName']}</surname>
            </personName>
            <contactPerson>
                <email>
                    <email>{$mainContact['email']}</email>
                </email>
                <phoneNumber>
                    <countryCode>+92</countryCode>
                    <subscriberNumber>{$mainContact['phone']}</subscriberNumber>
                </phoneNumber>
            </contactPerson>
        </airTravelerList>
    ";

    // Build flight segments
    $segmentsXML = "";
    $segments = $flight['fareComponentGroupList']['boundList']['availFlightSegmentList'];
    if (isset($segments['flightSegment'])) {
        $segments = [$segments];
    }

    foreach ($segments as $index=>$seg) {
        $carrier = $seg['flightSegment']['airline']['code'];
        $flightNumber = $seg['flightSegment']['flightNumber'];
        $dep = $seg['flightSegment']['departureAirport']['locationCode'];
        $arr = $seg['flightSegment']['arrivalAirport']['locationCode'];
        $depDate = $seg['flightSegment']['departureDateTime'];
        $arrDate = $seg['flightSegment']['arrivalDateTime'];
        $cabin = $seg['bookingClassList']['cabin'];
        $rbd = $seg['bookingClassList']['resBookDesigCode'];

        

        $segmentsXML .= "
            <bookFlightSegmentList>
                <actionCode>NN</actionCode>
                <bookingClass>
                    <cabin>{$cabin}</cabin>
                    <resBookDesigCode>{$rbd}</resBookDesigCode>
                </bookingClass>
                <fareInfo>
                    <cabin>{$cabin}</cabin>
                    <fareReferenceCode>{$fareReferenceCode}</fareReferenceCode>
                    <fareReferenceID>{$fareReferenceID}</fareReferenceID>
                    <fareGroupName>ECO</fareGroupName>
                    <fareBaggageAllowance>
                        <allowanceType>WEIGHT</allowanceType>
                        <maxAllowedPieces>0</maxAllowedPieces>
                        <maxAllowedWeight>
                            <unitOfMeasureCode>KG</unitOfMeasureCode>
                            <weight>{$baggageWeight}</weight>
                        </maxAllowedWeight>
                    </fareBaggageAllowance>
                </fareInfo>
                <flightSegment>
                    <airline><code>{$carrier}</code></airline>
                    <departureAirport><locationCode>{$dep}</locationCode></departureAirport>
                    <arrivalAirport><locationCode>{$arr}</locationCode></arrivalAirport>
                    <departureDateTime>{$depDate}</departureDateTime>
                    <arrivalDateTime>{$arrDate}</arrivalDateTime>
                    <flightNumber>{$flightNumber}</flightNumber>
                </flightSegment>
            </bookFlightSegmentList>
        ";
    }

    // Build full XML
    $xml = "
    <soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:impl=\"http://impl.soap.ws.crane.hititcs.com/\">
        <soapenv:Body>
            <impl:CreateBooking>
                <AirBookingRequest>
                    <clientInformation>
                        <clientIP>127.0.0.1</clientIP>
                        <member>false</member>
                        <password>Test123</password>
                        <userName>OTATEST</userName>
                        <preferredCurrency>PKR</preferredCurrency>
                    </clientInformation>
                    <airItinerary>
                        <bookOriginDestinationOptions>
                            <bookOriginDestinationOptionList>
                                {$segmentsXML}
                            </bookOriginDestinationOptionList>
                        </bookOriginDestinationOptions>
                    </airItinerary>
                    {$travelerXML}
                    <requestPurpose>MODIFY_PERMANENTLY_AND_CALC</requestPurpose>
                </AirBookingRequest>
            </impl:CreateBooking>
        </soapenv:Body>
    </soapenv:Envelope>
    ";

    Log::info("Generated XML:");
    Log::info($xml);

    return trim($xml);
}


    public function filterFlights($requestData, $fareReferences)
    {


        $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
        $cachedFlights = Cache::get($cacheKeyPrefix . '_PIA_flights');

        $response = $cachedFlights['Body']['GetAvailabilityResponse']['Availability']['availabilityResultList']['availabilityRouteList']['availabilityByDateList'];

        $filtered = [];

        // Normalize option list
        $optionList = $response['originDestinationOptionList'];
        if (isset($optionList['fareComponentGroupList'])) {
            $optionList = [$optionList];
        }

        // Loop through all selected fareReferences
        foreach ($fareReferences as $internalId) {
            foreach ($optionList as $option) {
                $fareGroups = $option['fareComponentGroupList'];

                if (isset($fareGroups['fareComponentList'])) {
                    $fareGroups = [$fareGroups];
                }

                foreach ($fareGroups as $group) {
                    $fareComponents = $group['fareComponentList'];

                    if (isset($fareComponents['internalID'])) {
                        $fareComponents = [$fareComponents];
                    }

                    foreach ($fareComponents as $component) {
                        if ($component['internalID'] === $internalId) {
                            $filtered[] = $option;
                        }
                    }
                }
            }
        }

        if (!empty($filtered)) {
            Log::info('Filtered flights:');
            Log::info(json_encode($filtered, JSON_PRETTY_PRINT));
        } else {
            Log::info('No flights found for given fareReferences.');
        }
        return $filtered;
    }









}
