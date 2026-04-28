<?php

namespace App\Jobs;

use App\Models\FlightBookings;
use App\Services\AirBlueApiService;
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
    public function handle(AirBlueApiService $airBlueApiService, TravelPortService $travelportApiService)
    {

        Log::info($this->booking);
        Log::info("Auto-cancel initiated for Booking ID: {$this->booking->id}, PNR: {$this->booking->itinerary_ref}");
        //     //Log::info("Auto-cancel initiated for Booking ID: {$this->bookingId}, PNR: {$this->pnr}");

        if ($this->booking->status !== 'booked') {
            Log::info("Booking ID: {$this->booking->id} is already '{$this->booking->status}', skipping auto-cancel.");
            return;
        }
        Log::info("Booking ID: {$this->booking->id} is currently '{$this->booking->status}', proceeding with auto-cancel.");
      
        if ($this->booking->flight_provider == 'airblue') {
            Log::info('Canceling booking via Airblue API');
            $pnrStatus = $airBlueApiService->cancelBooking($this->booking->itinerary_ref);
            Log::info($pnrStatus);
            if ($pnrStatus != null) {
                $booking = FlightBookings::where('id', $this->booking->id)->first();
                $booking->status = 'canceled';
                $booking->save();
            }
        } else if ($this->booking->flight_provider == 'travelport') {
            Log::info('Canceling booking via Travelport API');
            
            $pnrStatus = $travelportApiService->cancelReservation($this->booking->itinerary_ref);
            Log::info($pnrStatus);
            if ($pnrStatus != null) {
                $booking = FlightBookings::where('id', $this->booking->id)->first();
                $booking->status = 'canceled';
                $booking->save();
            }
        } else if($this->booking->flight_provider == "OneApi") {
            Log::info('Canceling booking via OneApi API');
            // $pnrStatus = $oneApiService->cancelReservation($this->booking->itinerary_ref);
            // Log::info($pnrStatus);
            // if ($pnrStatus != null) {
                $booking = FlightBookings::where('id', $this->booking->id)->first();
                $booking->status = 'canceled';
                $booking->save();
            // }
        } else if ($this->booking->flight_provider == 'airsial') {
            Log::info('Canceling booking via Airsial API');
            // $pnrStatus = $airsialApiService->cancelReservation($this->booking->itinerary_ref);
            // Log::info($pnrStatus);
            // if ($pnrStatus != null) {
                $booking = FlightBookings::where('id', $this->booking->id)->first();
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

        //     $response = $airBlueApiService->cancelBooking($this->pnr);
        //     $response = json_decode($response, true); // decode as associative array

        //     if (isset($response['booking']['bookingId']) && $response['booking']['bookingId'] === $this->pnr) {
        //         $booking->update(['status' => 'canceled']);
        //        // Log::info("Booking for PNR {$this->pnr} successfully auto-canceled.");
        //     } else {
        //         //Log::error("Failed to auto-cancel booking for PNR {$this->pnr}: " . json_encode($response));
        //     }

        //Log::info("Auto-cancel response: " . json_encode($response));
    }

}
