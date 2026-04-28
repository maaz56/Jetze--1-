<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingConfirmedMail extends Mailable implements ShouldQueue
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
    public $ticketNumber;
    public $eTicketUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($email, $booking, $flightData)
    {
        $this->email = $email;
        $this->booking = $booking;
        $this->flightData = $flightData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Booking Confirmed Mail',
        );
    }

    /**
     * Get the message content definition.
     */
   public function build()
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $this->loginUrl = $frontend . '/login';

        $mainEmail = (string) ($this->booking->main_email ?? $this->email ?? '');
        $user = null;
        if ($mainEmail !== '') {
            $user = User::where('email', $mainEmail)->first();
        }

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
        $subject = $pnr !== '' ? 'Your Booking is Confirmed - PNR: ' . strtoupper($pnr) : 'Your Booking is Confirmed!';

        $this->ticketNumber = trim((string) ($this->booking->ticket_number ?? ''));

        $providerRaw = (string) ($this->booking->flight_provider ?? ($this->flightData['provider']['name'] ?? ''));
        $providerLower = strtolower(trim($providerRaw));
        $flightProvider = match ($providerLower) {
            'oneapi' => 'OneApi',
            'airblue' => 'airblue',
            'sabre' => 'sabre',
            'airsial' => 'airsial',
            'travelport' => 'travelport',
            default => 'travelport',
        };

        $flightMode = (string) ($this->booking->booking_mode ?? 'B2C');
        $bookingSource = (string) ($this->booking->booking_source ?? 'web');
        $bookingId = (string) ($this->booking->id ?? '');
        $flightId = (string) ($this->booking->flight_id ?? '');

        $eTicketParams = [
            'booking_id' => $bookingId,
            'flight_mode' => $flightMode,
            'flight_provider' => $flightProvider,
            'booking_source' => $bookingSource,
            'pnr' => $pnr,
        ];
        if ($flightId !== '' && $flightId !== 'UNKNOWN') {
            $eTicketParams['flight_id'] = $flightId;
        }
        $this->eTicketUrl = $frontend . '/customer-bookings-details?' . http_build_query($eTicketParams);

        return $this->to($this->email) 
            ->subject($subject)
            ->view('emails.booking_confirmed')
            ->with([
                'booking' => $this->booking,
                'flightData' => $this->flightData,
                'loginUrl' => $this->loginUrl,
                'userName' => $this->userName,
                'ticketNumber' => $this->ticketNumber,
                'eTicketUrl' => $this->eTicketUrl,
            ]);
    }
    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
