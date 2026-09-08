<?php

namespace App\Services;

use App\Models\HotelBooking;
use App\Models\HotelBookingPriceSnapshot;
use App\Models\HotelPriceQuote;
use Illuminate\Support\Facades\DB;

/** Copy the active PreBook quote into an immutable record after TBO confirms the booking. */
class HotelBookingPricingService
{
    public function createSnapshot(HotelBooking $booking, HotelPriceQuote $quote): HotelBookingPriceSnapshot
    {
        return DB::transaction(function () use ($booking, $quote) {
            return HotelBookingPriceSnapshot::firstOrCreate(
                ['hotel_booking_id' => $booking->id],
                [
                    'hotel_price_quote_id' => $quote->id,
                    'quote_uuid' => $quote->uuid,
                    'provider' => $quote->provider,
                    'provider_amount' => $quote->provider_amount,
                    'provider_currency' => $quote->provider_currency,
                    'provider_rate_to_aed' => $quote->provider_rate_to_aed,
                    'provider_aed_amount' => $quote->provider_aed_amount,
                    'selling_amount' => $quote->selling_amount,
                    'selling_currency' => $quote->selling_currency,
                    'selling_rate_to_aed' => $quote->selling_rate_to_aed,
                    'aed_amount' => $quote->aed_amount,
                    'adjustments_snapshot' => $quote->adjustments_snapshot ?? [],
                ],
            );
        });
    }
}
