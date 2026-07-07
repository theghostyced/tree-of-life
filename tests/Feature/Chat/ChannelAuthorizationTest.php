<?php

use App\Broadcasting\ConversationChannel;
use App\Models\Conversation;
use App\Models\Pairing;

function conversationFor($entrepreneur, $mentor): Conversation
{
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);

    return $pairing->conversation()->firstOrFail();
}

test('both participants may join the conversation channel', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = conversationFor($entrepreneur, $mentor);

    $channel = new ConversationChannel;

    expect($channel->join($mentor, $conversation->id))->toBeTrue()
        ->and($channel->join($entrepreneur, $conversation->id))->toBeTrue();
});

test('a non-participant may not join the conversation channel', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $intruder = availableMentor();
    $conversation = conversationFor($entrepreneur, $mentor);

    expect((new ConversationChannel)->join($intruder, $conversation->id))->toBeFalse();
});
