<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingCanceledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $email;
    public $booking;
    public $flightData;
    public $loginUrl;

    public function __construct($email, $booking, $flightData)
    {
        $this->email = $email;
        $this->booking = $booking;
        $this->flightData = $flightData;
    }

    public function build()
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $this->loginUrl = $frontend . '/login';

        return $this->subject('Your Booking has been Canceled')
            ->view('emails.booking_canceled')
            ->with([
                'booking' => $this->booking,
                'flightData' => $this->flightData,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}

