<?php

return [
    /* Test keys must only use the test API path and remain server-side. */
    'base_url' => rtrim(env('HAKIMI_BASE_URL', 'https://travelwithhakimi.com/api/v1/test'), '/'),
    'api_key' => env('HAKIMI_API_KEY'),
    'timeout' => (int) env('HAKIMI_TIMEOUT_SECONDS', 30),

    /* Hakimi returns provider-payable amounts in INR in both test and live. */
    'provider_currency' => 'INR',
];
