<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AgentCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $agentData;
    public $plainPassword;


    /**
     * Create a new message instance.
     */
    public function __construct($user, $agentData,$plainPassword)
    {
        $this->user = $user;
        $this->agentData = $agentData;
        $this->plainPassword = $plainPassword;


        
    }

    /**
     * Build the message.
     */
     /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Welcome to Our Platform!')
                    ->view('emails.agent_created')
                    ->with([
                        'name' => $this->agentData->company_name,
                        'email' => $this->user->email,
                        'password' => $this->plainPassword, // Pass plain password here
                    ]);
    }
}
