<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an entrepreneur can choose an available mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/pairings', ['mentor_id' => $mentor->id])
        ->assertRedirect('/entrepreneur/mentors');

    expect($entrepreneur->mentors()->whereKey($mentor->id)->exists())->toBeTrue();
});

test('an entrepreneur can work with more than one mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $first = availableMentor('Grace');
    $second = availableMentor('Noah');

    $this->actingAs($entrepreneur)->post('/entrepreneur/pairings', ['mentor_id' => $first->id]);
    $this->actingAs($entrepreneur)->post('/entrepreneur/pairings', ['mentor_id' => $second->id]);

    expect($entrepreneur->mentors()->count())->toBe(2);
});

test('an entrepreneur cannot choose the same mentor twice', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $entrepreneur->mentors()->attach($mentor->id);

    $this->actingAs($entrepreneur)->from('/entrepreneur/mentors')
        ->post('/entrepreneur/pairings', ['mentor_id' => $mentor->id])
        ->assertSessionHasErrors('mentor_id');

    expect($entrepreneur->mentors()->count())->toBe(1);
});

test('the chosen mentors appear on the dashboard', function () {
    $entrepreneur = completeEntrepreneur();
    $entrepreneur->mentors()->attach(availableMentor('Grace Mentor')->id);
    $entrepreneur->mentors()->attach(availableMentor('Noah Guide')->id);

    $this->actingAs($entrepreneur)->get('/entrepreneur/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Dashboard')
            ->has('mentors', 2)
            ->where('mentors.0.name', 'Grace Mentor'));
});

test('an entrepreneur cannot choose a mentor before finishing onboarding', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $mentor = availableMentor();

    $this->actingAs($entrepreneur)->from('/entrepreneur/dashboard')
        ->post('/entrepreneur/pairings', ['mentor_id' => $mentor->id])
        ->assertSessionHasErrors('mentor_id');

    expect($entrepreneur->mentors()->count())->toBe(0);
});

test('an entrepreneur cannot pair with a non-mentor', function () {
    $entrepreneur = completeEntrepreneur();
    $notMentor = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($entrepreneur)->from('/entrepreneur/mentors')
        ->post('/entrepreneur/pairings', ['mentor_id' => $notMentor->id])
        ->assertSessionHasErrors('mentor_id');

    expect($entrepreneur->mentors()->count())->toBe(0);
});

test('a chosen mentor sees the entrepreneur on their mentor dashboard', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();

    $this->actingAs($entrepreneur)->post('/entrepreneur/pairings', ['mentor_id' => $mentor->id]);

    $this->actingAs($mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->count('mentees', 1)
            ->where('mentees.0.name', $entrepreneur->name));
});
