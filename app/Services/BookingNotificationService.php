<?php

namespace App\Services;

use App\Mail\BookingConfirmedMail;
use App\Models\FlightBookings;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class BookingNotificationService
{
    /** Queue the normal confirmation notice only after a ticketing transition. */
    public function ticketed(FlightBookings $booking): void
    {
        if (! config('app.booking_email_notifications', true)) {
            return;
        }

        $admin = User::query()->where('role', 'admin')->first();
        $flightData = json_decode((string) $booking->flight_data, true) ?: [];
        $recipients = array_values(array_unique(array_filter([
            $booking->main_email,
            $booking->agency_email,
            $admin?->email,
        ])));

        foreach ($recipients as $email) {
            Mail::to($email)->queue(
                (new BookingConfirmedMail($email, $booking, $flightData))->afterCommit(),
            );
        }
    }
}
