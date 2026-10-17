<?php

namespace App\Services;

use App\Models\BookingPriceSnapshot;
use App\Models\FlightBookings;
use App\Models\PriceQuote;
use Illuminate\Support\Facades\DB;

class BookingPricingService
{
    // Public booking-price functions

    /**
     * Permanently lock the quote values after a provider booking succeeds.
     */
    public function createSnapshot(FlightBookings $booking, PriceQuote $quote): BookingPriceSnapshot
    {
        return DB::transaction(function () use ($booking, $quote) {
            $adjustments = $quote->adjustments()->orderBy('id')->get();
            $this->assertQuoteReconciles($quote, $adjustments->all());

            $snapshot = BookingPriceSnapshot::firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'price_quote_id' => $quote->id,
                    'quote_uuid' => $quote->uuid,
                    'provider' => $quote->provider,
                    'provider_amount' => $quote->provider_amount,
                    'provider_currency' => $quote->provider_currency,
                    'provider_rate_to_aed' => $quote->provider_rate_to_aed,
                    'provider_aed_amount' => $quote->provider_aed_amount,
                    'provider_gross_amount' => $quote->provider_gross_amount,
                    'provider_gross_currency' => $quote->provider_currency,
                    'provider_gross_aed_amount' => $quote->provider_gross_aed_amount,
                    'selling_amount' => $quote->display_amount,
                    'selling_currency' => $quote->display_currency,
                    'selling_rate_to_aed' => $quote->display_rate_to_aed,
                    'aed_amount' => $quote->aed_amount,
                    'adjustments_snapshot' => $adjustments
                        ->values()
                        ->map(fn ($adjustment, int $sequence) => [
                            'sequence' => $sequence + 1,
                            'type' => $adjustment->type,
                            'application_order' => $adjustment->application_order,
                            'rule_id' => $adjustment->rule_id,
                            'title' => $adjustment->title,
                            'direction' => $adjustment->direction,
                            'calculation_type' => $adjustment->calculation_type,
                            'application_scope' => $adjustment->application_scope,
                            'configured_value' => $adjustment->configured_value,
                            'passenger_count' => $adjustment->passenger_count,
                            'segment_count' => $adjustment->segment_count,
                            'aed_amount' => $adjustment->aed_amount,
                            'amount_before_aed' => $adjustment->amount_before_aed,
                            'amount_after_aed' => $adjustment->amount_after_aed,
                            'fare_reference' => $adjustment->fare_reference,
                            'flight_reference' => $adjustment->flight_reference,
                            'formula_version' => $adjustment->formula_version,
                            'rule_snapshot' => $adjustment->rule_snapshot,
                        ])
                        ->all(),
                ],
            );

            $booking->update([
                'price_quote_id' => $quote->id,
                'price_snapshot_id' => $snapshot->id,
                'selling_currency' => $snapshot->selling_currency,
                'selling_amount' => $snapshot->selling_amount,
                'aed_amount' => $snapshot->aed_amount,
                'amount' => $snapshot->selling_amount,
            ]);

            return $snapshot;
        });
    }

    /** Block a permanent snapshot when the locked quote arithmetic is inconsistent. */
    private function assertQuoteReconciles(PriceQuote $quote, array $adjustments): void
    {
        if ($quote->provider_aed_amount === null) {
            return;
        }

        $expectedSellingAed = (string) $quote->provider_aed_amount;
        foreach ($adjustments as $adjustment) {
            $expectedSellingAed = bcadd($expectedSellingAed, (string) $adjustment->aed_amount, 8);
        }

        $difference = bcsub($expectedSellingAed, (string) $quote->aed_amount, 8);
        $absoluteDifference = str_starts_with($difference, '-') ? substr($difference, 1) : $difference;

        // One fils tolerance covers only conversion/display rounding. Any real
        // calculation difference prevents a financially unsafe snapshot.
        if (bccomp($absoluteDifference, '0.01000000', 8) > 0) {
            throw new \LogicException('The quote cannot be snapshotted because its commercial pricing does not reconcile.');
        }
    }

    /**
     * Load the permanent snapshot that is the only source for a booking payment.
     */
    public function snapshotFor(FlightBookings $booking): BookingPriceSnapshot
    {
        return $booking->priceSnapshot()->firstOrFail();
    }
}
