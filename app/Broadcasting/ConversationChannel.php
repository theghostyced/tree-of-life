<?php

namespace App\Broadcasting;

use App\Models\Conversation;
use App\Models\User;

class ConversationChannel
{
    /**
     * Authorize a user to join a conversation's private channel: only the two
     * participants of the underlying pairing may subscribe.
     */
    public function join(User $user, int $conversationId): bool
    {
        return Conversation::where('id', $conversationId)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
