<?php

namespace App\Services;

use App\Models\PriceQuote;

class AtPreBookingSsrLimitService
{
    public function capabilityForQuote(PriceQuote $quote): array
    {
        $scope = $this->scopeForQuote($quote);
        $providers = $this->selectedProviderCodes($quote);

        return $this->buildCapability($scope, $providers);
    }

    public function requestFlags(array $capability): array
    {
        return [
            'includeSSR' => (bool) ($capability['services']['baggage']['available'] ?? false)
                || (bool) ($capability['services']['meal']['available'] ?? false),
            'includeSeatLayout' => (bool) ($capability['services']['seat']['available'] ?? false),
        ];
    }

    public function emptyAncillaries(array $capability, string $displayCurrency, string $providerCurrency): array
    {
        return [
            'data' => [
                'provider' => 'AT',
                'provider_currency' => $providerCurrency,
                'display_currency' => $displayCurrency,
                'limitations' => $capability,
                'ssrData' => [
                    'TUI' => null,
                    'CurrencyCode' => $providerCurrency,
                    'PaidSSR' => false,
                    'Trips' => [],
                ],
                'seatLayout' => [
                    'TUI' => null,
                    'CurrencyCode' => $providerCurrency,
                    'Trips' => [],
                ],
            ],
        ];
    }

    public function apply(array $ancillaries, array $capability): array
    {
        $ancillaries['data']['limitations'] = $capability;
        $ancillaries['data']['ssrData'] = $this->filterSsrData(
            $ancillaries['data']['ssrData'] ?? [],
            $capability,
        );
        $ancillaries['data']['seatLayout'] = $this->filterSeatLayout(
            $ancillaries['data']['seatLayout'] ?? [],
            $capability,
        );

        return $ancillaries;
    }

    private function filterSsrData(array $ssrData, array $capability): array
    {
        foreach ($ssrData['Trips'] ?? [] as $tripIndex => $trip) {
            foreach ($trip['Journey'] ?? [] as $journeyIndex => $journey) {
                foreach ($journey['Segments'] ?? [] as $segmentIndex => $segment) {
                    $provider = $this->normalizeCode($segment['VAC'] ?? $journey['Provider'] ?? null);
                    $ssrData['Trips'][$tripIndex]['Journey'][$journeyIndex]['Segments'][$segmentIndex]['SSR'] = array_values(
                        array_filter($segment['SSR'] ?? [], function (array $ssr) use ($provider, $capability): bool {
                            return match ((string) ($ssr['Type'] ?? '')) {
                                '1' => $this->isServiceAllowedForProvider($capability, 'meal', $provider),
                                '2' => $this->isServiceAllowedForProvider($capability, 'baggage', $provider),
                                default => false,
                            };
                        }),
                    );
                }
            }
        }

        return $ssrData;
    }

    private function filterSeatLayout(array $seatLayout, array $capability): array
    {
        foreach ($seatLayout['Trips'] ?? [] as $tripIndex => $trip) {
            foreach ($trip['Journey'] ?? [] as $journeyIndex => $journey) {
                foreach ($journey['Segments'] ?? [] as $segmentIndex => $segment) {
                    $provider = $this->normalizeCode(
                        $segment['VAC'] ?? $segment['AirlineCode'] ?? $journey['Provider'] ?? null,
                    );

                    if (!$this->isServiceAllowedForProvider($capability, 'seat', $provider)) {
                        $seatLayout['Trips'][$tripIndex]['Journey'][$journeyIndex]['Segments'][$segmentIndex]['Seats'] = [];
                    }
                }
            }
        }

        return $seatLayout;
    }

    private function buildCapability(string $scope, array $providers): array
    {
        return [
            'scope' => $scope,
            'providers' => $providers,
            'services' => [
                'baggage' => $this->serviceCapability($scope, $providers, 'baggage', true),
                'meal' => $this->serviceCapability($scope, $providers, 'meal', true),
                'seat' => $this->serviceCapability($scope, $providers, 'seat', false),
            ],
        ];
    }

    private function serviceCapability(string $scope, array $providers, string $service, bool $requiresSsr): array
    {
        $serviceCodes = $this->codes($scope, $service);
        $ssrCodes = $this->codes($scope, 'ssr');
        $allowedProviders = array_values(array_filter($providers, function (string $provider) use ($serviceCodes, $ssrCodes, $requiresSsr): bool {
            if (!in_array($provider, $serviceCodes, true)) {
                return false;
            }

            return !$requiresSsr || in_array($provider, $ssrCodes, true);
        }));

        return [
            'available' => count($allowedProviders) > 0,
            'providers' => $allowedProviders,
            'message' => count($allowedProviders) > 0
                ? null
                : $this->unavailableMessage($service),
        ];
    }

    private function selectedProviderCodes(PriceQuote $quote): array
    {
        $selectedFareReferences = array_flip($quote->selected_fare_references ?? []);
        $providers = [];

        foreach (data_get($quote->flight_data, 'leg.flights', []) as $flight) {
            $hasSelectedFare = collect($flight['fares'] ?? [])
                ->contains(fn (array $fare) => isset($selectedFareReferences[$fare['ref_id'] ?? null]));

            if (!$hasSelectedFare) {
                continue;
            }

            foreach ($flight['segments'] ?? [] as $segment) {
                $providers[] = $this->normalizeCode(data_get($segment, 'operating_carrier.iata'));
            }
        }

        return array_values(array_unique(array_filter($providers)));
    }

    private function isServiceAllowedForProvider(array $capability, string $service, ?string $provider): bool
    {
        if ($provider === null) {
            return false;
        }

        $providers = $capability['services'][$service]['providers'] ?? [];
        $scope = $capability['scope'] ?? 'international';

        return in_array($provider, $providers, true)
            || in_array($provider, $this->codes($scope, $service), true);
    }

    private function scopeForQuote(PriceQuote $quote): string
    {
        return strtolower((string) data_get($quote->flight_data, 'leg.trip_nature')) === 'domestic'
            ? 'domestic'
            : 'international';
    }

    private function codes(string $scope, string $service): array
    {
        $value = config("at.pre_booking_ssr_limits.{$scope}.{$service}", '');

        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map([$this, 'normalizeCode'], $value))));
        }

        return array_values(array_unique(array_filter(array_map(
            [$this, 'normalizeCode'],
            explode(',', (string) $value),
        ))));
    }

    private function normalizeCode(mixed $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized === '' ? null : $normalized;
    }

    private function unavailableMessage(string $service): string
    {
        return match ($service) {
            'baggage' => 'Extra baggage is not available for this airline before booking.',
            'meal' => 'Meal selection is not available for this airline before booking.',
            'seat' => 'Seat selection is not available for this airline before booking.',
            default => 'This extra service is not available for this airline before booking.',
        };
    }
}
