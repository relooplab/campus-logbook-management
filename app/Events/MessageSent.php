<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public Conversation $conversation,
    ) {
    }

    public function broadcastOn(): array
    {
        // Channel privat per conversation: user.{id} + conversation.{id}.
        $channels = [
            new PrivateChannel('conversation.'.$this->conversation->id),
        ];
        if ($this->conversation->user_one_id) {
            $channels[] = new PrivateChannel('user.'.$this->conversation->user_one_id);
        }
        if ($this->conversation->user_two_id) {
            $channels[] = new PrivateChannel('user.'.$this->conversation->user_two_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }
}
