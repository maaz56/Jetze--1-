<?php

namespace App\Console\Commands;

use App\Jobs\FulfilTboNomodPayment;
use App\Models\HotelBooking;
use App\Models\HotelBookingEvent;
use App\Models\HotelPaymentAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverPendingTboNomodFulfilments extends Command
{
    protected $signature = 'payments:nomod:recover-tbo-fulfilments';

    protected $description = 'Dispatch pending TBO Nomod fulfilments and quarantine uncertain supplier requests.';

    public function handle(): int
    {
        $expired = 0;
        HotelPaymentAttempt::query()
            ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
            ->whereIn('status', [
                HotelPaymentAttempt::STATUS_CREATED,
                HotelPaymentAttempt::STATUS_INITIATING,
            ])
            ->whereHas('booking.prebook', fn ($query) => $query->where('expires_at', '<=', now()))
            ->orderBy('id')
            ->eachById(function (HotelPaymentAttempt $attempt) use (&$expired): void {
                $expired += HotelPaymentAttempt::query()
                    ->whereKey($attempt->id)
                    ->whereIn('status', [
                        HotelPaymentAttempt::STATUS_CREATED,
                        HotelPaymentAttempt::STATUS_INITIATING,
                    ])
                    ->update([
                        'status' => HotelPaymentAttempt::STATUS_CANCELLED,
                        'failed_at' => now(),
                        'fulfilment_error' => 'Reservation expired before Nomod checkout completion.',
                    ]);
            });

        $pendingIds = HotelPaymentAttempt::query()
            ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
            ->where('status', HotelPaymentAttempt::STATUS_PAID)
            ->where('fulfilment_status', HotelPaymentAttempt::FULFILMENT_PENDING)
            ->pluck('id');

        foreach ($pendingIds as $paymentAttemptId) {
            FulfilTboNomodPayment::dispatch((int) $paymentAttemptId);
        }

        $quarantined = 0;
        HotelPaymentAttempt::query()
            ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
            ->where('status', HotelPaymentAttempt::STATUS_PAID)
            ->where('fulfilment_status', HotelPaymentAttempt::FULFILMENT_PROCESSING)
            ->where('fulfilment_started_at', '<=', now()->subMinutes(10))
            ->orderBy('id')
            ->eachById(function (HotelPaymentAttempt $candidate) use (&$quarantined): void {
                DB::transaction(function () use ($candidate, &$quarantined): void {
                    $attempt = HotelPaymentAttempt::query()->lockForUpdate()->find($candidate->id);
                    $booking = $attempt
                        ? HotelBooking::query()->lockForUpdate()->find($attempt->hotel_booking_id)
                        : null;

                    if (! $attempt || ! $booking
                        || $attempt->fulfilment_status !== HotelPaymentAttempt::FULFILMENT_PROCESSING) {
                        return;
                    }

                    $attempt->update([
                        'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
                        'fulfilment_error' => 'The worker stopped while the TBO booking request was in progress. Reconcile with TBO before any retry.',
                        'fulfilment_failed_at' => now(),
                    ]);
                    if ($booking->status === 'processing') {
                        $booking->update(['status' => 'unknown']);
                    }

                    HotelBookingEvent::create([
                        'hotel_prebook_id' => $booking->hotel_prebook_id,
                        'hotel_booking_id' => $booking->id,
                        'provider' => 'tbo',
                        'stage' => 'book_reconciliation_required',
                        'provider_reference' => $booking->booking_reference_id,
                        'response_data' => [
                            'reason' => 'Stale in-progress fulfilment recovered by scheduler.',
                            'payment_attempt_uuid' => $attempt->uuid,
                        ],
                        'occurred_at' => now(),
                    ]);

                    $quarantined++;
                });
            });

        $this->info(sprintf(
            'Expired %d pending hotel payment attempt(s); dispatched %d pending TBO Nomod fulfilment(s); quarantined %d uncertain request(s).',
            $expired,
            $pendingIds->count(),
            $quarantined,
        ));

        return self::SUCCESS;
    }
}
