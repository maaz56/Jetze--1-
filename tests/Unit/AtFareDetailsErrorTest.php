<?php

use App\Services\AtApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;

class AtFareDetailsTestCase extends LaravelTestCase
{
}

uses(AtFareDetailsTestCase::class);

it('does not expose provider errors when loading fare details', function (string $endpoint, string $method, bool $throws) {
    $token = 'f0630ddc-fd44-4fd4-b808-56d904242066';
    Cache::put("flight_quote_{$token}_flight-1", [
        'provider' => ['name' => 'at', 'identifier' => 'AT', 'TUI' => 'tui'],
        'leg' => ['flights' => [
            ['fares' => [
                ['ref_id' => 'fare-1', 'index' => '1', 'billable_price' => 100, 'total_price' => 100],
            ]],
        ]],
    ]);

    $service = Mockery::mock(AtApiService::class);
    $expectation = $service->shouldReceive($method)->once();
    if ($throws) {
        $expectation->andThrow(new RuntimeException('cURL error 28: https://supplier.example/Flights/FlightInfo'));
    } else {
        $expectation->andReturn(['Code' => '500', 'Msg' => ['cURL error 28: https://supplier.example']]);
    }
    $this->app->instance(AtApiService::class, $service);

    $response = $this->postJson("/api/at/{$endpoint}", [
        'flight_ref_id' => 'flight-1', 'fare_references' => ['fare-1'], 'search_token' => $token,
    ]);

    $response->assertStatus(502)->assertJsonMissing(['message' => 'cURL error 28: https://supplier.example']);
    expect($response->getContent())->not->toContain('cURL', 'supplier.example');
})->with([
    ['fare-breakdown', 'fetchFlightInfo', true],
    ['fare-breakdown', 'fetchFlightInfo', false],
    ['fare-rules', 'fetchFareRules', true],
    ['fare-rules', 'fetchFareRules', false],
]);
