<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingPriceSnapshot extends Model
{
    protected $fillable = [
        'hotel_booking_id',
        'hotel_price_quote_id',
        'quote_uuid',
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
    ];

    protected $casts = [
        'provider_amount' => 'decimal:8',
        'provider_rate_to_aed' => 'decimal:8',
        'provider_aed_amount' => 'decimal:8',
        'selling_amount' => 'decimal:8',
        'selling_rate_to_aed' => 'decimal:8',
        'aed_amount' => 'decimal:8',
        'adjustments_snapshot' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function priceQuote(): BelongsTo
    {
        return $this->belongsTo(HotelPriceQuote::class, 'hotel_price_quote_id');
    }
}
