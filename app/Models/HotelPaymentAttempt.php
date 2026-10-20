<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelPaymentAttempt extends Model
{
    public const PROVIDER_NOMOD = 'nomod';

    public const STATUS_INITIATING = 'initiating';

    public const STATUS_CREATED = 'created';

    public const STATUS_CREATION_FAILED = 'creation_failed';

    public const STATUS_CREATION_UNKNOWN = 'creation_unknown';

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
            'base_amount' => 'decimal:8',
            'percentage_fee' => 'decimal:8',
            'fixed_fee' => 'decimal:8',
            'fee_amount' => 'decimal:8',
            'checkout_created_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'fulfilment_started_at' => 'datetime',
            'fulfilment_completed_at' => 'datetime',
            'fulfilment_failed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HotelBooking::class, 'hotel_booking_id');
    }

    public function priceSnapshot(): BelongsTo
    {
        return $this->belongsTo(HotelBookingPriceSnapshot::class, 'hotel_booking_price_snapshot_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(HotelPaymentWebhookEvent::class);
    }
}
