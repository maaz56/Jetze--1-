<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelBooking extends Model
{
    protected $fillable = [
        'uuid',
        'hotel_prebook_id',
        'user_id',
        'status',
        'provider_status_code',
        'client_reference_id',
        'booking_reference_id',
        'confirmation_number',
        'hotel_confirmation_number',
        'currency',
        'total_fare',
        'email',
        'phone_number',
        'customer_details',
        'tbo_request',
        'tbo_response',
        'tbo_booking_detail_response',
        'tbo_cancel_response',
        'booking_details_checked_at',
        'cancelled_at',
    ];

    protected $casts = [
        'total_fare' => 'decimal:2',
        'customer_details' => 'array',
        'tbo_request' => 'array',
        'tbo_response' => 'array',
        'tbo_booking_detail_response' => 'array',
        'tbo_cancel_response' => 'array',
        'booking_details_checked_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function prebook(): BelongsTo
    {
        return $this->belongsTo(HotelPrebook::class, 'hotel_prebook_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(HotelBookingGuest::class);
    }

    public function priceSnapshot(): HasOne
    {
        return $this->hasOne(HotelBookingPriceSnapshot::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HotelBookingEvent::class);
    }
}
