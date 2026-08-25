<?php

namespace App\Services;

use App\Models\FlightBookings;
use App\Models\ProviderBookingEvent;

class ProviderBookingEventService
{
    // Public provider-event functions

    /**
     * Store one immutable provider response without changing the locked booking price.
     */
    public function record(
        FlightBookings $booking,
        string $provider,
        string $stage,
        mixed $responseData,
        ?string $providerReference = null,
    ): ProviderBookingEvent {
        $snapshot = $booking->priceSnapshot()->first();

        return ProviderBookingEvent::create([
            'booking_id' => $booking->id,
            'provider' => strtolower($provider),
            'stage' => $stage,
            'provider_reference' => $providerReference ?? $booking->itinerary_ref,
            'provider_amount' => $snapshot?->provider_amount,
            'provider_currency' => $snapshot?->provider_currency,
            'response_data' => $this->normalizeResponse($responseData),
        ]);
    }

    // Helper functions

    /**
     * Convert any provider response into JSON-safe data for the audit record.
     */
    private function normalizeResponse(mixed $responseData): array
    {
        if (is_array($responseData)) {
            return $responseData;
        }

        if (is_object($responseData)) {
            return (array) $responseData;
        }

        if (is_string($responseData)) {
            $decoded = json_decode($responseData, true);

            return is_array($decoded) ? $decoded : ['raw' => $responseData];
        }

        return ['raw' => $responseData];
    }
}
