<?php

return [

    'sign_base_url' => env('AT_SIGN_BASE_URL'),
    'flight_base_url' => env('AT_FLIGHT_BASE_URL'),
    'merchant_id' => env('AT_MERCHANT_ID'),
    'api_key'     => env('AT_API_KEY'),
    'client_id'   => env('AT_CLIENT_ID'),
    'password'    => env('AT_PASSWORD'),
    'agent_code'  => env('AT_AGENT_CODE'),
    'browser_key' => env('AT_BROWSER_KEY'),
    'ca_bundle'   => env('AT_CA_BUNDLE', storage_path('certs/server_chain.pem')),

];
