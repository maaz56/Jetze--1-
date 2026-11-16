<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A small, consistent database/realtime notification for operational events. */
class SystemNotification extends Notification
{
    public function __construct(
        private readonly array $payload,
        private readonly bool $sendMail = false,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sendMail
            ? ['database', 'broadcast', 'mail']
            : ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function broadcastAs(): string
    {
        return 'system-notification';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject((string) ($this->payload['heading'] ?? config('app.name').' notification'))
            ->line((string) ($this->payload['description'] ?? 'You have a new notification.'))
            ->when(!empty($this->payload['url']), fn (MailMessage $mail) => $mail->action('View details', url($this->payload['url'])));
    }
}
