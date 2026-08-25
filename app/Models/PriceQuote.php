<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceQuote extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_amount' => 'decimal:8',
            'provider_rate_to_aed' => 'decimal:8',
            'display_amount' => 'decimal:8',
            'display_rate_to_aed' => 'decimal:8',
            'aed_amount' => 'decimal:8',
            'flight_data' => 'array',
            'selected_fare_references' => 'array',
            'provider_pricing_data' => 'array',
            'expires_at' => 'datetime',
            'retain_until' => 'datetime',
        ];
    }

    /**
     * Return only quotes that can still be used for checkout.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Check whether the quote's 15-minute checkout window has passed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Get the user that created this quote.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Return the ancillary selections retained with this quote. */
    public function items()
    {
        return $this->hasMany(PriceQuoteItem::class);
    }
}
