<?php

use App\Events\MessageSent;
use App\Models\Pairing;
use Illuminate\Support\Facades\Event;

function activeConversation(): array
{
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);

    return [$entrepreneur, $mentor, $pairing->conversation()->firstOrFail()];
}

test('a participant can send a message and it broadcasts', function () {
    Event::fake([MessageSent::class]);
    [$entrepreneur, $mentor, $conversation] = activeConversation();

    $this->actingAs($entrepreneur)
        ->postJson("/conversations/{$conversation->id}/messages", ['body' => 'Hi mentor'])
        ->assertCreated()
        ->assertJsonPath('message.body', 'Hi mentor')
        ->assertJsonPath('message.sender_id', $entrepreneur->id);

    $conversation->refresh();
    expect($conversation->messages()->count())->toBe(1)
        ->and($conversation->last_message_preview)->toBe('Hi mentor')
        ->and($conversation->last_message_sender_id)->toBe($entrepreneur->id);

    Event::assertDispatched(MessageSent::class, fn ($e) => $e->recipientId === $mentor->id);
});

test('a non-participant cannot send a message', function () {
    [$entrepreneur, $mentor, $conversation] = activeConversation();
    $intruder = availableMentor();

    $this->actingAs($intruder)
        ->postJson("/conversations/{$conversation->id}/messages", ['body' => 'Sneaky'])
        ->assertForbidden();
});

test('a message cannot be sent on an ended pairing', function () {
    [$entrepreneur, $mentor, $conversation] = activeConversation();
    $conversation->pairing->update(['ended_at' => now()]);

    $this->actingAs($entrepreneur)
        ->postJson("/conversations/{$conversation->id}/messages", ['body' => 'Still there?'])
        ->assertForbidden();
});
