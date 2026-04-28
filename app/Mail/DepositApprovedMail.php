<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DepositApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $email;
    public $deposit;
    public $loginUrl;

    public function __construct($email, $deposit)
    {
        $this->email = $email;
        $this->deposit = $deposit;
    }

    public function build()
    {
        $frontend = rtrim(config('app.frontend_url'), '/');
        $this->loginUrl = $frontend . '/login';

        return $this->subject('Your Deposit Request Has Been Approved')
            ->view('emails.deposit_approved_mail')
            ->with([
                'deposit' => $this->deposit,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}
