<?php

namespace App\Services;

use App\Exceptions\TabbyCheckoutException;
use App\Jobs\FulfilAtNomodPayment;
use App\Jobs\FulfilTboNomodPayment;
use App\Models\FlightBookings;
use App\Models\HotelBooking;
use App\Models\HotelBookingEvent;
use App\Models\HotelPaymentAttempt;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Moves a local Tabby payment attempt forward from the authoritative Tabby
 * payment state. Used by the customer return page and by webhooks; both paths
 * are idempotent because every transition happens under a row lock.
 *
 * AUTHORIZED payments are captured before the attempt is marked paid, so a
 * booking is never fulfilled against an uncaptured authorisation. Late or
 * duplicate payments are recorded for review and deliberately not captured.
 */
class TabbyPaymentReconciler
{
    private const SETTLED_STATUSES = [
        PaymentAttempt::STATUS_PAID,
        PaymentAttempt::STATUS_FAILED,
    ];

    public function __construct(
        private readonly TabbyService $tabbyService,
        private readonly ProviderBookingEventService $providerBookingEventService,
    ) {}

    public function syncFlight(PaymentAttempt $attempt): PaymentAttempt
    {
        if ($attempt->provider !== PaymentAttempt::PROVIDER_TABBY
            || ! $attempt->provider_charge_id
            || in_array($attempt->status, self::SETTLED_STATUSES, true)) {
            return $attempt;
        }

        $payment = $this->tabbyService->retrievePayment($attempt->provider_charge_id);
        $this->assertPaymentMatchesAttempt($payment, $attempt);
        $status = strtoupper((string) ($payment['status'] ?? ''));

        if (in_array($status, [TabbyService::PAYMENT_REJECTED, TabbyService::PAYMENT_EXPIRED], true)) {
            return $this->markFailed($attempt, $status);
        }

        if (! in_array($status, [TabbyService::PAYMENT_AUTHORIZED, TabbyService::PAYMENT_CLOSED], true)) {
            return $attempt;
        }

        $booking = FlightBookings::query()->findOrFail($attempt->booking_id);
        $review = $this->flightReviewReason($attempt, $booking);
        $captureId = $review ? null : $this->ensureCaptured($attempt, $payment);

        $shouldDispatchAt = DB::transaction(function () use ($attempt, $status, $review, $captureId): bool {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status === PaymentAttempt::STATUS_PAID) {
                return false;
            }

            $booking = FlightBookings::query()->lockForUpdate()->findOrFail($locked->booking_id);
            $review = $review ?? $this->flightReviewReason($locked, $booking);

            $locked->update([
                'status' => PaymentAttempt::STATUS_PAID,
                'provider_status' => $status,
                'provider_capture_id' => $captureId ?? $locked->provider_capture_id,
                'captured_at' => $captureId ? ($locked->captured_at ?? now()) : $locked->captured_at,
                'paid_at' => $locked->paid_at ?? now(),
                'fulfilment_status' => $review['fulfilment_status'] ?? $locked->fulfilment_status,
                'fulfilment_error' => $review['message'] ?? $locked->fulfilment_error,
            ]);

            $this->providerBookingEventService->record(
                $booking,
                PaymentAttempt::PROVIDER_TABBY,
                $review['stage'] ?? 'payment_paid',
                [
                    'payment_attempt_uuid' => $locked->uuid,
                    'tabby_payment_id' => $locked->provider_charge_id,
                    'tabby_capture_id' => $locked->provider_capture_id,
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'provider_amount' => $locked->provider_amount,
                    'provider_currency' => $locked->provider_currency,
                ],
                $locked->reference_id,
            );

            if ($review) {
                return false;
            }

            // Hakimi is booked with the supplier before checkout begins, so
            // only the local booking needs to be ticketed (same as Nomod).
            if (strtolower((string) $booking->flight_provider) === 'hakimi') {
                $booking->update([
                    'status' => 'ticketed',
                    'issuance_date' => $booking->issuance_date ?? now(),
                ]);
                $locked->update([
                    'fulfilment_status' => PaymentAttempt::FULFILMENT_COMPLETED,
                    'fulfilment_completed_at' => now(),
                    'fulfilment_error' => null,
                ]);
                $this->providerBookingEventService->record(
                    $booking,
                    'hakimi',
                    'payment_confirmed',
                    [
                        'payment_attempt_uuid' => $locked->uuid,
                        'amount' => $locked->amount,
                        'currency' => $locked->currency,
                    ],
                    $booking->itinerary_ref,
                );

                return false;
            }

            return strtolower((string) $booking->flight_provider) === 'at';
        });

        if ($shouldDispatchAt) {
            FulfilAtNomodPayment::dispatch($attempt->id)->afterCommit();
        }

        return $attempt->fresh();
    }

    public function syncHotel(HotelPaymentAttempt $attempt): HotelPaymentAttempt
    {
        if ($attempt->provider !== HotelPaymentAttempt::PROVIDER_TABBY
            || ! $attempt->provider_charge_id
            || in_array($attempt->status, self::SETTLED_STATUSES, true)) {
            return $attempt;
        }

        $payment = $this->tabbyService->retrievePayment($attempt->provider_charge_id);
        $this->assertPaymentMatchesAttempt($payment, $attempt);
        $status = strtoupper((string) ($payment['status'] ?? ''));

        if (in_array($status, [TabbyService::PAYMENT_REJECTED, TabbyService::PAYMENT_EXPIRED], true)) {
            return $this->markFailed($attempt, $status);
        }

        if (! in_array($status, [TabbyService::PAYMENT_AUTHORIZED, TabbyService::PAYMENT_CLOSED], true)) {
            return $attempt;
        }

        $booking = HotelBooking::query()->with('prebook')->findOrFail($attempt->hotel_booking_id);
        $review = $this->hotelReviewReason($attempt, $booking);
        $captureId = $review ? null : $this->ensureCaptured($attempt, $payment);

        $shouldDispatch = DB::transaction(function () use ($attempt, $status, $review, $captureId): bool {
            $locked = HotelPaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($locked->status === HotelPaymentAttempt::STATUS_PAID) {
                return false;
            }

            $booking = HotelBooking::query()->with('prebook')->lockForUpdate()->findOrFail($locked->hotel_booking_id);
            $review = $review ?? $this->hotelReviewReason($locked, $booking);

            $locked->update([
                'status' => HotelPaymentAttempt::STATUS_PAID,
                'provider_status' => $status,
                'provider_capture_id' => $captureId ?? $locked->provider_capture_id,
                'captured_at' => $captureId ? ($locked->captured_at ?? now()) : $locked->captured_at,
                'paid_at' => $locked->paid_at ?? now(),
                'fulfilment_status' => $review['fulfilment_status'] ?? $locked->fulfilment_status,
                'fulfilment_error' => $review['message'] ?? $locked->fulfilment_error,
            ]);

            HotelBookingEvent::create([
                'hotel_prebook_id' => $booking->hotel_prebook_id,
                'hotel_booking_id' => $booking->id,
                'provider' => HotelPaymentAttempt::PROVIDER_TABBY,
                'stage' => $review['stage'] ?? 'payment_paid',
                'provider_reference' => $locked->reference_id,
                'response_data' => [
                    'payment_attempt_uuid' => $locked->uuid,
                    'tabby_payment_id' => $locked->provider_charge_id,
                    'tabby_capture_id' => $locked->provider_capture_id,
                    'amount' => $locked->amount,
                    'currency' => $locked->currency,
                    'provider_amount' => $locked->provider_amount,
                    'provider_currency' => $locked->provider_currency,
                ],
                'occurred_at' => now(),
            ]);

            return $review === null;
        });

        if ($shouldDispatch) {
            FulfilTboNomodPayment::dispatch($attempt->id)->afterCommit();
        }

        return $attempt->fresh();
    }

    /**
     * Capture the full authorised amount once. Tabby treats the capture
     * reference_id as an idempotency key, so a retry cannot double-capture.
     */
    private function ensureCaptured(PaymentAttempt|HotelPaymentAttempt $attempt, array $payment): ?string
    {
        $existingCapture = data_get($payment, 'captures.0.id');
        if (is_string($existingCapture) && $existingCapture !== '') {
            return $existingCapture;
        }

        if (strtoupper((string) ($payment['status'] ?? '')) !== TabbyService::PAYMENT_AUTHORIZED) {
            return null;
        }

        $captured = $this->tabbyService->capturePayment(
            (string) $attempt->provider_charge_id,
            $attempt->provider_amount,
            $attempt->reference_id.'-CAPTURE',
        );

        $captureId = data_get($captured, 'captures.0.id');
        if (! is_string($captureId) || $captureId === '') {
            throw new TabbyCheckoutException('Tabby capture response did not include a capture ID.');
        }

        return $captureId;
    }

    private function markFailed(PaymentAttempt|HotelPaymentAttempt $attempt, string $providerStatus): PaymentAttempt|HotelPaymentAttempt
    {
        $attempt->newQuery()
            ->whereKey($attempt->id)
            ->whereNotIn('status', self::SETTLED_STATUSES)
            ->update([
                'status' => PaymentAttempt::STATUS_FAILED,
                'provider_status' => $providerStatus,
                'failed_at' => now(),
                'updated_at' => now(),
            ]);

        return $attempt->fresh();
    }

    /** @return array{fulfilment_status: string, stage: string, message: string}|null */
    private function flightReviewReason(PaymentAttempt $attempt, FlightBookings $booking): ?array
    {
        $hasAnotherPaidAttempt = PaymentAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('status', PaymentAttempt::STATUS_PAID)
            ->whereKeyNot($attempt->id)
            ->exists();

        if ($hasAnotherPaidAttempt) {
            return [
                'fulfilment_status' => PaymentAttempt::FULFILMENT_DUPLICATE_PAYMENT_REVIEW,
                'stage' => 'payment_duplicate_review',
                'message' => 'Another successful payment exists for this booking. The Tabby payment was not captured; review it before any supplier action.',
            ];
        }

        if ($booking->payment_expires_at && ! $booking->payment_expires_at->isFuture()) {
            return [
                'fulfilment_status' => PaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
                'stage' => 'payment_after_expiry',
                'message' => 'Tabby payment was authorised after the booking payment window expired. It was not captured; review is required before any supplier action.',
            ];
        }

        return null;
    }

    /** @return array{fulfilment_status: string, stage: string, message: string}|null */
    private function hotelReviewReason(HotelPaymentAttempt $attempt, HotelBooking $booking): ?array
    {
        $hasAnotherPaidAttempt = HotelPaymentAttempt::query()
            ->where('hotel_booking_id', $booking->id)
            ->where('status', HotelPaymentAttempt::STATUS_PAID)
            ->whereKeyNot($attempt->id)
            ->exists();

        if ($hasAnotherPaidAttempt) {
            return [
                'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_DUPLICATE_PAYMENT_REVIEW,
                'stage' => 'payment_duplicate_review',
                'message' => 'Another successful payment exists for this hotel reservation. The Tabby payment was not captured; review it before supplier action.',
            ];
        }

        if ($booking->prebook?->expires_at && ! $booking->prebook->expires_at->isFuture()) {
            return [
                'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
                'stage' => 'payment_after_expiry',
                'message' => 'Tabby payment was authorised after the hotel reservation expired. It was not captured; review is required before supplier action.',
            ];
        }

        return null;
    }

    private function assertPaymentMatchesAttempt(array $payment, PaymentAttempt|HotelPaymentAttempt $attempt): void
    {
        if (! hash_equals((string) $attempt->provider_charge_id, (string) ($payment['id'] ?? ''))
            || ! hash_equals($attempt->reference_id, (string) data_get($payment, 'order.reference_id', ''))
            || strtoupper((string) ($payment['currency'] ?? '')) !== strtoupper((string) $attempt->provider_currency)
            || number_format((float) ($payment['amount'] ?? -1), 2, '.', '')
                !== number_format((float) $attempt->provider_amount, 2, '.', '')) {
            throw new TabbyCheckoutException('Tabby payment data did not match the payment attempt.');
        }
    }
}
