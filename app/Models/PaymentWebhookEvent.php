<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookEvent extends Model
{
    use HasFactory;

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

    public function paymentAttempt()
    {
        return $this->belongsTo(PaymentAttempt::class);
    }
}
