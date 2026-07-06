<?php

use App\Models\MentorPairing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the directory lists available mentors and their focus areas', function () {
    $entrepreneur = completeEntrepreneur();

    availableMentor('Grace Mentor')->mentorProfile
        ->update(['industry_focus' => ['Agriculture', 'Manufacturing']]);
    availableMentor('Noah Guide')->mentorProfile
        ->update(['industry_focus' => ['Manufacturing']]);
    User::factory()->mentor()->approved()->create(); // no expertise → excluded

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Mentors')
            ->has('mentors.data', 2)
            ->where('mentors.total', 2)
            // Focus areas are the de-duplicated, sorted union across the pool.
            ->where('focusAreas', ['Agriculture', 'Manufacturing'])
            // An unpaired entrepreneur drives the "Mentors" nav.
            ->where('auth.hasMentor', false));
});

test('the directory filters by search server-side', function () {
    $entrepreneur = completeEntrepreneur();
    availableMentor('Grace Mentor'); // expertise 'Trade finance'
    availableMentor('Noah Guide')->mentorProfile
        ->update(['primary_expertise' => 'Operations & supply chain']);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors?search=operations')
        ->assertInertia(fn (Assert $page) => $page
            ->has('mentors.data', 1)
            ->where('mentors.data.0.name', 'Noah Guide'));
});

test('the directory filters by focus area server-side', function () {
    $entrepreneur = completeEntrepreneur();
    availableMentor('Grace')->mentorProfile->update(['industry_focus' => ['Agriculture']]);
    availableMentor('Noah')->mentorProfile->update(['industry_focus' => ['Manufacturing']]);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors?focus=Manufacturing')
        ->assertInertia(fn (Assert $page) => $page
            ->has('mentors.data', 1)
            ->where('mentors.data.0.name', 'Noah'));
});

test('the directory paginates the mentor pool', function () {
    $entrepreneur = completeEntrepreneur();
    foreach (range(1, 13) as $i) {
        availableMentor(sprintf('Mentor %02d', $i));
    }

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertInertia(fn (Assert $page) => $page
            ->where('mentors.total', 13)
            ->where('mentors.last_page', 2)
            ->has('mentors.data', 12));

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors?page=2')
        ->assertInertia(fn (Assert $page) => $page->has('mentors.data', 1));
});

test('the directory redirects an entrepreneur who has not finished onboarding', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    availableMentor();

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertRedirect('/entrepreneur/dashboard');
});

test('the directory redirects an entrepreneur who is already paired to their mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    MentorPairing::create(['entrepreneur_id' => $entrepreneur->id, 'mentor_id' => $mentor->id]);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertRedirect('/entrepreneur/mentor');
});
