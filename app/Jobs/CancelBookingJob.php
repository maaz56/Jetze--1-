<?php

namespace App\Jobs;

use App\Models\FlightBookings;
use App\Models\PaymentAttempt;
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
    public function handle()
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
            ->whereIn('provider', [PaymentAttempt::PROVIDER_NOMOD, PaymentAttempt::PROVIDER_TABBY])
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
      
        // AT and Hakimi bookings are held and released by their own flows; there is
        // no supplier-side auto-cancel to perform here.

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
