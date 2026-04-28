<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Log;

class BookingCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300];
    public $timeout = 120;

    public $token;
    public $email;
    public $booking;
    public $flightData;

    public $loginUrl;
    public $userName;

    /**
     * Create a new message instance.
     */
    public function __construct($email, $booking, $flightData)
    {
        $this->email = $email;
        $this->booking = $booking;
        $this->flightData = $flightData;
    }

    public function build()
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $this->loginUrl = $frontend;

        $mainEmail = (string) ($this->booking->main_email ?? $this->email ?? '');
        $user = null;
        if ($mainEmail !== '') {
            $user = User::where('email', $mainEmail)->first();
        }
        Log::info('BookingCreatedMail: Found user for email ' . $mainEmail . ': ' . ($user ? 'Yes' : 'No'));
        $this->userName = trim((string) ($user->name ?? ''));
        if ($this->userName === '') {
            $fallbackFirst = trim((string) ($this->booking->main_first_name ?? ''));
            $fallbackLast = trim((string) ($this->booking->main_last_name ?? ''));
            $this->userName = trim($fallbackFirst . ' ' . $fallbackLast);
        }
        if ($this->userName === '') {
            $this->userName = 'Valued Traveler';
        }

        $pnr = trim((string) ($this->booking->itinerary_ref ?? $this->booking->pnr ?? ''));
        $subject = $pnr !== '' ? 'Booking On Hold - PNR: ' . strtoupper($pnr) : 'Booking On Hold';

        return $this->subject($subject)
            ->view('emails.booking_created')
            ->with([
                'booking' => $this->booking,
                'flightData' => $this->flightData,
                'loginUrl' => $this->loginUrl,
                'userName' => $this->userName,
            ]);
    }

}
