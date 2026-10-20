<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPaymentWebhookEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'payload' => 'encrypted:array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(HotelPaymentAttempt::class, 'hotel_payment_attempt_id');
    }
}
