<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelSearchSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'destination_type',
        'destination_code',
        'destination_label',
        'check_in',
        'check_out',
        'guest_nationality',
        'pax_rooms',
        'tbo_request',
        'tbo_response',
        'expires_at',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'pax_rooms' => 'array',
        'tbo_request' => 'array',
        'tbo_response' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
