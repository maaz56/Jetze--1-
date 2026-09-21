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
        'redirects' => [
            'success' => env('NOMOD_SUCCESS_URL', rtrim(env('APP_URL', ''), '/').'/payment/nomod/success'),
            'failure' => env('NOMOD_FAILURE_URL', rtrim(env('APP_URL', ''), '/').'/payment/nomod/failure'),
            'cancelled' => env('NOMOD_CANCELLED_URL', rtrim(env('APP_URL', ''), '/').'/payment/nomod/cancelled'),
        ],
    ],

];
