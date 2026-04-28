<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\User;

class UserApproved extends Notification
{
    // use Queueable; // Not needed if not queued

    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your Account Has Been Approved')
            ->line('Congratulations, your account has been approved by the admin.')
            ->action('Login', url('/'));
    }

    public function toArray($notifiable)
    {
        return [
            'id' => $this->id,
            'data' => [
                'user_id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'type' => 'user_approved',
                'heading' => 'Account Approved',
                'description' => 'Your account has been approved by the admin.'
            ],
            'read_at' => null
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastOn()
    {
        return ['is-approved-' . $this->user->id];
    }

    public function broadcastAs()
    {
        return 'approval-event';
    }
}
