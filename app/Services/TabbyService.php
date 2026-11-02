<?php

namespace App\Services;

use App\Exceptions\TabbyCheckoutException;
use App\Models\CustomerSetting;
use App\Models\HotelPaymentAttempt;
use App\Models\PaymentAttempt;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tabby Pay-in-4 API client shared by flight and hotel payments.
 *
 * Flow: create a checkout session (also used as the eligibility check), send
 * the customer to web_url, verify the payment through the API (never through
 * redirect parameters), then capture AUTHORIZED payments.
 */
class TabbyService
{
    public const SESSION_CREATED = 'created';

    public const SESSION_REJECTED = 'rejected';

    public const PAYMENT_AUTHORIZED = 'AUTHORIZED';

    public const PAYMENT_CLOSED = 'CLOSED';

    public const PAYMENT_REJECTED = 'REJECTED';

    public const PAYMENT_EXPIRED = 'EXPIRED';

    public function __construct(private readonly CurrencyConversionService $currencyConversion) {}

    /**
     * Calculate the immutable Tabby customer charge. Fees are applied in the
     * booking currency (same as Nomod) and the total is then converted to the
     * Tabby settlement currency.
     *
     * @return array<string, string>
     */
    public function checkoutMoney(mixed $baseAmount, string $currency): array
    {
        $currency = strtoupper($currency);
        $baseAmount = $this->decimalAmount($baseAmount);
        $settings = CustomerSetting::query()->first();
        $percentageRate = $this->decimalString($settings?->tabby_percentage_charge ?? '0');
        $fixedFee = $this->decimalString($settings?->tabby_fixed_charge ?? '0');
        $percentageFee = bcdiv(bcmul($baseAmount, $percentageRate, 8), '100', 8);
        $feeAmount = bcadd($percentageFee, $fixedFee, 8);
        $amount = $this->decimalAmount(bcadd($baseAmount, $feeAmount, 8));

        $providerCurrency = $this->providerCurrency();
        $providerMoney = $this->currencyConversion->convertMoney($amount, $currency, $providerCurrency);
        $exchangeRate = bcdiv(
            $this->currencyConversion->rateToBase($currency),
            $this->currencyConversion->rateToBase($providerCurrency),
            12,
        );

        return [
            'amount' => $amount,
            'currency' => $currency,
            'base_amount' => $baseAmount,
            'percentage_fee' => $percentageFee,
            'fixed_fee' => $fixedFee,
            'fee_amount' => $feeAmount,
            'percentage_rate' => $percentageRate,
            'provider_amount' => $this->decimalAmount($providerMoney['amount']),
            'provider_currency' => $providerCurrency,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * Create a Tabby checkout session. A "rejected" status is a normal result
     * (customer not eligible), not an exception.
     *
     * POST is not retried: a timed-out request may still have created a session.
     *
     * @param  array{name: string, email: string, phone: string, dob?: string|null, registered_since?: string|null}  $buyer
     * @param  list<array{title: string, quantity: int, unit_price: string, reference_id: string, category: string, description?: string}>  $items
     * @return array{session_id: string, payment_id: ?string, web_url: ?string, status: string, rejection_reason: ?string}
     */
    public function createCheckoutSession(
        PaymentAttempt|HotelPaymentAttempt $attempt,
        array $buyer,
        array $items,
        string $description,
        ?string $returnOrigin = null,
        array $shippingAddress = [],
        string $lang = 'en',
    ): array {
        $amount = $this->decimalAmount($attempt->provider_amount);
        $currency = strtoupper((string) $attempt->provider_currency);

        if ((float) $amount <= 0 || $currency !== $this->providerCurrency()) {
            throw new TabbyCheckoutException('The locked Tabby payment amount or currency is invalid.');
        }

        $payload = [
            'payment' => [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'buyer' => array_filter([
                    'name' => $buyer['name'],
                    'email' => $buyer['email'],
                    'phone' => $this->normalizePhone($buyer['phone']) ?? $buyer['phone'],
                    'dob' => $buyer['dob'] ?? null,
                ]),
                'shipping_address' => [
                    'city' => $shippingAddress['city'] ?? 'N/A',
                    'address' => $shippingAddress['address'] ?? 'N/A',
                    'zip' => $shippingAddress['zip'] ?? '00000',
                ],
                'order' => [
                    'reference_id' => $attempt->reference_id,
                    'tax_amount' => '0.00',
                    'shipping_amount' => '0.00',
                    'discount_amount' => '0.00',
                    'updated_at' => now()->toIso8601String(),
                    'items' => $items,
                ],
                'buyer_history' => [
                    'registered_since' => $buyer['registered_since'] ?? now()->toIso8601String(),
                    'loyalty_level' => 0,
                ],
                'order_history' => [],
                'meta' => [
                    'order_id' => $attempt->reference_id,
                    'customer' => $attempt->uuid,
                ],
            ],
            'lang' => $lang,
            'merchant_code' => $this->merchantCode(),
            'merchant_urls' => [
                'success' => $this->redirectUrl('success', $attempt, $returnOrigin),
                'cancel' => $this->redirectUrl('cancelled', $attempt, $returnOrigin),
                'failure' => $this->redirectUrl('failure', $attempt, $returnOrigin),
            ],
        ];

        $response = $this->client()->post('api/v2/checkout', $payload);

        if (! $response->successful()) {
            $this->logFailure('Tabby checkout API rejected the request.', $response, [
                'payment_attempt_uuid' => $attempt->uuid,
                'reference_id' => $attempt->reference_id,
                'amount' => $amount,
                'currency' => $currency,
            ]);

            throw new TabbyCheckoutException(sprintf(
                'Tabby checkout creation failed with HTTP status %d.',
                $response->status(),
            ));
        }

        $session = $response->json();
        $sessionId = data_get($session, 'id');
        $status = (string) data_get($session, 'status', '');

        if (! is_string($sessionId) || $sessionId === '' || $status === '') {
            throw new TabbyCheckoutException('Tabby returned an invalid checkout response.');
        }

        $webUrl = data_get($session, 'configuration.available_products.installments.0.web_url');

        if ($status === self::SESSION_CREATED && (! is_string($webUrl) || ! filter_var($webUrl, FILTER_VALIDATE_URL))) {
            throw new TabbyCheckoutException('Tabby did not return a checkout URL.');
        }

        return [
            'session_id' => $sessionId,
            'payment_id' => data_get($session, 'payment.id'),
            'web_url' => $status === self::SESSION_CREATED ? $webUrl : null,
            'status' => $status,
            'rejection_reason' => data_get($session, 'configuration.products.installments.rejection_reason'),
        ];
    }

    /**
     * Fetch the authoritative payment state from Tabby.
     *
     * @return array<string, mixed>
     */
    public function retrievePayment(string $paymentId): array
    {
        $response = $this->client()->get('api/v2/payments/'.rawurlencode($paymentId));

        if (! $response->successful() || ! is_array($response->json())) {
            $this->logFailure('Tabby payment retrieval failed.', $response, ['payment_id' => $paymentId]);

            throw new TabbyCheckoutException(sprintf(
                'Tabby payment retrieval failed with HTTP status %d.',
                $response->status(),
            ));
        }

        return $response->json();
    }

    /**
     * Capture an AUTHORIZED payment. Tabby uses the capture reference_id for
     * idempotency, so retrying with the same reference is safe.
     *
     * @return array<string, mixed> The updated payment object.
     */
    public function capturePayment(string $paymentId, mixed $amount, string $referenceId): array
    {
        $response = $this->client()->post('api/v2/payments/'.rawurlencode($paymentId).'/captures', [
            'amount' => $this->decimalAmount($amount),
            'reference_id' => $referenceId,
            'tax_amount' => '0.00',
            'shipping_amount' => '0.00',
            'discount_amount' => '0.00',
            'created_at' => now()->toIso8601String(),
        ]);

        if (! $response->successful() || ! is_array($response->json())) {
            $this->logFailure('Tabby payment capture failed.', $response, [
                'payment_id' => $paymentId,
                'reference_id' => $referenceId,
            ]);

            throw new TabbyCheckoutException(sprintf(
                'Tabby payment capture failed with HTTP status %d.',
                $response->status(),
            ));
        }

        return $response->json();
    }

    /**
     * Register a webhook endpoint for the current key (test or live). Tabby
     * sends the configured header back with every webhook for verification.
     *
     * @return array<string, mixed>
     */
    public function registerWebhook(string $url): array
    {
        if (! $this->isHttpsUrl($url)) {
            throw new TabbyCheckoutException('The Tabby webhook URL must be HTTPS.');
        }

        $response = $this->webhookClient()->post('api/v1/webhooks', [
            'url' => $url,
            'is_test' => $this->isTestMode(),
            'header' => $this->webhookHeader(),
        ]);

        return $this->webhookResponse($response, 'registration');
    }

    /** @return array<string, mixed> */
    public function updateWebhook(string $webhookId, string $url): array
    {
        if (! $this->isHttpsUrl($url)) {
            throw new TabbyCheckoutException('The Tabby webhook URL must be HTTPS.');
        }

        $response = $this->webhookClient()->put('api/v1/webhooks/'.rawurlencode($webhookId), [
            'url' => $url,
            'is_test' => $this->isTestMode(),
            'header' => $this->webhookHeader(),
        ]);

        return $this->webhookResponse($response, 'update');
    }

    /** @return array<int, array<string, mixed>> */
    public function listWebhooks(): array
    {
        return $this->webhookResponse($this->webhookClient()->get('api/v1/webhooks'), 'listing');
    }

    /** Whether Tabby may be offered for this customer mobile number. */
    public function isEligiblePhone(?string $phone): bool
    {
        return $this->normalizePhone($phone) !== null;
    }

    /**
     * Normalise to +<code><number> when the number has an allowed country code
     * ("+971 50 123 4567", "00971501234567" and "971501234567" all match).
     */
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $digits = str_starts_with($digits, '00') ? substr($digits, 2) : $digits;

        foreach ((array) config('services.tabby.allowed_phone_codes', []) as $code) {
            if ($code !== '' && str_starts_with($digits, $code) && strlen($digits) - strlen($code) >= 8) {
                return '+'.$digits;
            }
        }

        return null;
    }

    public function providerCurrency(): string
    {
        return strtoupper((string) config('services.tabby.currency', 'AED'));
    }

    public function isTestMode(): bool
    {
        return str_starts_with($this->secretKey(), 'sk_test_');
    }

    private function client(): PendingRequest
    {
        $baseUrl = (string) config('services.tabby.base_url');

        if (! $this->isHttpsUrl($baseUrl)) {
            throw new TabbyCheckoutException('Tabby is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken($this->secretKey())
            ->connectTimeout(5)
            ->timeout((int) config('services.tabby.timeout', 15));
    }

    private function webhookClient(): PendingRequest
    {
        return $this->client()->withHeaders(['X-Merchant-Code' => $this->merchantCode()]);
    }

    /** @return array{title: string, value: string} */
    private function webhookHeader(): array
    {
        $secret = (string) config('services.tabby.webhook_secret');

        if ($secret === '') {
            throw new TabbyCheckoutException('Tabby webhook secret is not configured.');
        }

        return [
            'title' => (string) config('services.tabby.webhook_header', 'X-Tabby-Webhook-Secret'),
            'value' => $secret,
        ];
    }

    private function webhookResponse(Response $response, string $action): array
    {
        if (! $response->successful() || ! is_array($response->json())) {
            $this->logFailure("Tabby webhook {$action} failed.", $response);

            throw new TabbyCheckoutException(sprintf(
                'Tabby webhook %s failed with HTTP status %d.',
                $action,
                $response->status(),
            ));
        }

        return $response->json();
    }

    private function secretKey(): string
    {
        $key = (string) config('services.tabby.secret_key');

        if ($key === '') {
            throw new TabbyCheckoutException('Tabby is not configured.');
        }

        return $key;
    }

    private function merchantCode(): string
    {
        $code = (string) config('services.tabby.merchant_code');

        if ($code === '') {
            throw new TabbyCheckoutException('Tabby merchant code is not configured.');
        }

        return $code;
    }

    private function redirectUrl(
        string $type,
        PaymentAttempt|HotelPaymentAttempt $attempt,
        ?string $returnOrigin = null,
    ): string {
        $url = $returnOrigin
            ? rtrim($returnOrigin, '/')."/payment/tabby/{$type}"
            : config("services.tabby.redirects.{$type}");

        if (! is_string($url) || ! $this->isHttpsUrl($url)) {
            throw new TabbyCheckoutException("Tabby {$type} redirect URL is not configured with a valid HTTPS URL.");
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        $query = ['payment_attempt' => $attempt->uuid];

        if ($attempt instanceof HotelPaymentAttempt) {
            $query['payment_type'] = 'hotel';
        }

        // This is an opaque local identifier for the return-page UI only. It is
        // never accepted as proof that a payment succeeded.
        return $url.$separator.http_build_query($query);
    }

    private function logFailure(string $message, Response $response, array $context = []): void
    {
        Log::warning($message, $context + [
            'http_status' => $response->status(),
            'response_body' => mb_substr($response->body(), 0, 4000),
        ]);
    }

    private function decimalAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function decimalString(mixed $amount): string
    {
        $value = (string) $amount;

        return preg_match("/^-?\d+(?:\.\d+)?$/", $value) ? $value : '0';
    }

    private function isHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }
}
