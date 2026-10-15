<?php

namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use Illuminate\Support\Str;

class HakimiFlightTransformer
{
    private array $airports = [];
    private array $airlines = [];

    /** Convert Hakimi's one-way test offers into Jetze's flight-card structure. */
    public function transform(array $response, array $params): array
    {
        $origin = strtoupper((string) $params['origin']);
        $destination = strtoupper((string) $params['destination']);
        $date = $params['departure_date'];

        return collect($response['results'] ?? [])
            ->filter(fn (array $offer): bool => !empty($offer['inventoryId']))
            ->map(function (array $offer) use ($origin, $destination, $date, $params): array {
                $currency = strtoupper((string) ($offer['currency'] ?? 'INR'));
                // Hakimi Search prices are per passenger. The flight card needs
                // the current party's total, but the source amounts remain on
                // the fare for the required Reprice call before booking.
                $adultPrice = (float) ($offer['price'] ?? 0);
                $childPrice = (float) ($offer['childPrice'] ?? $adultPrice);
                $infantPrice = (float) ($offer['infantPrice'] ?? 0);
                $segments = $this->segments($offer, $origin, $destination, $date);
                $firstSegment = $segments[0];
                $lastSegment = $segments[array_key_last($segments)];
                $counts = ['ADT' => (int) ($params['adults'] ?? 1), 'CHD' => (int) ($params['children'] ?? 0), 'INF' => (int) ($params['infants'] ?? 0)];
                $total = ($adultPrice * $counts['ADT'])
                    + ($childPrice * $counts['CHD'])
                    + ($infantPrice * $counts['INF']);
                $taxTotal = (float) ($offer['taxTotal'] ?? 0) * array_sum($counts);
                $fare = [
                    'ref_id' => (string) Str::uuid(), 'inventory_id' => $offer['inventoryId'],
                    'offer_id' => $offer['offerId'] ?? null, 'fare_id' => $offer['fareId'] ?? null,
                    'name' => $offer['fareLabel'] ?? 'Fixed Departure', 'name_class' => 'Economy',
                    'brand_tier' => $offer['fareLabel'] ?? 'Fixed Departure',
                    'currency' => ['code' => $currency, 'name' => $currency, 'symbol' => $currency, 'decimal' => 2],
                    // Search total for the selected party; Reprice totalFare is still authoritative.
                    'base_price' => $total, 'taxes' => $taxTotal,
                    'total_price' => $total, 'billable_price' => $total,
                    'display_money' => ['currency' => $currency, 'amount' => $total],
                    'provider_booking_money' => ['currency' => $currency, 'amount' => $total],
                    'provider_gross_money' => ['currency' => $currency, 'amount' => $total],
                    'per_pax_prices' => ['adult' => $adultPrice, 'child' => $childPrice, 'infant' => $infantPrice],
                    'passenger_counts' => $counts, 'passenger_count' => array_sum($counts),
                    'is_refundable' => (bool) ($offer['refundable'] ?? false),
                    'seats_left' => $offer['seatsLeft'] ?? null, 'requires_reprice' => true,
                    'baggage_policies' => $this->baggagePolicies($offer['baggage'] ?? [], $segments),
                    'passenger_fares' => $this->passengerFares($counts, $adultPrice, $childPrice, $infantPrice, $currency),
                ];
                $flight = [
                    'ref_id' => (string) Str::uuid(), 'from' => $firstSegment['from'], 'to' => $lastSegment['to'],
                    'segments' => $segments, 'fares' => [$fare], 'departure_at' => $firstSegment['departure_at'],
                    'arrival_at' => $lastSegment['arrival_at'],
                    'travel_time' => $this->durationMinutes($offer['duration'] ?? null, $firstSegment, $lastSegment),
                    'has_layovers' => count($segments) > 1, 'layovers_count' => max(count($segments) - 1, 0),
                    'change_of_plane' => count($segments) > 1, 'marketing_carrier' => $firstSegment['marketing_carrier'],
                    'operating_carrier' => $firstSegment['operating_carrier'], 'is_refundable' => $fare['is_refundable'],
                    'flight_number' => $firstSegment['flight_number'],
                ];

                return [
                    'provider' => ['name' => 'Travel With Hakimi (Test)', 'identifier' => 'HAKIMI', 'contentSource' => 'HAKIMI_TEST', 'sector' => $origin . '-' . $destination, 'travel_date' => $date],
                    'currencyCode' => $currency, 'displayCurrencyCode' => $currency,
                    'leg' => ['ref_id' => (string) Str::uuid(), 'flights' => [$flight], 'trip_nature' => 'one-way'],
                ];
            })->values()->all();
    }

    private function segments(array $offer, string $origin, string $destination, string $date): array
    {
        $rawSegments = $offer['segments'] ?? [[
            'origin' => $offer['origin'] ?? $origin, 'destination' => $offer['destination'] ?? $destination,
            'departure' => $offer['departure'] ?? $date, 'arrival' => $offer['arrival'] ?? null,
            'duration' => $offer['duration'] ?? null, 'airlineCode' => $offer['airlineCode'] ?? null,
            'airlineName' => $offer['airlineName'] ?? null, 'flightNo' => $offer['flightNo'] ?? null,
        ]];

        return array_map(function (array $segment) use ($offer): array {
            $carrier = $this->carrier($segment['airlineCode'] ?? $offer['airlineCode'] ?? null, $segment['airlineName'] ?? $offer['airlineName'] ?? null);
            return [
                'ref_id' => (string) Str::uuid(), 'from' => $this->airport($segment['origin'] ?? $offer['origin']),
                'to' => $this->airport($segment['destination'] ?? $offer['destination']),
                'departure_at' => $segment['departure'] ?? $offer['departure'] ?? null,
                'arrival_at' => $segment['arrival'] ?? $offer['arrival'] ?? null,
                'flight_number' => $segment['flightNo'] ?? $segment['flightNumber'] ?? $offer['flightNo'] ?? null,
                'flight_time' => $segment['duration'] ?? $offer['duration'] ?? null,
                'travel_time' => $this->durationMinutes($segment['duration'] ?? $offer['duration'] ?? null),
                'cabin_class' => $segment['cabin'] ?? $offer['cabin'] ?? 'Economy',
                'marketing_carrier' => $carrier, 'operating_carrier' => $carrier,
            ];
        }, $rawSegments);
    }

    private function carrier(?string $iata, ?string $name): array
    {
        $iata = strtoupper(trim((string) $iata));
        $airline = $iata === '' ? null : ($this->airlines[$iata] ??= Airline::where('iata_code', $iata)->first());
        return ['iata' => $iata ?: null, 'name' => $airline?->name ?? $name ?? $iata, 'logo' => $airline?->logo_url];
    }

    private function airport(?string $iata): array
    {
        $iata = strtoupper(trim((string) $iata));
        $airport = $iata === '' ? null : ($this->airports[$iata] ??= Airport::where('iata_code', $iata)->first());
        return [
            'name' => $airport?->name, 'iata' => $iata ?: null,
            'city' => ['name' => $airport?->city_name ?? $iata ?: null, 'code' => $airport?->iata_city_code ?? $iata ?: null,
                'country' => ['name' => $airport?->iata_country_code, 'code' => $airport?->iata_country_code]],
            'country' => ['name' => $airport?->iata_country_code, 'code' => $airport?->iata_country_code],
        ];
    }

    private function baggagePolicies(array $baggage, array $segments): array
    {
        $policies = [];
        foreach ($segments as $segment) {
            foreach (['hand' => 'carry', 'checkIn' => 'checkIn'] as $key => $type) {
                if (array_key_exists($key, $baggage)) {
                    $policies[] = ['type' => $type, 'description' => (string) $baggage[$key],
                        'allowance_known' => true, 'traveler_type' => 'ADT', 'segment_ref_id' => $segment['ref_id']];
                }
            }
        }
        return $policies;
    }

    private function passengerFares(array $counts, float $adultPrice, float $childPrice, float $infantPrice, string $currency): array
    {
        return [
            ['type' => 'ADT', 'count' => $counts['ADT'], 'base_price' => $adultPrice, 'total_price' => $adultPrice * $counts['ADT'], 'currency' => $currency],
            ['type' => 'CHD', 'count' => $counts['CHD'], 'base_price' => $childPrice, 'total_price' => $childPrice * $counts['CHD'], 'currency' => $currency],
            ['type' => 'INF', 'count' => $counts['INF'], 'base_price' => $infantPrice, 'total_price' => $infantPrice * $counts['INF'], 'currency' => $currency],
        ];
    }

    /** Jetze's cards expect durations as integer minutes, not "02:00" strings. */
    private function durationMinutes(mixed $duration, array $firstSegment = [], array $lastSegment = []): int
    {
        if (is_numeric($duration)) {
            return max(0, (int) $duration);
        }

        $duration = trim((string) $duration);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $duration, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }
        if (preg_match('/(\d+)\s*h(?:ours?)?\s*(\d+)?\s*m?/i', $duration, $matches)) {
            return ((int) $matches[1] * 60) + (int) ($matches[2] ?? 0);
        }

        $departure = $firstSegment['departure_at'] ?? null;
        $arrival = $lastSegment['arrival_at'] ?? null;
        if ($departure && $arrival) {
            return max(0, (int) ((strtotime($arrival) - strtotime($departure)) / 60));
        }

        return 0;
    }
}
