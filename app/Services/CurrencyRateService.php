<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyRateHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CurrencyRateService
{
    // Public rate-history functions

    /**
     * Check whether the submitted rate differs from the stored AED rate.
     */
    public function hasChanged(Currency $currency, mixed $newRate): bool
    {
        return $this->normalizeRate($currency->exchange_rate) !== $this->normalizeRate($newRate);
    }

    /**
     * Save the audit entry created when an admin adds a non-AED currency rate.
     */
    public function recordInitialRate(Currency $currency, User $admin, string $reason): CurrencyRateHistory
    {
        return CurrencyRateHistory::create([
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'old_rate' => null,
            'new_rate' => $currency->exchange_rate,
            'reason' => $reason,
            'changed_by' => $admin->id,
        ]);
    }

    /**
     * Update a non-base rate and record its old and new values in the audit trail.
     */
    public function changeRate(Currency $currency, mixed $newRate, User $admin, string $reason): void
    {
        if ($currency->is_base) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'The AED base rate cannot be changed.',
            ]);
        }

        if (!$this->hasChanged($currency, $newRate)) {
            return;
        }

        $oldRate = $currency->exchange_rate;
        $currency->update(['exchange_rate' => $newRate]);

        CurrencyRateHistory::create([
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'old_rate' => $oldRate,
            'new_rate' => $currency->exchange_rate,
            'reason' => $reason,
            'changed_by' => $admin->id,
        ]);
    }

    // Helper functions

    /**
     * Convert equivalent decimal rate formats to the same fixed-scale value.
     */
    private function normalizeRate(mixed $rate): string
    {
        $value = trim((string) $rate);

        if ($value === '') {
            return '0';
        }

        return bcadd($value, '0', 8);
    }
}
