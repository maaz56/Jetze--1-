<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlightBookings extends Model
{
    use HasFactory;
    protected $guarded = [];

    /** Passenger statuses that come from an approved void/refund request. */
    public const SETTLED_PASSENGER_STATUSES = ['voided', 'refunded'];

    /** Booking statuses whose amount is debited from the customer/agent wallet ledger. */
    public const WALLET_DEBIT_STATUSES = ['ticketed', 'issued', 'voided', 'refunded', 'partially_voided', 'partially_refunded'];

    /**
     * Bookings whose amount is debited from the wallet. Card / one-bill approved
     * payments, and issued bookings with an external transaction id, are paid
     * outside the wallet.
     */
    public function scopeChargedToWallet($query)
    {
        return $query->whereIn('status', self::WALLET_DEBIT_STATUSES)
            ->where(fn ($q) => $q->whereNull('t_status')->orWhere('t_status', '!=', 'approved'))
            ->where(fn ($q) => $q->whereNull('tid')->orWhere('status', '!=', 'issued'));
    }

    /** Same rule as scopeChargedToWallet() for an already loaded booking. */
    public function isChargedToWallet(): bool
    {
        return in_array($this->status, self::WALLET_DEBIT_STATUSES, true)
            && $this->t_status !== 'approved'
            && ($this->tid === null || $this->status !== 'issued');
    }

    protected static function booted(): void
    {
        // Keep passenger statuses in step with whole-booking status changes. Partial
        // statuses are set per passenger by BookingRequestService, and passengers
        // already voided or refunded keep their own status.
        static::updated(function (FlightBookings $booking) {
            if (!$booking->wasChanged('status') || str_starts_with((string) $booking->status, 'partially_')) {
                return;
            }

            $booking->pessangers()
                ->whereNotIn('status', self::SETTLED_PASSENGER_STATUSES)
                ->update(['status' => $booking->status]);
        });
    }

    protected function casts(): array
    {
        return ['payment_expires_at' => 'datetime'];
    }

    public function pessangers()
    {
        return $this->hasMany(FlightPassenger::class, 'booking_id');
    }

    public function agent()
    {
        return $this->belongsTo(AgentData::class, 'agent_id');
    }

    public function user(){
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function bookingInvoice()
    {
        return $this->hasOne(BookingInvoice::class, 'booking_id');
    }

    /**
     * Get the permanent financial snapshot created after booking succeeds.
     */
    public function priceSnapshot()
    {
        return $this->belongsTo(BookingPriceSnapshot::class, 'price_snapshot_id');
    }

    /** Get the immutable financial result created when this booking is voided. */
    public function voidSnapshot()
    {
        return $this->hasOne(BookingVoidSnapshot::class, 'booking_id');
    }

    /**
     * Get every provider response saved during this booking's lifecycle.
     */
    public function providerEvents()
    {
        return $this->hasMany(ProviderBookingEvent::class, 'booking_id');
    }

    /** Payment attempts are separate from booking status and provider fulfilment. */
    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class, 'booking_id');
    }
}

