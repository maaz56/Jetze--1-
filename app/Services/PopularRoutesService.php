<?php

namespace App\Services;

use App\Models\PopularRoute;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PopularRoutesService
{
    public function __construct(
        private readonly FlightAggregationService $flightAggregationService
    ) {
    }

    public function refreshDynamicPrice(PopularRoute $route): ?float
    {
        $route->loadMissing('airline');

        if ($route->price_type !== 'dynamic') {
            return null;
        }

        $params = $this->buildFlightSearchParams($route);
        $response = $this->flightAggregationService->getFlights($params);
        $itineraries = $response['results'] ?? [];

        if (!is_array($itineraries) || empty($itineraries)) {
            Log::info('No Travelport flights found for popular route refresh', [
                'popular_route_id' => $route->id,
                'params' => $params,
            ]);
            $route->touch();

            return null;
        }

        $cheapestPrice = $this->findCheapestMatchingPrice($itineraries, $route->airline?->iata_code);

        if ($cheapestPrice === null) {
            Log::info('No matching supplier fare found for popular route refresh', [
                'popular_route_id' => $route->id,
                'airline_iata' => $route->airline?->iata_code,
            ]);
            $route->touch();

            return null;
        }

        $route->forceFill([
            'static_price' => round($cheapestPrice, 2),
        ])->save();

        Log::info('Popular route dynamic price refreshed', [
            'popular_route_id' => $route->id,
            'price' => $route->static_price,
        ]);

        return (float) $route->static_price;
    }

    public function buildFlightSearchParams(PopularRoute $route): array
    {
        $departureDate = Carbon::today()->addDays((int) ($route->departure_plus_days ?? 0));
        $isRoundTrip = $route->journey_type === 'round';

        return [
            'airline' => 'TravelPort',
            'cabin_class' => $this->mapTravelClass($route->travel_class),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'flight_type' => $isRoundTrip ? 'return' : 'one-way',
            'currency_code' => 'PKR',
            'flexible_plus_minus_3' => false,
            'origin' => $route->from_airport,
            'destination' => $route->to_airport,
            'departure_date' => $departureDate->toDateString(),
            'return_date' => $isRoundTrip && $route->stay_duration_days !== null
                ? $departureDate->copy()->addDays((int) $route->stay_duration_days)->toDateString()
                : null,
        ];
    }

    private function mapTravelClass(?string $travelClass): string
    {
        return match ($travelClass) {
            'business' => 'C',
            'first' => 'F',
            'premium_economy', 'premium-economy' => 'S',
            default => 'Y',
        };
    }

    private function findCheapestMatchingPrice(array $itineraries, ?string $airlineIata): ?float
    {
        $prices = [];
        $airlineIata = strtoupper((string) $airlineIata);

        foreach ($itineraries as $itinerary) {
            if ($airlineIata !== '' && !$this->itineraryMatchesAirline($itinerary, $airlineIata)) {
                continue;
            }

            $price = $this->calculateItineraryPrice($itinerary);
            if ($price !== null) {
                $prices[] = $price;
            }
        }

        return empty($prices) ? null : min($prices);
    }

    private function itineraryMatchesAirline(array $itinerary, string $airlineIata): bool
    {
        foreach (data_get($itinerary, 'leg.flights', []) as $flight) {
            $marketingCarrier = strtoupper((string) data_get($flight, 'marketing_carrier.iata', ''));
            if ($marketingCarrier === $airlineIata) {
                return true;
            }

            foreach (($flight['segments'] ?? []) as $segment) {
                $operatingCarrier = strtoupper((string) data_get($segment, 'operating_carrier.iata', ''));
                if ($operatingCarrier === $airlineIata) {
                    return true;
                }
            }
        }

        return false;
    }

    private function calculateItineraryPrice(array $itinerary): ?float
    {
        $total = 0.0;
        $hasPrice = false;

        foreach (data_get($itinerary, 'leg.flights', []) as $flight) {
            $fares = $flight['fares'] ?? [];
            if (!is_array($fares) || empty($fares)) {
                continue;
            }

            $farePrices = array_values(array_filter(array_map(function ($fare) {
                $price = $fare['total_price'] ?? null;

                return is_numeric($price) ? (float) $price : null;
            }, $fares), fn ($price) => $price !== null));

            if (empty($farePrices)) {
                continue;
            }

            $total += min($farePrices);
            $hasPrice = true;
        }

        return $hasPrice ? $total : null;
    }
}
