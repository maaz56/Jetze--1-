<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Log;

class AirSialApiService
{
    protected $client;
    protected $headers;

    protected $apiUrl;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiUrl = config('airsial.url');
        $this->username = config('airsial.username');
        $this->password = config('airsial.password');
    }

    public function getAccessToken()
    {
        try {
            $tokenURL = $this->apiUrl; // Adjust endpoint if necessary

            $headers = [
                'Content-Type' => 'application/json',
            ];

            $body = [
                [
                    'Caller' => 'login',
                    'Username' => $this->username,
                    'Password' => $this->password,
                ]
            ];

            // Create Duffel-style Guzzle request
            $request = new Request(
                'POST',
                $tokenURL,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response
            $response = $this->client->sendAsync($request)->wait();

            // Decode the response JSON
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("AirSial Access Token Response: " . json_encode($data));

            // Extract the token from nested structure
            $token = $data['Response']['Data']['token'] ?? null;

            if (!$token) {
                Log::warning("AirSial token missing in response");
            }

            return $token;

        } catch (\Exception $e) {
            Log::error("AirSial Access Token Error: " . $e->getMessage());
            return null;
        }
    }



    public function searchFlights($params)
    {

        Log::info("Searching AirSial Flights with params: " . json_encode($params));
        try {
            Log::info("AirSial API URL: " . $this->apiUrl);
            // First, get the AirSial token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("AirSial Token not retrieved");
                return ['error' => 'Unable to get access token'];
            }
            // Prepare API endpoint
            $searchUrl = $this->apiUrl;

            // Prepare headers
            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Prepare request body as per AirSial documentation
            $body = [
                [
                    "Caller" => "getSingleflight",
                    "token" => $token,
                    "DepartingOn" => $params['departure_date'], // e.g. "15-01-2026"
                    "LocationDep" => $params['origin'],         // e.g. "KHI"
                    "LocationArr" => $params['destination'],    // e.g. "ISB"
                    "Return" => $params['flight_type'] == 'return' ? true : false, // true if round trip
                    "ReturningOn" => $params['return_date'] ?? null,
                    "AdultNo" => (int) ($params['adults'] ?? 1),
                    "ChildNo" => (int) ($params['children'] ?? 0),
                    "InfantNo" => (int) ($params['infants'] ?? 0),
                ]
            ];
            Log::info("AirSial Flight Search Request Body: " . json_encode($body));
            // Create Duffel-style Guzzle request
            $request = new Request(
                'POST',
                $searchUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response
            $response = $this->client->sendAsync($request)->wait();
            // Decode the JSON response
            $data = json_decode($response->getBody()->getContents(), true);

            // Log::info("AirSial Flight Search Response: " . json_encode($data));

            return $data;

        } catch (\Exception $e) {
            Log::error("AirSial Flight Search Error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    public function createBooking($requestData, $flightData, $fareReference)
    {
        Log::info($requestData);
        Log::info($flightData);
        Log::info($fareReference);
        try {
            // Get the AirSial token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("AirSial Token not retrieved");
                return null;
            }

            // Initialize passenger counts
            $totalAdult = 0;
            $totalChild = 0;
            $totalInfant = 0;

            // Count passengers by type
            foreach ($requestData['travellers'] as $traveller) {
                switch ($traveller['type']) {
                    case 'ADT':
                        $totalAdult++;
                        break;
                    case 'CNN':
                        $totalChild++;
                        break;
                    case 'INF':
                        $totalInfant++;
                        break;
                }
            }

            // Calculate total seats (adults + children)
            $totalSeats = $totalAdult + $totalChild;

            // Initialize flight details
            $isReturn = count($requestData['flight']['leg']['flights']) > 1;
            $departureJourney = '';
            $departureFareType = '1'; // Default
            $departureClass = '';
            $departureFlight = '';
            $locationDep = '';
            $locationArr = '';
            $returningJourney = '';
            $returningClass = '';
            $returningFlight = '';
            $returningFareType = '1'; // Default for one-way
            $ftype = $requestData['flight']['leg']['trip_nature'] === 'international' ? 'int' : 'dom';

            // Process flights using a loop
            foreach ($requestData['flight']['leg']['flights'] as $index => $flight) {
                // Get the first segment (assuming one segment per flight for simplicity)
                $segment = null;
                foreach ($flight['segments'] as $seg) {
                    $segment = $seg; // Take the first segment
                    break; // Exit after first segment
                }

                if ($segment === null) {
                    Log::error("No segments found for flight index: $index");
                    return null;
                }

                // Handle departure flight (first flight)
                if ($index === 0) {
                    $departureJourney = $segment['journey_code'];
                    $departureClass = $segment['class_code'];
                    $departureFlight = $segment['flight_number'];
                    $locationDep = $flight['from']['iata'];
                    $locationArr = $flight['to']['iata'];

                    // Find departure fare type
                    $fareRef = !empty($fareReference[$index]) ? $fareReference[$index] : null;
                    if ($fareRef) {
                        foreach ($flight['fares'] as $fare) {
                            if ($fare['ref_id'] === $fareRef) {
                                $departureFareType = $fare['sub_class_id'];
                                break;
                            }
                        }
                    }
                }
                // Handle return flight (second flight, if exists)
                elseif ($index === 1 && $isReturn) {
                    $returningJourney = $segment['journey_code'];
                    $returningClass = $segment['class_code'];
                    $returningFlight = $segment['flight_number'];

                    // Find return fare type
                    $fareRef = !empty($fareReference[$index]) ? $fareReference[$index] : null;
                    if ($fareRef) {
                        foreach ($flight['fares'] as $fare) {
                            if ($fare['ref_id'] === $fareRef) {
                                $returningFareType = $fare['sub_class_id'];
                                break;
                            }
                        }
                    }
                }
            }

            // Prepare the body
            $body = [
                [
                    "Caller" => "bookSeat",
                    "token" => $token,
                    "Return" => $isReturn,
                    "DepartureJourney" => $departureJourney,
                    "DepartureFareType" => $departureFareType,
                    "DepartureClass" => $departureClass,
                    "DepartureFlight" => $departureFlight,
                    "ReturningJourney" => $returningJourney,
                    "ReturningClass" => $returningClass,
                    "ReturningFlight" => $returningFlight,
                    "ReturningFareType" => $returningFareType,
                    "LocationDep" => $locationDep,
                    "LocationArr" => $locationArr,
                    "ftype" => $ftype,
                    "TotalSeats" => $totalSeats,
                    "totalInfant" => $totalInfant,
                    "totalAdult" => $totalAdult,
                    "totalChild" => $totalChild
                ]
            ];

            // Prepare API endpoint
            $bookingUrl = $this->apiUrl;
            $headers = [
                'Content-Type' => 'application/json',
            ];
            // Log the body for debugging
            Log::info("Booking Request Body: " . json_encode($body));
            // Add your API call logic here (e.g., using HTTP client to send the request)
            // Example:
            $request = new Request(
                'POST',
                $bookingUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response
            $response = $this->client->sendAsync($request)->wait();
            // Decode the JSON response
            $data = json_decode($response->getBody()->getContents(), true);
            Log::info("AirSial Flight Booking Response: " . json_encode($data));
            if (isset($data['Response']['Data'])) {
                $pnr = $data['Response']['Data'];
                // Insert passengers after successful booking
                $data = $this->insertPassengers($requestData, $pnr);
            }
            return $data;

        } catch (\Exception $e) {
            Log::error("AirSial Booking Error: " . $e->getMessage());
            return null;
        }
    }


    public function insertPassengers($requestData, $pnr)
    {
        $token = $this->getAccessToken(); // or wherever you set it

        $body = [
            [
                "Caller" => "passengerInsertion",
                "PNR" => $pnr,
                "token" => $token,
                "adult" => [],
                "child" => [],
                "infant" => [],
                "PrimaryCell" => $requestData['agency_contact']['phone'],
                "SecondaryCell" => "+92",
                "EmailAddress" => $requestData['agency_contact']['email'] ?? "",
                "CNIC" => "",
                "Comments" => ""
            ]
        ];

        // Loop through travellers and add each to correct group
        foreach ($requestData['travellers'] as $traveller) {
            // Normalize type to lowercase (ADT → adult, CHD → child, INF → infant)
            $type = strtolower($traveller['type']);
            if ($type === 'adt')
                $type = 'adult';
            elseif ($type === 'cnn')
                $type = 'child';
            elseif ($type === 'inf')
                $type = 'infant';

            // Build passenger data
            $passenger = [
                "Title" => strtoupper($traveller['title'] == 'Miss' ? 'MS' : $traveller['title']),
                "WheelChair" => "N",
                "FullName" => trim($traveller['firstName'] . " " . $traveller['lastName']),
                "Firstname" => $traveller['firstName'],
                "Lastname" => $traveller['lastName'],
                "Passport" => $traveller['documentNo'] ?? "",
                "PassportCountry" => strtoupper($traveller['issueCountry'] ?? ""),
                "PassportExpiry" => !empty($traveller['expiryDate'])
                    ? date("d-m-Y", strtotime($traveller['expiryDate']))
                    : "",
                "Dob" => !empty($traveller['dob'])
                    ? date("d-m-Y", strtotime($traveller['dob']))
                    : "",
            ];

            if (isset($traveller['cnic'])) {
                $passenger['Cnic'] = $traveller['cnic'];
            }

            $body[0][$type][] = $passenger;


        }

        Log::info("Passenger Request Body: " . json_encode($body, JSON_PRETTY_PRINT));
        try {
            $passengerUrl = $this->apiUrl;
            $headers = [
                'Content-Type' => 'application/json',
            ];
            // Log the body for debugging
            Log::info("Passenger Insertion Request Body: " . json_encode($body));
            // Add your API call logic here (e.g., using HTTP client to send the request)
            // Example:
            $request = new Request(
                'POST',
                $passengerUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response
            $response = $this->client->sendAsync($request)->wait();
            // Decode the JSON response
            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info("AirSial Passenger Insertion Response: " . json_encode($data, JSON_PRETTY_PRINT));
            return $data;

        } catch (\Exception $e) {
            Log::error("AirSial Passenger Insertion Error: " . $e->getMessage());
            return null;
        }

    }


    public function getBookingDetails($request)
    {
        try {
            // Get the AirSial token
            $token = $this->getAccessToken();
            if (!$token) {
                Log::error("AirSial Token not retrieved");
                return null;
            }

            // Prepare API endpoint
            $pnrUrl = $this->apiUrl;

            // Prepare headers
            $headers = [
                'Content-Type' => 'application/json',
            ];

            // Prepare request body as per AirSial documentation
            $body = [
                [
                    "Caller" => "viewTicket",
                    "token" => $token,
                    "PNR" => $request->pnr
                ]
            ];

            Log::info("Get Booking Details Request Body: " . json_encode($body));

            // Create Duffel-style Guzzle request
            $request = new Request(
                'POST',
                $pnrUrl,
                $headers,
                json_encode($body)
            );

            // Send async request and wait for response
            $response = $this->client->sendAsync($request)->wait();
            // Decode the JSON response
            $data = json_decode($response->getBody()->getContents(), true);

            Log::info("AirSial Get Booking Details Response: " . json_encode($data));

            return $data;

        } catch (\Exception $e) {
            Log::error("AirSial Get Booking Details Error: " . $e->getMessage());
            return null;
        }
    }

  

}
