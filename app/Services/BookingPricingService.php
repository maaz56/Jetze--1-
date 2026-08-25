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
            $snapshot = BookingPriceSnapshot::firstOrCreate(
                ['booking_id' => $booking->id],
                [
                    'price_quote_id' => $quote->id,
                    'quote_uuid' => $quote->uuid,
                    'provider' => $quote->provider,
                    'provider_amount' => $quote->provider_amount,
                    'provider_currency' => $quote->provider_currency,
                    'provider_rate_to_aed' => $quote->provider_rate_to_aed,
                    'selling_amount' => $quote->display_amount,
                    'selling_currency' => $quote->display_currency,
                    'selling_rate_to_aed' => $quote->display_rate_to_aed,
                    'aed_amount' => $quote->aed_amount,
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
