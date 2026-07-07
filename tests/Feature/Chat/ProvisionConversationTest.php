<?php

use App\Console\Commands\BackfillConversations;
use App\Models\Conversation;
use App\Models\Pairing;

test('creating a pairing provisions a conversation with both participants', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();

    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);

    $conversation = $pairing->conversation()->first();
    expect($conversation)->not->toBeNull()
        ->and($conversation->participants()->pluck('user_id')->sort()->values()->all())
        ->toEqual(collect([$entrepreneur->id, $mentor->id])->sort()->values()->all());
});

test('backfill provisions conversations for pairings that lack one', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    // Simulate a legacy pairing with no conversation.
    Conversation::where('pairing_id', $pairing->id)->delete();
    expect($pairing->conversation()->exists())->toBeFalse();

    $this->artisan(BackfillConversations::class)->assertOk();

    expect($pairing->fresh()->conversation()->exists())->toBeTrue();
});
