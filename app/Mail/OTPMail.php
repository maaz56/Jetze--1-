<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OTPMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $otp;
    public $name;



    /**
     * Create a new message instance.
     */
    public function __construct(string $email, string $otp, ?string $name = null)
    {
        $this->email = $email;
        $this->otp = $otp;
        $this->name = $name;
    }

    /**
     * Build the message.
     */
     /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('MFA Verification Code')
                    ->view('emails.otp_mail')
                    ->with([
                        'otp' => $this->otp,
                        'email' => $this->email,
                        'name' => $this->name,
                    ]);
    }
}
