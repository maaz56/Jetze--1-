<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ModifyRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;
    public $modifyRequest;
    /**
     * Create a new message instance.
     */
     public function __construct($modifyRequest)
    {
        $this->modifyRequest = $modifyRequest;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Modify Request Created Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function build()
    {
        return $this->subject('New Modify Request Submitted')
            ->view('emails.modify_request_created');
    }
}
