<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\TabbyCheckoutException;
use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelPaymentAttempt;
use App\Services\TabbyPaymentReconciler;
use App\Services\TabbyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class HotelTabbyCheckoutController extends Controller
{
    public function __construct(
        private readonly TabbyService $tabbyService,
        private readonly TabbyPaymentReconciler $reconciler,
    ) {}

    /**
     * Create or safely reuse a hotel Tabby checkout. No TBO booking is
     * performed here; only a verified, captured payment starts fulfilment.
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

                if (! $this->tabbyService->isEligiblePhone($booking->phone_number)) {
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'Tabby is only available for UAE and Saudi Arabia mobile numbers.',
                    ]);
                }
                $snapshot = $booking->priceSnapshot()->first();
                if (! $snapshot || bccomp((string) $snapshot->selling_amount, '0', 8) <= 0) {
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'This hotel reservation has no valid locked price snapshot.',
                    ]);
                }

                if (HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('status', HotelPaymentAttempt::STATUS_PAID)
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'A successful payment already exists for this hotel reservation.',
                    ]);
                }

                $existing = HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('provider', HotelPaymentAttempt::PROVIDER_TABBY)
                    ->where('status', HotelPaymentAttempt::STATUS_CREATED)
                    ->whereNotNull('checkout_url')
                    ->where('return_origin', $returnOrigin)
                    ->latest('id')
                    ->first();
                if ($existing) {
                    return [$existing, true];
                }

                $inProgress = HotelPaymentAttempt::query()
                    ->where('hotel_booking_id', $booking->id)
                    ->where('provider', HotelPaymentAttempt::PROVIDER_TABBY)
                    ->where('status', HotelPaymentAttempt::STATUS_INITIATING)
                    ->latest('id')
                    ->first();
                if ($inProgress && $inProgress->created_at->gt(now()->subMinutes(2))) {
                    throw ValidationException::withMessages([
                        'hotel_booking_id' => 'A Tabby checkout is already being created. Please wait and try again.',
                    ]);
                }

                // An abandoned Tabby session cannot be paid without its web_url,
                // and any authorisation is reconciled through its payment ID.
                if ($inProgress) {
                    $inProgress->update(['status' => HotelPaymentAttempt::STATUS_CREATION_FAILED]);
                }

                $money = $this->tabbyService->checkoutMoney($snapshot->selling_amount, (string) $snapshot->selling_currency);

                return [HotelPaymentAttempt::create([
                    'uuid' => (string) Str::uuid(),
                    'hotel_booking_id' => $booking->id,
                    'hotel_booking_price_snapshot_id' => $snapshot->id,
                    'provider' => HotelPaymentAttempt::PROVIDER_TABBY,
                    'reference_id' => sprintf('JETZE-HOTEL-%d-%s', $booking->id, Str::upper(Str::random(12))),
                    'status' => HotelPaymentAttempt::STATUS_INITIATING,
                    'fulfilment_status' => HotelPaymentAttempt::FULFILMENT_PENDING,
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

            $booking = HotelBooking::with(['guests', 'prebook'])->findOrFail($attempt->hotel_booking_id);
            if ($booking->status !== 'payment_pending' || $booking->prebook?->expires_at?->isPast()) {
                $attempt->update([
                    'status' => HotelPaymentAttempt::STATUS_CREATION_FAILED,
                    'failed_at' => now(),
                ]);

                return response()->json([
                    'message' => 'This hotel reservation expired before checkout could be created.',
                ], 422);
            }

            $leadGuest = $booking->guests
                ->sortBy(fn ($guest) => sprintf('%03d-%03d', $guest->room_index, $guest->guest_index))
                ->first();
            $buyerName = trim(($leadGuest?->first_name ?: 'Customer').' '.($leadGuest?->last_name ?: ''));
            $title = 'Hotel '.($booking->prebook?->hotel_code ?: 'hotel').' reservation';

            try {
                $session = $this->tabbyService->createCheckoutSession(
                    $attempt,
                    [
                        'name' => $buyerName,
                        'email' => (string) $booking->email,
                        'phone' => (string) $booking->phone_number,
                        'registered_since' => $request->user()->created_at?->toIso8601String(),
                    ],
                    [[
                        'title' => $title,
                        'quantity' => 1,
                        'unit_price' => number_format((float) $attempt->provider_amount, 2, '.', ''),
                        'reference_id' => 'hotel-booking-'.$booking->uuid,
                        'category' => 'Travel',
                    ]],
                    $title,
                    $attempt->return_origin,
                );
            } catch (Throwable $exception) {
                $attempt->update([
                    'status' => HotelPaymentAttempt::STATUS_CREATION_FAILED,
                    'failed_at' => now(),
                ]);

                Log::warning('Tabby hotel checkout creation failed.', [
                    'payment_attempt_uuid' => $attempt->uuid,
                    'hotel_booking_uuid' => $booking->uuid,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Unable to start Tabby checkout. Please try again or use another payment method.',
                ], 502);
            }

            if ($session['status'] !== TabbyService::SESSION_CREATED) {
                $attempt->update([
                    'status' => HotelPaymentAttempt::STATUS_CREATION_FAILED,
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
                'status' => HotelPaymentAttempt::STATUS_CREATED,
                'provider_checkout_id' => $session['session_id'],
                'provider_charge_id' => $session['payment_id'],
                'checkout_url' => $session['web_url'],
                'checkout_created_at' => now(),
            ]);

            return response()->json($this->checkoutResponse($attempt->fresh()), 201);
        } finally {
            $paymentLock->release();
        }
    }

    /** Return (and sync from Tabby) only the customer's own payment state. */
    public function show(Request $request, string $paymentAttempt): JsonResponse
    {
        $attempt = HotelPaymentAttempt::query()
            ->with('booking:id,uuid,user_id,status')
            ->where('uuid', $paymentAttempt)
            ->where('provider', HotelPaymentAttempt::PROVIDER_TABBY)
            ->first();

        if (! $attempt
            || ! $attempt->booking
            || (int) $attempt->booking->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'Hotel payment attempt was not found.'], 404);
        }

        try {
            $attempt = $this->reconciler->syncHotel($attempt)->load('booking:id,uuid,user_id,status');
        } catch (TabbyCheckoutException $exception) {
            Log::warning('Tabby hotel payment sync failed.', [
                'payment_attempt_uuid' => $attempt->uuid,
                'message' => $exception->getMessage(),
            ]);
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
            'provider_amount' => $attempt->provider_amount,
            'provider_currency' => $attempt->provider_currency,
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
                'hotel_booking_id' => 'This hotel reservation is not eligible for a new checkout.',
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
                'hotel_booking_id' => 'Tabby checkout is not enabled for this website domain.',
            ]);
        }

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
            'provider_amount' => $attempt->provider_amount,
            'provider_currency' => $attempt->provider_currency,
        ];
    }
}
