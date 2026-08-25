<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderBookingEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_amount' => 'decimal:8',
            'response_data' => 'array',
        ];
    }

    /**
     * Get the booking whose provider lifecycle event was recorded.
     */
    public function booking()
    {
        return $this->belongsTo(FlightBookings::class, 'booking_id');
    }
}
