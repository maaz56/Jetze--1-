<?php

namespace App\Transformers;

use App\Services\CurrencyConversionService;

class AtFareBreakdownTransformer
{
    public function __construct(private readonly CurrencyConversionService $currencyConversionService)
    {
    }

    /** Transform AT FlightInfo into selected-fare money fields for the FlightSearch breakdown tab. */
    public function transform(array $response, array $flight, array $fareReferences): array
    {
        $providerCurrency = strtoupper((string) (data_get($flight, 'currencyCode') ?: 'AED'));
        $displayCurrency = strtoupper((string) (data_get($flight, 'displayCurrencyCode') ?: 'AED'));
        $selectedReferences = array_flip($fareReferences);
        $selectedFaresByLeg = [];

        foreach (data_get($flight, 'leg.flights', []) as $flightIndex => $leg) {
            foreach ($leg['fares'] ?? [] as $fare) {
                if (isset($selectedReferences[$fare['ref_id'] ?? ''])) {
                    $selectedFaresByLeg[$flightIndex] = $fare;
                    break;
                }
            }
        }

        $breakdowns = [];
        $flightIndex = 0;

        foreach (data_get($response, 'Trips', []) as $trip) {
            foreach (data_get($trip, 'Journey', []) as $journey) {
                $selectedFare = $selectedFaresByLeg[$flightIndex] ?? [];
                $fares = data_get($journey, 'Segments.0.Fares', []);
                $netFareMoney = $this->money(
                    data_get($journey, 'NetFare', 0),
                    $providerCurrency,
                    $displayCurrency,
                );

                $breakdowns[] = [
                    'flight_index' => $flightIndex,
                    'fare' => [
                        'name_class' => data_get($journey, 'FCType') ?? data_get($selectedFare, 'name_class'),
                        'class' => data_get($journey, 'FareType') ?? data_get($selectedFare, 'class'),
                        'provider_booking_money' => $netFareMoney['provider_money'],
                        'base_money' => $netFareMoney['base_money'],
                        'display_money' => $netFareMoney['display_money'],
                        'passenger_fares' => $this->passengerFares(
                            data_get($fares, 'PTCFare', []),
                            $response,
                            $providerCurrency,
                            $displayCurrency,
                        ),
                    ],
                    'provider_money' => [
                        'base_fare' => $this->money(data_get($fares, 'TotalBaseFare', 0), $providerCurrency, $displayCurrency),
                        'taxes' => $this->money(data_get($fares, 'TotalTax', 0), $providerCurrency, $displayCurrency),
                        'fees' => $this->money(data_get($fares, 'TotalTransactionFee', 0), $providerCurrency, $displayCurrency),
                        'service_charges' => $this->money($this->serviceCharges($fares), $providerCurrency, $displayCurrency),
                        'surcharge' => $this->money(0, $providerCurrency, $displayCurrency),
                        'discount' => $this->money(data_get($fares, 'TotalCommission', 0), $providerCurrency, $displayCurrency),
                        'total_price' => $this->money(data_get($journey, 'NetFare', 0), $providerCurrency, $displayCurrency),
                    ],
                ];

                $flightIndex++;
            }
        }

        return [
            'provider' => 'AT',
            'provider_currency' => $providerCurrency,
            'display_currency' => $displayCurrency,
            'trips' => $breakdowns,
        ];
    }

    /** Map each AT passenger type with money fields in provider, AED, and selected display currencies. */
    private function passengerFares(array $passengerFares, array $response, string $providerCurrency, string $displayCurrency): array
    {
        return array_map(function (array $passengerFare) use ($response, $providerCurrency, $displayCurrency): array {
            $travelerType = strtoupper((string) data_get($passengerFare, 'PTC', 'ADT'));

            return [
                'traveler_type' => $travelerType,
                'total_passenger' => (int) data_get($response, $travelerType, 0),
                'display_money' => [
                    'base_price' => $this->displayMoney(data_get($passengerFare, 'Fare', 0), $providerCurrency, $displayCurrency),
                    'taxes' => $this->displayMoney(data_get($passengerFare, 'Tax', 0), $providerCurrency, $displayCurrency),
                    'fees' => $this->displayMoney(data_get($passengerFare, 'TransactionFee', 0), $providerCurrency, $displayCurrency),
                    'service_charges' => $this->displayMoney($this->serviceCharges($passengerFare), $providerCurrency, $displayCurrency),
                    'surchage' => $this->displayMoney(0, $providerCurrency, $displayCurrency),
                    'discount' => $this->displayMoney(data_get($passengerFare, 'Discount', 0), $providerCurrency, $displayCurrency),
                    'total_price' => $this->displayMoney(data_get($passengerFare, 'NetFare', 0), $providerCurrency, $displayCurrency),
                ],
            ];
        }, $passengerFares);
    }

    /** Combine AT service-tax fields without double-counting taxes or transaction fees. */
    private function serviceCharges(array $fares): string
    {
        return bcadd(
            bcadd((string) data_get($fares, 'TotalServiceTax', data_get($fares, 'ST', 0)), (string) data_get($fares, 'TotalVATonServiceCharge', data_get($fares, 'VATonServiceCharge', 0)), 8),
            (string) data_get($fares, 'TotalVATonTransactionFee', data_get($fares, 'VATonTransactionFee', 0)),
            8,
        );
    }

    /** Create provider, AED base, and selected-currency values from one AT amount. */
    private function money(mixed $amount, string $providerCurrency, string $displayCurrency): array
    {
        $providerMoney = $this->currencyConversionService->makeMoney((string) ($amount ?? 0), $providerCurrency);

        return [
            'provider_money' => $providerMoney,
            'base_money' => $this->currencyConversionService->toBaseMoney($providerMoney['amount'], $providerCurrency),
            'display_money' => $this->currencyConversionService->convertMoney($providerMoney['amount'], $providerCurrency, $displayCurrency),
        ];
    }

    /** Return only the selected-currency money value expected by the fare-breakdown UI. */
    private function displayMoney(mixed $amount, string $providerCurrency, string $displayCurrency): array
    {
        return $this->money($amount, $providerCurrency, $displayCurrency)['display_money'];
    }
}
