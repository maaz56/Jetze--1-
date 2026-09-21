<?php

use App\Models\PaymentAttempt;
use App\Services\NomodHostedCheckoutService;
use Tests\TestCase;

uses(TestCase::class);

it('builds Nomod return URLs from the checkout origin when provided', function () {
    $attempt = new PaymentAttempt(['uuid' => '11111111-1111-1111-1111-111111111111']);

    $url = invokeNomodRedirectUrl('success', $attempt, 'https://jetze.ae');

    expect($url)->toBe('https://jetze.ae/payment/nomod/success?payment_attempt=11111111-1111-1111-1111-111111111111');
});

it('falls back to configured Nomod return URLs when no checkout origin is provided', function () {
    config()->set('services.nomod.redirects.failure', 'https://jetze.pk/payment/nomod/failure');
    $attempt = new PaymentAttempt(['uuid' => '22222222-2222-2222-2222-222222222222']);

    $url = invokeNomodRedirectUrl('failure', $attempt);

    expect($url)->toBe('https://jetze.pk/payment/nomod/failure?payment_attempt=22222222-2222-2222-2222-222222222222');
});

function invokeNomodRedirectUrl(string $type, PaymentAttempt $attempt, ?string $returnOrigin = null): string
{
    $method = (new ReflectionClass(NomodHostedCheckoutService::class))->getMethod('redirectUrl');
    $method->setAccessible(true);

    return $method->invoke(app(NomodHostedCheckoutService::class), $type, $attempt, $returnOrigin);
}
