<?php

return [
    'url' => env('FLY_DUBAI_BASE_URL'),
    'username' => env('FLY_DUBAI_USERNAME'),
    'password' => env('FLY_DUBAI_PASSWORD'),
    'client_id' => env('FLY_DUBAI_CLIENT_ID', ''),
    'client_secret' => env('FLY_DUBAI_CLIENT_SECRET', ''),
    'iata_number' => env('FLY_UNIQUE_REQUESTER_IATA', ''),
];