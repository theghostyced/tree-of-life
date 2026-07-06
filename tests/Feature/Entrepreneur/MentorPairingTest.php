<?php

use App\Models\MentorPairing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an entrepreneur can choose an available mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/pairings', ['mentor_id' => $mentor->id])
        ->assertRedirect('/entrepreneur/dashboard');

    expect(MentorPairing::where('entrepreneur_id', $entrepreneur->id)
        ->where('mentor_id', $mentor->id)->exists())->toBeTrue();
});

test('the chosen mentor appears on the dashboard', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor('Grace Mentor');
    MentorPairing::create(['entrepreneur_id' => $entrepreneur->id, 'mentor_id' => $mentor->id]);

    $this->actingAs($entrepreneur)->get('/entrepreneur/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Dashboard')
            ->where('mentor.name', 'Grace Mentor'));
});

test('an entrepreneur cannot choose a mentor before finishing onboarding', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $mentor = availableMentor();

    $this->actingAs($entrepreneur)->from('/entrepreneur/dashboard')
        ->post('/entrepreneur/pairings', ['mentor_id' => $mentor->id])
        ->assertSessionHasErrors('mentor_id');

    expect(MentorPairing::count())->toBe(0);
});

test('an entrepreneur cannot pair with a non-mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $notMentor = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($entrepreneur)->from('/entrepreneur/dashboard')
        ->post('/entrepreneur/pairings', ['mentor_id' => $notMentor->id])
        ->assertSessionHasErrors('mentor_id');

    expect(MentorPairing::count())->toBe(0);
});

test('an entrepreneur cannot choose a second mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $first = availableMentor('Grace');
    $second = availableMentor('Noah');
    MentorPairing::create(['entrepreneur_id' => $entrepreneur->id, 'mentor_id' => $first->id]);

    $this->actingAs($entrepreneur)->from('/entrepreneur/dashboard')
        ->post('/entrepreneur/pairings', ['mentor_id' => $second->id])
        ->assertSessionHasErrors('mentor_id');

    expect(MentorPairing::where('entrepreneur_id', $entrepreneur->id)->count())->toBe(1);
});
