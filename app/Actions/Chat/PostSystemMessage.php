<?php

namespace App\Actions\Chat;

use App\Enums\MessageType;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostSystemMessage
{
    public function handle(Conversation $conversation, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $body) {
            $message = $conversation->messages()->create([
                'sender_user_id' => null,
                'type' => MessageType::System,
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_preview' => Str::limit($body, 180),
                'last_message_sender_id' => null,
            ])->save();

            return $message;
        });

        // Broadcast to both participants; a system line is informational, so it
        // counts toward each side's unread badge (unreadCountFor includes
        // sender-less messages for everyone).
        foreach ($conversation->participants as $participant) {
            $unread = $conversation->unreadCountFor($participant->user);
            MessageSent::dispatch($message, $participant->user_id, $unread);
        }

        return $message;
    }
}
