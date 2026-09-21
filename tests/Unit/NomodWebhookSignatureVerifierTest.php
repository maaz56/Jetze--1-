<?php

use App\Exceptions\NomodWebhookSignatureException;
use App\Services\NomodWebhookSignatureVerifier;
use Tests\TestCase;

uses(TestCase::class);

it('accepts a valid Svix v1 signature over the unchanged request body', function () {
    $secretBytes = random_bytes(32);
    config()->set('services.nomod.webhook_secret', 'whsec_'.base64_encode($secretBytes));
    config()->set('services.nomod.webhook_tolerance_seconds', 300);

    $messageId = 'msg_123';
    $timestamp = (string) time();
    $rawBody = '{"type":"charge.completed","eventId":"evt_123"}';
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', $messageId.'.'.$timestamp.'.'.$rawBody, $secretBytes, true));

    app(NomodWebhookSignatureVerifier::class)->verify($rawBody, $messageId, $timestamp, $signature);

    expect(true)->toBeTrue();
});

it('rejects an altered Nomod webhook body', function () {
    $secretBytes = random_bytes(32);
    config()->set('services.nomod.webhook_secret', 'whsec_'.base64_encode($secretBytes));
    config()->set('services.nomod.webhook_tolerance_seconds', 300);

    $messageId = 'msg_123';
    $timestamp = (string) time();
    $signedBody = '{"type":"charge.completed"}';
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', $messageId.'.'.$timestamp.'.'.$signedBody, $secretBytes, true));

    app(NomodWebhookSignatureVerifier::class)->verify('{"type":"charge.failed"}', $messageId, $timestamp, $signature);
})->throws(NomodWebhookSignatureException::class);
