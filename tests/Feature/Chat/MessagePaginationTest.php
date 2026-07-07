<?php

use App\Models\Pairing;

test('messages paginate oldest-to-newest with a before cursor', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();

    $ids = collect(range(1, 40))->map(fn ($n) => $conversation->messages()->create([
        'sender_user_id' => $mentor->id, 'type' => 'text', 'body' => "msg {$n}",
    ])->id);

    $firstPage = $this->actingAs($entrepreneur)
        ->getJson("/conversations/{$conversation->id}/messages")
        ->assertOk()->json('messages');

    expect($firstPage)->toHaveCount(30)
        ->and($firstPage[0]['id'])->toBe($ids[10])          // oldest of the newest 30
        ->and(end($firstPage)['id'])->toBe($ids[39]);       // newest overall

    $oldest = $firstPage[0]['id'];
    $secondPage = $this->actingAs($entrepreneur)
        ->getJson("/conversations/{$conversation->id}/messages?before={$oldest}")
        ->assertOk()->json('messages');

    expect($secondPage)->toHaveCount(10)
        ->and(end($secondPage)['id'])->toBe($ids[9]);
});
