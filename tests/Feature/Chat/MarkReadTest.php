<?php

use App\Events\MessageRead;
use App\Models\Pairing;
use Illuminate\Support\Facades\Event;

test('marking a conversation read updates the participant and broadcasts', function () {
    Event::fake([MessageRead::class]);
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $message = $conversation->messages()->create([
        'sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'Hello',
    ]);

    $this->actingAs($entrepreneur)
        ->postJson("/conversations/{$conversation->id}/read")
        ->assertOk();

    $participant = $conversation->participantFor($entrepreneur);
    expect($participant->last_read_message_id)->toBe($message->id)
        ->and($participant->last_read_at)->not->toBeNull()
        ->and($conversation->unreadCountFor($entrepreneur))->toBe(0);

    Event::assertDispatched(MessageRead::class, fn ($e) => $e->readerId === $entrepreneur->id);
});
