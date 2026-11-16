<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\User;

class NewUserRegistered extends Notification
{
    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New User Registered')
            ->line("{$this->user->name} ({$this->user->email}) has registered on ".config('app.name').'.')
            ->action('View Users', url('/admin/users'));
    }

    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'type' => 'new_user',
            'heading' => 'New User Registered',
            'description' => 'A new user has registered.',
            'url' => \App\Support\AdminNotificationLinks::customer($this->user->id),
        ];
    }

      public function toBroadcast($notifiable)
    {
        return new BroadcastMessage($this->toArray($notifiable) + ['notification_type' => 'new_user']);
    }

    public function broadcastAs()
    {
        return 'new-user-registered';
    }
}
