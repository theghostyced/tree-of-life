<?php

use App\Models\Pairing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a mentor sees only their own active mentees', function () {
    $pairing = Pairing::factory()->create();
    $otherPairing = Pairing::factory()->create();

    $this->actingAs($pairing->mentor)
        ->get('/mentor/mentees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mentor/Mentees')
            ->has('active', 1)
            ->where('active.0.name', $pairing->entrepreneur->name)
            ->where('active.0.pairingId', $pairing->id)
            ->has('ended', 0));

    expect($otherPairing->mentor->id)->not->toBe($pairing->mentor->id);
});

test('an ended pairing is listed separately, not as a current mentee', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    Pairing::factory()->ended()->create(['mentor_user_id' => $mentor->id]);

    $this->actingAs($mentor)
        ->get('/mentor/mentees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('active', 0)->has('ended', 1));
});

test('a mentor with no pairings gets an empty list rather than an error', function () {
    $this->actingAs(User::factory()->mentor()->approved()->create())
        ->get('/mentor/mentees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('active', 0)->has('ended', 0));
});

test('a non-mentor cannot open the mentees page', function () {
    $this->actingAs(User::factory()->entrepreneur()->approved()->create())
        ->get('/mentor/mentees')
        ->assertForbidden();
});
