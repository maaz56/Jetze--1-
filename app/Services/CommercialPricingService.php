<?php

namespace App\Services;

/** Build AT selling prices in AED while preserving the provider booking amount. */
class CommercialPricingService
{
    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
        private readonly SegmentMarginService $segmentMarginService,
        private readonly PromotionService $promotionService,
    ) {
    }

    /** Add AED selling and selected-display money to AT search fares. */
    public function applyToAtFlights(array $itineraries): array
    {
        foreach ($itineraries as &$itinerary) {
            if (strtolower((string) data_get($itinerary, 'provider.name')) !== 'at') {
                continue;
            }

            $provider = data_get($itinerary, 'provider', []);
            $displayCurrency = strtoupper((string) data_get($itinerary, 'displayCurrencyCode', 'AED'));

            foreach (data_get($itinerary, 'leg.flights', []) as &$flight) {
                foreach ($flight['fares'] ?? [] as &$fare) {
                    $providerMoney = $fare['provider_booking_money'] ?? null;
                    if (!is_array($providerMoney) || !isset($providerMoney['amount'], $providerMoney['currency'])) {
                        continue;
                    }

                    $costBaseMoney = $this->currencyConversionService->toBaseMoney(
                        $providerMoney['amount'],
                        $providerMoney['currency'],
                    );
                    $margin = $this->segmentMarginService->commercialRuleForFare($provider, $flight);
                    $marginAmount = $this->adjustmentAmount($costBaseMoney['amount'], $margin);
                    $beforePromotion = bcadd($costBaseMoney['amount'], $marginAmount, 8);
                    $promotion = $this->promotionService->commercialRuleForFare($provider, $flight);
                    $promotionAmount = $this->adjustmentAmount($beforePromotion, $promotion);
                    $sellingAed = bcadd($beforePromotion, $promotionAmount, 8);

                    if (bccomp($sellingAed, '0', 8) < 0) {
                        $sellingAed = '0';
                    }

                    $fare['provider_cost_base_money'] = $costBaseMoney;
                    $fare['selling_base_money'] = $this->currencyConversionService->makeMoney($sellingAed, 'AED');
                    $fare['selling_display_money'] = $this->currencyConversionService->convertMoney($sellingAed, 'AED', $displayCurrency);
                    $fare['commercial_adjustments'] = array_values(array_filter([
                        $this->adjustmentPayload('segment_margin', $margin, $marginAmount),
                        $this->adjustmentPayload('promotion', $promotion, $promotionAmount),
                    ]));
                }
                unset($fare);
            }
            unset($flight);
        }
        unset($itinerary);

        return $itineraries;
    }

    /** Calculate locked selling totals from AT's fresh provider price for the selected fares. */
    public function quoteTotals(array $flight, array $fareReferences, string $providerAmount, string $providerCurrency, string $displayCurrency): array
    {
        $costBaseMoney = $this->currencyConversionService->toBaseMoney($providerAmount, $providerCurrency);
        $selectedFares = $this->selectedFares($flight, $fareReferences);
        $previousTotal = '0';

        foreach ($selectedFares as $item) {
            $previousTotal = bcadd($previousTotal, (string) $item['fare']['provider_booking_money']['amount'], 8);
        }

        $adjustments = [];
        $allocatedCost = '0';
        $sellingAed = '0';
        $lastIndex = count($selectedFares) - 1;

        foreach ($selectedFares as $index => $item) {
            $fareCost = $index === $lastIndex
                ? bcsub($costBaseMoney['amount'], $allocatedCost, 8)
                : bcdiv(bcmul($costBaseMoney['amount'], (string) $item['fare']['provider_booking_money']['amount'], 12), $previousTotal, 8);
            $allocatedCost = bcadd($allocatedCost, $fareCost, 8);

            $margin = $this->segmentMarginService->commercialRuleForFare($flight['provider'] ?? [], $item['flight']);
            $marginAmount = $this->adjustmentAmount($fareCost, $margin);
            $promotion = $this->promotionService->commercialRuleForFare($flight['provider'] ?? [], $item['flight']);
            $promotionAmount = $this->adjustmentAmount(bcadd($fareCost, $marginAmount, 8), $promotion);
            $sellingAed = bcadd($sellingAed, bcadd(bcadd($fareCost, $marginAmount, 8), $promotionAmount, 8), 8);

            foreach ([
                $this->adjustmentPayload('segment_margin', $margin, $marginAmount),
                $this->adjustmentPayload('promotion', $promotion, $promotionAmount),
            ] as $adjustment) {
                if ($adjustment) {
                    $adjustment['rule_snapshot']['fare_ref_id'] = $item['fare']['ref_id'];
                    $adjustment['rule_snapshot']['flight_ref_id'] = $item['flight']['ref_id'] ?? null;
                    $adjustments[] = $adjustment;
                }
            }
        }

        $sellingAed = bccomp($sellingAed, '0', 8) < 0 ? '0' : $sellingAed;

        return [
            'provider_cost_base_money' => $costBaseMoney,
            'selling_base_money' => $this->currencyConversionService->makeMoney($sellingAed, 'AED'),
            'selling_display_money' => $this->currencyConversionService->convertMoney($sellingAed, 'AED', $displayCurrency),
            'adjustments' => $adjustments,
        ];
    }

    /** Calculate one signed AED adjustment from its rule snapshot. */
    private function adjustmentAmount(string $amountBeforeAdjustment, ?array $rule): string
    {
        if (!$rule) {
            return '0';
        }

        $value = (string) $rule['configured_value'];
        if ($rule['calculation_type'] === 'percentage') {
            // The input is already the total AED cost for this selected leg.
            $amount = bcdiv(bcmul($amountBeforeAdjustment, $value, 12), '100', 12);
        } else {
            $multiplier = (string) max((int) $rule['passenger_count'] * (int) $rule['segment_count'], 1);
            $amount = bcmul($value, $multiplier, 8);
        }

        return $rule['direction'] === 'discount' ? '-' . $amount : $amount;
    }

    /** Create the immutable adjustment data that will be persisted with a quote in phase 2. */
    private function adjustmentPayload(string $type, ?array $rule, string $amount): ?array
    {
        if (!$rule) {
            return null;
        }

        return array_merge($rule, [
            'type' => $type,
            'aed_amount' => $this->currencyConversionService->makeMoney(ltrim($amount, '-'), 'AED')['amount'],
            'signed_aed_amount' => $amount,
        ]);
    }

    /** Find the trusted selected fare and flight pair from one cached search result. */
    private function selectedFares(array $flight, array $fareReferences): array
    {
        $references = array_flip($fareReferences);
        $selected = [];

        foreach (data_get($flight, 'leg.flights', []) as $flightItem) {
            foreach ($flightItem['fares'] ?? [] as $fare) {
                if (isset($references[$fare['ref_id'] ?? '']) && isset($fare['provider_booking_money']['amount'])) {
                    $selected[] = ['flight' => $flightItem, 'fare' => $fare];
                }
            }
        }

        if (count($selected) !== count($fareReferences)) {
            throw new \InvalidArgumentException('Selected AT fare pricing data is incomplete.');
        }

        return $selected;
    }
}
