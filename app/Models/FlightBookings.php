<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightBookings extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function pessangers()
    {
        return $this->hasMany(FlightPassenger::class, 'booking_id');
    }

    public function agent()
    {
        return $this->belongsTo(AgentData::class, 'agent_id');
    }

    public function user(){
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function bookingInvoice()
    {
        return $this->hasOne(BookingInvoice::class, 'booking_id');
    }

    /**
     * Get the permanent financial snapshot created after booking succeeds.
     */
    public function priceSnapshot()
    {
        return $this->belongsTo(BookingPriceSnapshot::class, 'price_snapshot_id');
    }

    /** Get the immutable financial result created when this booking is voided. */
    public function voidSnapshot()
    {
        return $this->hasOne(BookingVoidSnapshot::class, 'booking_id');
    }

    /**
     * Get every provider response saved during this booking's lifecycle.
     */
    public function providerEvents()
    {
        return $this->hasMany(ProviderBookingEvent::class, 'booking_id');
    }

}
