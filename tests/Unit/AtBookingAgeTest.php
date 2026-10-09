<?php

use App\Services\AtApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;

it('calculates booking age using the final round-trip departure', function () {
    Carbon::setTestNow('2026-10-09 16:00:00');

    $service = (new ReflectionClass(AtApiService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(AtApiService::class, 'bookingAgeReferenceDate');
    $referenceDate = $method->invoke($service, [
        'flight' => [
            'leg' => [
                'flights' => [
                    ['departure_at' => '2026-12-17T01:45:00'],
                    ['departure_at' => '2027-01-06T06:00:00'],
                ],
            ],
        ],
    ]);

    expect((int) Carbon::parse('1978-12-27')->diffInYears($referenceDate))->toBe(48);

    Carbon::setTestNow();
});

it('falls back to the booking date when itinerary dates are unavailable', function () {
    Carbon::setTestNow('2026-10-09 16:00:00');

    $service = (new ReflectionClass(AtApiService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(AtApiService::class, 'bookingAgeReferenceDate');
    $referenceDate = $method->invoke($service, []);

    expect($referenceDate->toDateTimeString())->toBe('2026-10-09 16:00:00');

    Carbon::setTestNow();
});

it('accepts the request object passed by the booking controller', function () {
    $service = (new ReflectionClass(AtApiService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(AtApiService::class, 'bookingAgeReferenceDate');
    $request = Request::create('/', 'POST', [
        'flight' => [
            'leg' => [
                'flights' => [
                    ['departure_at' => '2026-12-17T01:45:00'],
                    ['departure_at' => '2027-01-06T06:00:00'],
                ],
            ],
        ],
    ]);

    $referenceDate = $method->invoke($service, $request);

    expect($referenceDate->toDateString())->toBe('2027-01-06');
});
