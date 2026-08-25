<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentLedgerEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'aed_amount' => 'decimal:8',
            'effective_date' => 'date',
        ];
    }

    /** Get the booking that produced this ledger entry. */
    public function booking()
    {
        return $this->belongsTo(FlightBookings::class);
    }

    /** Get the immutable void settlement behind this entry. */
    public function voidSnapshot()
    {
        return $this->belongsTo(BookingVoidSnapshot::class, 'booking_void_snapshot_id');
    }
}
