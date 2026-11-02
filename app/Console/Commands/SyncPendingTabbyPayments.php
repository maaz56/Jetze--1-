<?php

namespace App\Console\Commands;

use App\Models\HotelPaymentAttempt;
use App\Models\PaymentAttempt;
use App\Services\TabbyPaymentReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPendingTabbyPayments extends Command
{
    protected $signature = 'payments:tabby:sync-pending';

    protected $description = 'Verify unsettled Tabby payments against the Tabby API in case a webhook and the customer return were both missed.';

    public function handle(TabbyPaymentReconciler $reconciler): int
    {
        $synced = 0;
        $failed = 0;

        // Recent sessions only: Tabby expires unpaid sessions well within a day.
        $since = now()->subDay();
        $statuses = [PaymentAttempt::STATUS_CREATED, PaymentAttempt::STATUS_CANCELLED];

        PaymentAttempt::query()
            ->where('provider', PaymentAttempt::PROVIDER_TABBY)
            ->whereIn('status', $statuses)
            ->whereNotNull('provider_charge_id')
            ->where('created_at', '>=', $since)
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->eachById(function (PaymentAttempt $attempt) use ($reconciler, &$synced, &$failed): void {
                $this->sync(fn () => $reconciler->syncFlight($attempt), $attempt->uuid, $synced, $failed);
            });

        HotelPaymentAttempt::query()
            ->where('provider', HotelPaymentAttempt::PROVIDER_TABBY)
            ->whereIn('status', $statuses)
            ->whereNotNull('provider_charge_id')
            ->where('created_at', '>=', $since)
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->eachById(function (HotelPaymentAttempt $attempt) use ($reconciler, &$synced, &$failed): void {
                $this->sync(fn () => $reconciler->syncHotel($attempt), $attempt->uuid, $synced, $failed);
            });

        $this->info(sprintf('Synced %d pending Tabby payment(s); %d failed.', $synced, $failed));

        return self::SUCCESS;
    }

    private function sync(callable $callback, string $uuid, int &$synced, int &$failed): void
    {
        try {
            $callback();
            $synced++;
        } catch (Throwable $exception) {
            $failed++;
            Log::warning('Scheduled Tabby payment sync failed.', [
                'payment_attempt_uuid' => $uuid,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
