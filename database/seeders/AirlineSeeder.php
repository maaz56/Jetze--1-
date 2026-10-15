<?php

namespace Database\Seeders;

use App\Models\Airline;
use GuzzleHttp\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AirlineSeeder extends Seeder
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
            $url = $baseUrl . '/air/airlines' . '?limit=' . $limit;
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
                $airlines = $data['data'];

                foreach ($airlines as $airline) {
                    $airlineToSave = [
                        'iata_code' => $airline['iata_code'],
                        'name' => $airline['name'],
                        'logo_url' => $airline['logo_symbol_url'],
                        // Duffel can return condition URLs longer than this
                        // legacy varchar column supports. These links are not
                        // needed for airline lookup, so leave them unset.
                        'carrier_condition_url' => null,
                    ];

                    // IATA codes are not globally unique in AT's carrier data.
                    Airline::updateOrCreate([
                        'iata_code' => $airlineToSave['iata_code'],
                        'name' => $airlineToSave['name'],
                    ], $airlineToSave);
                }

                $after = $data['meta']['after'] ?? null; // Update pagination cursor
            } else {
                $after = null; // Stop loop if no more data or error occurred
            }
        } while ($after);
    }
}
