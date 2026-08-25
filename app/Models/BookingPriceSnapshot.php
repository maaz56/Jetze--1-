<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingPriceSnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'provider_amount' => 'decimal:8',
            'provider_rate_to_aed' => 'decimal:8',
            'selling_amount' => 'decimal:8',
            'selling_rate_to_aed' => 'decimal:8',
            'aed_amount' => 'decimal:8',
        ];
    }

    /**
     * Get the booking whose financial values were locked in this snapshot.
     */
    public function booking()
    {
        return $this->belongsTo(FlightBookings::class, 'booking_id');
    }

    /**
     * Get the temporary quote from which this permanent snapshot was created.
     */
    public function priceQuote()
    {
        return $this->belongsTo(PriceQuote::class);
    }
}
