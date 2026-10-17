<?php

namespace App\Services;

use App\Models\PriceQuote;
use App\Models\PriceQuoteAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PriceQuoteService
{
    private const QUOTE_TTL_MINUTES = 15;

    private const QUOTE_RETENTION_DAYS = 30;

    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
        private readonly CommercialPricingService $commercialPricingService,
        private readonly FareBreakdownPricingService $fareBreakdownPricingService,
    )
    {
    }

    // Public quote functions

    /**
     * Create a 15-minute quote from the selected fares in a server-cached search result.
     */
    public function create(User $user, array $flight, array $fareReferences, string $displayCurrency): PriceQuote
    {
        [$providerMoney, $providerGrossMoney, $provider] = $this->selectedProviderAmounts(
            $flight,
            $fareReferences,
        );

        return $this->createCommercialQuote(
            $user,
            $flight,
            $fareReferences,
            $displayCurrency,
            [
                'source' => 'server_cached_search',
                'currency' => $providerMoney['currency'],
                'net_amount' => $providerMoney['amount'],
                'gross_amount' => $providerGrossMoney['amount'],
            ],
            $provider,
            $providerMoney['amount'],
            $providerGrossMoney['amount'],
            $providerMoney['currency'],
        );
    }

    /** Create an AT quote only after fresh provider pricing and AED commercial pricing are locked. */
    public function createAt(
        User $user,
        array $flight,
        array $fareReferences,
        string $displayCurrency,
        array $providerPricing,
        ?array $fareBreakdownData = null,
    ): PriceQuote {
        $providerCurrency = strtoupper((string) $providerPricing['currency']);
        $providerAmount = (string) $providerPricing['net_amount'];
        $providerGrossAmount = (string) $providerPricing['gross_amount'];

        return $this->createCommercialQuote(
            $user,
            $flight,
            $fareReferences,
            $displayCurrency,
            $providerPricing,
            strtoupper((string) (data_get($flight, 'provider.identifier') ?? 'AT')),
            $providerAmount,
            $providerGrossAmount,
            $providerCurrency,
            $fareBreakdownData,
        );
    }

    /** Create a Hakimi quote from its authoritative Reprice total. */
    public function createHakimi(
        User $user,
        array $flight,
        array $fareReferences,
        string $displayCurrency,
        array $providerPricing,
    ): PriceQuote {
        $providerCurrency = strtoupper((string) config('hakimi.provider_currency', 'INR'));
        $reportedCurrency = strtoupper((string) ($providerPricing['currency'] ?? $providerCurrency));

        if ($reportedCurrency !== $providerCurrency) {
            throw new \UnexpectedValueException('Hakimi Reprice currency does not match its INR provider contract.');
        }
        $providerAmount = (string) $providerPricing['totalFare'];

        return $this->createCommercialQuote(
            $user,
            $flight,
            $fareReferences,
            $displayCurrency,
            $providerPricing,
            'HAKIMI',
            $providerAmount,
            $providerAmount,
            $providerCurrency,
        );
    }

    /** Persist a fresh provider amount, its AED selling total, and immutable commercial adjustments. */
    private function createCommercialQuote(
        User $user,
        array $flight,
        array $fareReferences,
        string $displayCurrency,
        array $providerPricing,
        string $provider,
        string $providerAmount,
        string $providerGrossAmount,
        string $providerCurrency,
        ?array $fareBreakdownData = null,
    ): PriceQuote {
        $commercial = $this->commercialPricingService->quoteTotals(
            $flight,
            $fareReferences,
            $providerAmount,
            $providerGrossAmount,
            $providerCurrency,
            $displayCurrency,
        );
        $fareBreakdownData = $this->fareBreakdownPricingService->withCommercialServiceCharges(
            $fareBreakdownData,
            $commercial,
            $displayCurrency,
        );

        return DB::transaction(function () use ($user, $flight, $fareReferences, $displayCurrency, $providerPricing, $fareBreakdownData, $provider, $providerCurrency, $providerAmount, $providerGrossAmount, $commercial) {
            $quote = PriceQuote::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_amount' => $providerAmount,
                'provider_currency' => $providerCurrency,
                'provider_rate_to_aed' => $this->currencyConversionService->rateToBase($providerCurrency),
                'provider_aed_amount' => $commercial['provider_cost_base_money']['amount'],
                'provider_gross_amount' => $providerGrossAmount,
                'provider_gross_aed_amount' => $commercial['provider_gross_base_money']['amount'],
                'display_amount' => $commercial['selling_display_money']['amount'],
                'display_currency' => $commercial['selling_display_money']['currency'],
                'display_rate_to_aed' => $this->currencyConversionService->rateToBase($commercial['selling_display_money']['currency']),
                'aed_amount' => $commercial['selling_base_money']['amount'],
                'flight_data' => $flight,
                'selected_fare_references' => array_values($fareReferences),
                'provider_pricing_data' => $providerPricing,
                'fare_breakdown_data' => $fareBreakdownData,
                'expires_at' => now()->addMinutes(self::QUOTE_TTL_MINUTES),
                'retain_until' => now()->addDays(self::QUOTE_RETENTION_DAYS),
            ]);

            foreach ($commercial['adjustments'] as $adjustment) {
                $this->persistAdjustment($quote, $adjustment);
            }

            return $quote->load('adjustments');
        });
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
        $providerGrossAmount = (string) (
            $providerPricing['gross_amount']
            ?? $quote->provider_gross_amount
            ?? $providerAmount
        );
        $commercial = $this->commercialPricingService->quoteTotals(
            $quote->flight_data,
            $quote->selected_fare_references,
            $providerAmount,
            $providerGrossAmount,
            $providerCurrency,
            $quote->display_currency,
        );
        $fareBreakdownData = $this->fareBreakdownPricingService->withCommercialServiceCharges(
            $quote->fare_breakdown_data,
            $commercial,
            $quote->display_currency,
        );

        return DB::transaction(function () use ($quote, $providerPricing, $providerCurrency, $providerAmount, $providerGrossAmount, $commercial, $fareBreakdownData) {
            $lockedQuote = PriceQuote::query()->lockForUpdate()->findOrFail($quote->id);
            $lockedQuote->update([
                'provider_amount' => $providerAmount,
                'provider_currency' => $providerCurrency,
                'provider_rate_to_aed' => $this->currencyConversionService->rateToBase($providerCurrency),
                'provider_aed_amount' => $commercial['provider_cost_base_money']['amount'],
                'provider_gross_amount' => $providerGrossAmount,
                'provider_gross_aed_amount' => $commercial['provider_gross_base_money']['amount'],
                'display_amount' => $commercial['selling_display_money']['amount'],
                'display_rate_to_aed' => $this->currencyConversionService->rateToBase($lockedQuote->display_currency),
                'aed_amount' => $commercial['selling_base_money']['amount'],
                'provider_pricing_data' => $providerPricing,
                'fare_breakdown_data' => $fareBreakdownData,
            ]);

            $lockedQuote->adjustments()->delete();
            foreach ($commercial['adjustments'] as $adjustment) {
                $this->persistAdjustment($lockedQuote, $adjustment);
            }

            return $lockedQuote->refresh()->load('adjustments');
        });
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
     * @return array{0: array{amount: string, currency: string}, 1: array{amount: string, currency: string}, 2: string}
     */
    private function selectedProviderAmounts(array $flight, array $fareReferences): array
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
        $providerGrossAmount = '0';

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

            $grossMoney = $fare['provider_gross_money'] ?? $money;
            $grossCurrency = strtoupper((string) ($grossMoney['currency'] ?? ''));
            if (!isset($grossMoney['amount']) || $grossCurrency !== $providerCurrency) {
                throw ValidationException::withMessages([
                    'fare_references' => 'The selected fare gross amount is missing or uses a different currency.',
                ]);
            }
            $providerGrossAmount = bcadd($providerGrossAmount, (string) $grossMoney['amount'], 8);
        }

        return [
            ['amount' => $providerAmount, 'currency' => $providerCurrency],
            ['amount' => $providerGrossAmount, 'currency' => $providerCurrency],
            data_get($flight, 'provider.identifier')
                ?? data_get($flight, 'provider.name')
                ?? 'unknown',
        ];
    }

    /** Store one immutable, fully ordered AED calculation row for quote audit. */
    private function persistAdjustment(PriceQuote $quote, array $adjustment): PriceQuoteAdjustment
    {
        return PriceQuoteAdjustment::create([
            'price_quote_id' => $quote->id,
            'type' => $adjustment['type'],
            'application_order' => $adjustment['application_order'],
            'rule_id' => $adjustment['rule_id'],
            'title' => $adjustment['title'],
            'direction' => $adjustment['direction'],
            'calculation_type' => $adjustment['calculation_type'],
            'application_scope' => $adjustment['application_scope'],
            'configured_value' => $adjustment['configured_value'],
            'passenger_count' => $adjustment['passenger_count'],
            'segment_count' => $adjustment['segment_count'],
            'aed_amount' => $adjustment['signed_aed_amount'],
            'amount_before_aed' => $adjustment['amount_before_aed'],
            'amount_after_aed' => $adjustment['amount_after_aed'],
            'fare_reference' => $adjustment['fare_reference'] ?? null,
            'flight_reference' => $adjustment['flight_reference'] ?? null,
            'formula_version' => $adjustment['formula_version'],
            'rule_snapshot' => $adjustment['snapshot'],
        ]);
    }
}
