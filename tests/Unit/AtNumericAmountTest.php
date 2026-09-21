<?php

use App\Services\AtApiService;

uses(Tests\TestCase::class);

it('preserves trailing zeroes in whole AT amounts', function () {
    $method = new ReflectionMethod(AtApiService::class, 'atNumericAmount');
    $service = new AtApiService();

    expect($method->invoke($service, '6580.00000000'))->toBe(6580)
        ->and($method->invoke($service, '1200'))->toBe(1200)
        ->and($method->invoke($service, '6580.50'))->toBe(6580.5);
});
