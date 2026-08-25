<?php

namespace App\Services;

use App\Models\PriceQuote;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PriceQuoteService
{
    private const QUOTE_TTL_MINUTES = 15;

    private const QUOTE_RETENTION_DAYS = 30;

    public function __construct(private readonly CurrencyConversionService $currencyConversionService)
    {
    }

    // Public quote functions

    /**
     * Create a 15-minute quote from the selected fares in a server-cached search result.
     */
    public function create(User $user, array $flight, array $fareReferences, string $displayCurrency): PriceQuote
    {
        [$providerMoney, $provider] = $this->selectedProviderMoney($flight, $fareReferences);
        $providerCurrency = $providerMoney['currency'];
        $baseMoney = $this->currencyConversionService->toBaseMoney(
            $providerMoney['amount'],
            $providerCurrency,
        );
        $displayMoney = $this->currencyConversionService->convertMoney(
            $providerMoney['amount'],
            $providerCurrency,
            $displayCurrency,
        );

        return PriceQuote::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_amount' => $providerMoney['amount'],
            'provider_currency' => $providerCurrency,
            'provider_rate_to_aed' => $this->currencyConversionService->rateToBase($providerCurrency),
            'display_amount' => $displayMoney['amount'],
            'display_currency' => $displayMoney['currency'],
            'display_rate_to_aed' => $this->currencyConversionService->rateToBase($displayMoney['currency']),
            'aed_amount' => $baseMoney['amount'],
            'flight_data' => $flight,
            'selected_fare_references' => array_values($fareReferences),
            'expires_at' => now()->addMinutes(self::QUOTE_TTL_MINUTES),
            'retain_until' => now()->addDays(self::QUOTE_RETENTION_DAYS),
        ]);
    }

    /**
     * Lock AT's freshly repriced TUI and NetAmount onto an active quote.
     */
    public function refreshProviderPricing(PriceQuote $quote, array $providerPricing): PriceQuote
    {
        $providerCurrency = strtoupper(
            (string) ($providerPricing['currency'] ?? $quote->provider_currency),
        );
        $providerAmount = (string) $providerPricing['net_amount'];
        $baseMoney = $this->currencyConversionService->toBaseMoney(
            $providerAmount,
            $providerCurrency,
        );
        $displayMoney = $this->currencyConversionService->convertMoney(
            $providerAmount,
            $providerCurrency,
            $quote->display_currency,
        );

        $quote->update([
            'provider_amount' => $providerAmount,
            'provider_currency' => $providerCurrency,
            'provider_rate_to_aed' => $this->currencyConversionService->rateToBase($providerCurrency),
            'display_amount' => $displayMoney['amount'],
            'display_rate_to_aed' => $this->currencyConversionService->rateToBase($displayMoney['currency']),
            'aed_amount' => $baseMoney['amount'],
            'provider_pricing_data' => $providerPricing,
        ]);

        return $quote->refresh();
    }

    /**
     * Load the current user's quote only while its checkout window is active.
     */
    public function findActive(string $quoteUuid, User $user): PriceQuote
    {
        $quote = PriceQuote::query()
            ->where('uuid', $quoteUuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($quote->isExpired()) {
            throw ValidationException::withMessages([
                'quote_id' => 'This quote has expired. Please refresh your flight search.',
            ]);
        }

        return $quote;
    }

    // Helper functions

    /**
     * Find each selected fare and add the provider booking amounts without floats.
     *
     * @return array{0: array{amount: string, currency: string}, 1: string}
     */
    private function selectedProviderMoney(array $flight, array $fareReferences): array
    {
        $fareReferences = array_values(array_unique(array_filter($fareReferences, 'is_string')));
        $legs = data_get($flight, 'leg.flights', []);

        if (empty($fareReferences) || !is_array($legs)) {
            throw ValidationException::withMessages([
                'fare_references' => 'At least one selected fare is required.',
            ]);
        }

        $matchedFares = [];

        foreach ($legs as $leg) {
            foreach ($leg['fares'] ?? [] as $fare) {
                if (in_array($fare['ref_id'] ?? null, $fareReferences, true)) {
                    $matchedFares[] = $fare;
                }
            }
        }

        if (count($matchedFares) !== count($fareReferences)) {
            throw ValidationException::withMessages([
                'fare_references' => 'One or more selected fares are no longer available in this search.',
            ]);
        }

        $providerCurrency = null;
        $providerAmount = '0';

        foreach ($matchedFares as $fare) {
            $money = $fare['provider_booking_money'] ?? null;

            if (!is_array($money) || !isset($money['amount'], $money['currency'])) {
                throw ValidationException::withMessages([
                    'fare_references' => 'The selected fare does not contain provider booking money.',
                ]);
            }

            $currency = strtoupper($money['currency']);

            if ($providerCurrency !== null && $providerCurrency !== $currency) {
                throw ValidationException::withMessages([
                    'fare_references' => 'All selected fares must use the same provider currency.',
                ]);
            }

            $providerCurrency = $currency;
            $providerAmount = bcadd($providerAmount, (string) $money['amount'], 8);
        }

        return [
            ['amount' => $providerAmount, 'currency' => $providerCurrency],
            data_get($flight, 'provider.identifier')
                ?? data_get($flight, 'provider.name')
                ?? 'unknown',
        ];
    }
}
