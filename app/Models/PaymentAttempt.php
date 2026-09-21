<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    use HasFactory;

    public const PROVIDER_NOMOD = 'nomod';

    public const STATUS_INITIATING = 'initiating';

    public const STATUS_CREATED = 'created';

    public const STATUS_CREATION_FAILED = 'creation_failed';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const FULFILMENT_PENDING = 'pending';

    public const FULFILMENT_PROCESSING = 'processing';

    public const FULFILMENT_COMPLETED = 'completed';

    public const FULFILMENT_FAILED = 'failed';

    public const FULFILMENT_RECONCILIATION_REQUIRED = 'reconciliation_required';

    public const FULFILMENT_DUPLICATE_PAYMENT_REVIEW = 'duplicate_payment_review';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'checkout_created_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'fulfilment_started_at' => 'datetime',
            'fulfilment_completed_at' => 'datetime',
            'fulfilment_failed_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(FlightBookings::class, 'booking_id');
    }

    public function priceSnapshot()
    {
        return $this->belongsTo(BookingPriceSnapshot::class, 'price_snapshot_id');
    }

    public function webhookEvents()
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }
}
