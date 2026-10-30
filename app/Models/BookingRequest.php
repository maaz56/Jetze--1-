<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BookingRequest extends Model
{
    public const TYPES = ['void', 'refund'];

    public const OPEN_STATUSES = ['pending', 'processing'];

    protected $fillable = [
        'booking_id',
        'type',
        'status',
        'requested_by',
        'customer_remarks',
        'reviewed_by',
        'reviewed_at',
        'admin_remarks',
        'booking_status_before',
        'currency',
        'ticket_amount',
        'penalty_amount',
        'refund_amount',
        'failure_reason',
    ];

    protected $casts = [
        'ticket_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(FlightBookings::class, 'booking_id');
    }

    public function passengers(): BelongsToMany
    {
        return $this->belongsToMany(
            FlightPassenger::class,
            'booking_request_passengers',
            'booking_request_id',
            'flight_passenger_id'
        )->withPivot('amount')->withTimestamps();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
