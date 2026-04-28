<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineBookingTravellers extends Model
{
    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(OfflineBooking::class, 'offline_booking_id');
    }
}
