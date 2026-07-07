<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public int $recipientId,
        public int $recipientUnreadCount,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("conversation.{$this->message->conversation_id}"),
            new PrivateChannel("user.{$this->recipientId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_user_id,
                'type' => $this->message->type->value,
                'body' => $this->message->body,
                'created_at' => $this->message->created_at->toIso8601String(),
            ],
            'conversation' => [
                'id' => $this->message->conversation_id,
                'last_message_preview' => Str::limit($this->message->body, 120),
                'last_message_at' => $this->message->created_at->toIso8601String(),
            ],
            'recipient_unread_count' => $this->recipientUnreadCount,
        ];
    }
}
