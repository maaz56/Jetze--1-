<?php

namespace App\Services;

use App\Models\PopularRoute;
use App\Transformers\AtFlightTransformer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PopularRoutesService
{
    public function __construct(
        private readonly AtApiService $atApiService,
        private readonly AtFlightTransformer $atFlightTransformer,
        private readonly CommercialPricingService $commercialPricingService,
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /**
     * Refresh dynamic price for a popular route via AT without touching updated_at.
     */
    public function refreshDynamicPrice(PopularRoute $route): array
    {
        $route->loadMissing('airline');

        if ($route->price_type !== 'dynamic') {
            return [
                'status' => 'skipped',
                'message' => 'Route is not configured for dynamic pricing.',
            ];
        }

        $refreshHours = max(1, (int) ($route->dynamic_refresh_hours ?: 24));
        $fallbackDays = (int) config('popular_routes.date_fallback_days', 3);
        $baseDeparturePlusDays = max(1, (int) ($route->departure_plus_days ?? 1));

        $foundMatch = null;
        $attemptedDates = [];

        try {
            // Attempt search with date fallback
            for ($dayOffset = 0; $dayOffset <= $fallbackDays; $dayOffset++) {
                $currentDays = $baseDeparturePlusDays + $dayOffset;
                $params = $this->buildFlightSearchParams($route, $currentDays);
                $attemptedDates[] = $params['departure_date'];

                $response = $this->atApiService->searchFlights($params);

                if (is_array($response) && (!empty($response['Trips']) || !empty($response['flights']))) {
                    $mappedFlights = $this->atFlightTransformer->fromAT($response, $params);
                    $mappedFlights = $this->commercialPricingService->applyToProviderFlights($mappedFlights);

                    if (!empty($mappedFlights)) {
                        $cheapest = $this->findCheapestMatchingOption(
                            $mappedFlights,
                            $route->airline?->iata_code,
                            $route->journey_type === 'round'
                        );

                        if ($cheapest !== null) {
                            $foundMatch = $cheapest;
                            $foundMatch['departure_date'] = $params['departure_date'];
                            $foundMatch['return_date'] = $params['return_date'] ?? null;
                            break;
                        }
                    }
                }
            }

            if ($foundMatch === null) {
                // No fares found matching criteria
                $failures = ((int) $route->price_failure_count) + 1;
                $backoffMinutes = min($refreshHours * 60, (int) (30 * (2 ** min(5, $failures))));

                $route->forceFill([
                    'price_checked_at' => now(),
                    'price_status' => $route->dynamic_price ? 'stale' : 'no_fares',
                    'price_failure_count' => $failures,
                    'price_last_error' => 'No matching flights found via AT for dates: ' . implode(', ', $attemptedDates),
                    'next_price_refresh_at' => now()->addMinutes($backoffMinutes),
                ])->saveQuietly();

                Log::info('Popular route refresh: no matching fares found via AT', [
                    'popular_route_id' => $route->id,
                    'from' => $route->from_airport,
                    'to' => $route->to_airport,
                    'airline' => $route->airline?->iata_code,
                    'attempted_dates' => $attemptedDates,
                ]);

                return [
                    'status' => 'no_fares',
                    'route_id' => $route->id,
                ];
            }

            // Success! Store the cheapest live fare in base currency (AED)
            $finalPrice = round((float) $foundMatch['price'], 2);
            $baseCurrency = config('popular_routes.base_currency', 'AED');
            $meta = [
                'airline_iata' => $foundMatch['airline_iata'] ?? $route->airline?->iata_code,
                'airline_name' => $foundMatch['airline_name'] ?? $route->airline?->name,
                'stops' => $foundMatch['stops'] ?? 0,
                'duration_minutes' => $foundMatch['duration_minutes'] ?? null,
                'departure_date' => $foundMatch['departure_date'],
                'return_date' => $foundMatch['return_date'],
                'currency' => $baseCurrency,
                'cabin_class' => $route->travel_class,
            ];

            $route->forceFill([
                'dynamic_price' => $finalPrice,
                'dynamic_price_currency' => $baseCurrency,
                'dynamic_price_meta' => $meta,
                'price_checked_at' => now(),
                'price_updated_at' => now(),
                'price_status' => 'fresh',
                'price_failure_count' => 0,
                'price_last_error' => null,
                'next_price_refresh_at' => now()->addHours($refreshHours),
            ])->saveQuietly();

            // Invalidate cached public lists
            Cache::forever('popular_routes:version', time());

            Log::info('Popular route dynamic price successfully refreshed via AT', [
                'popular_route_id' => $route->id,
                'price' => $finalPrice,
                'currency' => $baseCurrency,
                'meta' => $meta,
            ]);

            return [
                'status' => 'fresh',
                'amount' => $finalPrice,
                'currency' => $baseCurrency,
                'meta' => $meta,
            ];

        } catch (Throwable $e) {
            $failures = ((int) $route->price_failure_count) + 1;
            $backoffMinutes = min($refreshHours * 60, (int) (30 * (2 ** min(5, $failures))));

            $route->forceFill([
                'price_checked_at' => now(),
                'price_status' => 'failed',
                'price_failure_count' => $failures,
                'price_last_error' => substr($e->getMessage(), 0, 500),
                'next_price_refresh_at' => now()->addMinutes($backoffMinutes),
            ])->saveQuietly();

            Log::error('Popular route price refresh error via AT', [
                'popular_route_id' => $route->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function buildFlightSearchParams(PopularRoute $route, ?int $departurePlusDays = null): array
    {
        $days = $departurePlusDays ?? max(1, (int) ($route->departure_plus_days ?? 1));
        $departureDate = Carbon::today()->addDays($days);
        $isRoundTrip = $route->journey_type === 'round';

        return [
            'airline' => 'AT',
            'cabin_class' => $this->mapCabinClass($route->travel_class),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'flight_type' => $isRoundTrip ? 'return' : 'one-way',
            'currency_code' => config('popular_routes.base_currency', 'AED'),
            'flexible_plus_minus_3' => false,
            'origin' => $route->from_airport,
            'destination' => $route->to_airport,
            'departure_date' => $departureDate->toDateString(),
            'return_date' => $isRoundTrip && $route->stay_duration_days !== null
                ? $departureDate->copy()->addDays((int) $route->stay_duration_days)->toDateString()
                : null,
        ];
    }

    private function mapCabinClass(?string $travelClass): string
    {
        return match (strtolower((string) $travelClass)) {
            'business' => 'C',
            'first' => 'F',
            'premium_economy', 'premium-economy' => 'S',
            default => 'Y',
        };
    }

    private function findCheapestMatchingOption(array $itineraries, ?string $airlineIata, bool $isRoundTrip): ?array
    {
        $options = [];
        $airlineIata = strtoupper((string) $airlineIata);

        foreach ($itineraries as $itinerary) {
            if ($airlineIata !== '' && !$this->itineraryMatchesAirline($itinerary, $airlineIata)) {
                continue;
            }

            $price = $this->calculateItineraryPrice($itinerary, $isRoundTrip);
            if ($price !== null && $price > 0) {
                $flights = data_get($itinerary, 'leg.flights', []);
                $firstFlight = $flights[0] ?? [];
                $segments = $firstFlight['segments'] ?? [];
                $firstSegment = $segments[0] ?? [];

                $carrierIata = data_get($firstSegment, 'marketing_carrier.code') ?: $airlineIata;
                $carrierName = data_get($firstSegment, 'marketing_carrier.name');
                $stops = max(0, count($segments) - 1);
                $duration = (int) (data_get($firstSegment, 'flight_time') ?: 0);

                $options[] = [
                    'price' => $price,
                    'airline_iata' => $carrierIata,
                    'airline_name' => $carrierName,
                    'stops' => $stops,
                    'duration_minutes' => $duration,
                ];
            }
        }

        if (empty($options)) {
            return null;
        }

        usort($options, fn ($a, $b) => $a['price'] <=> $b['price']);
        return $options[0];
    }

    private function itineraryMatchesAirline(array $itinerary, string $airlineIata): bool
    {
        foreach (data_get($itinerary, 'leg.flights', []) as $flight) {
            foreach (($flight['segments'] ?? []) as $segment) {
                $marketingCarrier = strtoupper((string) data_get($segment, 'marketing_carrier.code', ''));
                if ($marketingCarrier === $airlineIata) {
                    return true;
                }

                $operatingCarrier = strtoupper((string) data_get($segment, 'operating_carrier.code', ''));
                if ($operatingCarrier === $airlineIata) {
                    return true;
                }
            }
        }

        return false;
    }

    private function calculateItineraryPrice(array $itinerary, bool $isRoundTrip): ?float
    {
        // 1. For return flights with commercial fare combinations
        $fareCombinations = data_get($itinerary, 'leg.fare_combinations', []);
        if ($isRoundTrip && is_array($fareCombinations) && !empty($fareCombinations)) {
            $combinationPrices = [];
            foreach ($fareCombinations as $combination) {
                $amt = data_get($combination, 'combined_base_money.amount')
                    ?? data_get($combination, 'selling_base_money.amount')
                    ?? data_get($combination, 'provider_gross_base_money.amount')
                    ?? data_get($combination, 'combined_amount');

                if (is_numeric($amt) && (float) $amt > 0) {
                    $combinationPrices[] = (float) $amt;
                }
            }

            if (!empty($combinationPrices)) {
                return min($combinationPrices);
            }
        }

        // 2. Summing leg flights
        $total = 0.0;
        $hasPrice = false;

        foreach (data_get($itinerary, 'leg.flights', []) as $flight) {
            $fares = $flight['fares'] ?? [];
            if (!is_array($fares) || empty($fares)) {
                continue;
            }

            $farePrices = [];
            foreach ($fares as $fare) {
                $amt = data_get($fare, 'selling_base_money.amount')
                    ?? data_get($fare, 'base_money.amount')
                    ?? data_get($fare, 'provider_gross_base_money.amount')
                    ?? data_get($fare, 'total_price');

                if (is_numeric($amt) && (float) $amt > 0) {
                    $farePrices[] = (float) $amt;
                }
            }

            if (empty($farePrices)) {
                continue;
            }

            $total += min($farePrices);
            $hasPrice = true;
        }

        return $hasPrice ? $total : null;
    }
}
