<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class FlightPassenger extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected static function booted(): void
    {
        // New passengers start with their booking's status unless one is given.
        static::creating(function (FlightPassenger $passenger) {
            if (empty($passenger->status) && $passenger->booking_id) {
                $bookingStatus = FlightBookings::whereKey($passenger->booking_id)->value('status');
                if ($bookingStatus) {
                    $passenger->status = $bookingStatus;
                }
            }
        });
    }

    public function booking()
    {
        return $this->belongsTo(FlightBookings::class, 'booking_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
