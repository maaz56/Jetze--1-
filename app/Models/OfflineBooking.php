<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineBooking extends Model
{
    protected $guarded = [];

    public function travellers()
    {
        return $this->hasMany(OfflineBookingTravellers::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
