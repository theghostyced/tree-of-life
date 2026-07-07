<?php

namespace App\Actions\Chat;

use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostMessage
{
    public function handle(Conversation $conversation, User $sender, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body) {
            $message = $conversation->messages()->create([
                'sender_user_id' => $sender->id,
                'type' => MessageType::Text,
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => Str::limit($body, 180),
                'last_message_sender_id' => $sender->id,
            ])->save();

            return $message;
        });

        $recipient = $conversation->otherParticipant($sender);
        $recipientUnread = $conversation->unreadCountFor($recipient->user);

        MessageSent::dispatch($message, $recipient->user_id, $recipientUnread);

        return $message;
    }
}
