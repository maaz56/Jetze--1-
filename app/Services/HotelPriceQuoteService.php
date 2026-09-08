<?php

namespace App\Services;

use App\Models\HotelPrebook;
use App\Models\HotelPriceQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Lock the TBO PreBook fare and the conversion rates used to show it at checkout. */
class HotelPriceQuoteService
{
    private const QUOTE_RETENTION_DAYS = 30;

    public function __construct(private readonly CurrencyConversionService $currencyConversionService) {}

    public function create(
        HotelPrebook $prebook,
        mixed $providerAmount,
        string $providerCurrency,
        string $sellingCurrency,
    ): HotelPriceQuote {
        $providerMoney = $this->currencyConversionService->makeMoney($providerAmount, $providerCurrency);
        $providerAedMoney = $this->currencyConversionService->toBaseMoney(
            $providerMoney['amount'],
            $providerMoney['currency'],
        );
        $sellingMoney = $this->currencyConversionService->convertMoney(
            $providerMoney['amount'],
            $providerMoney['currency'],
            $sellingCurrency,
        );

        return DB::transaction(function () use ($prebook, $providerMoney, $providerAedMoney, $sellingMoney) {
            $quote = HotelPriceQuote::firstOrNew([
                'hotel_prebook_id' => $prebook->id,
            ]);

            $quote->fill([
                'uuid' => $quote->uuid ?: (string) Str::uuid(),
                'user_id' => $prebook->user_id,
                'provider' => 'tbo',
                'provider_amount' => $providerMoney['amount'],
                'provider_currency' => $providerMoney['currency'],
                'provider_rate_to_aed' => $this->currencyConversionService->rateToBase($providerMoney['currency']),
                'provider_aed_amount' => $providerAedMoney['amount'],
                // There is no hotel markup rule yet. Selling is therefore the locked converted provider fare.
                'selling_amount' => $sellingMoney['amount'],
                'selling_currency' => $sellingMoney['currency'],
                'selling_rate_to_aed' => $this->currencyConversionService->rateToBase($sellingMoney['currency']),
                'aed_amount' => $providerAedMoney['amount'],
                'adjustments_snapshot' => [],
                'expires_at' => $prebook->expires_at,
                'retain_until' => now()->addDays(self::QUOTE_RETENTION_DAYS),
            ]);

            $quote->save();

            return $quote;
        });
    }
}
