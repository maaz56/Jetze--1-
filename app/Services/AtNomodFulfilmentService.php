<?php

namespace App\Services;

use App\Models\FlightBookings;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class AtNomodFulfilmentService
{
    public function __construct(
        private readonly AtApiService $atApiService,
        private readonly ProviderBookingEventService $providerBookingEventService,
        private readonly BookingNotificationService $bookingNotificationService,
    ) {}

    /**
     * Confirm a paid AT reservation exactly once from persisted data.
     *
     * A network failure after StartPay is deliberately marked for reconciliation
     * instead of being retried blindly: repeating supplier settlement could
     * create a duplicate supplier charge or ticket.
     */
    public function fulfil(int $paymentAttemptId): void
    {
        $context = $this->claim($paymentAttemptId);
        if ($context === null) {
            return;
        }

        try {
            $reservation = $this->reservationFrom($context['booking']);
            $response = $this->atApiService->startDepositPayment(
                $reservation,
                $this->supplierNetAmount($context['attempt']),
                $this->bookingType($context['booking']),
            );
        } catch (Throwable $exception) {
            $this->markUncertain($paymentAttemptId, $exception->getMessage());

            return;
        }

        if ($response === null) {
            $this->markUncertain($paymentAttemptId, 'No response was received from AT after payment was initiated.');

            return;
        }

        if (($response['status'] ?? true) === false) {
            $this->markFailed(
                $paymentAttemptId,
                (string) ($response['error'] ?? $response['message'] ?? 'AT rejected the supplier settlement.'),
                $response,
            );

            return;
        }

        $this->markCompleted($paymentAttemptId, $response);
    }

    /** @return array{attempt: PaymentAttempt, booking: FlightBookings}|null */
    private function claim(int $paymentAttemptId): ?array
    {
        return DB::transaction(function () use ($paymentAttemptId): ?array {
            $attempt = PaymentAttempt::query()
                ->with(['booking.priceSnapshot', 'priceSnapshot'])
                ->lockForUpdate()
                ->find($paymentAttemptId);

            if (! $attempt || $attempt->provider !== PaymentAttempt::PROVIDER_NOMOD || $attempt->status !== PaymentAttempt::STATUS_PAID) {
                return null;
            }

            $booking = $attempt->booking;
            if (! $booking || strtolower((string) $booking->flight_provider) !== 'at') {
                return null;
            }

            if (in_array(strtolower((string) $booking->status), ['ticketed', 'issued'], true)) {
                if ($attempt->fulfilment_status !== PaymentAttempt::FULFILMENT_COMPLETED) {
                    $attempt->update([
                        'fulfilment_status' => PaymentAttempt::FULFILMENT_COMPLETED,
                        'fulfilment_completed_at' => $attempt->fulfilment_completed_at ?? now(),
                        'fulfilment_error' => null,
                    ]);
                }

                return null;
            }

            // Only a durable recovery process may move an uncertain attempt
            // forward. A duplicate webhook/job must never call StartPay again.
            if ($attempt->fulfilment_status !== PaymentAttempt::FULFILMENT_PENDING) {
                return null;
            }

            $attempt->update([
                'fulfilment_status' => PaymentAttempt::FULFILMENT_PROCESSING,
                'fulfilment_attempts' => $attempt->fulfilment_attempts + 1,
                'fulfilment_started_at' => now(),
                'fulfilment_error' => null,
            ]);

            $this->providerBookingEventService->record(
                $booking,
                'at',
                'ticket_payment_started',
                [
                    'payment_attempt_uuid' => $attempt->uuid,
                    'nomod_charge_id' => $attempt->provider_charge_id,
                    'supplier_amount' => $attempt->priceSnapshot?->provider_amount,
                    'supplier_currency' => $attempt->priceSnapshot?->provider_currency,
                ],
                $booking->itinerary_ref,
            );

            return ['attempt' => $attempt->fresh(['priceSnapshot']), 'booking' => $booking->fresh()];
        });
    }

    private function markCompleted(int $paymentAttemptId, array $response): void
    {
        DB::transaction(function () use ($paymentAttemptId, $response): void {
            $attempt = PaymentAttempt::query()
                ->with('booking')
                ->lockForUpdate()
                ->findOrFail($paymentAttemptId);
            $booking = $attempt->booking;

            if (! $booking || $attempt->fulfilment_status !== PaymentAttempt::FULFILMENT_PROCESSING) {
                return;
            }

            $booking->update([
                'status' => 'ticketed',
                'issuance_date' => $booking->issuance_date ?? now(),
            ]);

            $attempt->update([
                'fulfilment_status' => PaymentAttempt::FULFILMENT_COMPLETED,
                'fulfilment_completed_at' => now(),
                'fulfilment_error' => null,
            ]);

            $this->providerBookingEventService->record(
                $booking,
                'at',
                'ticket_payment_completed',
                $response,
                $booking->itinerary_ref,
            );

            $this->bookingNotificationService->ticketed($booking);
        });
    }

    private function markFailed(int $paymentAttemptId, string $error, array $response): void
    {
        $this->markTerminalFailure($paymentAttemptId, $error, PaymentAttempt::FULFILMENT_FAILED, $response);
    }

    private function markUncertain(int $paymentAttemptId, string $error): void
    {
        $this->markTerminalFailure(
            $paymentAttemptId,
            $error,
            PaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
            ['error' => Str::limit($error, 1000)],
        );
    }

    private function markTerminalFailure(int $paymentAttemptId, string $error, string $status, array $eventData): void
    {
        DB::transaction(function () use ($paymentAttemptId, $error, $status, $eventData): void {
            $attempt = PaymentAttempt::query()
                ->with('booking')
                ->lockForUpdate()
                ->findOrFail($paymentAttemptId);
            $booking = $attempt->booking;

            if (! $booking || $attempt->fulfilment_status !== PaymentAttempt::FULFILMENT_PROCESSING) {
                return;
            }

            $attempt->update([
                'fulfilment_status' => $status,
                'fulfilment_error' => Str::limit($error, 4000),
                'fulfilment_failed_at' => now(),
            ]);

            $this->providerBookingEventService->record(
                $booking,
                'at',
                $status === PaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED
                    ? 'ticket_payment_reconciliation_required'
                    : 'ticket_payment_failed',
                $eventData,
                $booking->itinerary_ref,
            );
        });
    }

    /** @return array<string, mixed> */
    private function reservationFrom(FlightBookings $booking): array
    {
        try {
            $reservation = json_decode((string) $booking->pnr_response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('The stored AT reservation response is invalid.', previous: $exception);
        }

        if (! is_array($reservation)) {
            throw new \RuntimeException('The stored AT reservation response is invalid.');
        }

        return $reservation;
    }

    private function supplierNetAmount(PaymentAttempt $attempt): int
    {
        $amount = $attempt->priceSnapshot?->provider_amount;
        if (! is_numeric($amount) || (float) $amount <= 0) {
            throw new \RuntimeException('The locked supplier amount is invalid.');
        }

        // AT's current StartPay contract accepts an integer net amount. Refuse
        // an unsafe truncation instead of settling less than the locked amount.
        if (floor((float) $amount) !== (float) $amount) {
            throw new \RuntimeException('The locked supplier amount is not compatible with AT StartPay.');
        }

        return (int) $amount;
    }

    private function bookingType(FlightBookings $booking): string
    {
        $flightData = json_decode((string) $booking->flight_data, true) ?: [];
        $flight = data_get($flightData, 'original.leg.flights.0')
            ?? data_get($flightData, 'leg.flights.0');

        return data_get($flight, 'hold_info') === null ? 'HP' : 'HB';
    }
}
