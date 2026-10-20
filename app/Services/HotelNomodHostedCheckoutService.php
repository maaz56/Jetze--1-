<?php

namespace App\Services;

use App\Exceptions\NomodCheckoutException;
use App\Models\CustomerSetting;
use App\Models\HotelBooking;
use App\Models\HotelBookingPriceSnapshot;
use App\Models\HotelPaymentAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HotelNomodHostedCheckoutService
{
    /**
     * Create a hotel Hosted Checkout without retrying the provider POST.
     * A transport timeout is treated as an unknown result by the controller.
     *
     * @return array{id: string, url: string, status: string}
     */
    public function createCheckout(
        HotelPaymentAttempt $attempt,
        HotelBooking $booking,
        HotelBookingPriceSnapshot $snapshot,
        ?string $returnOrigin = null,
    ): array {
        $apiKey = config('services.nomod.api_key');
        $baseUrl = (string) config('services.nomod.base_url');

        if (! is_string($apiKey) || $apiKey === '' || ! $this->isHttpsUrl($baseUrl)) {
            throw new NomodCheckoutException('Nomod Hosted Checkout is not configured.');
        }

        if ((int) $attempt->hotel_booking_id !== (int) $booking->id
            || (int) $attempt->hotel_booking_price_snapshot_id !== (int) $snapshot->id) {
            throw new NomodCheckoutException('The locked hotel payment data does not match the reservation.');
        }

        $amount = $this->decimalAmount($attempt->amount);
        $currency = strtoupper((string) $attempt->currency);
        if ((float) $amount <= 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new NomodCheckoutException('The locked hotel payment amount or currency is invalid.');
        }

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-API-KEY' => $apiKey])
            ->connectTimeout(5)
            ->timeout((int) config('services.nomod.timeout', 15))
            ->post(
                $this->checkoutPath($baseUrl),
                $this->payload($attempt, $booking, $amount, $currency, $returnOrigin),
            );

        if (! $response->successful()) {
            Log::warning('Nomod hotel Hosted Checkout API rejected the request.', [
                'http_status' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 4000),
                'payment_attempt_uuid' => $attempt->uuid,
                'hotel_booking_uuid' => $booking->uuid,
                'amount' => $amount,
                'currency' => $currency,
                'reference_id' => $attempt->reference_id,
                'return_origin' => $returnOrigin,
            ]);

            if ($response->status() === 400
                && data_get($response->json(), 'error.code') === 'checkout_invalid_currency') {
                throw new NomodCheckoutException(
                    'Card payment is temporarily unavailable for this currency. Please use wallet payment or another supported currency.'
                );
            }

            throw new NomodCheckoutException(sprintf(
                'Nomod hotel checkout creation returned HTTP status %d.',
                $response->status(),
            ));
        }

        $checkout = $response->json();
        $id = is_array($checkout) ? ($checkout['id'] ?? null) : null;
        $url = is_array($checkout) ? ($checkout['url'] ?? null) : null;

        if (! is_string($id) || $id === '' || ! is_string($url) || ! $this->isHttpsUrl($url)) {
            throw new NomodCheckoutException('Nomod returned an invalid hotel checkout response.');
        }

        return [
            'id' => $id,
            'url' => $url,
            'status' => is_string($checkout['status'] ?? null)
                ? $checkout['status']
                : HotelPaymentAttempt::STATUS_CREATED,
        ];
    }

    /** Calculate and freeze the customer charge from the hotel selling price. */
    public function checkoutMoney(HotelBookingPriceSnapshot $snapshot): array
    {
        $baseAmount = $this->decimalAmount($snapshot->selling_amount);
        $settings = CustomerSetting::query()->first();
        $percentageRate = $this->decimalString($settings?->nomod_percentage_charge ?? '2.5000');
        $fixedFee = $this->decimalString($settings?->nomod_fixed_charge ?? '0');
        $percentageFee = bcdiv(bcmul($baseAmount, $percentageRate, 8), '100', 8);
        $feeAmount = bcadd($percentageFee, $fixedFee, 8);

        return [
            'amount' => $this->decimalAmount(bcadd($baseAmount, $feeAmount, 8)),
            'currency' => strtoupper((string) $snapshot->selling_currency),
            'base_amount' => $baseAmount,
            'percentage_fee' => $percentageFee,
            'fixed_fee' => $fixedFee,
            'fee_amount' => $feeAmount,
            'percentage_rate' => $percentageRate,
        ];
    }

    /** @return array<string, mixed> */
    public function retrieveCheckout(HotelPaymentAttempt $attempt): array
    {
        $apiKey = config('services.nomod.api_key');
        $baseUrl = (string) config('services.nomod.base_url');

        if (! is_string($apiKey) || $apiKey === '' || ! $this->isHttpsUrl($baseUrl) || ! $attempt->provider_checkout_id) {
            throw new NomodCheckoutException('Nomod hotel checkout retrieval is not configured.');
        }

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(['X-API-KEY' => $apiKey])
            ->connectTimeout(5)
            ->timeout((int) config('services.nomod.timeout', 15))
            ->get($this->checkoutPath($baseUrl).'/'.rawurlencode($attempt->provider_checkout_id));

        if (! $response->successful() || ! is_array($response->json())) {
            throw new NomodCheckoutException(sprintf(
                'Nomod hotel checkout retrieval failed with HTTP status %d.',
                $response->status(),
            ));
        }

        return $response->json();
    }

    /** @return array<string, mixed> */
    private function payload(
        HotelPaymentAttempt $attempt,
        HotelBooking $booking,
        string $amount,
        string $currency,
        ?string $returnOrigin,
    ): array {
        $leadGuest = $booking->guests
            ->sortBy(fn ($guest) => sprintf('%03d-%03d', $guest->room_index, $guest->guest_index))
            ->first();
        $firstName = trim((string) ($leadGuest?->first_name ?: 'Customer'));
        $lastName = trim((string) ($leadGuest?->last_name ?: ''));
        $hotelCode = (string) ($booking->prebook?->hotel_code ?: 'hotel');

        return [
            'reference_id' => $attempt->reference_id,
            'amount' => $amount,
            'currency' => $currency,
            'items' => [[
                'item_id' => 'hotel-booking-'.$booking->uuid,
                'name' => 'Hotel '.$hotelCode.' reservation',
                'quantity' => 1,
                'unit_amount' => $amount,
                'discount_type' => 'flat',
                'discount_amount' => '0.00',
                'total_amount' => $amount,
                'net_amount' => $amount,
            ]],
            'discount' => '0.00',
            'customer' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => (string) $booking->email,
                'phone_number' => (string) $booking->phone_number,
            ],
            'success_url' => $this->redirectUrl('success', $attempt, $returnOrigin),
            'failure_url' => $this->redirectUrl('failure', $attempt, $returnOrigin),
            'cancelled_url' => $this->redirectUrl('cancelled', $attempt, $returnOrigin),
            'metadata' => [
                'payment_type' => 'hotel',
                'hotel_payment_attempt' => $attempt->uuid,
                'hotel_booking_id' => $booking->uuid,
                'base_amount' => (string) $attempt->base_amount,
                'percentage_fee' => (string) $attempt->percentage_fee,
                'fixed_fee' => (string) $attempt->fixed_fee,
                'fee_amount' => (string) $attempt->fee_amount,
            ],
        ];
    }

    private function redirectUrl(
        string $type,
        HotelPaymentAttempt $attempt,
        ?string $returnOrigin = null,
    ): string {
        $url = $returnOrigin
            ? rtrim($returnOrigin, '/')."/payment/nomod/{$type}"
            : config("services.nomod.redirects.{$type}");

        if (! is_string($url) || ! $this->isHttpsUrl($url)) {
            throw new NomodCheckoutException("Nomod {$type} redirect URL is not configured with a valid HTTPS URL.");
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([
            'payment_attempt' => $attempt->uuid,
            'payment_type' => 'hotel',
        ]);
    }

    private function decimalAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function decimalString(mixed $amount): string
    {
        $value = (string) $amount;

        return preg_match('/^-?\d+(?:\.\d+)?$/', $value) ? $value : '0';
    }

    private function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private function checkoutPath(string $baseUrl): string
    {
        $basePath = rtrim((string) parse_url($baseUrl, PHP_URL_PATH), '/');

        return str_ends_with($basePath, '/v1') ? 'checkout' : 'v1/checkout';
    }
}
