<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\User;

class NewUserRegistered extends Notification
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
            ->subject('New User Registered')
            ->line('A new user has registered.')
            ->action('View User', url('/admin/user-details?user_id=' . $this->user->id));
    }

    public function toArray($notifiable)
    {
        return [
            'id' => $this->id,
            'data' => [
        'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'type' => 'new_user',
            'heading' => 'New User Registered',
            'description' => 'A new user has registered: ',
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

        return ['admin-notification'];
    }

    public function broadcastAs()
    {
        return 'admin-event';
    }
}
