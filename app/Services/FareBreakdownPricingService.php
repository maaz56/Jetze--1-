<?php

namespace App\Services;

/** Build one displayable fare breakdown from provider components and admin adjustments. */
class FareBreakdownPricingService
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /** Sum AT trip components into the display-currency Price Details structure. */
    public function withProviderPriceDetails(array $fareBreakdown): array
    {
        $fields = [
            'base_fare',
            'taxes',
            'fees',
            'service_charges',
            'surcharge',
            'discount',
            'total_price',
        ];
        $summary = [];

        foreach ($fields as $field) {
            $displayAmount = '0';
            $baseAmount = '0';
            $currency = $fareBreakdown['display_currency'] ?? null;

            foreach ($fareBreakdown['trips'] ?? [] as $trip) {
                $money = data_get($trip, "provider_money.{$field}");
                $displayMoney = data_get($money, 'display_money');
                $baseMoney = data_get($money, 'base_money');

                if (is_array($displayMoney) && isset($displayMoney['amount'])) {
                    $displayAmount = bcadd($displayAmount, (string) $displayMoney['amount'], 8);
                    $currency ??= $displayMoney['currency'] ?? null;
                }
                if (is_array($baseMoney) && isset($baseMoney['amount'])) {
                    $baseAmount = bcadd($baseAmount, (string) $baseMoney['amount'], 8);
                }
            }

            $summary[$field] = [
                'amount' => $displayAmount,
                'currency' => $currency,
                'base_money' => $this->currencyConversionService->makeMoney($baseAmount, 'AED'),
            ];
        }

        $fareBreakdown['price_details'] = $summary;
        $fareBreakdown['tax_breakdown_available'] = !empty($fareBreakdown['trips'])
            && collect($fareBreakdown['trips'])->every(fn ($trip) => ($trip['tax_breakdown_available'] ?? false) === true);

        return $fareBreakdown;
    }

    /** Preserve AT/admin charges for audit while exposing their signed combined total. */
    public function withCommercialServiceCharges(
        ?array $fareBreakdown,
        array $commercial,
        string $displayCurrency,
    ): ?array {
        if (!$fareBreakdown || !isset($fareBreakdown['price_details'])) {
            return $fareBreakdown;
        }

        $displayCurrency = strtoupper($displayCurrency);
        $existingServiceCharges = data_get($fareBreakdown, 'price_details.service_charges', []);
        $atServiceCharges = data_get($existingServiceCharges, 'at_service_charges');
        $atServiceBaseMoney = data_get($existingServiceCharges, 'at_service_base_money')
            ?? data_get($existingServiceCharges, 'base_money');

        if (!is_array($atServiceCharges) || !isset($atServiceCharges['amount'], $atServiceCharges['currency'])) {
            $atServiceCharges = [
                'amount' => (string) data_get($existingServiceCharges, 'amount', '0'),
                'currency' => strtoupper((string) data_get(
                    $existingServiceCharges,
                    'currency',
                    $displayCurrency,
                )),
            ];
        }
        $atServiceCharges['currency'] = strtoupper((string) $atServiceCharges['currency']);

        if ($atServiceCharges['currency'] !== $displayCurrency) {
            $atServiceCharges = $this->currencyConversionService->convertMoney(
                $atServiceCharges['amount'],
                $atServiceCharges['currency'],
                $displayCurrency,
            );
        } else {
            $atServiceCharges = $this->currencyConversionService->makeMoney(
                $atServiceCharges['amount'],
                $displayCurrency,
            );
        }

        if (!is_array($atServiceBaseMoney) || !isset($atServiceBaseMoney['amount'])) {
            $atServiceBaseMoney = $this->currencyConversionService->toBaseMoney(
                $atServiceCharges['amount'],
                $displayCurrency,
            );
        } else {
            $atServiceBaseMoney = $this->currencyConversionService->makeMoney(
                $atServiceBaseMoney['amount'],
                'AED',
            );
        }

        $adminAedAmount = '0';
        foreach ($commercial['adjustments'] ?? [] as $adjustment) {
            $adminAedAmount = bcadd(
                $adminAedAmount,
                (string) ($adjustment['signed_aed_amount'] ?? '0'),
                8,
            );
        }

        $adminServiceCharges = $this->signedConvertedMoney(
            $adminAedAmount,
            'AED',
            $displayCurrency,
        );
        $totalServiceBaseMoney = $this->signedMoney(
            bcadd((string) $atServiceBaseMoney['amount'], $adminAedAmount, 8),
            'AED',
        );
        $totalServiceCharges = $this->signedConvertedMoney(
            (string) $totalServiceBaseMoney['amount'],
            'AED',
            $displayCurrency,
        );

        $fareBreakdown['price_details']['service_charges'] = array_merge(
            $totalServiceCharges,
            [
                'base_money' => $totalServiceBaseMoney,
                'at_service_charges' => $atServiceCharges,
                'at_service_base_money' => $atServiceBaseMoney,
                'admin_service_charges' => $adminServiceCharges,
                'admin_service_base_money' => $this->signedMoney($adminAedAmount, 'AED'),
            ],
        );
        $fareBreakdown['price_details']['total_price'] = array_merge(
            $commercial['selling_display_money'],
            ['base_money' => $commercial['selling_base_money']],
        );

        $this->mergeAdminChargesIntoFirstPassengerRow(
            $fareBreakdown,
            $adminAedAmount,
            $displayCurrency,
        );

        return $fareBreakdown;
    }

    /**
     * Add the quote-level admin adjustment to one existing Service Charges cell.
     *
     * AT's row amount remains available separately for audit. Applying the
     * admin total to only the first row prevents duplicate charges when a
     * quote contains return trips or multiple passenger types.
     */
    private function mergeAdminChargesIntoFirstPassengerRow(
        array &$fareBreakdown,
        string $adminAedAmount,
        string $displayCurrency,
    ): void {
        if (!isset($fareBreakdown['trips']) || !is_array($fareBreakdown['trips'])) {
            return;
        }

        foreach ($fareBreakdown['trips'] as &$trip) {
            $passengerFares = &$trip['fare']['passenger_fares'];
            if (!is_array($passengerFares ?? null) || empty($passengerFares)) {
                unset($passengerFares);
                continue;
            }

            $passengerFare = &$passengerFares[0];
            $atDisplayMoney = data_get($passengerFare, 'display_money.at_service_charges')
                ?? data_get($passengerFare, 'display_money.service_charges')
                ?? $this->currencyConversionService->makeMoney('0', $displayCurrency);
            $atBaseMoney = data_get($passengerFare, 'base_money.at_service_charges')
                ?? data_get($passengerFare, 'base_money.service_charges')
                ?? $this->currencyConversionService->makeMoney('0', 'AED');

            $atBaseAmount = (string) data_get($atBaseMoney, 'amount', '0');
            $combinedBaseAmount = bcadd($atBaseAmount, $adminAedAmount, 8);
            $adminDisplayMoney = $this->signedConvertedMoney(
                $adminAedAmount,
                'AED',
                $displayCurrency,
            );

            data_set($passengerFare, 'display_money.at_service_charges', $atDisplayMoney);
            data_set($passengerFare, 'display_money.admin_service_charges', $adminDisplayMoney);
            data_set(
                $passengerFare,
                'display_money.service_charges',
                $this->signedConvertedMoney($combinedBaseAmount, 'AED', $displayCurrency),
            );
            data_set($passengerFare, 'base_money.at_service_charges', $atBaseMoney);
            data_set(
                $passengerFare,
                'base_money.admin_service_charges',
                $this->signedMoney($adminAedAmount, 'AED'),
            );
            data_set(
                $passengerFare,
                'base_money.service_charges',
                $this->signedMoney($combinedBaseAmount, 'AED'),
            );

            unset($passengerFare, $passengerFares);
            break;
        }
        unset($trip);
    }

    /** Convert a signed amount while keeping currency validation centralized. */
    private function signedConvertedMoney(string $amount, string $sourceCurrency, string $targetCurrency): array
    {
        $isNegative = bccomp($amount, '0', 8) < 0;
        $absoluteAmount = $isNegative ? bcmul($amount, '-1', 8) : $amount;
        $money = $this->currencyConversionService->convertMoney(
            $absoluteAmount,
            $sourceCurrency,
            $targetCurrency,
        );

        if ($isNegative && bccomp((string) $money['amount'], '0', 8) !== 0) {
            $money['amount'] = '-' . $money['amount'];
        }

        return $money;
    }

    /** Format a signed amount that is already in its target currency. */
    private function signedMoney(string $amount, string $currency): array
    {
        return $this->signedConvertedMoney($amount, $currency, $currency);
    }
}
