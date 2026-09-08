<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelBookingGuest extends Model
{
    protected $fillable = [
        'hotel_booking_id',
        'room_index',
        'guest_index',
        'type',
        'title',
        'first_name',
        'last_name',
        'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }
}
