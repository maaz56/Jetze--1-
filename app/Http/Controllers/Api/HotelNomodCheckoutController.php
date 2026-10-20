<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\NomodCheckoutException;
use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelPaymentAttempt;
use App\Services\HotelNomodHostedCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class HotelNomodCheckoutController extends Controller
{
    public function __construct(
        private readonly HotelNomodHostedCheckoutService $checkoutService,
    ) {}

    /**
     * Create or safely reuse a hotel Nomod Hosted Checkout. No TBO booking is
     * performed here; only a later verified webhook may start fulfilment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_booking_id' => ['required', 'uuid'],
        ]);
        $paymentLock = Cache::lock('hotel-payment:booking:'.$validated['hotel_booking_id'], 120);
        if (! $paymentLock->get()) {
            return response()->json([
                'message' => 'A payment is already being processed for this hotel reservation.',
            ], 409);
        }

        try {
            $returnOrigin = $this->resolveReturnOrigin($request);

            [$attempt, $reused] = DB::transaction(function () use ($validated, $request, $returnOrigin): array {
                $booking = HotelBooking::query()
                    ->where('uuid', $validated['hotel_booking_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureBookingCanBePaidBy($booking, $request);
                $snapshot = $booking->priceSnapshot()->first();
                if (! $snapshot || bccomp((string) $snapshot->selling_amount, '0', 8) <= 0) {
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'This hotel reservation has no valid locked price snapshot.',
                    ]);
                }

                $checkoutMoney = $this->checkoutService->checkoutMoney($snapshot);

                if (HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                    ->where('status', HotelPaymentAttempt::STATUS_PAID)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'A successful card payment already exists for this hotel reservation.',
                    ]);
                }

                $existing = HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                    ->where('status', HotelPaymentAttempt::STATUS_CREATED)
                    ->whereNotNull('checkout_url')
                    ->where('return_origin', $returnOrigin)
                    ->latest('id')
                    ->first();
                if ($existing) {
                    return [$existing, true];
                }

                $unknown = HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                    ->where('status', HotelPaymentAttempt::STATUS_CREATION_UNKNOWN)
                    ->exists();
                if ($unknown) {
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'A previous checkout result is still being reviewed. Please do not start another payment.',
                    ]);
                }

                $inProgress = HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                    ->where('status', HotelPaymentAttempt::STATUS_INITIATING)
                    ->latest('id')
                    ->first();
                if ($inProgress) {
                    if ($inProgress->created_at->gt(now()->subMinutes(2))) {
                        throw ValidationException::withMessages([
                            'hotel_booking_id' => 'A card checkout is already being created. Please wait and try again.',
                        ]);
                    }

                    // A crashed process could have submitted the provider request.
                    $inProgress->update(['status' => HotelPaymentAttempt::STATUS_CREATION_UNKNOWN]);
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'A previous checkout result is being reviewed. Please do not start another payment.',
                    ]);
                }

                return [HotelPaymentAttempt::create([
                    'uuid' => (string) Str::uuid(),
                    'hotel_booking_id' => $booking->id,
                    'hotel_booking_price_snapshot_id' => $snapshot->id,
                    'provider' => HotelPaymentAttempt::PROVIDER_NOMOD,
                    'reference_id' => sprintf('JETZE-HOTEL-%d-%s', $booking->id, Str::upper(Str::random(12))),
                    'status' => HotelPaymentAttempt::STATUS_INITIATING,
                    'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_PENDING,
                    'amount' => $checkoutMoney['amount'],
                    'base_amount' => $checkoutMoney['base_amount'],
                    'percentage_fee' => $checkoutMoney['percentage_fee'],
                    'fixed_fee' => $checkoutMoney['fixed_fee'],
                    'fee_amount' => $checkoutMoney['fee_amount'],
                    'currency' => $checkoutMoney['currency'],
                    'return_origin' => $returnOrigin,
                ]), false];
            });

            if ($reused) {
                return response()->json($this->checkoutResponse($attempt));
            }

            $booking = HotelBooking::with(['guests', 'prebook', 'priceSnapshot'])
                ->findOrFail($attempt->hotel_booking_id);
            if ($booking->status !== 'payment_pending' || $booking->prebook?->expires_at?->isPast()) {
                $attempt->update([
                    'status' => HotelPaymentAttempt::STATUS_CREATION_FAILED,
                    'failed_at' => now(),
                ]);

                return response()->json([
                    'message' => 'This hotel reservation expired before checkout could be created.',
                ], 422);
            }

            try {
                $checkout = $this->checkoutService->createCheckout(
                    $attempt,
                    $booking,
                    $booking->priceSnapshot,
                    $attempt->return_origin,
                );
            } catch (Throwable $exception) {
                $safeFailure = $exception instanceof NomodCheckoutException
                    && ($exception->getMessage() === 'Nomod Hosted Checkout is not configured.'
                        || str_starts_with($exception->getMessage(), 'Card payment is temporarily unavailable'));
                $attempt->update([
                    'status' => $safeFailure
                        ? HotelPaymentAttempt::STATUS_CREATION_FAILED
                        : HotelPaymentAttempt::STATUS_CREATION_UNKNOWN,
                    'failed_at' => $safeFailure ? now() : null,
                ]);

                Log::warning('Nomod hotel Hosted Checkout creation failed.', [
                    'payment_attempt_uuid' => $attempt->uuid,
                    'hotel_booking_uuid' => $booking->uuid,
                    'status' => $attempt->status,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $safeMessage = $safeFailure
                    ? $exception->getMessage()
                    : 'The checkout result is unknown. Please do not try another card payment while it is reviewed.';

                return response()->json(['message' => $safeMessage], 502);
            }

            $attempt->update([
                'status' => HotelPaymentAttempt::STATUS_CREATED,
                'provider_checkout_id' => $checkout['id'],
                'checkout_url' => $checkout['url'],
                'checkout_created_at' => now(),
            ]);

            return response()->json($this->checkoutResponse($attempt->fresh()), 201);
        } finally {
            $paymentLock->release();
        }
    }

    /** Return only the authenticated customer's own durable payment state. */
    public function show(Request $request, string $paymentAttempt): JsonResponse
    {
        $attempt = HotelPaymentAttempt::query()
            ->with('booking:id,uuid,user_id,status')
            ->where('uuid', $paymentAttempt)
            ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
            ->first();

        if (! $attempt
            || ! $attempt->booking
            || (int) $attempt->booking->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Hotel payment attempt was not found.'], 404);
        }

        return response()->json([
            'payment_type' => 'hotel',
            'payment_attempt' => $attempt->uuid,
            'booking_id' => $attempt->booking->uuid,
            'booking_status' => $attempt->booking->status,
            'status' => $attempt->status,
            'fulfilment_status' => $attempt->fulfilment_status,
            'fulfilment_error' => $attempt->fulfilment_error,
            'amount' => $attempt->amount,
            'base_amount' => $attempt->base_amount,
            'fee_amount' => $attempt->fee_amount,
            'currency' => $attempt->currency,
            'paid_at' => $attempt->paid_at?->toIso8601String(),
            'created_at' => $attempt->created_at?->toIso8601String(),
        ]);
    }

    private function ensureBookingCanBePaidBy(HotelBooking $booking, Request $request): void
    {
        if (! $booking->user_id || (int) $booking->user_id !== (int) $request->user()->id) {
            abort(403, 'You cannot pay for this hotel reservation.');
        }

        if ($booking->status !== 'payment_pending') {
            throw ValidationException::withMessages([
                'hotel_booking_id' => 'This hotel reservation is not eligible for a new card checkout.',
            ]);
        }

        $booking->loadMissing('prebook');
        if ($booking->prebook?->expires_at?->isPast()) {
            $booking->update(['status' => 'expired']);
            throw ValidationException::withMessages([
                'hotel_booking_id' => 'This hotel reservation has expired. Please search again.',
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
                'hotel_booking_id' => 'Nomod checkout is not enabled for this website domain.',
            ]);
        }

        // Local HTTP requests cannot be valid Nomod return targets. Passing
        // null makes the checkout use the existing HTTPS NOMOD_*_URL values,
        // exactly as configured for the flight checkout integration.
        if (! $request->isSecure()) {
            return null;
        }

        return 'https://'.$host;
    }

    private function checkoutResponse(HotelPaymentAttempt $attempt): array
    {
        return [
            'payment_attempt' => $attempt->uuid,
            'checkout_url' => $attempt->checkout_url,
            'status' => $attempt->status,
            'amount' => $attempt->amount,
            'base_amount' => $attempt->base_amount,
            'percentage_fee' => $attempt->percentage_fee,
            'fixed_fee' => $attempt->fixed_fee,
            'fee_amount' => $attempt->fee_amount,
            'currency' => $attempt->currency,
        ];
    }
}
