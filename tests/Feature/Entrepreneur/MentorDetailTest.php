<?php

use App\Models\MentorPairing;
use Inertia\Testing\AssertableInertia as Assert;

test('the detail page shows the entrepreneur their chosen mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor('Grace Mentor');
    MentorPairing::create(['entrepreneur_id' => $entrepreneur->id, 'mentor_id' => $mentor->id]);

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentor')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Mentor')
            ->where('mentor.name', 'Grace Mentor')
            ->where('mentor.expertise', 'Trade finance')
            ->whereNot('pairedAt', null)
            // A paired entrepreneur drives the "My mentor" nav.
            ->where('auth.hasMentor', true));
});

test('the detail page redirects an entrepreneur who has no mentor', function () {
    $entrepreneur = completeEntrepreneur();

    $this->actingAs($entrepreneur)->get('/entrepreneur/mentor')
        ->assertRedirect('/entrepreneur/mentors');
});
