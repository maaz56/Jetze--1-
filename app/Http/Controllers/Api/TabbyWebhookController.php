<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelPaymentAttempt;
use App\Models\HotelPaymentWebhookEvent;
use App\Models\PaymentAttempt;
use App\Models\PaymentWebhookEvent;
use App\Services\TabbyPaymentReconciler;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

/**
 * Tabby webhooks carry no signature; Tabby echoes back the secret header we
 * registered. The payload is only used to find the attempt: the reconciler
 * always re-reads the authoritative payment from the Tabby API.
 *
 * Non-200 responses make Tabby retry (up to 4 more times, 1-4 minutes apart),
 * so unknown attempts and reconciliation failures deliberately return errors.
 */
class TabbyWebhookController extends Controller
{
    public function __construct(private readonly TabbyPaymentReconciler $reconciler) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.tabby.webhook_secret');
        $received = (string) $request->header((string) config('services.tabby.webhook_header', 'X-Tabby-Webhook-Secret'));

        if ($secret === '' || ! hash_equals($secret, $received)) {
            Log::warning('Rejected Tabby webhook with a missing or invalid secret header.', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid webhook credentials.'], 401);
        }

        $rawBody = $request->getContent();
        if (strlen($rawBody) > 1024 * 1024) {
            return response()->json(['message' => 'Webhook payload is too large.'], 413);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Invalid webhook JSON.'], 400);
        }

        $paymentId = is_array($payload) ? ($payload['id'] ?? null) : null;
        $status = strtolower((string) ($payload['status'] ?? ''));
        if (! is_string($paymentId) || $paymentId === '' || strlen($paymentId) > 128 || $status === '') {
            return response()->json(['message' => 'Invalid webhook event.'], 400);
        }

        // Tabby sends no event ID. Capture/refund counts distinguish events
        // that share a status (e.g. "closed" after capture vs. after refund).
        $eventId = 'tabby:'.hash('sha256', implode('|', [
            $paymentId,
            $status,
            count((array) ($payload['captures'] ?? [])),
            count((array) ($payload['refunds'] ?? [])),
        ]));

        Log::info('Tabby webhook received.', [
            'payment_id' => $paymentId,
            'status' => $status,
            'is_test' => $payload['is_test'] ?? null,
        ]);

        $reference = strtoupper((string) data_get($payload, 'order.reference_id', ''));

        return str_starts_with($reference, 'JETZE-HOTEL-')
            ? $this->handleHotel($eventId, $status, $paymentId, $payload)
            : $this->handleFlight($eventId, $status, $paymentId, $payload);
    }

    private function handleFlight(string $eventId, string $status, string $paymentId, array $payload): JsonResponse
    {
        [$event, $isNew] = $this->recordEvent(PaymentWebhookEvent::class, $eventId, $status, $payload);
        if (! $isNew && in_array($event->processing_status, ['processed', 'ignored'], true)) {
            return response()->json(['received' => true]);
        }

        $attempt = PaymentAttempt::query()
            ->where('provider', PaymentAttempt::PROVIDER_TABBY)
            ->where('provider_charge_id', $paymentId)
            ->first();

        if (! $attempt) {
            // The attempt may not be committed yet; a 404 makes Tabby retry.
            $event->update(['processing_status' => 'retry']);

            return response()->json(['message' => 'Payment attempt not found.'], 404);
        }

        try {
            $this->reconciler->syncFlight($attempt);
        } catch (Throwable $exception) {
            return $this->failed($event, $attempt->uuid, $exception);
        }

        $event->update([
            'payment_attempt_id' => $attempt->id,
            'processing_status' => 'processed',
            'processed_at' => now(),
        ]);

        return response()->json(['received' => true]);
    }

    private function handleHotel(string $eventId, string $status, string $paymentId, array $payload): JsonResponse
    {
        [$event, $isNew] = $this->recordEvent(HotelPaymentWebhookEvent::class, $eventId, $status, $payload);
        if (! $isNew && in_array($event->processing_status, ['processed', 'ignored'], true)) {
            return response()->json(['received' => true]);
        }

        $attempt = HotelPaymentAttempt::query()
            ->where('provider', HotelPaymentAttempt::PROVIDER_TABBY)
            ->where('provider_charge_id', $paymentId)
            ->first();

        if (! $attempt) {
            $event->update(['processing_status' => 'retry']);

            return response()->json(['message' => 'Hotel payment attempt not found.'], 404);
        }

        try {
            $this->reconciler->syncHotel($attempt);
        } catch (Throwable $exception) {
            return $this->failed($event, $attempt->uuid, $exception);
        }

        $event->update([
            'hotel_payment_attempt_id' => $attempt->id,
            'processing_status' => 'processed',
            'processed_at' => now(),
        ]);

        return response()->json(['received' => true]);
    }

    /**
     * @param  class-string<PaymentWebhookEvent|HotelPaymentWebhookEvent>  $model
     * @return array{PaymentWebhookEvent|HotelPaymentWebhookEvent, bool}
     */
    private function recordEvent(string $model, string $eventId, string $status, array $payload): array
    {
        try {
            return [$model::create([
                'provider' => PaymentAttempt::PROVIDER_TABBY,
                'event_id' => $eventId,
                'event_type' => $status,
                'signature_verified' => true,
                'processing_status' => 'received',
                'payload' => $payload,
                'received_at' => now(),
            ]), true];
        } catch (QueryException $exception) {
            $event = $model::query()
                ->where('provider', PaymentAttempt::PROVIDER_TABBY)
                ->where('event_id', $eventId)
                ->first();

            if (! $event) {
                throw $exception;
            }

            return [$event, false];
        }
    }

    private function failed(PaymentWebhookEvent|HotelPaymentWebhookEvent $event, string $attemptUuid, Throwable $exception): JsonResponse
    {
        $event->update(['processing_status' => 'retry']);

        Log::error('Tabby webhook could not be reconciled.', [
            'event_id' => $event->event_id,
            'payment_attempt_uuid' => $attemptUuid,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return response()->json(['message' => 'Webhook reconciliation failed.'], 500);
    }
}
