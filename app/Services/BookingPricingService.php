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
            $quote->loadMissing('adjustments');

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
                    'selling_amount' => $quote->display_amount,
                    'selling_currency' => $quote->display_currency,
                    'selling_rate_to_aed' => $quote->display_rate_to_aed,
                    'aed_amount' => $quote->aed_amount,
                    'adjustments_snapshot' => $quote->adjustments
                        ->map(fn ($adjustment) => [
                            'type' => $adjustment->type,
                            'rule_id' => $adjustment->rule_id,
                            'title' => $adjustment->title,
                            'direction' => $adjustment->direction,
                            'calculation_type' => $adjustment->calculation_type,
                            'configured_value' => $adjustment->configured_value,
                            'passenger_count' => $adjustment->passenger_count,
                            'segment_count' => $adjustment->segment_count,
                            'aed_amount' => $adjustment->aed_amount,
                            'rule_snapshot' => $adjustment->rule_snapshot,
                        ])
                        ->values()
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

    /**
     * Load the permanent snapshot that is the only source for a booking payment.
     */
    public function snapshotFor(FlightBookings $booking): BookingPriceSnapshot
    {
        return $booking->priceSnapshot()->firstOrFail();
    }
}
