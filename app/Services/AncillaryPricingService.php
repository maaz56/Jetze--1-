<?php

namespace App\Services;

use App\Models\PriceQuote;
use App\Models\PriceQuoteItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AncillaryPricingService
{
    public function __construct(private readonly CurrencyConversionService $currencyConversionService)
    {
    }

    /**
     * Replace active AT selections with provider-validated ancillary option data.
     */
    public function replaceSelections(PriceQuote $quote, array $selections): PriceQuote
    {
        return DB::transaction(function () use ($quote, $selections) {
            $selectionKeys = [];

            foreach ($selections as $selection) {
                $key = implode(':', [
                    $selection['type'],
                    $selection['trip_index'],
                    $selection['journey_index'],
                    $selection['segment_index'],
                    $selection['passenger_id'],
                ]);

                if (isset($selectionKeys[$key])) {
                    throw ValidationException::withMessages([
                        'selections' => 'Only one ancillary can be selected for each passenger, service type, and segment.',
                    ]);
                }

                $selectionKeys[$key] = true;
            }

            $quote->items()->active()->update([
                'status' => 'removed',
                'removed_at' => now(),
            ]);

            foreach ($selections as $selection) {
                PriceQuoteItem::updateOrCreate(
                    [
                        'price_quote_id' => $quote->id,
                        'type' => $selection['type'],
                        'trip_index' => $selection['trip_index'],
                        'journey_index' => $selection['journey_index'],
                        'segment_index' => $selection['segment_index'],
                        'passenger_id' => $selection['passenger_id'],
                    ],
                    [
                        'status' => 'active',
                        'fuid' => $selection['fuid'],
                        'ssid' => $selection['ssid'],
                        'title' => $selection['title'],
                        'provider_references' => $selection['provider_references'],
                        'provider_amount' => $selection['provider_money']['amount'],
                        'provider_currency' => $selection['provider_money']['currency'],
                        'provider_rate_to_aed' => $quote->provider_rate_to_aed,
                        'aed_amount' => $selection['base_money']['amount'],
                        'display_amount' => $selection['display_money']['amount'],
                        'display_currency' => $selection['display_money']['currency'],
                        'display_rate_to_aed' => $quote->display_rate_to_aed,
                        'provider_item_data' => $selection['provider_item_data'],
                        'selected_at' => now(),
                        'removed_at' => null,
                    ],
                );
            }

            $this->recalculateQuote($quote);

            return $quote->fresh(['items']);
        });
    }

    /**
     * Reapply the quote's locked rates to AT options before they are displayed or selected.
     */
    public function applyQuoteRates(array $ancillaries, PriceQuote $quote): array
    {
        foreach (['ssrData' => 'SSR', 'seatLayout' => 'Seats'] as $section => $itemsKey) {
            foreach (data_get($ancillaries, 'data.' . $section . '.Trips', []) as $tripIndex => $trip) {
                foreach ($trip['Journey'] ?? [] as $journeyIndex => $journey) {
                    foreach ($journey['Segments'] ?? [] as $segmentIndex => $segment) {
                        foreach ($segment[$itemsKey] ?? [] as $itemIndex => $item) {
                            $ancillaries['data'][$section]['Trips'][$tripIndex]['Journey'][$journeyIndex]
                                ['Segments'][$segmentIndex][$itemsKey][$itemIndex] = array_merge(
                                    $item,
                                    $this->quoteMoney($quote, (string) data_get($item, 'provider_money.amount', '0')),
                                );
                        }
                    }
                }
            }
        }

        return $ancillaries;
    }

    /**
     * Return money totals for active ancillary lines using values locked on the quote.
     */
    public function ancillaryTotals(PriceQuote $quote): array
    {
        $items = $quote->items()->active()->get();

        $providerAmount = '0';
        $aedAmount = '0';
        $displayAmount = '0';

        foreach ($items as $item) {
            $providerAmount = bcadd($providerAmount, (string) $item->provider_amount, 8);
            $aedAmount = bcadd($aedAmount, (string) $item->aed_amount, 8);
            $displayAmount = bcadd($displayAmount, (string) $item->display_amount, 8);
        }

        return [
            'provider_money' => $this->money($providerAmount, $quote->provider_currency),
            'base_money' => $this->money($aedAmount, 'AED'),
            'display_money' => $this->money($displayAmount, $quote->display_currency),
        ];
    }

    /** Return backend-calculated ancillary totals for each outbound or return flight. */
    public function ancillaryTotalsByTrip(PriceQuote $quote): array
    {
        $totals = [];

        foreach ($quote->items()->active()->get()->groupBy('trip_index') as $tripIndex => $items) {
            $providerAmount = '0';
            $aedAmount = '0';
            $displayAmount = '0';

            foreach ($items as $item) {
                $providerAmount = bcadd($providerAmount, (string) $item->provider_amount, 8);
                $aedAmount = bcadd($aedAmount, (string) $item->aed_amount, 8);
                $displayAmount = bcadd($displayAmount, (string) $item->display_amount, 8);
            }

            $totals[(int) $tripIndex] = [
                'provider_money' => $this->money($providerAmount, $quote->provider_currency),
                'base_money' => $this->money($aedAmount, 'AED'),
                'display_money' => $this->money($displayAmount, $quote->display_currency),
            ];
        }

        return $totals;
    }

    /**
     * Recalculate provider cost and customer selling totals after ancillary changes.
     *
     * AT receives Net Fare plus SSR cost, while the customer continues to pay the
     * quote's locked gross selling fare (including its locked adjustments) plus SSRs.
     */
    private function recalculateQuote(PriceQuote $quote): void
    {
        $totals = $this->ancillaryTotals($quote);
        $fareNetAmount = (string) (data_get($quote->provider_pricing_data, 'net_amount') ?? $quote->provider_amount);
        $providerAmount = bcadd($fareNetAmount, $totals['provider_money']['amount'], 8);
        $providerAedAmount = bcmul($providerAmount, (string) $quote->provider_rate_to_aed, 12);
        $fareSellingMoney = $this->lockedFareSellingMoney($quote, $totals);
        $aedAmount = bcadd($fareSellingMoney['base_amount'], $totals['base_money']['amount'], 12);
        $displayAmount = bcadd($fareSellingMoney['display_amount'], $totals['display_money']['amount'], 12);

        $quote->update([
            'provider_amount' => $this->round($providerAmount, $quote->provider_currency),
            'provider_aed_amount' => $this->round($providerAedAmount, 'AED'),
            'aed_amount' => $this->round($aedAmount, 'AED'),
            'display_amount' => $this->round($displayAmount, $quote->display_currency),
        ]);
    }

    /**
     * Return the fare-only selling price locked when the quote was created.
     *
     * The gross provider fare and saved margin/promotion rows are immutable, so an
     * ancillary replacement cannot accidentally turn the customer total back into
     * Net Fare. The fallback supports older quotes without commercial audit fields.
     *
     * @param array{provider_money: array{amount: string}, base_money: array{amount: string}, display_money: array{amount: string}} $activeTotals
     * @return array{base_amount: string, display_amount: string}
     */
    private function lockedFareSellingMoney(PriceQuote $quote, array $activeTotals): array
    {
        if ($quote->provider_gross_aed_amount !== null) {
            $adjustmentAmount = (string) $quote->adjustments()->sum('aed_amount');
            $baseAmount = bcadd((string) $quote->provider_gross_aed_amount, $adjustmentAmount, 12);
            $displayAmount = bcdiv($baseAmount, (string) $quote->display_rate_to_aed, 12);

            return [
                'base_amount' => $baseAmount,
                'display_amount' => $displayAmount,
            ];
        }

        return [
            'base_amount' => bcsub((string) $quote->aed_amount, $activeTotals['base_money']['amount'], 12),
            'display_amount' => bcsub((string) $quote->display_amount, $activeTotals['display_money']['amount'], 12),
        ];
    }

    /** Convert a provider amount using rates stored on the quote, not today's admin rates. */
    private function quoteMoney(PriceQuote $quote, string $providerAmount): array
    {
        $aedAmount = bcmul($providerAmount, (string) $quote->provider_rate_to_aed, 12);
        $displayAmount = bcdiv($aedAmount, (string) $quote->display_rate_to_aed, 12);

        return [
            'provider_money' => $this->money($providerAmount, $quote->provider_currency),
            'base_money' => $this->money($aedAmount, 'AED'),
            'display_money' => $this->money($displayAmount, $quote->display_currency),
        ];
    }

    /** Build a display-ready money object using the configured decimal precision. */
    private function money(string $amount, string $currency): array
    {
        return [
            'amount' => $this->round($amount, $currency),
            'currency' => $currency,
            'decimal_places' => $this->currencyConversionService->decimalPlacesFor($currency),
        ];
    }

    /** Round a BCMath decimal value without using floating point arithmetic. */
    private function round(string $amount, string $currency): string
    {
        $decimalPlaces = $this->currencyConversionService->decimalPlacesFor($currency);
        $factor = '1' . str_repeat('0', $decimalPlaces);
        $scaledAmount = bcmul($amount, $factor, max(12, $decimalPlaces + 8));

        return bcdiv(
            bcadd($scaledAmount, '0.5', max(12, $decimalPlaces + 8)),
            $factor,
            $decimalPlaces,
        );
    }
}
