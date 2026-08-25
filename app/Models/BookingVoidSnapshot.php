<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingVoidSnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'original_aed_amount' => 'decimal:8',
            'void_charge_aed' => 'decimal:8',
            'refund_aed' => 'decimal:8',
            'effective_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    /** Get the booking whose void financial result was locked. */
    public function booking()
    {
        return $this->belongsTo(FlightBookings::class);
    }

    /** Get the original immutable booking price snapshot. */
    public function originalPriceSnapshot()
    {
        return $this->belongsTo(BookingPriceSnapshot::class, 'original_price_snapshot_id');
    }
}
