<?php

use App\Services\CurrencyConversionService;
use App\Transformers\AtFareBreakdownTransformer;

it('only marks provider tax details available when passenger and total tax reconcile', function () {
    $transformer = new AtFareBreakdownTransformer(Mockery::mock(CurrencyConversionService::class));
    $method = new ReflectionMethod($transformer, 'taxBreakdownIsReliable');

    expect($method->invoke($transformer, [
        'TotalTax' => 0,
        'PTCFare' => [['PTC' => 'ADT', 'Tax' => 9363]],
    ], ['ADT' => 1]))->toBeFalse()
        ->and($method->invoke($transformer, [
            'TotalTax' => 4285,
            'PTCFare' => [['PTC' => 'ADT', 'Tax' => 4285]],
        ], ['ADT' => 1]))->toBeTrue()
        ->and($method->invoke($transformer, [
            'TotalTax' => 200,
            'PTCFare' => [['PTC' => 'ADT', 'Tax' => 100]],
        ], ['ADT' => 2]))->toBeTrue()
        ->and($method->invoke($transformer, [
            'TotalTax' => 0,
            'PTCFare' => [['PTC' => 'ADT', 'Tax' => 0]],
        ], ['ADT' => 1]))->toBeTrue();
});
