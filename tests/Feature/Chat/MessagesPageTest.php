<?php

use App\Models\Pairing;
use Inertia\Testing\AssertableInertia as Assert;

test('the messages page lists the user conversations with unread counts', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'Welcome!']);

    $this->actingAs($entrepreneur)
        ->get('/entrepreneur/messages')
        ->assertInertia(fn (Assert $page) => $page
            ->component('messages/Index')
            ->where('currentUserId', $entrepreneur->id)
            ->has('conversations', 1, fn (Assert $c) => $c
                ->where('id', $conversation->id)
                ->where('other.id', $mentor->id)
                ->where('other.name', $mentor->name)
                ->where('unread_count', 1)
                ->where('is_active', true)
                ->etc()));
});

test('opening a conversation returns its thread and forbids non-participants', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $conversation = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ])->conversation()->firstOrFail();
    $conversation->messages()->create(['sender_user_id' => $mentor->id, 'type' => 'text', 'body' => 'Hi']);

    $this->actingAs($entrepreneur)
        ->get("/entrepreneur/messages/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page->where('selectedId', $conversation->id)->has('thread.messages', 1));

    $this->actingAs(availableMentor())
        ->get("/entrepreneur/messages/{$conversation->id}")
        ->assertForbidden();
});
