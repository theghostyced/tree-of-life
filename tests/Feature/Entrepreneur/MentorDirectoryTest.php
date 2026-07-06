<?php

use App\Models\MentorPairing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the directory lists available mentors and their focus areas', function () {
    $entrepreneur = completeEntrepreneur();

    $grace = availableMentor('Grace Mentor');
    $grace->mentorProfile->update(['industry_focus' => ['Agriculture', 'Manufacturing']]);
    $noah = availableMentor('Noah Guide');
    $noah->mentorProfile->update(['industry_focus' => ['Manufacturing']]);
    User::factory()->mentor()->approved()->create(); // no expertise → excluded

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Mentors')
            ->has('mentors', 2)
            // Focus areas are the de-duplicated, sorted union across the pool.
            ->where('focusAreas', ['Agriculture', 'Manufacturing']));
});

test('the directory redirects an entrepreneur who has not finished onboarding', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    availableMentor();

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertRedirect('/entrepreneur/dashboard');
});

test('the directory redirects an entrepreneur who is already paired', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    MentorPairing::create(['entrepreneur_id' => $entrepreneur->id, 'mentor_id' => $mentor->id]);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertRedirect('/entrepreneur/dashboard');
});
