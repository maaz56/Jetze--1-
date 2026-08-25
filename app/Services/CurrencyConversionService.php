<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Validation\ValidationException;

class CurrencyConversionService
{
    private const BASE_CURRENCY = 'AED';

    /** @var array<string, Currency|array<string, int|string>> */
    private array $currencies = [];

    // Public conversion functions

    /**
     * Create a standard money object in the supplied currency.
     */
    public function makeMoney(mixed $amount, string $currencyCode): array
    {
        $currency = $this->getCurrency($currencyCode);
        $decimalPlaces = $this->decimalPlaces($currency);

        return [
            'amount' => $this->round($this->normalizeAmount($amount), $decimalPlaces),
            'currency' => $this->currencyCode($currency),
            'decimal_places' => $decimalPlaces,
        ];
    }

    /**
     * Convert an amount using rates defined as "1 currency = X AED".
     */
    public function convertMoney(mixed $amount, string $sourceCurrency, string $targetCurrency): array
    {
        $source = $this->getCurrency($sourceCurrency);
        $target = $this->getCurrency($targetCurrency);
        $normalizedAmount = $this->normalizeAmount($amount);

        if ($this->currencyCode($source) === $this->currencyCode($target)) {
            return $this->makeMoney($normalizedAmount, $this->currencyCode($target));
        }

        $targetDecimalPlaces = $this->decimalPlaces($target);
        $calculationScale = max(12, $targetDecimalPlaces + 8);
        $amountInAed = bcmul(
            $normalizedAmount,
            $this->rateToAed($source),
            $calculationScale,
        );
        $convertedAmount = bcdiv(
            $amountInAed,
            $this->rateToAed($target),
            $calculationScale,
        );

        return [
            'amount' => $this->round($convertedAmount, $targetDecimalPlaces),
            'currency' => $this->currencyCode($target),
            'decimal_places' => $targetDecimalPlaces,
        ];
    }

    /**
     * Convert an amount to the fixed AED accounting currency.
     */
    public function toBaseMoney(mixed $amount, string $sourceCurrency): array
    {
        return $this->convertMoney($amount, $sourceCurrency, self::BASE_CURRENCY);
    }

    /**
     * Get the AED rate currently configured for one unit of a currency.
     */
    public function rateToBase(string $currencyCode): string
    {
        return $this->rateToAed($this->getCurrency($currencyCode));
    }

    /**
     * Get the configured number of fraction digits for a currency.
     */
    public function decimalPlacesFor(string $currencyCode): int
    {
        return $this->decimalPlaces($this->getCurrency($currencyCode));
    }

    // Currency lookup helpers

    /**
     * Load a currency once per service instance. AED always has a rate of one.
     */
    private function getCurrency(string $currencyCode): Currency|array
    {
        $currencyCode = strtoupper(trim($currencyCode));

        if (!preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw ValidationException::withMessages([
                'currency' => 'A valid three-letter currency code is required.',
            ]);
        }

        if (isset($this->currencies[$currencyCode])) {
            return $this->currencies[$currencyCode];
        }

        if ($currencyCode === self::BASE_CURRENCY) {
            return $this->currencies[$currencyCode] = [
                'code' => self::BASE_CURRENCY,
                'exchange_rate' => '1.00000000',
                'decimal_places' => 2,
            ];
        }

        $currency = Currency::query()->where('code', $currencyCode)->first();

        if (!$currency || $currency->exchange_rate === null) {
            throw ValidationException::withMessages([
                'currency' => "An AED conversion rate is missing for {$currencyCode}.",
            ]);
        }

        return $this->currencies[$currencyCode] = $currency;
    }

    /**
     * Read the normalized code from a configured currency.
     */
    private function currencyCode(Currency|array $currency): string
    {
        return $currency instanceof Currency ? $currency->code : $currency['code'];
    }

    /**
     * Read the number of fraction digits used when displaying the currency.
     */
    private function decimalPlaces(Currency|array $currency): int
    {
        return $currency instanceof Currency
            ? $currency->decimal_places
            : $currency['decimal_places'];
    }

    /**
     * Return the AED value of one unit and ensure the configured rate is usable.
     */
    private function rateToAed(Currency|array $currency): string
    {
        $rate = $currency instanceof Currency ? $currency->exchange_rate : $currency['exchange_rate'];

        if (bccomp((string) $rate, '0', 8) <= 0) {
            throw ValidationException::withMessages([
                'currency' => "The AED conversion rate for {$this->currencyCode($currency)} must be greater than zero.",
            ]);
        }

        return (string) $rate;
    }

    // Amount helpers

    /**
     * Validate a non-negative decimal amount before BCMath calculations.
     */
    private function normalizeAmount(mixed $amount): string
    {
        $amount = str_replace(',', '', trim((string) $amount));

        if (!preg_match('/^\d+(?:\.\d+)?$/', $amount)) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must be a non-negative decimal number.',
            ]);
        }

        return $amount;
    }

    /**
     * Round a positive decimal amount with BCMath, without floating-point math.
     */
    private function round(string $amount, int $decimalPlaces): string
    {
        $factor = '1' . str_repeat('0', $decimalPlaces);
        $scale = max(12, $decimalPlaces + 8);
        $scaledAmount = bcmul($amount, $factor, $scale);

        return bcdiv(bcadd($scaledAmount, '0.5', $scale), $factor, $decimalPlaces);
    }
}
