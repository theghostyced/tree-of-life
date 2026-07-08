<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the hub lists available mentors and their focus areas', function () {
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
            ->has('yourMentors', 0)
            ->where('focusAreas', ['Agriculture', 'Manufacturing']));
});

test('the directory excludes mentors the entrepreneur already works with', function () {
    $entrepreneur = completeEntrepreneur();
    $chosen = availableMentor('Grace Mentor');
    availableMentor('Noah Guide');
    $entrepreneur->mentors()->attach($chosen->id);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertInertia(fn (Assert $page) => $page
            // Grace is under "your mentors"; only Noah is left to add.
            ->has('yourMentors', 1)
            ->where('yourMentors.0.name', 'Grace Mentor')
            ->has('mentors.data', 1)
            ->where('mentors.data.0.name', 'Noah Guide'));
});

test('the hub filters by search server-side', function () {
    $entrepreneur = completeEntrepreneur();
    availableMentor('Grace Mentor'); // expertise 'Trade finance'
    availableMentor('Noah Guide')->mentorProfile
        ->update(['primary_expertise' => 'Operations & supply chain']);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors?search=operations')
        ->assertInertia(fn (Assert $page) => $page
            ->has('mentors.data', 1)
            ->where('mentors.data.0.name', 'Noah Guide'));
});

test('the hub filters by focus area server-side', function () {
    $entrepreneur = completeEntrepreneur();
    availableMentor('Grace')->mentorProfile->update(['industry_focus' => ['Agriculture']]);
    availableMentor('Noah')->mentorProfile->update(['industry_focus' => ['Manufacturing']]);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors?focus=Manufacturing')
        ->assertInertia(fn (Assert $page) => $page
            ->has('mentors.data', 1)
            ->where('mentors.data.0.name', 'Noah'));
});

test('the hub paginates the mentor pool', function () {
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

test('the hub redirects an entrepreneur who has not finished onboarding to onboarding', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    availableMentor();

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentors')
        ->assertRedirect('/entrepreneur/onboarding')
        ->assertSessionHas('info');
});
