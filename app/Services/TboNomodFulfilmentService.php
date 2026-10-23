<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelBookingEvent;
use App\Models\HotelPaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TboNomodFulfilmentService
{
    public function __construct(
        private readonly TboHotelService $tboHotelService,
    ) {}

    /**
     * Confirm a paid hotel reservation exactly once from its persisted request.
     *
     * A transport error is deliberately quarantined instead of retried because
     * TBO may have accepted the booking before the connection was interrupted.
     */
    public function fulfil(int $paymentAttemptId): void
    {
        $context = $this->claim($paymentAttemptId);
        if ($context === null) {
            return;
        }

        try {
            $response = $this->tboHotelService->book($context['request']);
        } catch (Throwable $exception) {
            $this->markUncertain($paymentAttemptId, $exception->getMessage());

            return;
        }

        $statusCode = (int) data_get($response, 'Status.Code', 500);
        if ($statusCode !== 200) {
            $this->markFailed(
                $paymentAttemptId,
                (string) (data_get($response, 'Status.Description') ?: 'TBO rejected the hotel booking.'),
                $response,
            );

            return;
        }

        $booking = $this->markCompleted($paymentAttemptId, $response);
        if ($booking) {
            $this->syncBookingDetails($booking);
        }
    }

    /** @return array{request: array<string, mixed>}|null */
    private function claim(int $paymentAttemptId): ?array
    {
        return DB::transaction(function () use ($paymentAttemptId): ?array {
            $attempt = HotelPaymentAttempt::query()
                ->with('priceSnapshot')
                ->lockForUpdate()
                ->find($paymentAttemptId);

            if (! $attempt
                || $attempt->provider !== HotelPaymentAttempt::PROVIDER_NOMOD
                || $attempt->status !== HotelPaymentAttempt::STATUS_PAID) {
                return null;
            }

            $booking = HotelBooking::query()
                ->with(['prebook', 'priceSnapshot'])
                ->lockForUpdate()
                ->find($attempt->hotel_booking_id);
            if (! $booking) {
                return null;
            }

            if ($booking->status === 'confirmed') {
                $booking->update([
                    'payment_method' => HotelPaymentAttempt::PROVIDER_NOMOD,
                    'paid_at' => $booking->paid_at ?? $attempt->paid_at ?? now(),
                ]);
                $attempt->update([
                    'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_COMPLETED,
                    'fulfilment_completed_at' => $attempt->fulfilment_completed_at ?? now(),
                    'fulfilment_error' => null,
                ]);

                return null;
            }

            // Duplicate jobs and uncertain supplier calls cannot move forward.
            if ($attempt->fulfilment_status !== HotelPaymentAttempt::FULFILMENT_PENDING) {
                return null;
            }

            if ($booking->status !== 'payment_pending') {
                $this->quarantineBeforeSupplierCall(
                    $attempt,
                    $booking,
                    'The reservation is not payment-pending; supplier booking was not started.',
                );

                return null;
            }

            if ($booking->prebook?->expires_at && ! $booking->prebook->expires_at->isFuture()) {
                $booking->update(['status' => 'expired']);
                $this->quarantineBeforeSupplierCall(
                    $attempt,
                    $booking,
                    'Payment was verified after the hotel reservation expired; supplier booking was not started.',
                );

                return null;
            }

            if (! $booking->priceSnapshot
                || (int) $attempt->hotel_booking_price_snapshot_id !== (int) $booking->priceSnapshot->id
                || (int) $attempt->hotel_booking_price_snapshot_id !== (int) $attempt->priceSnapshot?->id) {
                $this->quarantineBeforeSupplierCall(
                    $attempt,
                    $booking,
                    'The paid attempt does not match the locked hotel price snapshot.',
                );

                return null;
            }

            if (! is_array($booking->tbo_request) || $booking->tbo_request === []) {
                $this->quarantineBeforeSupplierCall(
                    $attempt,
                    $booking,
                    'The persisted TBO booking request is unavailable.',
                );

                return null;
            }

            $attempt->update([
                'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_PROCESSING,
                'fulfilment_attempts' => $attempt->fulfilment_attempts + 1,
                'fulfilment_started_at' => now(),
                'fulfilment_error' => null,
            ]);
            $booking->update([
                'status' => 'processing',
                'payment_method' => HotelPaymentAttempt::PROVIDER_NOMOD,
                'paid_at' => $booking->paid_at ?? $attempt->paid_at ?? now(),
            ]);

            $this->recordEvent($booking, 'tbo', 'book_request', [
                'payment_attempt_uuid' => $attempt->uuid,
                'payment_reference' => $attempt->reference_id,
            ]);

            return ['request' => $booking->tbo_request];
        });
    }

    private function quarantineBeforeSupplierCall(
        HotelPaymentAttempt $attempt,
        HotelBooking $booking,
        string $message,
    ): void {
        $attempt->update([
            'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
            'fulfilment_error' => Str::limit($message, 4000),
            'fulfilment_failed_at' => now(),
        ]);

        $this->recordEvent($booking, 'nomod', 'book_blocked', [
            'payment_attempt_uuid' => $attempt->uuid,
            'reason' => $message,
        ]);
    }

    private function markUncertain(int $paymentAttemptId, string $error): void
    {
        DB::transaction(function () use ($paymentAttemptId, $error): void {
            $attempt = HotelPaymentAttempt::query()->lockForUpdate()->find($paymentAttemptId);
            $booking = $attempt
                ? HotelBooking::query()->lockForUpdate()->find($attempt->hotel_booking_id)
                : null;

            if (! $attempt || ! $booking
                || $attempt->fulfilment_status !== HotelPaymentAttempt::FULFILMENT_PROCESSING) {
                return;
            }

            $attempt->update([
                'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
                'fulfilment_error' => Str::limit($error, 4000),
                'fulfilment_failed_at' => now(),
            ]);
            $booking->update(['status' => 'unknown']);

            $this->recordEvent($booking, 'tbo', 'book_transport_error', [
                'message' => 'No authoritative response was received from TBO. Manual reconciliation is required.',
            ]);
        });

        Log::error('TBO paid hotel booking result is uncertain.', [
            'hotel_payment_attempt_id' => $paymentAttemptId,
            'message' => $error,
        ]);
    }

    /** @param array<string, mixed> $response */
    private function markFailed(int $paymentAttemptId, string $error, array $response): void
    {
        DB::transaction(function () use ($paymentAttemptId, $error, $response): void {
            $attempt = HotelPaymentAttempt::query()->lockForUpdate()->find($paymentAttemptId);
            $booking = $attempt
                ? HotelBooking::query()->lockForUpdate()->find($attempt->hotel_booking_id)
                : null;

            if (! $attempt || ! $booking
                || $attempt->fulfilment_status !== HotelPaymentAttempt::FULFILMENT_PROCESSING) {
                return;
            }

            $statusCode = (int) data_get($response, 'Status.Code', 500);
            $booking->update([
                'status' => 'failed',
                'provider_status_code' => $statusCode,
                'tbo_response' => $response,
            ]);
            $attempt->update([
                'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_FAILED,
                'fulfilment_error' => Str::limit($error, 4000),
                'fulfilment_failed_at' => now(),
            ]);

            $this->recordEvent($booking, 'tbo', 'book', $response, $statusCode);
        });
    }

    /** @param array<string, mixed> $response */
    private function markCompleted(int $paymentAttemptId, array $response): ?HotelBooking
    {
        return DB::transaction(function () use ($paymentAttemptId, $response): ?HotelBooking {
            $attempt = HotelPaymentAttempt::query()->lockForUpdate()->find($paymentAttemptId);
            $booking = $attempt
                ? HotelBooking::query()->with('prebook')->lockForUpdate()->find($attempt->hotel_booking_id)
                : null;

            if (! $attempt || ! $booking
                || $attempt->fulfilment_status !== HotelPaymentAttempt::FULFILMENT_PROCESSING) {
                return null;
            }

            $booking->update([
                'status' => 'confirmed',
                'payment_method' => HotelPaymentAttempt::PROVIDER_NOMOD,
                'paid_at' => $booking->paid_at ?? $attempt->paid_at ?? now(),
                'provider_status_code' => 200,
                'booking_reference_id' => data_get($response, 'BookingReferenceId')
                    ?? $booking->booking_reference_id,
                'confirmation_number' => data_get($response, 'ConfirmationNumber'),
                'hotel_confirmation_number' => data_get($response, 'HotelConfirmationNumber'),
                'tbo_response' => $response,
            ]);
            $attempt->update([
                'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_COMPLETED,
                'fulfilment_completed_at' => now(),
                'fulfilment_error' => null,
            ]);

            $this->recordEvent($booking, 'tbo', 'book', $response, 200);

            return $booking->fresh(['prebook']);
        });
    }

    private function syncBookingDetails(HotelBooking $booking): void
    {
        if (! $booking->prebook) {
            return;
        }

        $payload = [];
        if ($booking->booking_reference_id) {
            $payload['BookingReferenceId'] = $booking->booking_reference_id;
        }
        if ($booking->confirmation_number) {
            $payload['ConfirmationNumber'] = $booking->confirmation_number;
        }
        $payload['PaymentMode'] = $booking->prebook->payment_mode;
        if (! $booking->confirmation_number && ! $booking->booking_reference_id) {
            Log::warning('TBO hotel details lookup skipped after Nomod fulfilment.', [
                'hotel_booking_uuid' => $booking->uuid,
                'reason' => 'The confirmed booking has no provider reference.',
            ]);

            return;
        }

        HotelBookingEvent::create([
            'hotel_prebook_id' => $booking->hotel_prebook_id,
            'hotel_booking_id' => $booking->id,
            'provider' => 'tbo',
            'stage' => 'detail_request',
            'provider_reference' => $booking->confirmation_number ?: $booking->booking_reference_id,
            'request_data' => $payload,
            'occurred_at' => now(),
        ]);

        try {
            $response = $this->tboHotelService->bookingDetail($payload);
        } catch (Throwable $exception) {
            $this->recordEvent($booking, 'tbo', 'detail_transport_error', [
                'trigger' => 'after_nomod_book',
                'message' => 'No response was received from the provider.',
            ]);
            Log::warning('TBO hotel details lookup failed after Nomod fulfilment.', [
                'hotel_booking_uuid' => $booking->uuid,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $statusCode = (int) data_get($response, 'Status.Code', 500);
        DB::transaction(function () use ($booking, $response, $statusCode): void {
            $lockedBooking = HotelBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $details = data_get($response, 'BookingDetail', []);
            $lockedBooking->update([
                'tbo_booking_detail_response' => $response,
                'booking_details_checked_at' => now(),
                'booking_reference_id' => data_get($details, 'BookingReferenceId')
                    ?? data_get($response, 'BookingReferenceId')
                    ?? $lockedBooking->booking_reference_id,
                'confirmation_number' => data_get($details, 'ConfirmationNumber')
                    ?? $lockedBooking->confirmation_number,
                'hotel_confirmation_number' => data_get($details, 'HotelConfirmationNumber')
                    ?? $lockedBooking->hotel_confirmation_number,
            ]);
            $this->recordEvent($lockedBooking, 'tbo', 'detail', $response, $statusCode);
        });
    }

    /** @param array<string, mixed> $responseData */
    private function recordEvent(
        HotelBooking $booking,
        string $provider,
        string $stage,
        array $responseData,
        ?int $providerStatusCode = null,
    ): void {
        HotelBookingEvent::create([
            'hotel_prebook_id' => $booking->hotel_prebook_id,
            'hotel_booking_id' => $booking->id,
            'provider' => $provider,
            'stage' => $stage,
            'provider_status_code' => $providerStatusCode,
            'provider_reference' => $booking->confirmation_number ?: $booking->booking_reference_id,
            'response_data' => $responseData,
            'occurred_at' => now(),
        ]);
    }
}
