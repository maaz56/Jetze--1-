<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class HotelPrebook extends Model
{
    protected $fillable = [
        'uuid',
        'hotel_search_session_id',
        'user_id',
        'hotel_code',
        'booking_code',
        'payment_mode',
        'provider_status_code',
        'search_room',
        'tbo_response',
        'expires_at',
    ];

    protected $casts = [
        'search_room' => 'array',
        'tbo_response' => 'array',
        'expires_at' => 'datetime',
    ];

    public function searchSession(): BelongsTo
    {
        return $this->belongsTo(HotelSearchSession::class, 'hotel_search_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(HotelBooking::class);
    }

    public function priceQuote(): HasOne
    {
        return $this->hasOne(HotelPriceQuote::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HotelBookingEvent::class);
    }
}
