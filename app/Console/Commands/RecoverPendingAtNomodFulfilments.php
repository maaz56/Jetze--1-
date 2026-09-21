<?php

namespace App\Console\Commands;

use App\Jobs\FulfilAtNomodPayment;
use App\Models\PaymentAttempt;
use App\Services\ProviderBookingEventService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecoverPendingAtNomodFulfilments extends Command
{
    protected $signature = 'payments:nomod:recover-at-fulfilments';

    protected $description = 'Dispatch durable pending AT Nomod fulfilments and quarantine stale supplier requests.';

    public function handle(ProviderBookingEventService $providerBookingEventService): int
    {
        $pendingIds = PaymentAttempt::query()
            ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
            ->where('status', PaymentAttempt::STATUS_PAID)
            ->where('fulfilment_status', PaymentAttempt::FULFILMENT_PENDING)
            ->whereHas('booking', fn ($query) => $query->whereRaw('LOWER(flight_provider) = ?', ['at']))
            ->pluck('id');

        foreach ($pendingIds as $paymentAttemptId) {
            FulfilAtNomodPayment::dispatch((int) $paymentAttemptId);
        }

        $quarantined = 0;
        PaymentAttempt::query()
            ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
            ->where('status', PaymentAttempt::STATUS_PAID)
            ->where('fulfilment_status', PaymentAttempt::FULFILMENT_PROCESSING)
            ->where('fulfilment_started_at', '<=', now()->subMinutes(10))
            ->whereHas('booking', fn ($query) => $query->whereRaw('LOWER(flight_provider) = ?', ['at']))
            ->orderBy('id')
            ->eachById(function (PaymentAttempt $candidate) use ($providerBookingEventService, &$quarantined): void {
                DB::transaction(function () use ($candidate, $providerBookingEventService, &$quarantined): void {
                    $attempt = PaymentAttempt::query()
                        ->with('booking')
                        ->lockForUpdate()
                        ->find($candidate->id);

                    if (! $attempt || $attempt->fulfilment_status !== PaymentAttempt::FULFILMENT_PROCESSING) {
                        return;
                    }

                    $attempt->update([
                        'fulfilment_status' => PaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
                        'fulfilment_error' => 'The worker stopped while AT supplier settlement was in progress. Reconcile with AT before any retry.',
                        'fulfilment_failed_at' => now(),
                    ]);

                    if ($attempt->booking) {
                        $providerBookingEventService->record(
                            $attempt->booking,
                            'at',
                            'ticket_payment_reconciliation_required',
                            [
                                'reason' => 'Stale in-progress fulfilment recovered by scheduler.',
                                'payment_attempt_uuid' => $attempt->uuid,
                            ],
                            $attempt->booking->itinerary_ref,
                        );
                    }

                    $quarantined++;
                });
            });

        $this->info(sprintf('Dispatched %d pending AT Nomod fulfilment(s); quarantined %d uncertain request(s).', $pendingIds->count(), $quarantined));

        return self::SUCCESS;
    }
}
