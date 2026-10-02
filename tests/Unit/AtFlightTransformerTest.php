<?php

use App\Transformers\AtFlightTransformer;
use Tests\TestCase;

uses(TestCase::class);

it('classifies multi-city fare type from airport countries', function () {
    $transformer = new AtFlightTransformer();

    $domesticFareType = $transformer->determineFareType([
        'flight_type' => 'multi-city',
        'trips' => [
            ['origin' => ['iata' => 'DEL', 'country' => ['code' => 'IN']], 'destination' => ['iata' => 'BOM', 'country' => ['code' => 'IN']]],
            ['origin' => ['iata' => 'BOM', 'country' => ['code' => 'IN']], 'destination' => ['iata' => 'MAA', 'country' => ['code' => 'IN']]],
        ],
    ]);

    $internationalFareType = $transformer->determineFareType([
        'flight_type' => 'multi-city',
        'trips' => [
            ['origin' => ['iata' => 'LHE', 'country' => ['code' => 'PK']], 'destination' => ['iata' => 'JED', 'country' => ['code' => 'SA']]],
            ['origin' => ['iata' => 'JED', 'country' => ['code' => 'SA']], 'destination' => ['iata' => 'DXB', 'country' => ['code' => 'AE']]],
        ],
    ]);

    expect($domesticFareType)->toBe('DM')
        ->and($internationalFareType)->toBe('IM');
});

it('combines international multi-city trips by AT provider combinability keys', function () {
    $transformer = new AtFlightTransformer();

    $processed = $transformer->atFlightProcessor([
        'CurrencyCode' => 'AED',
        'Trips' => [
            [
                'Journey' => [
                    atJourney(['Index' => 'EY|7', 'ReturnIdentifier' => 3, 'FlightNo' => '101', 'From' => 'LHE', 'To' => 'JED']),
                    atJourney(['Index' => 'EY|8', 'ReturnIdentifier' => 4, 'FlightNo' => '102', 'From' => 'LHE', 'To' => 'JED']),
                ],
            ],
            [
                'Journey' => [
                    atJourney(['Index' => 'EY|7', 'ReturnIdentifier' => 3, 'FlightNo' => '201', 'From' => 'JED', 'To' => 'DXB']),
                    atJourney(['Index' => 'EY|9', 'ReturnIdentifier' => 5, 'FlightNo' => '202', 'From' => 'JED', 'To' => 'DXB']),
                ],
            ],
        ],
    ], [
        'flight_type' => 'multi-city',
        'fare_type' => 'IM',
    ]);

    expect($processed['flights'])->toHaveCount(1);

    $flight = $processed['flights'][0];

    expect($flight['type'])->toBe('multicity')
        ->and($flight['legs'])->toHaveCount(2)
        ->and($flight['legs'][0]['fares'][0]['index'])->toBe('EY|7')
        ->and($flight['legs'][1]['fares'][0]['index'])->toBe('EY|7');
});

it('keeps repeated international multi-city fares under the same physical flight', function () {
    $transformer = new AtFlightTransformer();

    $processed = $transformer->atFlightProcessor([
        'CurrencyCode' => 'AED',
        'Trips' => [
            [
                'Journey' => [
                    atJourney([
                        'Index' => '1G|1',
                        'ReturnIdentifier' => 1,
                        'FlightNo' => '9092',
                        'From' => 'DXB',
                        'To' => 'DEL',
                        'JourneyKey' => 'AI,9092,DXB,ATQ,2026-10-22T04:05:00,2026-10-22T09:50:00,2,,04h 15m ,~AI,1826,ATQ,DEL,2026-10-22T12:20:00,2026-10-22T13:35:00,,2,01h 15m ,',
                        'GrossFare' => 965.0,
                        'NetFare' => 965.0,
                    ]),
                    atJourney([
                        'Index' => '1G|2',
                        'ReturnIdentifier' => 2,
                        'FlightNo' => '9092',
                        'From' => 'DXB',
                        'To' => 'DEL',
                        'JourneyKey' => 'AI,9092,DXB,ATQ,2026-10-22T04:05:00,2026-10-22T09:50:00,2,,04h 15m ,~AI,1826,ATQ,DEL,2026-10-22T12:20:00,2026-10-22T13:35:00,,2,01h 15m ,',
                        'GrossFare' => 985.0,
                        'NetFare' => 985.0,
                    ]),
                ],
            ],
            [
                'Journey' => [
                    atJourney([
                        'Index' => '1G|1',
                        'ReturnIdentifier' => 1,
                        'FlightNo' => '2985',
                        'From' => 'DEL',
                        'To' => 'BOM',
                        'JourneyKey' => 'AI,2985,DEL,BOM,2026-10-29T19:30:00,2026-10-29T21:55:00,3,2,02h 25m ,',
                        'GrossFare' => 360.0,
                        'NetFare' => 360.0,
                    ]),
                    atJourney([
                        'Index' => '1G|2',
                        'ReturnIdentifier' => 2,
                        'FlightNo' => '2985',
                        'From' => 'DEL',
                        'To' => 'BOM',
                        'JourneyKey' => 'AI,2985,DEL,BOM,2026-10-29T19:30:00,2026-10-29T21:55:00,3,2,02h 25m ,',
                        'GrossFare' => 380.0,
                        'NetFare' => 380.0,
                    ]),
                ],
            ],
        ],
    ], [
        'flight_type' => 'multi-city',
        'fare_type' => 'IM',
    ]);

    expect($processed['flights'])->toHaveCount(1);

    $flight = $processed['flights'][0];

    expect($flight['legs'])->toHaveCount(2)
        ->and($flight['legs'][0]['fares'])->toHaveCount(2)
        ->and($flight['legs'][1]['fares'])->toHaveCount(2)
        ->and(array_column($flight['legs'][0]['fares'], 'index'))->toBe(['1G|1', '1G|2'])
        ->and(array_column($flight['legs'][1]['fares'], 'index'))->toBe(['1G|1', '1G|2']);
});

function atJourney(array $overrides = []): array
{
    return array_merge([
        'Provider' => 'EY',
        'ReturnIdentifier' => 3,
        'VAC' => 'EY',
        'MAC' => 'EY',
        'OAC' => 'EY',
        'Index' => 'EY|7',
        'FlightNo' => '101',
        'From' => 'LHE',
        'To' => 'JED',
        'JourneyKey' => 'EY,101,LHE,JED,2026-12-11T04:15:00,2026-12-11T10:40:00,M,1,06h 25m ,',
        'FareClass' => 'EV',
        'RBD' => 'M',
        'FBC' => 'MN900V6R',
        'FCType' => 'ECONOMY VALUE',
        'FCGroup' => 'NDC',
        'GrossFare' => 2275.0,
        'NetFare' => 2255.3,
        'TrendFare' => 2275.0,
        'Refundable' => 'N',
        'Hold' => true,
        'HoldInfo' => 'E|01:00|0|SD|EE',
        'Inclusions' => ['Baggage' => '25 Kg', 'Meals' => '', 'PieceDescription' => '', 'Seat' => null],
    ], $overrides);
}
