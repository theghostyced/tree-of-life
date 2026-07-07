<?php

// tests/Feature/Chat/ChatModelsTest.php

use App\Enums\MessageType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Pairing;

test('a conversation has messages, participants, and a pairing', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $conversation = Conversation::create(['pairing_id' => $pairing->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $entrepreneur->id]);
    ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $mentor->id]);
    $conversation->messages()->create([
        'sender_user_id' => $mentor->id, 'type' => MessageType::Text, 'body' => 'Hello there',
    ]);

    expect($conversation->pairing->is($pairing))->toBeTrue()
        ->and($conversation->messages)->toHaveCount(1)
        ->and($conversation->messages->first()->type)->toBe(MessageType::Text)
        ->and($conversation->participants)->toHaveCount(2)
        ->and($conversation->participantFor($mentor)->user_id)->toBe($mentor->id)
        ->and($conversation->otherParticipant($mentor)->user_id)->toBe($entrepreneur->id)
        ->and($conversation->isActive())->toBeTrue()
        ->and($conversation->unreadCountFor($entrepreneur))->toBe(1)
        ->and($conversation->unreadCountFor($mentor))->toBe(0);
});
