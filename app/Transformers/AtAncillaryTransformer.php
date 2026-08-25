<?php

namespace App\Transformers;

use App\Services\CurrencyConversionService;

class AtAncillaryTransformer
{
    private readonly CurrencyConversionService $currencyConversionService;

    public function __construct(?CurrencyConversionService $currencyConversionService = null)
    {
        $this->currencyConversionService = $currencyConversionService
            ?? app(CurrencyConversionService::class);
    }

    /**
     * Convert AT's SSR and seat-layout response into the checkout ancillary format.
     */
    public function transform(array $response, string $displayCurrency, string $providerCurrency = 'AED'): array
    {
        $data = $response['data'] ?? [];
        $ssrData = $data['ssrData'] ?? [];
        $seatLayout = $data['seatLayout'] ?? [];
        $providerCurrency = strtoupper($providerCurrency ?: ($ssrData['CurrencyCode'] ?? 'AED'));
        $displayCurrency = strtoupper($displayCurrency ?: 'AED');

        return [
            'data' => [
                'provider' => 'AT',
                'provider_currency' => $providerCurrency,
                'display_currency' => $displayCurrency,
                'ssrData' => $this->mapSsrData($ssrData, $providerCurrency, $displayCurrency),
                'seatLayout' => $this->mapSeatLayout($seatLayout, $providerCurrency, $displayCurrency),
            ],
        ];
    }

    /**
     * Keep the SSR journey structure used by checkout and map each selectable service.
     */
    private function mapSsrData(array $ssrData, string $providerCurrency, string $displayCurrency): array
    {
        return [
            'TUI' => $ssrData['TUI'] ?? null,
            'CurrencyCode' => $providerCurrency,
            'PaidSSR' => (bool) ($ssrData['PaidSSR'] ?? false),
            'Trips' => array_map(
                fn (array $trip) => [
                    'From' => $trip['From'] ?? null,
                    'To' => $trip['To'] ?? null,
                    'Journey' => array_map(
                        fn (array $journey) => [
                            'Provider' => $journey['Provider'] ?? null,
                            'Segments' => array_map(
                                fn (array $segment) => $this->mapSsrSegment(
                                    $segment,
                                    $providerCurrency,
                                    $displayCurrency,
                                ),
                                $journey['Segments'] ?? [],
                            ),
                        ],
                        $trip['Journey'] ?? [],
                    ),
                ],
                $ssrData['Trips'] ?? [],
            ),
        ];
    }

    /**
     * Map SSR references and their money without exposing AT's complete raw object.
     */
    private function mapSsrSegment(array $segment, string $providerCurrency, string $displayCurrency): array
    {
        return [
            'FUID' => $segment['FUID'] ?? null,
            'VAC' => $segment['VAC'] ?? null,
            'FlightNo' => $segment['FlightNo'] ?? null,
            'SSR' => array_map(
                fn (array $ssr) => [
                    'ID' => $ssr['ID'] ?? null,
                    'Code' => $ssr['Code'] ?? null,
                    'Description' => $ssr['Description'] ?? null,
                    'PieceDescription' => $ssr['PieceDescription'] ?? null,
                    'Type' => (string) ($ssr['Type'] ?? ''),
                    'Category' => $ssr['Category'] ?? null,
                    'PTC' => $ssr['PTC'] ?? null,
                    'MealImage' => $ssr['MealImage'] ?? null,
                    'IsFreeMeal' => (bool) ($ssr['IsFreeMeal'] ?? false),
                    ...$this->money($ssr['SSRNetAmount'] ?? $ssr['Charge'] ?? 0, $providerCurrency, $displayCurrency),
                ],
                $segment['SSR'] ?? [],
            ),
        ];
    }

    /**
     * Keep the seat-map shape used by checkout and map every selectable seat.
     */
    private function mapSeatLayout(array $seatLayout, string $providerCurrency, string $displayCurrency): array
    {
        return [
            'TUI' => $seatLayout['TUI'] ?? null,
            'CurrencyCode' => $providerCurrency,
            'Trips' => array_map(
                fn (array $trip) => [
                    'From' => $trip['From'] ?? null,
                    'To' => $trip['To'] ?? null,
                    'Journey' => array_map(
                        fn (array $journey) => [
                            'Provider' => $journey['Provider'] ?? null,
                            'Segments' => array_map(
                                fn (array $segment) => $this->mapSeatSegment(
                                    $segment,
                                    $providerCurrency,
                                    $displayCurrency,
                                ),
                                $journey['Segments'] ?? [],
                            ),
                        ],
                        $trip['Journey'] ?? [],
                    ),
                ],
                $seatLayout['Trips'] ?? [],
            ),
        ];
    }

    /**
     * Map only seat layout metadata, provider references, and converted price fields.
     */
    private function mapSeatSegment(array $segment, string $providerCurrency, string $displayCurrency): array
    {
        return [
            'FUID' => $segment['FUID'] ?? null,
            'FlightNo' => $segment['FlightNo'] ?? null,
            'AirlineName' => $segment['AirlineName'] ?? null,
            'AirlineUnit' => $segment['AirlineUnit'] ?? null,
            'Seats' => array_map(
                fn (array $seat) => [
                    'SSID' => $seat['SSID'] ?? null,
                    'SeatNumber' => $seat['SeatNumber'] ?? null,
                    'SeatStatus' => $seat['SeatStatus'] ?? null,
                    'AvailStatus' => (bool) ($seat['AvailStatus'] ?? false),
                    'SeatGroup' => $seat['SeatGroup'] ?? null,
                    'SeatInfo' => $seat['SeatInfo'] ?? null,
                    'SeatType' => $seat['SeatType'] ?? null,
                    'XValue' => $seat['XValue'] ?? null,
                    'YValue' => $seat['YValue'] ?? null,
                    'Height' => $seat['Height'] ?? null,
                    'Width' => $seat['Width'] ?? null,
                    ...$this->money($seat['SSRNetAmount'] ?? $seat['Fare'] ?? 0, $providerCurrency, $displayCurrency),
                ],
                $segment['Seats'] ?? [],
            ),
        ];
    }

    /**
     * Build consistent provider, AED, and selected-currency money fields.
     *
     * @return array{provider_money: array, base_money: array, display_money: array}
     */
    private function money(mixed $amount, string $providerCurrency, string $displayCurrency): array
    {
        return [
            'provider_money' => $this->currencyConversionService->makeMoney($amount, $providerCurrency),
            'base_money' => $this->currencyConversionService->toBaseMoney($amount, $providerCurrency),
            'display_money' => $this->currencyConversionService->convertMoney(
                $amount,
                $providerCurrency,
                $displayCurrency,
            ),
        ];
    }
}
