<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the detail page shows one of the entrepreneur’s chosen mentors', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor('Grace Mentor');
    $entrepreneur->mentors()->attach($mentor->id);

    $this->actingAs($entrepreneur)->get("/entrepreneur/mentors/{$mentor->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Mentor')
            ->where('mentor.name', 'Grace Mentor')
            ->where('mentor.expertise', 'Trade finance')
            ->whereNot('pairedAt', null));
});

test('the detail page redirects for a mentor the entrepreneur has not chosen', function () {
    $entrepreneur = completeEntrepreneur();
    $stranger = availableMentor('Not Mine');

    $this->actingAs($entrepreneur)->get("/entrepreneur/mentors/{$stranger->id}")
        ->assertRedirect('/entrepreneur/mentors');
});
