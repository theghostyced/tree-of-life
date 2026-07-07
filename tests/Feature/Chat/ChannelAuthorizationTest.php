<?php

use App\Models\Conversation;
use App\Models\Pairing;

// The default `null` broadcaster used in tests neither enforces channel
// authorization nor resolves channel handlers, so force a Pusher-protocol
// broadcaster (which does both) and re-register the channels on it —
// `Broadcast::channel` binds to whichever broadcaster was default at boot (null).
// This drives auth through the real `/broadcasting/auth` flow, so it also covers
// the channel WIRING (a broken handler form 500s here). Signing is local HMAC.
beforeEach(function () {
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher' => [
            'driver' => 'pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app-id',
            'options' => ['cluster' => 'mt1', 'useTLS' => true],
        ],
    ]);

    require base_path('routes/channels.php');
});

function conversationFor($entrepreneur, $mentor): Conversation
{
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);

    return $pairing->conversation()->firstOrFail();
}

test('a participant can authorize the conversation channel', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = conversationFor($entrepreneur, $mentor);

    $this->actingAs($mentor)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])->assertOk();
});

test('a non-participant is rejected from the conversation channel', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $intruder = availableMentor();
    $conversation = conversationFor($entrepreneur, $mentor);

    $this->actingAs($intruder)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])->assertForbidden();
});
