<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AirBlue API Configuration
    |--------------------------------------------------------------------------
    |
    | These values are used to connect with the AirBlue API services.
    | All credentials should be stored in your .env file and never committed.
    |
    */
    "endpoint" => [
        "search" => env('AIRBLUE_SEARCH_ENDPOINT', 'https://ota.zapways.com/v3.0/OTAAPI.asmx'),
    ],
    "service" => [
        "target" => env('AIRBLUE_SERVICE_TARGET', 'Production'),
        "version" => env('AIRBLUE_SERVICE_VERSION', '1.04'),
    ],

    "api_client" => [
        "id" => env('AIRBLUE_API_CLIENT_ID'),
        "key" => env('AIRBLUE_API_CLIENT_KEY'),
    ],

    "agent" => [
        "type" => env('AIRBLUE_AGENT_TYPE'),
        "id" => env('AIRBLUE_AGENT_ID'),
        "password" => env('AIRBLUE_AGENT_PASSWORD'),
    ],

    // "cert" => [
    //     "password" => env('AIRBLUE_CERT_PASSWORD'),
    // ]


];
