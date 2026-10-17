<?php

return [
    'suppliers'           => ['AT'],
    'batch_size'          => 20,
    'stagger_seconds'     => 10,
    'max_price_age_hours' => 72,   // older → hide price
    'date_fallback_days'  => 3,
    'rate_limit_per_min'  => 10,
    'list_cache_minutes'  => 10,
    'base_currency'       => 'AED',
];
