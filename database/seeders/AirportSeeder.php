<?php

namespace Database\Seeders;

use App\Models\Airport;
use GuzzleHttp\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AirportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accessToken = env('DUFFEL_API_KEY');
        $baseUrl = env('DUFFEL_BASE_URL');

        $client = new Client();

        $after = null; // Initial value for pagination
        $limit = 100; // Number of airports to fetch per request

        do {
            $url = $baseUrl . '/air/airports' . '?limit=' . $limit;
            if ($after) {
                $url .= '&after=' . $after;
            }

            $response = $client->get($url, [
                'headers' => [
                    'Accept-Encoding' => 'gzip',
                    'Accept' => 'application/json',
                    'Duffel-Version' => env('DUFFEL_API_VERSION'),
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['data']) && is_array($data['data'])) {
                $airports = $data['data'];

                foreach ($airports as $airport) {
                    $airportToSave = [
                        "iata_city_code" => $airport['iata_city_code'],
                        "city_name" => $airport['city_name'],
                        "iata_country_code" => $airport['iata_country_code'],
                        "iata_code" => $airport['iata_code'],
                        "latitude" => $airport['latitude'],
                        "longitude" => $airport['longitude'],
                        "time_zone" => $airport['time_zone'],
                        "name" => $airport['name'],
                    ];

                    // Use 'iata_code' as the unique identifier for updateOrCreate
                    Airport::updateOrCreate(['iata_code' => $airportToSave['iata_code']], $airportToSave);
                }

                $after = $data['meta']['after'] ?? null; // Update pagination cursor
            } else {
                $after = null; // Stop loop if no more data or error occurred
            }
        } while ($after);
    }
}
