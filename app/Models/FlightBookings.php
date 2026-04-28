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

}
