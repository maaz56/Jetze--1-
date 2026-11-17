<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModifyRequestMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $modifyRequestId,
        public readonly array $message,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('modify-requests.'.$this->modifyRequestId)];
    }

    public function broadcastAs(): string
    {
        return 'modify-request-message';
    }

    public function broadcastWith(): array
    {
        return [
            'modify_request_id' => $this->modifyRequestId,
            'message' => $this->message,
        ];
    }
}
