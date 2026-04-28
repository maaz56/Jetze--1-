<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailVerification extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public ?string $userName;
    public ?string $userEmail;
    public ?string $temporaryPassword;
    public ?string $companyName;

    /**
     * Create a new message instance.
     */
    public function __construct(
        string $url,
        ?string $userName = null,
        ?string $userEmail = null,
        ?string $temporaryPassword = null,
        ?string $companyName = null
    )
    {
        $this->url = $url;
        $this->userName = $userName;
        $this->userEmail = $userEmail;
        $this->temporaryPassword = $temporaryPassword;
        $this->companyName = $companyName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Email Verification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
        );
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
