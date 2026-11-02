<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'duffel' => [
        'key' => env('DUFFEL_API_KEY'),
        'url' => env('DUFFEL_BASE_URL'),
        'version' => env('DUFFEL_API_VERSION'),
    ],

    'amadeus' => [
        'key' => env('AMADEUS_API_KEY'),
        'secret' => env('AMADEUS_API_SECRET'),
        'url' => env('AMADEUS_BASE_URL'),
        'version' => env('AMADEUS_API_VERSION'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'key' => env('STRIPE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomod Hosted Checkout
    |--------------------------------------------------------------------------
    |
    | These credentials must only ever be used by Laravel. In particular, the
    | API key and webhook signing secret must not be exposed through Vite or a
    | JSON endpoint.
    |
    */
    'nomod' => [
        // Prefer the name used by Nomod's Hosted Checkout documentation while
        // allowing the initial integration variable during a safe rollout.
        'api_key' => trim((string) env('NOMOD_HOSTED_CHECKOUT_API_KEY', env('NOMOD_API_KEY'))),
        'base_url' => rtrim(env('NOMOD_API_BASE_URL', 'https://api.nomod.com'), '/'),
        'timeout' => (int) env('NOMOD_TIMEOUT_SECONDS', 15),
        'webhook_secret' => env('NOMOD_WEBHOOK_SECRET'),
        'webhook_tolerance_seconds' => (int) env('NOMOD_WEBHOOK_TOLERANCE_SECONDS', 300),
        'allowed_return_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', env('NOMOD_ALLOWED_RETURN_HOSTS', 'jetze.pk,www.jetze.pk,jetze.ae,www.jetze.ae'))
        ))),
        'redirects' => [
            'success' => env('NOMOD_SUCCESS_URL', rtrim(env('APP_URL', ''), '/').'/payment/nomod/success'),
            'failure' => env('NOMOD_FAILURE_URL', rtrim(env('APP_URL', ''), '/').'/payment/nomod/failure'),
            'cancelled' => env('NOMOD_CANCELLED_URL', rtrim(env('APP_URL', ''), '/').'/payment/nomod/cancelled'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabby Pay-in-4
    |--------------------------------------------------------------------------
    |
    | The secret key and webhook secret must only ever be used by Laravel. The
    | public key is safe for frontend promo widgets. Tabby only settles in the
    | configured region currency, so other booking currencies are converted.
    |
    */
    'tabby' => [
        'secret_key' => trim((string) env('TABBY_SECRET_KEY')),
        'public_key' => trim((string) env('TABBY_PUBLIC_KEY')),
        'merchant_code' => trim((string) env('TABBY_MERCHANT_CODE')),
        'base_url' => rtrim(env('TABBY_API_BASE_URL', 'https://api.tabby.ai'), '/'),
        'currency' => strtoupper(env('TABBY_CURRENCY', 'AED')),
        // Tabby is only offered when the customer's mobile has one of these
        // country calling codes (UAE, KSA). Mirrored in resources/js/lib/tabbyEligibility.js.
        'allowed_phone_codes' => array_values(array_filter(array_map('trim', explode(',', env('TABBY_ALLOWED_PHONE_CODES', '971,966'))))),
        'timeout' => (int) env('TABBY_TIMEOUT_SECONDS', 15),
        'webhook_secret' => env('TABBY_WEBHOOK_SECRET'),
        'webhook_header' => env('TABBY_WEBHOOK_HEADER', 'X-Tabby-Webhook-Secret'),
        'webhook_url' => env('TABBY_WEBHOOK_URL', rtrim(env('APP_URL', ''), '/').'/api/webhooks/tabby'),
        'redirects' => [
            'success' => env('TABBY_SUCCESS_URL', rtrim(env('APP_URL', ''), '/').'/payment/tabby/success'),
            'failure' => env('TABBY_FAILURE_URL', rtrim(env('APP_URL', ''), '/').'/payment/tabby/failure'),
            'cancelled' => env('TABBY_CANCELLED_URL', rtrim(env('APP_URL', ''), '/').'/payment/tabby/cancelled'),
        ],
    ],

];
