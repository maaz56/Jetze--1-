<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\NomodCheckoutException;
use App\Exceptions\NomodWebhookSignatureException;
use App\Http\Controllers\Controller;
use App\Jobs\FulfilAtNomodPayment;
use App\Models\FlightBookings;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\NomodHostedCheckoutService;
use App\Services\NomodWebhookSignatureVerifier;
use App\Services\ProviderBookingEventService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class NomodWebhookController extends Controller
{
    public function __construct(
        private readonly NomodWebhookSignatureVerifier $signatureVerifier,
        private readonly NomodHostedCheckoutService $nomodHostedCheckoutService,
        private readonly ProviderBookingEventService $providerBookingEventService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        Log::info('Nomod webhook request received.', [
            'svix_id' => $request->header('svix-id'),
            'content_length' => strlen($rawBody),
        ]);

        if (strlen($rawBody) > 1024 * 1024) {
            return response()->json(['message' => 'Webhook payload is too large.'], 413);
        }

        try {
            $this->signatureVerifier->verify(
                $rawBody,
                $request->header('svix-id'),
                $request->header('svix-timestamp'),
                $request->header('svix-signature'),
            );
        } catch (NomodWebhookSignatureException $exception) {
            Log::warning('Rejected Nomod webhook with an invalid signature.', [
                'reason' => $exception->getMessage(),
                'svix_id' => $request->header('svix-id'),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid webhook JSON.'], 400);
        }

        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid webhook event.'], 400);
        }

        // Current Nomod payloads use snake_case; accept older camelCase too.
        $eventId = $payload['event_id'] ?? $payload['eventId'] ?? null;
        $eventType = $payload['type'] ?? null;
        if (! is_string($eventId) || $eventId === '' || strlen($eventId) > 128 || ! is_string($eventType) || $eventType === '') {
            return response()->json(['message' => 'Invalid webhook event.'], 400);
        }

        [$event, $isNew] = $this->findOrRecordEvent($eventId, $eventType, $payload);

        if (! $isNew && in_array($event->processing_status, ['processed', 'ignored'], true)) {
            return response()->json(['received' => true]);
        }

        if ($eventType !== 'charge.completed') {
            $event->update([
                'processing_status' => 'ignored',
                'processed_at' => now(),
            ]);

            return response()->json(['received' => true]);
        }

        $attempt = $this->findPaymentAttempt($payload);

        if (! $attempt) {
            $event->update([
                'processing_status' => 'ignored',
                'processed_at' => now(),
            ]);

            Log::warning('Ignored verified Nomod payment event for an unknown payment attempt.', ['event_id' => $eventId]);

            return response()->json(['received' => true]);
        }

        try {
            $checkout = $this->nomodHostedCheckoutService->retrieveCheckout($attempt);
            $this->assertPaidCheckoutMatchesAttempt($checkout, $attempt);

            $shouldDispatchFulfilment = DB::transaction(function () use ($attempt, $event, $payload): bool {
                $lockedAttempt = PaymentAttempt::query()
                    ->with('booking')
                    ->lockForUpdate()
                    ->findOrFail($attempt->id);
                $wasAlreadyPaid = $lockedAttempt->status === PaymentAttempt::STATUS_PAID;
                $lockedBooking = FlightBookings::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedAttempt->booking_id);
                $hasAnotherPaidAttempt = ! $wasAlreadyPaid && PaymentAttempt::query()
                    ->where('booking_id', $lockedBooking->id)
                    ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                    ->where('status', PaymentAttempt::STATUS_PAID)
                    ->whereKeyNot($lockedAttempt->id)
                    ->exists();
                $lockedAttempt->update([
                    'status' => PaymentAttempt::STATUS_PAID,
                    'provider_charge_id' => data_get($payload, 'data.id') ?? data_get($payload, 'objectId'),
                    'paid_at' => $lockedAttempt->paid_at ?? now(),
                    'fulfilment_status' => $hasAnotherPaidAttempt
                        ? PaymentAttempt::FULFILMENT_DUPLICATE_PAYMENT_REVIEW
                        : $lockedAttempt->fulfilment_status,
                    'fulfilment_error' => $hasAnotherPaidAttempt
                        ? 'Another successful Nomod payment exists for this booking. Review the duplicate payment before any supplier action.'
                        : $lockedAttempt->fulfilment_error,
                ]);

                $event->update([
                    'payment_attempt_id' => $lockedAttempt->id,
                    'processing_status' => 'processed',
                    'processed_at' => now(),
                ]);

                if (! $wasAlreadyPaid && $lockedAttempt->booking) {
                    $this->providerBookingEventService->record(
                        $lockedAttempt->booking,
                        PaymentAttempt::PROVIDER_NOMOD,
                        $hasAnotherPaidAttempt
                            ? 'payment_duplicate_review'
                            : 'payment_paid',
                        [
                            'payment_attempt_uuid' => $lockedAttempt->uuid,
                            'checkout_id' => $lockedAttempt->provider_checkout_id,
                            'charge_id' => data_get($payload, 'data.id') ?? data_get($payload, 'objectId'),
                            'amount' => $lockedAttempt->amount,
                            'currency' => $lockedAttempt->currency,
                        ],
                        $lockedAttempt->reference_id,
                    );
                }

                return ! $wasAlreadyPaid
                    && ! $hasAnotherPaidAttempt
                    && strtolower((string) $lockedBooking->flight_provider) === 'at';
            });

            if ($shouldDispatchFulfilment) {
                FulfilAtNomodPayment::dispatch($attempt->id)->afterCommit();
            }
        } catch (Throwable $exception) {
            $event->update(['processing_status' => 'retry']);

            Log::error('Verified Nomod webhook could not be reconciled.', [
                'event_id' => $eventId,
                'payment_attempt_uuid' => $attempt->uuid,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            // A non-2xx response tells Nomod to retry. The event record remains
            // in retry state so the repeated delivery is processed again.
            return response()->json(['message' => 'Webhook reconciliation failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    private function findOrRecordEvent(string $eventId, string $eventType, array $payload): array
    {
        try {
            $event = PaymentWebhookEvent::create([
                'provider' => PaymentAttempt::PROVIDER_NOMOD,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'signature_verified' => true,
                'processing_status' => 'received',
                'payload' => $payload,
                'received_at' => now(),
            ]);

            return [$event, true];
        } catch (QueryException $exception) {
            $event = PaymentWebhookEvent::query()
                ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                ->where('event_id', $eventId)
                ->first();

            if (! $event) {
                throw $exception;
            }

            return [$event, false];
        }
    }

    private function assertPaidCheckoutMatchesAttempt(array $checkout, PaymentAttempt $attempt): void
    {
        if (($checkout['status'] ?? null) !== PaymentAttempt::STATUS_PAID
            || ! hash_equals((string) $attempt->provider_checkout_id, (string) ($checkout['id'] ?? ''))
            || ! hash_equals($attempt->reference_id, (string) ($checkout['reference_id'] ?? ''))
            || strtoupper((string) ($checkout['currency'] ?? '')) !== strtoupper($attempt->currency)
            || number_format((float) ($checkout['amount'] ?? -1), 2, '.', '') !== number_format((float) $attempt->amount, 2, '.', '')) {
            throw new NomodCheckoutException('Nomod checkout reconciliation data did not match the payment attempt.');
        }
    }

    private function findPaymentAttempt(array $payload): ?PaymentAttempt
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $metadataAttempt = data_get($data, 'metadata.payment_attempt');

        if (is_string($metadataAttempt) && $metadataAttempt !== '') {
            $attempt = PaymentAttempt::query()
                ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                ->where('uuid', $metadataAttempt)
                ->first();

            if ($attempt) {
                return $attempt;
            }
        }

        // Hosted Checkout also returns the opaque payment-attempt UUID in the
        // configured success URL. It is safe to use only as a lookup key; the
        // paid checkout is still retrieved and fully reconciled below.
        $successUrl = data_get($data, 'success_url');
        if (is_string($successUrl) && parse_url($successUrl, PHP_URL_QUERY)) {
            parse_str((string) parse_url($successUrl, PHP_URL_QUERY), $query);
            $redirectAttempt = $query['payment_attempt'] ?? null;

            if (is_string($redirectAttempt) && $redirectAttempt !== '') {
                $attempt = PaymentAttempt::query()
                    ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                    ->where('uuid', $redirectAttempt)
                    ->first();

                if ($attempt) {
                    return $attempt;
                }
            }
        }

        $providerIds = array_values(array_filter([
            data_get($data, 'link.id'),
            data_get($data, 'id'),
            $payload['object_id'] ?? $payload['objectId'] ?? null,
        ], static fn ($value): bool => is_string($value) && $value !== ''));

        if ($providerIds !== []) {
            $attempt = PaymentAttempt::query()
                ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                ->whereIn('provider_checkout_id', $providerIds)
                ->first();

            if ($attempt) {
                return $attempt;
            }
        }

        $references = array_values(array_filter([
            data_get($data, 'referenceId'),
            data_get($data, 'reference_id'),
            data_get($data, 'link.reference_id'),
        ], static fn ($value): bool => is_string($value) && $value !== ''));

        return $references === [] ? null : PaymentAttempt::query()
            ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
            ->whereIn('reference_id', $references)
            ->first();
    }
}
