<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'decimal_places' => 'integer',
            'is_enabled' => 'boolean',
            'is_base' => 'boolean',
        ];
    }

    public function rateHistories(): HasMany
    {
        return $this->hasMany(CurrencyRateHistory::class);
    }
}
