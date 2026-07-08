<?php

use App\Models\Pairing;
use Inertia\Testing\AssertableInertia as Assert;

test('the total unread message count is shared to Inertia', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'a']);
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'b']);

    $this->actingAs($entrepreneur)
        ->get('/entrepreneur/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.unreadMessages', 2));
});
