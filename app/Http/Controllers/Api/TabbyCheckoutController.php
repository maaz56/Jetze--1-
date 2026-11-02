<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TabbyCheckoutException;
use App\Http\Controllers\Controller;
use App\Models\FlightBookings;
use App\Models\PaymentAttempt;
use App\Services\TabbyPaymentReconciler;
use App\Services\TabbyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TabbyCheckoutController extends Controller
{
    public function __construct(
        private readonly TabbyService $tabbyService,
        private readonly TabbyPaymentReconciler $reconciler,
    ) {}

    /**
     * Create (or safely reuse) a Tabby checkout session for a B2C flight
     * booking. The browser supplies an ID only; all money comes from the
     * locked server-side snapshot and is converted to the Tabby currency.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_id' => ['required', 'integer'],
        ]);
        $returnOrigin = $this->resolveReturnOrigin($request);

        [$attempt, $reused] = DB::transaction(function () use ($validated, $request, $returnOrigin): array {
            $booking = FlightBookings::query()
                ->whereKey($validated['booking_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureBookingCanBePaidBy($booking, $request);

            if (! $this->tabbyService->isEligiblePhone($booking->main_phone)) {
                throw ValidationException::withMessages([
                    'booking_id' => 'Tabby is only available for UAE and Saudi Arabia mobile numbers.',
                ]);
            }

            if ($booking->payment_expires_at && ! $booking->payment_expires_at->isFuture()) {
                throw ValidationException::withMessages([
                    'booking_id' => 'This booking payment window has expired. Please create a new booking.',
                ]);
            }

            $snapshot = $booking->priceSnapshot()->first();
            if (! $snapshot) {
                throw ValidationException::withMessages([
                    'booking_id' => 'This booking has no locked price snapshot and cannot be paid securely.',
                ]);
            }

            if (PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('status', PaymentAttempt::STATUS_PAID)
                ->exists()) {
                throw ValidationException::withMessages([
                    'booking_id' => 'A successful payment already exists for this booking. Ticket confirmation is being processed.',
                ]);
            }

            $existing = PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('provider', PaymentAttempt::PROVIDER_TABBY)
                ->where('status', PaymentAttempt::STATUS_CREATED)
                ->whereNotNull('checkout_url')
                ->where('return_origin', $returnOrigin)
                ->latest('id')
                ->first();

            if ($existing) {
                return [$existing, true];
            }

            $inProgress = PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('provider', PaymentAttempt::PROVIDER_TABBY)
                ->where('status', PaymentAttempt::STATUS_INITIATING)
                ->latest('id')
                ->first();

            if ($inProgress && $inProgress->created_at->gt(now()->subMinutes(2))) {
                throw ValidationException::withMessages([
                    'booking_id' => 'A Tabby checkout is already being created. Please wait a moment and try again.',
                ]);
            }

            if ($inProgress) {
                $inProgress->update(['status' => PaymentAttempt::STATUS_CREATION_FAILED]);
            }

            $money = $this->tabbyService->checkoutMoney($snapshot->selling_amount, (string) $snapshot->selling_currency);

            return [PaymentAttempt::create([
                'uuid' => (string) Str::uuid(),
                'booking_id' => $booking->id,
                'price_snapshot_id' => $snapshot->id,
                'provider' => PaymentAttempt::PROVIDER_TABBY,
                'reference_id' => sprintf('JETZE-FLIGHT-%d-%s', $booking->id, Str::upper(Str::random(12))),
                'status' => PaymentAttempt::STATUS_INITIATING,
                'amount' => $money['amount'],
                'base_amount' => $money['base_amount'],
                'percentage_fee' => $money['percentage_fee'],
                'fixed_fee' => $money['fixed_fee'],
                'fee_amount' => $money['fee_amount'],
                'currency' => $money['currency'],
                'provider_amount' => $money['provider_amount'],
                'provider_currency' => $money['provider_currency'],
                'exchange_rate' => $money['exchange_rate'],
                'return_origin' => $returnOrigin,
            ]), false];
        });

        if ($reused) {
            return response()->json($this->checkoutResponse($attempt));
        }

        $booking = FlightBookings::with('pessangers')->findOrFail($attempt->booking_id);
        $passenger = $booking->pessangers->sortBy('id')->first();
        $buyerName = trim(($passenger?->first_name ?: 'Customer').' '.($passenger?->last_name ?: ''));

        try {
            $session = $this->tabbyService->createCheckoutSession(
                $attempt,
                [
                    'name' => $buyerName,
                    'email' => (string) $booking->main_email,
                    'phone' => (string) $booking->main_phone,
                    'registered_since' => $request->user()->created_at?->toIso8601String(),
                ],
                [[
                    'title' => 'Flight booking #'.$booking->id,
                    'quantity' => 1,
                    'unit_price' => number_format((float) $attempt->provider_amount, 2, '.', ''),
                    'reference_id' => 'flight-booking-'.$booking->id,
                    'category' => 'Travel',
                ]],
                'Flight booking #'.$booking->id,
                $attempt->return_origin,
            );
        } catch (Throwable $exception) {
            $attempt->update(['status' => PaymentAttempt::STATUS_CREATION_FAILED, 'failed_at' => now()]);

            Log::warning('Tabby checkout creation failed.', [
                'payment_attempt_uuid' => $attempt->uuid,
                'booking_id' => $attempt->booking_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to start Tabby checkout. Please try again or use another payment method.',
            ], 502);
        }

        if ($session['status'] !== TabbyService::SESSION_CREATED) {
            $attempt->update([
                'status' => PaymentAttempt::STATUS_CREATION_FAILED,
                'provider_checkout_id' => $session['session_id'],
                'provider_status' => $session['status'],
                'failed_at' => now(),
            ]);

            return response()->json([
                'message' => 'Tabby is not available for this purchase. Please use another payment method.',
                'rejection_reason' => $session['rejection_reason'],
            ], 422);
        }

        $attempt->update([
            'status' => PaymentAttempt::STATUS_CREATED,
            'provider_checkout_id' => $session['session_id'],
            'provider_charge_id' => $session['payment_id'],
            'checkout_url' => $session['web_url'],
            'checkout_created_at' => now(),
        ]);

        return response()->json($this->checkoutResponse($attempt->fresh()), 201);
    }

    /**
     * Return the payment-attempt state for the post-checkout UI. Unlike Nomod,
     * Tabby requires the merchant to verify and capture after the redirect, so
     * an unsettled attempt is synced from the Tabby API (never from the URL).
     */
    public function show(Request $request, string $paymentAttempt): JsonResponse
    {
        $attempt = PaymentAttempt::query()
            ->with('booking')
            ->where('uuid', $paymentAttempt)
            ->where('provider', PaymentAttempt::PROVIDER_TABBY)
            ->firstOrFail();

        if ((int) $attempt->booking->agent_id !== (int) $request->user()->id) {
            abort(404);
        }

        try {
            $attempt = $this->reconciler->syncFlight($attempt)->load('booking');
        } catch (TabbyCheckoutException $exception) {
            // The webhook will retry; the UI keeps polling meanwhile.
            Log::warning('Tabby flight payment sync failed.', [
                'payment_attempt_uuid' => $attempt->uuid,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'payment_attempt' => $attempt->uuid,
            'booking_id' => $attempt->booking_id,
            'flight_provider' => $attempt->booking->flight_provider,
            'status' => $attempt->status,
            'amount' => $attempt->amount,
            'base_amount' => $attempt->base_amount,
            'percentage_fee' => $attempt->percentage_fee,
            'fixed_fee' => $attempt->fixed_fee,
            'fee_amount' => $attempt->fee_amount,
            'currency' => $attempt->currency,
            'provider_amount' => $attempt->provider_amount,
            'provider_currency' => $attempt->provider_currency,
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
                'booking_id' => 'Tabby is currently available for B2C flight bookings only.',
            ]);
        }

        if (! in_array(strtolower((string) $booking->flight_provider), ['at', 'hakimi'], true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'Tabby is currently configured for AT and Hakimi B2C bookings only.',
            ]);
        }

        if (in_array(strtolower((string) $booking->status), ['ticketed', 'issued', 'voided', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'This booking is not eligible for a new payment checkout.',
            ]);
        }
    }

    private function resolveReturnOrigin(Request $request): ?string
    {
        $host = strtolower($request->getHost());
        $allowedHosts = array_values(array_unique(array_filter(array_map(
            static fn (?string $host): string => strtolower(trim((string) $host)),
            [
                ...((array) config('services.nomod.allowed_return_hosts', [])),
                parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
                parse_url((string) config('app.frontend_url'), PHP_URL_HOST) ?: null,
            ],
        ))));

        if (! in_array($host, $allowedHosts, true)) {
            throw ValidationException::withMessages([
                'booking_id' => 'Tabby checkout is not enabled for this website domain.',
            ]);
        }

        // Local HTTP requests cannot be valid return targets. Null makes the
        // session use the HTTPS TABBY_*_URL values instead.
        if (! $request->isSecure()) {
            return null;
        }

        return 'https://'.$host;
    }

    private function checkoutResponse(PaymentAttempt $attempt): array
    {
        return [
            'payment_attempt' => $attempt->uuid,
            'checkout_url' => $attempt->checkout_url,
            'status' => $attempt->status,
            'amount' => $attempt->amount,
            'currency' => $attempt->currency,
            'provider_amount' => $attempt->provider_amount,
            'provider_currency' => $attempt->provider_currency,
        ];
    }
}
