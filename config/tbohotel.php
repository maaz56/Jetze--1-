<?php

return [
    'base_url' => env('TBO_HOTEL_BASE_URL', 'https://api.tbotechnology.in/HotelAPI'),
    'username' => env('TBO_HOTEL_USERNAME'),
    'password' => env('TBO_HOTEL_PASSWORD'),
    'timeout_search' => (int) env('TBO_HOTEL_TIMEOUT_SEARCH', 23),
    'timeout_prebook' => (int) env('TBO_HOTEL_TIMEOUT_PREBOOK', 23),
    'timeout_book' => (int) env('TBO_HOTEL_TIMEOUT_BOOK', 120),
    'connect_timeout' => (int) env('TBO_HOTEL_CONNECT_TIMEOUT', 15),
];
