<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FlightSearch extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /**
     * Remember the routes of a flight search. Never lets a tracking failure
     * break the search itself.
     */
    public static function record(Request $request): void
    {
        try {
            $trips = $request->input('flightType') === 'multi-city' && is_array($request->input('trips'))
                ? $request->input('trips')
                : [['origin' => $request->input('origin'), 'destination' => $request->input('destination')]];

            $userId = $request->user('sanctum')?->id;
            $rows = [];

            foreach ($trips as $trip) {
                $origin = strtoupper(trim((string) data_get($trip, 'origin')));
                $destination = strtoupper(trim((string) data_get($trip, 'destination')));

                if ($origin === '' || $destination === '' || strlen($origin) > 10 || strlen($destination) > 10) {
                    continue;
                }

                $rows[] = ['user_id' => $userId, 'origin' => $origin, 'destination' => $destination, 'created_at' => now()];
            }

            if ($rows) {
                static::insert($rows);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not record flight search: ' . $e->getMessage());
        }
    }
}
