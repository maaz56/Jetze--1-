<?php

namespace App\Services;

use App\Exceptions\NomodCheckoutException;
use App\Models\BookingPriceSnapshot;
use App\Models\CustomerSetting;
use App\Models\FlightBookings;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;

class NomodHostedCheckoutService
{
    /**
     * Create a Nomod Hosted Checkout session.
     *
     * This service deliberately does not retry POST requests. Nomod's hosted
     * checkout create endpoint does not document an idempotency key, so retrying
     * a timed-out POST could create a second payable checkout.
     *
     * @return array{id: string, url: string, status: string}
     */
    public function createCheckout(
        PaymentAttempt $attempt,
        FlightBookings $booking,
        BookingPriceSnapshot $snapshot,
        ?string $returnOrigin = null,
    ): array {
        $apiKey = config('services.nomod.api_key');
        $baseUrl = config('services.nomod.base_url');

        if (! is_string($apiKey) || $apiKey === '' || ! $this->isHttpsUrl($baseUrl)) {
            throw new NomodCheckoutException('Nomod Hosted Checkout is not configured.');
        }

        $amount = $this->decimalAmount($attempt->amount);
        $currency = strtoupper((string) $attempt->currency);

        if ((float) $amount <= 0 || ! preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new NomodCheckoutException('The locked booking payment amount or currency is invalid.');
        }
        
        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['X-API-KEY' => $apiKey])
            ->connectTimeout(5)
            ->timeout((int) config('services.nomod.timeout', 15))
            ->post($this->checkoutPath($baseUrl), $this->payload($attempt, $booking, $amount, $currency, $returnOrigin));

        if (! $response->successful()) {
            throw new NomodCheckoutException(sprintf(
                'Nomod checkout creation failed with HTTP status %d.',
                $response->status(),
            ));
        }

        $checkout = $response->json();
        $id = is_array($checkout) ? ($checkout['id'] ?? null) : null;
        $url = is_array($checkout) ? ($checkout['url'] ?? null) : null;

        if (! is_string($id) || $id === '' || ! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new NomodCheckoutException('Nomod returned an invalid checkout response.');
        }

        return [
            'id' => $id,
            'url' => $url,
            'status' => is_string($checkout['status'] ?? null) ? $checkout['status'] : PaymentAttempt::STATUS_CREATED,
        ];
    }

    /** Calculate the immutable Nomod customer charge from the locked amount. */
    public function checkoutMoney(BookingPriceSnapshot $snapshot): array
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
    public function retrieveCheckout(PaymentAttempt $attempt): array
    {
        $apiKey = config('services.nomod.api_key');
        $baseUrl = config('services.nomod.base_url');

        if (! is_string($apiKey) || $apiKey === '' || ! $this->isHttpsUrl($baseUrl) || ! $attempt->provider_checkout_id) {
            throw new NomodCheckoutException('Nomod checkout retrieval is not configured.');
        }

        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(['X-API-KEY' => $apiKey])
            ->connectTimeout(5)
            ->timeout((int) config('services.nomod.timeout', 15))
            ->get($this->checkoutPath($baseUrl).'/'.rawurlencode($attempt->provider_checkout_id));

        if (! $response->successful() || ! is_array($response->json())) {
            throw new NomodCheckoutException(sprintf('Nomod checkout retrieval failed with HTTP status %d.', $response->status()));
        }

        return $response->json();
    }

    private function payload(
        PaymentAttempt $attempt,
        FlightBookings $booking,
        string $amount,
        string $currency,
        ?string $returnOrigin,
    ): array {
        $passenger = $booking->pessangers()->orderBy('id')->first();
        $firstName = trim((string) ($passenger?->first_name ?: 'Customer'));
        $lastName = trim((string) ($passenger?->last_name ?: ''));

        return [
            'reference_id' => $attempt->reference_id,
            'amount' => $amount,
            'currency' => $currency,
            'items' => [[
                'item_id' => 'flight-booking-'.$booking->id,
                'name' => 'Flight booking #'.$booking->id,
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
                'email' => (string) $booking->main_email,
                'phone_number' => (string) $booking->main_phone,
            ],
            'success_url' => $this->redirectUrl('success', $attempt, $returnOrigin),
            'failure_url' => $this->redirectUrl('failure', $attempt, $returnOrigin),
            'cancelled_url' => $this->redirectUrl('cancelled', $attempt, $returnOrigin),
            'metadata' => [
                'payment_attempt' => $attempt->uuid,
                'booking_id' => (string) $booking->id,
                'base_amount' => (string) $attempt->base_amount,
                'percentage_fee' => (string) $attempt->percentage_fee,
                'fixed_fee' => (string) $attempt->fixed_fee,
                'fee_amount' => (string) $attempt->fee_amount,
            ],
        ];
    }

    private function redirectUrl(string $type, PaymentAttempt $attempt, ?string $returnOrigin = null): string
    {
        $url = $returnOrigin
            ? rtrim($returnOrigin, '/')."/payment/nomod/{$type}"
            : config("services.nomod.redirects.{$type}");

        if (! is_string($url) || ! $this->isHttpsUrl($url)) {
            throw new NomodCheckoutException("Nomod {$type} redirect URL is not configured with a valid HTTPS URL.");
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        // This is an opaque local identifier for the return-page UI only. It is
        // never accepted as proof that a payment succeeded.
        return $url.$separator.http_build_query(['payment_attempt' => $attempt->uuid]);
    }

    private function decimalAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function decimalString(mixed $amount): string
    {
        $value = (string) $amount;
        return preg_match("/^-?\d+(?:\.\d+)?$/", $value) ? $value : "0";
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
