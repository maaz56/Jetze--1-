<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingInvoice extends Model
{
    protected $guarded = [];

    public function booking(){
        return $this->belongsTo(FlightBookings::class,'booking_id');
    }
}
