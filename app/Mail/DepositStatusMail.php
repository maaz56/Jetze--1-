<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DepositStatusMail extends Mailable implements ShouldQueue
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

        $status = strtolower((string) ($this->deposit->deposit_status ?? 'updated'));
        $subject = match ($status) {
            'approved' => 'Your Deposit Request has been Approved',
            'rejected' => 'Your Deposit Request has been Rejected',
            default => 'Your Deposit Request Status was Updated',
        };

        return $this->subject($subject)
            ->view('emails.deposit_status_mail')
            ->with([
                'deposit' => $this->deposit,
                'loginUrl' => $this->loginUrl,
            ]);
    }
}

