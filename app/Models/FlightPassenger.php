<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class FlightPassenger extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(FlightBookings::class, 'booking_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
