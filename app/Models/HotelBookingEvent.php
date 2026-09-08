<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingEvent extends Model
{
    protected $fillable = [
        'hotel_prebook_id',
        'hotel_booking_id',
        'provider',
        'stage',
        'provider_status_code',
        'provider_reference',
        'request_data',
        'response_data',
        'occurred_at',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function prebook(): BelongsTo
    {
        return $this->belongsTo(HotelPrebook::class, 'hotel_prebook_id');
    }
}
