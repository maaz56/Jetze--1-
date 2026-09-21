<?php

namespace App\Services;

use App\Exceptions\NomodWebhookSignatureException;

class NomodWebhookSignatureVerifier
{
    /**
     * Verify Nomod's Svix signature without ever parsing or re-encoding the
     * body. The latter would change the bytes which were signed.
     */
    public function verify(string $rawBody, ?string $messageId, ?string $timestamp, ?string $signature): void
    {
        if (! is_string($messageId) || $messageId === '' || ! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature) || $signature === '') {
            throw new NomodWebhookSignatureException('Required Svix signature headers are missing or invalid.');
        }

        $tolerance = (int) config('services.nomod.webhook_tolerance_seconds', 300);
        if ($tolerance < 1 || abs(time() - (int) $timestamp) > $tolerance) {
            throw new NomodWebhookSignatureException('Webhook timestamp is outside the permitted tolerance.');
        }

        $secret = config('services.nomod.webhook_secret');
        if (! is_string($secret) || ! str_starts_with($secret, 'whsec_')) {
            throw new NomodWebhookSignatureException('Nomod webhook signing secret is not configured.');
        }

        $secretBytes = base64_decode(substr($secret, strlen('whsec_')), true);
        if ($secretBytes === false || $secretBytes === '') {
            throw new NomodWebhookSignatureException('Nomod webhook signing secret is invalid.');
        }

        $signedContent = $messageId.'.'.$timestamp.'.'.$rawBody;
        $expectedSignature = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));

        foreach (preg_split('/\s+/', trim($signature)) ?: [] as $candidate) {
            [$version, $providedSignature] = array_pad(explode(',', $candidate, 2), 2, null);

            if ($version === 'v1' && is_string($providedSignature) && hash_equals($expectedSignature, $providedSignature)) {
                return;
            }
        }

        throw new NomodWebhookSignatureException('Nomod webhook signature does not match.');
    }
}
