<?php

namespace App\Mail;

use App\Models\BookingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookingRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public BookingRequest $bookingRequest)
    {
    }

    public function envelope(): Envelope
    {
        $type = ucfirst($this->bookingRequest->type);
        $pnr = $this->bookingRequest->booking?->itinerary_ref ?? $this->bookingRequest->booking?->pnr ?? 'N/A';

        return new Envelope(
            subject: config('app.name') . ': ' . "New {$type} Request for PNR {$pnr}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking_request_submitted');
    }
}
