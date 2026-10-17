<?php

use App\Services\CommercialPricingService;
use App\Services\CurrencyConversionService;
use App\Services\PromotionService;
use App\Services\SegmentMarginService;
use App\Transformers\HakimiFlightTransformer;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

beforeEach(function () {
    $container = new Container();
    $container->instance('config', new Repository([
        'hakimi' => ['provider_currency' => 'INR'],
    ]));
    Container::setInstance($container);
});

afterEach(function () {
    Mockery::close();
    Container::setInstance(null);
});

function hakimiCurrencyService(): CurrencyConversionService
{
    return new class extends CurrencyConversionService
    {
        public function makeMoney(mixed $amount, string $currencyCode): array
        {
            return [
                'amount' => number_format((float) $amount, 2, '.', ''),
                'currency' => strtoupper($currencyCode),
                'decimal_places' => 2,
            ];
        }

        public function toBaseMoney(mixed $amount, string $sourceCurrency): array
        {
            return $this->makeMoney((float) $amount / 25, 'AED');
        }

        public function convertMoney(mixed $amount, string $sourceCurrency, string $targetCurrency): array
        {
            return $this->makeMoney($amount, $targetCurrency);
        }
    };
}

it('normalizes Hakimi search money from INR into AED base and selected display currency', function () {
    config(['hakimi.provider_currency' => 'INR']);

    $transformer = new HakimiFlightTransformer(hakimiCurrencyService());
    $result = $transformer->transform([
        'results' => [[
            'inventoryId' => 'inventory-1',
            'currency' => 'INR',
            'price' => 6550,
            'childPrice' => 6550,
            'infantPrice' => 2550,
            'origin' => '',
            'destination' => '',
            'departure' => '2026-11-05T20:25:00.000Z',
            'arrival' => '2026-11-05T22:00:00.000Z',
            'airlineCode' => '',
            'airlineName' => 'Air India Express',
            'flightNo' => '296',
        ]],
    ], [
        'origin' => '',
        'destination' => '',
        'departure_date' => '2026-11-05',
        'adults' => 1,
        'children' => 0,
        'infants' => 0,
        'currency_code' => 'USD',
    ]);

    $fare = data_get($result, '0.leg.flights.0.fares.0');

    expect(data_get($result, '0.stream_key'))->toBe('HAKIMI:inventory-1')
        ->and(data_get($fare, 'provider_booking_money.currency'))->toBe('INR')
        ->and(data_get($fare, 'provider_booking_money.amount'))->toBe('6550.00')
        ->and(data_get($fare, 'base_money.currency'))->toBe('AED')
        ->and(data_get($fare, 'display_money.currency'))->toBe('USD');
});

it('publishes reliable selling money for Hakimi fares through shared commercial pricing', function () {
    $promotionService = Mockery::mock(PromotionService::class);
    $promotionService->shouldReceive('commercialRuleForItinerary')->once()->andReturnNull();
    $segmentMarginService = Mockery::mock(SegmentMarginService::class);
    $segmentMarginService->shouldReceive('commercialRuleForItinerary')->once()->andReturnNull();

    $service = new CommercialPricingService(
        hakimiCurrencyService(),
        $segmentMarginService,
        $promotionService,
    );

    $result = $service->applyToProviderFlights([[
        'provider' => ['identifier' => 'HAKIMI'],
        'displayCurrencyCode' => 'PKR',
        'leg' => [
            'flights' => [[
                'ref_id' => 'flight-1',
                'fares' => [[
                    'ref_id' => 'fare-1',
                    'provider_booking_money' => ['amount' => '6550.00', 'currency' => 'INR'],
                    'provider_gross_money' => ['amount' => '6550.00', 'currency' => 'INR'],
                ]],
            ]],
        ],
    ]]);

    $fare = data_get($result, '0.leg.flights.0.fares.0');

    expect(data_get($fare, 'provider_booking_money.currency'))->toBe('INR')
        ->and(data_get($fare, 'selling_base_money.currency'))->toBe('AED')
        ->and(data_get($fare, 'selling_display_money.currency'))->toBe('PKR')
        ->and(data_get($fare, 'base_money'))->toBe(data_get($fare, 'selling_base_money'))
        ->and(data_get($fare, 'display_money'))->toBe(data_get($fare, 'selling_display_money'));
});

it('rejects a Hakimi fare when normalized provider money is missing', function () {
    $service = new CommercialPricingService(
        hakimiCurrencyService(),
        Mockery::mock(SegmentMarginService::class),
        Mockery::mock(PromotionService::class),
    );

    $service->applyToProviderFlights([[
        'provider' => ['identifier' => 'HAKIMI'],
        'displayCurrencyCode' => 'AED',
        'leg' => [
            'flights' => [[
                'ref_id' => 'flight-1',
                'fares' => [['ref_id' => 'fare-1', 'total_price' => 6550]],
            ]],
        ],
    ]]);
})->throws(UnexpectedValueException::class, 'missing normalized provider money');

