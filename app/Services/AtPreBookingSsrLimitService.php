<?php

namespace App\Services;

use App\Models\PriceQuote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AtPreBookingSsrLimitService
{
    private const WEB_SETTING_KEYS = [
        'domestic' => [
            'ssr' => 'ShowSSRDom',
            'baggage' => 'ShowBaggageDom',
            'meal' => 'ShowMealsDom',
            'seat' => 'ShowSeatLayoutDom',
        ],
        'international' => [
            'ssr' => 'ShowSSRInt',
            'baggage' => 'ShowBaggageInt',
            'meal' => 'ShowMealsInt',
            'seat' => 'ShowSeatLayoutInt',
        ],
    ];

    public function __construct(private readonly AtApiService $atApiService)
    {
    }

    public function capabilityForQuote(PriceQuote $quote): array
    {
        $scope = $this->scopeForQuote($quote);
        $providers = $this->selectedProviderCodes($quote);
        $settings = $this->settingsForQuote($quote);

        return $this->buildCapability($scope, $providers, $settings);
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

    private function buildCapability(string $scope, array $providers, array $settings): array
    {
        return [
            'scope' => $scope,
            'providers' => $providers,
            'source' => 'GetWebSettings',
            'settings_available' => $settings !== [],
            'services' => [
                'baggage' => $this->serviceCapability($scope, $providers, 'baggage', true, $settings),
                'meal' => $this->serviceCapability($scope, $providers, 'meal', true, $settings),
                'seat' => $this->serviceCapability($scope, $providers, 'seat', false, $settings),
            ],
        ];
    }

    private function serviceCapability(string $scope, array $providers, string $service, bool $requiresSsr, array $settings): array
    {
        $serviceCodes = $this->codes($settings, $scope, $service);
        $ssrCodes = $this->codes($settings, $scope, 'ssr');
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

        return in_array($provider, $providers, true);
    }

    private function scopeForQuote(PriceQuote $quote): string
    {
        return strtolower((string) data_get($quote->flight_data, 'leg.trip_nature')) === 'domestic'
            ? 'domestic'
            : 'international';
    }

    private function settingsForQuote(PriceQuote $quote): array
    {
        $settings = $this->settingsMap(data_get($quote->flight_data, 'provider.web_settings'));

        if ($settings !== []) {
            return $settings;
        }

        $tui = data_get($quote->flight_data, 'provider.TUI')
            ?? data_get($quote->provider_pricing_data, 'tui');
        $cacheKey = AtApiService::webSettingsCacheKey($tui);

        if ($cacheKey) {
            $settings = $this->settingsMap(Cache::get($cacheKey));

            if ($settings !== []) {
                return $settings;
            }
        }

        try {
            return $this->settingsMap($this->atApiService->getWebSettings($tui));
        } catch (\Throwable $exception) {
            Log::warning('Unable to load AT GetWebSettings for pre-booking SSR limits.', [
                'message' => $exception->getMessage(),
                'tui' => is_scalar($tui) ? $tui : null,
            ]);

            return [];
        }
    }

    private function settingsMap(mixed $webSettings): array
    {
        if (!is_array($webSettings)) {
            return [];
        }

        if (isset($webSettings['Settings']) && is_array($webSettings['Settings'])) {
            $webSettings = $webSettings['Settings'];
        }

        $settings = [];

        foreach ($webSettings as $key => $setting) {
            if (is_array($setting) && array_key_exists('Key', $setting)) {
                $settings[(string) $setting['Key']] = $setting['Value'] ?? '';
                continue;
            }

            if (is_string($key)) {
                $settings[$key] = $setting;
            }
        }

        return $settings;
    }

    private function codes(array $settings, string $scope, string $service): array
    {
        $key = self::WEB_SETTING_KEYS[$scope][$service] ?? null;
        $value = $key ? ($settings[$key] ?? '') : '';

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
