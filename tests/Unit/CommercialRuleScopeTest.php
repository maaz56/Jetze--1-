<?php

use App\Models\Promotion;
use App\Models\SegmentMargin;
use App\Services\CurrencyConversionService;
use App\Services\PromotionService;
use App\Services\SegmentMarginService;
use Illuminate\Support\Collection;

function invokeCommercialRuleResolver(object $service, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod($service, $method);

    return $reflection->invoke($service, ...$arguments);
}

it('uses an airline-specific promotion over an all-airlines promotion regardless of legacy priority', function () {
    $service = new PromotionService(Mockery::mock(CurrencyConversionService::class));

    $default = new Promotion([
        'id' => 20,
        'sale_channel' => 'AT',
        'reservation_type' => 'ALL-SECTORS',
        'priority' => 999,
    ]);
    $default->id = 20;

    $airlineOverride = new Promotion([
        'id' => 10,
        'sale_channel' => 'AT',
        'airline_id' => 7,
        'reservation_type' => 'ALL-SECTORS',
        'priority' => -999,
    ]);
    $airlineOverride->id = 10;

    $resolved = invokeCommercialRuleResolver($service, 'resolveBestPromotion', new Collection([
        $default,
        $airlineOverride,
    ]), [
        'channels' => ['AT'],
        'airline_ids' => [7],
        'ticketing_date' => now()->toDateString(),
    ]);

    expect($resolved?->id)->toBe(10);
});

it('uses an airline-specific segment margin over an all-airlines default regardless of legacy priority', function () {
    $service = new SegmentMarginService(Mockery::mock(CurrencyConversionService::class));

    $default = new SegmentMargin([
        'id' => 20,
        'sale_channel' => 'AT',
        'priority' => 999,
        'disabled_airline_ids' => [],
    ]);
    $default->id = 20;

    $airlineOverride = new SegmentMargin([
        'id' => 10,
        'sale_channel' => 'AT',
        'airline_id' => 7,
        'priority' => -999,
        'disabled_airline_ids' => [],
    ]);
    $airlineOverride->id = 10;

    $resolved = invokeCommercialRuleResolver($service, 'resolveBestMargin', new Collection([
        $default,
        $airlineOverride,
    ]), [
        'channels' => ['AT'],
        'airline_ids' => [7],
    ]);

    expect($resolved?->id)->toBe(10);
});
