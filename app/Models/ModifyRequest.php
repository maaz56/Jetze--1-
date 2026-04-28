<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifyRequest extends Model
{
    protected $guarded = [];

    public function booking()
    {
        return $this->belongsTo(FlightBookings::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
