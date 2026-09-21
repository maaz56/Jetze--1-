<?php

namespace App\Jobs;

use App\Models\FlightBookings;
use App\Models\PaymentAttempt;
use App\Services\TravelPortService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CancelBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $booking;

    public function __construct($booking)
    {
        $this->booking = $booking;
    }
    public function handle(TravelPortService $travelportApiService)
    {
        // This delayed job contains a serialized snapshot. Reload the current
        // booking so a later payment/ticketing transition is respected.
        $booking = FlightBookings::query()->find($this->booking->id);
        if (! $booking) {
            return;
        }

        Log::info($booking);
        Log::info("Auto-cancel initiated for Booking ID: {$booking->id}, PNR: {$booking->itinerary_ref}");
        //     //Log::info("Auto-cancel initiated for Booking ID: {$this->bookingId}, PNR: {$this->pnr}");

        if ($booking->status !== 'booked') {
            Log::info("Booking ID: {$booking->id} is already '{$booking->status}', skipping auto-cancel.");
            return;
        }
        Log::info("Booking ID: {$booking->id} is currently '{$booking->status}', proceeding with auto-cancel.");

        $nomodAttempt = PaymentAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', PaymentAttempt::PROVIDER_NOMOD)
            ->latest('id')
            ->first();

        if ($nomodAttempt && (
            $nomodAttempt->status === PaymentAttempt::STATUS_PAID
            || in_array($nomodAttempt->fulfilment_status, [
                PaymentAttempt::FULFILMENT_PENDING,
                PaymentAttempt::FULFILMENT_PROCESSING,
                PaymentAttempt::FULFILMENT_RECONCILIATION_REQUIRED,
            ], true)
        )) {
            Log::info("Booking ID: {$booking->id} has a paid/in-progress Nomod attempt; skipping auto-cancel.");
            return;
        }
      
        if ($booking->flight_provider == 'travelport') {
            Log::info('Canceling booking via Travelport API');
            
            $pnrStatus = $travelportApiService->cancelReservation($booking->itinerary_ref);
            Log::info($pnrStatus);
            if ($pnrStatus != null) {
                $booking = FlightBookings::where('id', $booking->id)->first();
                $booking->status = 'canceled';
                $booking->save();
            }
        } else if($booking->flight_provider == "OneApi") {
            Log::info('Canceling booking via OneApi API');
            // $pnrStatus = $oneApiService->cancelReservation($this->booking->itinerary_ref);
            // Log::info($pnrStatus);
            // if ($pnrStatus != null) {
                $booking = FlightBookings::where('id', $booking->id)->first();
                $booking->status = 'canceled';
                $booking->save();
            // }
        } else if ($booking->flight_provider == 'airsial') {
            Log::info('Canceling booking via Airsial API');
            // $pnrStatus = $airsialApiService->cancelReservation($this->booking->itinerary_ref);
            // Log::info($pnrStatus);
            // if ($pnrStatus != null) {
                $booking = FlightBookings::where('id', $booking->id)->first();
                $booking->status = 'canceled';
                $booking->save();
            // }
        }


        //     $booking = FlightBookings::where('id', $this->bookingId)->first();
        //    // Log::info($booking );

        //     if (!$booking) {
        //        // Log::error("Booking not found for PNR: " . $this->pnr);
        //         return;
        //     }
        //     if ($booking->status !== 'booked') {
        //        // Log::info("Booking for PNR {$this->pnr} is already '{$booking->status}', skipping auto-cancel.");
        //         return;
        //     }
        //     Log::info("Booking for PNR {$this->pnr} has'{$booking->status}'");

        //Log::info("Auto-cancel response: " . json_encode($response));
    }

}
