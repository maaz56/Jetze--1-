<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FlightBookings;
use App\Models\PaymentAttempt;
use App\Services\BookingPricingService;
use App\Services\NomodHostedCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class NomodCheckoutController extends Controller
{
    public function __construct(
        private readonly BookingPricingService $bookingPricingService,
        private readonly NomodHostedCheckoutService $nomodHostedCheckoutService,
    ) {}

    /**
     * Create (or safely reuse) a Nomod Hosted Checkout session for a B2C flight
     * booking. The browser supplies an ID only; all money comes from the locked
     * server-side snapshot.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'integer'],
        ]);

        [$attempt, $reused] = DB::transaction(function () use ($validated, $request): array {
            $booking = FlightBookings::query()
                ->whereKey($validated['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureBookingCanBePaidBy($booking, $request);

            $snapshot = $booking->priceSnapshot()->first();
            if (! $snapshot) {
                throw ValidationException::withMessages([
                    'booking_id' => 'This booking has no locked price snapshot and cannot be paid securely.',
                ]);
            }

            $checkoutMoney = $this->nomodHostedCheckoutService->checkoutMoney($snapshot);

            if (PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                ->where('status', PaymentAttempt::STATUS_PAID)
                ->exists()) {
                throw ValidationException::withMessages([
                    'booking_id' => 'A successful Nomod payment already exists for this booking. Ticket confirmation is being processed.',
                ]);
            }

            $existing = PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                ->where('status', PaymentAttempt::STATUS_CREATED)
                ->whereNotNull('checkout_url')
                ->latest('id')
                ->first();

            if ($existing) {
                return [$existing, true];
            }

            $inProgress = PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
                ->where('status', PaymentAttempt::STATUS_INITIATING)
                ->latest('id')
                ->first();

            if ($inProgress && $inProgress->created_at->gt(now()->subMinutes(2))) {
                throw ValidationException::withMessages([
                    'booking_id' => 'A payment checkout is already being created. Please wait a moment and try again.',
                ]);
            }

            if ($inProgress) {
                $inProgress->update(['status' => PaymentAttempt::STATUS_CREATION_FAILED]);
            }

            return [PaymentAttempt::create([
                'uuid' => (string) Str::uuid(),
                'booking_id' => $booking->id,
                'price_snapshot_id' => $snapshot->id,
                'provider' => PaymentAttempt::PROVIDER_NOMOD,
                'reference_id' => sprintf('JETZE-FLIGHT-%d-%s', $booking->id, Str::upper(Str::random(12))),
                'status' => PaymentAttempt::STATUS_INITIATING,
                'amount' => $checkoutMoney['amount'],
                'currency' => $checkoutMoney['currency'],
            ]), false];
        });

        if ($reused) {
            return response()->json($this->checkoutResponse($attempt));
        }

        $booking = FlightBookings::with('pessangers')->findOrFail($attempt->booking_id);
        $snapshot = $this->bookingPricingService->snapshotFor($booking);

        try {
            $checkout = $this->nomodHostedCheckoutService->createCheckout($attempt, $booking, $snapshot);
        } catch (Throwable $exception) {
            $attempt->update(['status' => PaymentAttempt::STATUS_CREATION_FAILED]);

            Log::warning('Nomod Hosted Checkout creation failed.', [
                'payment_attempt_uuid' => $attempt->uuid,
                'booking_id' => $attempt->booking_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to create the payment checkout. Please try again.',
            ], 502);
        }

        $attempt->update([
            'status' => PaymentAttempt::STATUS_CREATED,
            'provider_checkout_id' => $checkout['id'],
            'checkout_url' => $checkout['url'],
            'checkout_created_at' => now(),
        ]);

        return response()->json($this->checkoutResponse($attempt->fresh()), 201);
    }

    /**
     * Return the local payment-attempt state for the post-checkout UI. This is
     * deliberately read-only: a browser redirect cannot change payment state.
     */
    public function show(Request $request, string $paymentAttempt): JsonResponse
    {
        $attempt = PaymentAttempt::query()
            ->with('booking')
            ->where('uuid', $paymentAttempt)
            ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
            ->firstOrFail();

        if ((int) $attempt->booking->agent_id !== (int) $request->user()->id) {
            abort(404);
        }

        return response()->json([
            'payment_attempt' => $attempt->uuid,
            'booking_id' => $attempt->booking_id,
            'status' => $attempt->status,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'created_at' => $attempt->created_at,
            'paid_at' => $attempt->paid_at,
            'fulfilment_status' => $attempt->fulfilment_status,
            'fulfilment_error' => $attempt->fulfilment_error,
            'fulfilment_completed_at' => $attempt->fulfilment_completed_at,
        ]);
    }

    private function ensureBookingCanBePaidBy(FlightBookings $booking, Request $request): void
    {
        if ((int) $booking->agent_id !== (int) $request->user()->id) {
            abort(403, 'You cannot pay for this booking.');
        }

        if (strtoupper((string) $booking->booking_mode) !== 'B2C') {
            throw ValidationException::withMessages([
                'booking_id' => 'Nomod Hosted Checkout is currently available for B2C flight bookings only.',
            ]);
        }

        if (strtolower((string) $booking->flight_provider) !== 'at') {
            throw ValidationException::withMessages([
                'booking_id' => 'Nomod Hosted Checkout is currently configured for AT B2C bookings only.',
            ]);
        }

        if (in_array(strtolower((string) $booking->status), ['ticketed', 'issued', 'voided', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'This booking is not eligible for a new payment checkout.',
            ]);
        }
    }

    private function checkoutResponse(PaymentAttempt $attempt): array
    {
        return [
            'payment_attempt' => $attempt->uuid,
            'checkout_url' => $attempt->checkout_url,
            'status' => $attempt->status,
        ];
    }
}
