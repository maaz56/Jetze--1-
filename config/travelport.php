<?php

return [
    'username' => env('TRAVELPORT_USERNAME'),
    'password' => env('TRAVELPORT_PASSWORD'),
    'client_id' => env('TRAVELPORT_CLIENT_ID'),
    'client_secret' => env('TRAVELPORT_CLIENT_SECRET'),
    'auth_url' => env('TRAVELPORT_AUTH_URL'),
    'search_url' => env('TRAVELPORT_SEARCH_URL'),
    'api_version' => env('TRAVELPORT_API_VERSION', '11'),
    'access_group' => env('XAUTH_TRAVELPORT_ACCESSGROUP'),
];
