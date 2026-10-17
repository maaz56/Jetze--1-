<?php

namespace App\Services;

/** Build provider selling prices in AED while preserving source amounts for audit. */
class CommercialPricingService
{
    private const FORMULA_VERSION = 'commercial-v3';

    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
        private readonly SegmentMarginService $segmentMarginService,
        private readonly PromotionService $promotionService,
    ) {
    }

    /** Add AED selling and selected-display money to every normalized provider fare. */
    public function applyToProviderFlights(array $itineraries): array
    {
        foreach ($itineraries as &$itinerary) {
            $provider = data_get($itinerary, 'provider', []);
            $displayCurrency = strtoupper((string) data_get($itinerary, 'displayCurrencyCode', 'AED'));
            $flights = data_get($itinerary, 'leg.flights', []);

            foreach ($flights as &$flight) {
                foreach ($flight['fares'] ?? [] as &$fare) {
                    $this->initializeFareMoney($fare, $displayCurrency);
                }
                unset($fare);
            }
            unset($flight);
            data_set($itinerary, 'leg.flights', $flights);

            if (!empty($itinerary['leg']['fare_combinations'])) {
                $itinerary['leg']['fare_combinations'] = $this->priceFareCombinations(
                    $itinerary['leg']['fare_combinations'],
                    $itinerary,
                    $provider,
                    $displayCurrency,
                );
                continue;
            }

            foreach ($flights as &$flight) {
                foreach ($flight['fares'] ?? [] as &$fare) {
                    $fareReference = (string) ($fare['ref_id'] ?? '');
                    $costAed = (string) data_get($fare, 'provider_cost_base_money.amount', '0');

                    if ($fareReference === '' || bccomp($costAed, '0', 8) <= 0) {
                        continue;
                    }

                    $promotion = $this->promotionService->commercialRuleForItinerary(
                        $provider,
                        $itinerary,
                        [$fareReference],
                    );
                    $promotionResult = $this->applyAdjustment($costAed, $promotion);
                    $margin = $this->segmentMarginService->commercialRuleForItinerary(
                        $provider,
                        $itinerary,
                        [$fareReference],
                    );
                    $marginResult = $this->applyAdjustment($promotionResult['amount_after'], $margin);

                    $fare['selling_base_money'] = $this->currencyConversionService->makeMoney(
                        $marginResult['amount_after'],
                        'AED',
                    );
                    $fare['selling_display_money'] = $this->currencyConversionService->convertMoney(
                        $marginResult['amount_after'],
                        'AED',
                        $displayCurrency,
                    );
                    $fare['commercial_adjustments'] = $this->buildAdjustmentPayloads(
                        $promotion,
                        $promotionResult,
                        $margin,
                        $marginResult,
                        [$fareReference],
                        [(string) ($flight['ref_id'] ?? '')],
                    );
                }
                unset($fare);
            }
            unset($flight);
            data_set($itinerary, 'leg.flights', $flights);
        }
        unset($itinerary);

        return $itineraries;
    }

    /** Preserve provider cost money on a fare before pricing its complete selection. */
    private function initializeFareMoney(array &$fare, string $displayCurrency): void
    {
        $providerMoney = $fare['provider_booking_money'] ?? null;
        $providerGrossMoney = $fare['provider_gross_money'] ?? $providerMoney;

        if (!is_array($providerMoney) || !isset($providerMoney['amount'], $providerMoney['currency'])
            || !is_array($providerGrossMoney) || !isset($providerGrossMoney['amount'], $providerGrossMoney['currency'])) {
            return;
        }

        $costBaseMoney = $this->currencyConversionService->toBaseMoney(
            $providerMoney['amount'],
            $providerMoney['currency'],
        );
        $grossBaseMoney = $this->currencyConversionService->toBaseMoney(
            $providerGrossMoney['amount'],
            $providerGrossMoney['currency'],
        );

        $fare['provider_cost_base_money'] = $costBaseMoney;
        $fare['provider_gross_base_money'] = $grossBaseMoney;
        $fare['selling_base_money'] = $this->currencyConversionService->makeMoney($costBaseMoney['amount'], 'AED');
        $fare['selling_display_money'] = $this->currencyConversionService->convertMoney(
            $costBaseMoney['amount'],
            'AED',
            $displayCurrency,
        );
        $fare['commercial_adjustments'] = [];
    }

    /** Price each provider-approved return pair as one commercial quote. */
    private function priceFareCombinations(
        array $combinations,
        array $itinerary,
        array $provider,
        string $displayCurrency,
    ): array {
        return collect($combinations)
            ->map(function (array $combination) use ($itinerary, $provider, $displayCurrency): array {
                $fareReferences = array_values(array_filter(
                    $combination['fare_references'] ?? [],
                    'is_string',
                ));
                $selectedFares = $this->selectedFares($itinerary, $fareReferences);
                $providerCurrency = null;
                $providerNetAmount = '0';
                $providerGrossAmount = '0';
                $flightReferences = [];

                foreach ($selectedFares as $item) {
                    $fareCurrency = strtoupper((string) data_get($item, 'fare.provider_booking_money.currency'));
                    $grossCurrency = strtoupper((string) data_get(
                        $item,
                        'fare.provider_gross_money.currency',
                        $fareCurrency,
                    ));

                    if ($providerCurrency !== null && $providerCurrency !== $fareCurrency) {
                        throw new \InvalidArgumentException('Combined provider fares must use one currency.');
                    }
                    if ($grossCurrency !== $fareCurrency) {
                        throw new \InvalidArgumentException('Combined provider gross fares must use the booking currency.');
                    }

                    $providerCurrency = $fareCurrency;
                    $providerNetAmount = bcadd(
                        $providerNetAmount,
                        (string) data_get($item, 'fare.provider_booking_money.amount', '0'),
                        8,
                    );
                    $providerGrossAmount = bcadd(
                        $providerGrossAmount,
                        (string) data_get(
                            $item,
                            'fare.provider_gross_money.amount',
                            data_get($item, 'fare.provider_booking_money.amount', '0'),
                        ),
                        8,
                    );
                    $flightReferences[] = (string) ($item['flight']['ref_id'] ?? '');
                }

                if (!$providerCurrency || bccomp($providerNetAmount, '0', 8) <= 0) {
                    return $combination;
                }

                $costBaseMoney = $this->currencyConversionService->toBaseMoney(
                    $providerNetAmount,
                    $providerCurrency,
                );
                $grossBaseMoney = $this->currencyConversionService->toBaseMoney(
                    $providerGrossAmount,
                    $providerCurrency,
                );
                $promotion = $this->promotionService->commercialRuleForItinerary(
                    $provider,
                    $itinerary,
                    $fareReferences,
                );
                $promotionResult = $this->applyAdjustment($costBaseMoney['amount'], $promotion);
                $margin = $this->segmentMarginService->commercialRuleForItinerary(
                    $provider,
                    $itinerary,
                    $fareReferences,
                );
                $marginResult = $this->applyAdjustment($promotionResult['amount_after'], $margin);

                $combination['provider_booking_money'] = [
                    'amount' => $providerNetAmount,
                    'currency' => $providerCurrency,
                ];
                $combination['provider_gross_money'] = [
                    'amount' => $providerGrossAmount,
                    'currency' => $providerCurrency,
                ];
                $combination['provider_cost_base_money'] = $costBaseMoney;
                $combination['provider_gross_base_money'] = $grossBaseMoney;
                $combination['combined_base_money'] = $this->currencyConversionService->makeMoney(
                    $marginResult['amount_after'],
                    'AED',
                );
                $combination['combined_display_money'] = $this->currencyConversionService->convertMoney(
                    $marginResult['amount_after'],
                    'AED',
                    $displayCurrency,
                );
                $combination['commercial_adjustments'] = $this->buildAdjustmentPayloads(
                    $promotion,
                    $promotionResult,
                    $margin,
                    $marginResult,
                    $fareReferences,
                    $flightReferences,
                );

                return $combination;
            })
            ->sortBy(fn (array $combination): float => (float) (
                data_get($combination, 'combined_base_money.amount')
                    ?? $combination['combined_amount']
                    ?? INF
            ))
            ->values()
            ->all();
    }

    /** Calculate locked selling totals from a fresh provider price for the selected fares. */
    public function quoteTotals(
        array $flight,
        array $fareReferences,
        string $providerNetAmount,
        string $providerGrossAmount,
        string $providerCurrency,
        string $displayCurrency,
    ): array
    {
        $costBaseMoney = $this->currencyConversionService->toBaseMoney($providerNetAmount, $providerCurrency);
        $grossBaseMoney = $this->currencyConversionService->toBaseMoney($providerGrossAmount, $providerCurrency);
        if (bccomp($providerNetAmount, '0', 8) <= 0) {
            throw new \InvalidArgumentException('Fresh provider pricing must be greater than zero.');
        }

        $selectedFares = $this->selectedFares($flight, $fareReferences);
        $flightReferences = [];

        foreach ($selectedFares as $item) {
            $fareCurrency = strtoupper((string) data_get($item, 'fare.provider_booking_money.currency'));
            if ($fareCurrency !== strtoupper($providerCurrency)) {
                throw new \InvalidArgumentException('Selected fares do not use the repriced provider currency.');
            }
            $flightReferences[] = (string) ($item['flight']['ref_id'] ?? '');
        }

        $promotion = $this->promotionService->commercialRuleForItinerary(
            $flight['provider'] ?? [],
            $flight,
            $fareReferences,
        );
        $promotionResult = $this->applyAdjustment($costBaseMoney['amount'], $promotion);
        $margin = $this->segmentMarginService->commercialRuleForItinerary(
            $flight['provider'] ?? [],
            $flight,
            $fareReferences,
        );
        $marginResult = $this->applyAdjustment($promotionResult['amount_after'], $margin);
        $sellingAed = $marginResult['amount_after'];
        $adjustments = $this->buildAdjustmentPayloads(
            $promotion,
            $promotionResult,
            $margin,
            $marginResult,
            $fareReferences,
            $flightReferences,
        );
        $this->assertQuoteReconciles($costBaseMoney['amount'], $sellingAed, $adjustments);

        return [
            'provider_cost_base_money' => $costBaseMoney,
            'provider_gross_base_money' => $grossBaseMoney,
            'selling_base_money' => $this->currencyConversionService->makeMoney($sellingAed, 'AED'),
            'selling_display_money' => $this->currencyConversionService->convertMoney($sellingAed, 'AED', $displayCurrency),
            'adjustments' => $adjustments,
        ];
    }

    /** Apply one rule in AED and cap discounts so no intermediate total is negative. */
    private function applyAdjustment(string $amountBeforeAdjustment, ?array $rule): array
    {
        if (!$rule) {
            return [
                'amount_before' => $amountBeforeAdjustment,
                'signed_amount' => '0',
                'amount_after' => $amountBeforeAdjustment,
            ];
        }

        $value = (string) $rule['configured_value'];
        if ($rule['calculation_type'] === 'percentage') {
            // The input is already the total AED cost for this selected leg.
            $amount = bcdiv(bcmul($amountBeforeAdjustment, $value, 12), '100', 12);
        } else {
            $multiplier = (string) max((int) $rule['passenger_count'] * (int) $rule['segment_count'], 1);
            $amount = bcmul($value, $multiplier, 8);
        }

        // Lock every normal AED adjustment at AED precision. This prevents a
        // display/payment mismatch caused by retaining hidden fractions.
        $amount = $this->currencyConversionService->makeMoney($amount, 'AED')['amount'];

        $signedAmount = bccomp($amount, '0', 2) === 0
            ? '0.00'
            : ($rule['direction'] === 'discount' ? '-' . $amount : $amount);
        $amountAfter = bcadd($amountBeforeAdjustment, $signedAmount, 8);

        if (bccomp($amountAfter, '0', 8) < 0) {
            $signedAmount = bcmul($amountBeforeAdjustment, '-1', 8);
            $amountAfter = '0';
        }

        return [
            'amount_before' => $amountBeforeAdjustment,
            'signed_amount' => $signedAmount,
            'amount_after' => $amountAfter,
        ];
    }

    /** Create one ordered adjustment payload for search display and later quote persistence. */
    private function adjustmentPayload(string $type, int $order, ?array $rule, array $result): ?array
    {
        if (!$rule) {
            return null;
        }

        $audit = [
            'type' => $type,
            'application_order' => $order,
            'application_scope' => $rule['application_scope'] ?? null,
            'aed_amount' => $this->currencyConversionService->makeMoney(ltrim($result['signed_amount'], '-'), 'AED')['amount'],
            'signed_aed_amount' => $result['signed_amount'],
            'amount_before_aed' => $result['amount_before'],
            'amount_after_aed' => $result['amount_after'],
            'formula_version' => self::FORMULA_VERSION,
        ];

        $audit['snapshot'] = array_merge((array) ($rule['snapshot'] ?? []), [
            'direction' => $rule['direction'],
            'calculation_type' => $rule['calculation_type'],
            'configured_value' => $rule['configured_value'],
            'passenger_count' => $rule['passenger_count'],
            'segment_count' => $rule['segment_count'],
            'application_order' => $order,
            'application_scope' => $audit['application_scope'],
            'amount_before_aed' => $result['amount_before'],
            'signed_aed_amount' => $result['signed_amount'],
            'amount_after_aed' => $result['amount_after'],
            'formula_version' => self::FORMULA_VERSION,
        ]);

        return array_merge($rule, $audit);
    }

    /** Build promotion then segment-margin audit rows for one complete selection. */
    private function buildAdjustmentPayloads(
        ?array $promotion,
        array $promotionResult,
        ?array $margin,
        array $marginResult,
        array $fareReferences,
        array $flightReferences,
    ): array {
        $fareReferences = array_values(array_unique(array_filter(
            $fareReferences,
            static fn ($reference): bool => is_string($reference) && $reference !== '',
        )));
        $flightReferences = array_values(array_unique(array_filter(
            $flightReferences,
            static fn ($reference): bool => is_string($reference) && $reference !== '',
        )));

        return array_values(array_filter(array_map(
            function (?array $adjustment) use ($fareReferences, $flightReferences): ?array {
                if (!$adjustment) {
                    return null;
                }

                $adjustment['fare_reference'] = implode('|', $fareReferences) ?: null;
                $adjustment['flight_reference'] = implode('|', $flightReferences) ?: null;
                $adjustment['snapshot']['fare_references'] = $fareReferences;
                $adjustment['snapshot']['flight_references'] = $flightReferences;

                return $adjustment;
            },
            [
                $this->adjustmentPayload('promotion', 1, $promotion, $promotionResult),
                $this->adjustmentPayload('segment_margin', 2, $margin, $marginResult),
            ],
        )));
    }

    /** Ensure provider cost plus every signed adjustment equals the locked selling total. */
    private function assertQuoteReconciles(string $providerAed, string $sellingAed, array $adjustments): void
    {
        $expected = $providerAed;

        foreach ($adjustments as $adjustment) {
            $expected = bcadd($expected, (string) $adjustment['signed_aed_amount'], 8);
        }

        if (bccomp($expected, $sellingAed, 8) !== 0) {
            throw new \LogicException('Commercial pricing adjustments do not reconcile with the selling total.');
        }
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
            throw new \InvalidArgumentException('Selected provider fare pricing data is incomplete.');
        }

        return $selected;
    }
}
