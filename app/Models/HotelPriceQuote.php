<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelPriceQuote extends Model
{
    protected $fillable = [
        'uuid',
        'hotel_prebook_id',
        'user_id',
        'provider',
        'provider_amount',
        'provider_currency',
        'provider_rate_to_aed',
        'provider_aed_amount',
        'selling_amount',
        'selling_currency',
        'selling_rate_to_aed',
        'aed_amount',
        'adjustments_snapshot',
        'expires_at',
        'retain_until',
    ];

    protected $casts = [
        'provider_amount' => 'decimal:8',
        'provider_rate_to_aed' => 'decimal:8',
        'provider_aed_amount' => 'decimal:8',
        'selling_amount' => 'decimal:8',
        'selling_rate_to_aed' => 'decimal:8',
        'aed_amount' => 'decimal:8',
        'adjustments_snapshot' => 'array',
        'expires_at' => 'datetime',
        'retain_until' => 'datetime',
    ];

    public function prebook(): BelongsTo
    {
        return $this->belongsTo(HotelPrebook::class, 'hotel_prebook_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookingPriceSnapshot(): HasOne
    {
        return $this->hasOne(HotelBookingPriceSnapshot::class);
    }
}
