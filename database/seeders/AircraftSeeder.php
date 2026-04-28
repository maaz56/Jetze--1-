<?php

namespace Database\Seeders;

use App\Models\Aircraft;
use GuzzleHttp\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AircraftSeeder extends Seeder
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
        $limit = 100; // Number of airlines to fetch per request

        do {
            $url = $baseUrl . '/air/aircraft' . '?limit=' . $limit;
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
                $aircrafts = $data['data'];

                foreach ($aircrafts as $aircraft) {
                    $aircraftToSave = [
                        'iata_code' => $aircraft['iata_code'],
                        'name' => $aircraft['name'],
                    ];

                    // Use 'iata_code' as the unique identifier for updateOrCreate
                    Aircraft::updateOrCreate(['iata_code' => $aircraftToSave['iata_code']], $aircraftToSave);
                }

                $after = $data['meta']['after'] ?? null; // Update pagination cursor
            } else {
                $after = null; // Stop loop if no more data or error occurred
            }
        } while ($after);
    }
}
