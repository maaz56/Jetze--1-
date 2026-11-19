<?php

namespace App\Jobs;

use App\Models\FlightBookings;
use App\Services\AtApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * AT does not return the CRS PNR straight after CreateItinerary/hold, so poll
 * RetrieveBooking in the background until it appears and store it.
 */
class SyncAtBookingPnrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public int $bookingId)
    {
    }

    /** Seconds to wait before each retry (15s, 30s, 1m, 2m, 5m). */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 300];
    }

    public function handle(AtApiService $service): void
    {
        $booking = FlightBookings::query()->find($this->bookingId);
        if (! $booking || strtolower((string) $booking->flight_provider) !== 'at') {
            return;
        }

        $transactionId = trim((string) $booking->transaction_id);
        if ($transactionId === '') {
            return;
        }

        $currentRef = trim((string) $booking->itinerary_ref);
        $placeholders = array_map('trim', explode(',', $transactionId));
        $needsCrs = $currentRef === '' || in_array($currentRef, $placeholders, true);
        if (! $needsCrs && filled($booking->airline_pnr)) {
            return; // Nothing left to sync.
        }

        $data = $service->getBookingDetails(new Request(['pnr' => $transactionId]));
        $flight = data_get($data, 'bookingDetails.Trips.0.Journey.0.Segments.0.Flight');
        $crsPnr = trim((string) data_get($flight, 'CRSPNR', ''));
        $airlinePnr = trim((string) (data_get($flight, 'AirlinePNR') ?: data_get($flight, 'APNR', '')));

        if ($needsCrs && $crsPnr !== '') {
            $booking->itinerary_ref = $crsPnr;
            $booking->pnr = $crsPnr;
        }
        if ($airlinePnr !== '') {
            $booking->airline_pnr = $airlinePnr;
        }
        if ($booking->isDirty()) {
            $booking->save();
        }

        if ($needsCrs && $crsPnr === '') {
            // Release back to the queue; throws after the last attempt.
            Log::info("AT CRS PNR not ready for booking {$booking->id}, attempt {$this->attempts()}.");
            $this->release($this->backoff()[min($this->attempts() - 1, 4)]);
        }
    }
}
