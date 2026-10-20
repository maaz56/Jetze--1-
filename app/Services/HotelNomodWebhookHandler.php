<?php

namespace App\Services;

use App\Exceptions\NomodCheckoutException;
use App\Jobs\FulfilTboNomodPayment;
use App\Models\HotelBooking;
use App\Models\HotelBookingEvent;
use App\Models\HotelPaymentAttempt;
use App\Models\HotelPaymentWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class HotelNomodWebhookHandler
{
    public function __construct(
        private readonly HotelNomodHostedCheckoutService $checkoutService,
    ) {}

    /** Determine whether a verified Nomod event belongs to the hotel flow. */
    public function matches(array $payload): bool
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $metadataType = data_get($data, 'metadata.payment_type')
            ?? data_get($data, 'link.metadata.payment_type');
        if (strtolower((string) $metadataType) === 'hotel') {
            return true;
        }

        if (data_get($data, 'metadata.hotel_payment_attempt')
            || data_get($data, 'link.metadata.hotel_payment_attempt')) {
            return true;
        }

        foreach ($this->successUrls($data) as $successUrl) {
            parse_str((string) parse_url($successUrl, PHP_URL_QUERY), $query);
            if (($query['payment_type'] ?? null) === 'hotel') {
                return true;
            }
        }

        foreach ($this->references($data) as $reference) {
            if (str_starts_with(strtoupper($reference), 'JETZE-HOTEL-')) {
                return true;
            }
        }

        return $this->findAttempt($payload) !== null;
    }

    /** Process an already signature-verified hotel event. */
    public function handle(string $eventId, string $eventType, array $payload): JsonResponse
    {
        [$event, $isNew] = $this->findOrRecordEvent($eventId, $eventType, $payload);

        if (! $isNew && in_array($event->processing_status, ['processed', 'ignored'], true)) {
            return response()->json(['received' => true]);
        }

        if ($eventType !== 'charge.completed') {
            $event->update(['processing_status' => 'ignored', 'processed_at' => now()]);

            return response()->json(['received' => true]);
        }

        $attempt = $this->findAttempt($payload);
        if (! $attempt) {
            $event->update(['processing_status' => 'ignored', 'processed_at' => now()]);
            Log::warning('Ignored verified Nomod hotel event for an unknown payment attempt.', [
                'event_id' => $eventId,
            ]);

            return response()->json(['received' => true]);
        }

        try {
            $checkout = $this->checkoutService->retrieveCheckout($attempt);
            $this->assertPaidCheckoutMatchesAttempt($checkout, $attempt);

            $shouldDispatch = DB::transaction(function () use ($attempt, $event, $payload): bool {
                $lockedAttempt = HotelPaymentAttempt::query()
                    ->with('booking.prebook')
                    ->lockForUpdate()
                    ->findOrFail($attempt->id);
                $wasAlreadyPaid = $lockedAttempt->status === HotelPaymentAttempt::STATUS_PAID;
                $booking = HotelBooking::query()->lockForUpdate()->findOrFail($lockedAttempt->hotel_booking_id);
                $booking->loadMissing('prebook');

                $hasAnotherPaidAttempt = ! $wasAlreadyPaid && HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                    ->where('status', HotelPaymentAttempt::STATUS_PAID)
                    ->whereKeyNot($lockedAttempt->id)
                    ->exists();
                $isLatePayment = $booking->prebook?->expires_at
                    && ! $booking->prebook->expires_at->isFuture();

                $fulfilmentStatus = $hasAnotherPaidAttempt
                    ? HotelPaymentAttempt::FULFILMENT_DUPLICATE_PAYMENT_REVIEW
                    : ($isLatePayment
                        ? HotelPaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED
                        : $lockedAttempt->fulfilment_status);
                $fulfilmentError = $hasAnotherPaidAttempt
                    ? 'Another successful Nomod payment exists for this hotel reservation. Review/refund the duplicate before supplier action.'
                    : ($isLatePayment
                        ? 'Payment completed after the hotel reservation expired. Review/refund is required before supplier action.'
                        : $lockedAttempt->fulfilment_error);

                $lockedAttempt->update([
                    'status' => HotelPaymentAttempt::STATUS_PAID,
                    'provider_charge_id' => data_get($payload, 'data.id')
                        ?? data_get($payload, 'object_id')
                        ?? data_get($payload, 'objectId'),
                    'paid_at' => $lockedAttempt->paid_at ?? now(),
                    'fulfilment_status' => $fulfilmentStatus,
                    'fulfilment_error' => $fulfilmentError,
                ]);
                $event->update([
                    'hotel_payment_attempt_id' => $lockedAttempt->id,
                    'processing_status' => 'processed',
                    'processed_at' => now(),
                ]);

                if (! $wasAlreadyPaid) {
                    HotelBookingEvent::create([
                        'hotel_prebook_id' => $booking->hotel_prebook_id,
                        'hotel_booking_id' => $booking->id,
                        'provider' => 'nomod',
                        'stage' => $hasAnotherPaidAttempt
                            ? 'payment_duplicate_review'
                            : ($isLatePayment ? 'payment_after_expiry' : 'payment_paid'),
                        'provider_reference' => $lockedAttempt->reference_id,
                        'response_data' => [
                            'payment_attempt_uuid' => $lockedAttempt->uuid,
                            'checkout_id' => $lockedAttempt->provider_checkout_id,
                            'charge_id' => $lockedAttempt->provider_charge_id,
                            'amount' => $lockedAttempt->amount,
                            'currency' => $lockedAttempt->currency,
                        ],
                        'occurred_at' => now(),
                    ]);
                }

                return ! $wasAlreadyPaid && ! $hasAnotherPaidAttempt && ! $isLatePayment;
            });

            if ($shouldDispatch) {
                FulfilTboNomodPayment::dispatch($attempt->id)->afterCommit();
            }
        } catch (Throwable $exception) {
            $event->update(['processing_status' => 'retry']);
            Log::error('Verified Nomod hotel webhook could not be reconciled.', [
                'event_id' => $eventId,
                'payment_attempt_uuid' => $attempt->uuid,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Hotel webhook reconciliation failed.'], 500);
        }

        return response()->json(['received' => true]);
    }

    /** @return array{HotelPaymentWebhookEvent, bool} */
    private function findOrRecordEvent(string $eventId, string $eventType, array $payload): array
    {
        try {
            $event = HotelPaymentWebhookEvent::create([
                'provider' => HotelPaymentAttempt::PROVIDER_NOMOD,
                'event_id' => $eventId,
                'event_type' => $eventType,
                'signature_verified' => true,
                'processing_status' => 'received',
                'payload' => $payload,
                'received_at' => now(),
            ]);

            return [$event, true];
        } catch (QueryException $exception) {
            $event = HotelPaymentWebhookEvent::query()
                ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                ->where('event_id', $eventId)
                ->first();
            if (! $event) {
                throw $exception;
            }

            return [$event, false];
        }
    }

    private function assertPaidCheckoutMatchesAttempt(array $checkout, HotelPaymentAttempt $attempt): void
    {
        if (($checkout['status'] ?? null) !== HotelPaymentAttempt::STATUS_PAID
            || ! hash_equals((string) $attempt->provider_checkout_id, (string) ($checkout['id'] ?? ''))
            || ! hash_equals($attempt->reference_id, (string) ($checkout['reference_id'] ?? ''))
            || strtoupper((string) ($checkout['currency'] ?? '')) !== strtoupper($attempt->currency)
            || number_format((float) ($checkout['amount'] ?? -1), 2, '.', '')
                !== number_format((float) $attempt->amount, 2, '.', '')) {
            throw new NomodCheckoutException('Nomod hotel checkout data did not match the payment attempt.');
        }
    }

    private function findAttempt(array $payload): ?HotelPaymentAttempt
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $metadataAttempt = data_get($data, 'metadata.hotel_payment_attempt')
            ?? data_get($data, 'link.metadata.hotel_payment_attempt');
        if (is_string($metadataAttempt) && $metadataAttempt !== '') {
            $attempt = HotelPaymentAttempt::query()
                ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                ->where('uuid', $metadataAttempt)
                ->first();
            if ($attempt) {
                return $attempt;
            }
        }

        foreach ($this->successUrls($data) as $successUrl) {
            parse_str((string) parse_url($successUrl, PHP_URL_QUERY), $query);
            $uuid = $query['payment_attempt'] ?? null;
            if (is_string($uuid) && $uuid !== '') {
                $attempt = HotelPaymentAttempt::query()
                    ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                    ->where('uuid', $uuid)
                    ->first();
                if ($attempt) {
                    return $attempt;
                }
            }
        }

        $providerIds = array_values(array_filter([
            data_get($data, 'link.id'),
            data_get($data, 'checkout_id'),
            data_get($data, 'id'),
            $payload['object_id'] ?? $payload['objectId'] ?? null,
        ], static fn ($value): bool => is_string($value) && $value !== ''));
        if ($providerIds !== []) {
            $attempt = HotelPaymentAttempt::query()
                ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                ->whereIn('provider_checkout_id', $providerIds)
                ->first();
            if ($attempt) {
                return $attempt;
            }
        }

        $references = $this->references($data);

        return $references === [] ? null : HotelPaymentAttempt::query()
            ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
            ->whereIn('reference_id', $references)
            ->first();
    }

    /** @return list<string> */
    private function successUrls(array $data): array
    {
        return array_values(array_filter([
            data_get($data, 'success_url'),
            data_get($data, 'link.success_url'),
        ], static fn ($value): bool => is_string($value) && $value !== ''));
    }

    /** @return list<string> */
    private function references(array $data): array
    {
        return array_values(array_filter([
            data_get($data, 'referenceId'),
            data_get($data, 'reference_id'),
            data_get($data, 'link.reference_id'),
        ], static fn ($value): bool => is_string($value) && $value !== ''));
    }
}
