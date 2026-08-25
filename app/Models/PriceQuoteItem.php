<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceQuoteItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_references' => 'array',
            'provider_item_data' => 'array',
            'provider_amount' => 'decimal:8',
            'provider_rate_to_aed' => 'decimal:8',
            'aed_amount' => 'decimal:8',
            'display_amount' => 'decimal:8',
            'display_rate_to_aed' => 'decimal:8',
            'selected_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /** Return only the selections currently included in the quote total. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /** Return the quote that owns this selected ancillary line. */
    public function quote()
    {
        return $this->belongsTo(PriceQuote::class, 'price_quote_id');
    }
}
