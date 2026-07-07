<?php

use App\Broadcasting\ConversationChannel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', [ConversationChannel::class, 'join']);

Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('online', function (User $user) {
    return ['id' => $user->id, 'name' => $user->name];
});
