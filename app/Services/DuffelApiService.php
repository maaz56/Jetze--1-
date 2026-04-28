<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class DuffelApiService
{
    protected $client;
    protected $headers;

    public function __construct()
    {
        $this->client = new Client();
        $this->headers = [
            'Accept-Encoding' => 'gzip',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Duffel-Version' => env('DUFFEL_API_VERSION'),
            'Authorization' => 'Bearer ' . env('DUFFEL_API_KEY'),
        ];
    }
    public function searchFlights($params)
    {
        $slices = [
            [
                'origin' => $params['origin'],
                'destination' => $params['destination'],
                'departure_date' => $params['departure_date'],
            ]
        ];

        // Add return flight if return_date is provided
        if (!empty($params['return_date'])) {
            $slices[] = [
                'origin' => $params['destination'],
                'destination' => $params['origin'],
                'departure_date' => $params['return_date'],
            ];
        }

        $passengers = [];
        $passengerTypes = [
            'adults' => 'adult',
            'children' => 'child',
            'infants' => 'infant_without_seat'
        ];

        foreach ($passengerTypes as $key => $type) {
            $count = $params[$key] ?? 0;
            for ($i = 0; $i < $count; $i++) {
                if ($type === 'infant_without_seat') {
                    $passengers[] = ['age' => 1]; // Example age for infant
                } else {
                    $passengers[] = ['type' => $type];
                }
            }
        }

        $body = [
            "data" =>  [
                "slices" => $slices,
                "passengers" => $passengers,
                "cabin_class" => $params->cabin_class ?? 'economy'
            ]
        ];

        $request = new Request('POST', 'https://api.duffel.com/air/offer_requests', $this->headers, json_encode($body));
        $res = $this->client->sendAsync($request)->wait();
        return json_decode($res->getBody()->getContents());
    }

    public function getFlightById($id)
    {
        $id = trim($id);
        $request = new Request('GET', "https://api.duffel.com/air/offers/$id", $this->headers);
        $res = $this->client->sendAsync($request)->wait();
        return json_decode($res->getBody());
    }
}
