<?php

use App\Data\OnboardingProgress;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The invitation is the approval, so accounts are approved on acceptance and the
 * onboarding nudge is driven purely by profile completeness — never by status.
 */

it('treats an approved entrepreneur with an empty profile as onboarding-incomplete', function () {
    $user = User::factory()->entrepreneur()->approved()->create();

    $progress = OnboardingProgress::forUser($user);

    expect($progress->isComplete)->toBeFalse()
        ->and($progress->total)->toBe(12)
        ->and($progress->remaining)->toBe(12);
});

it('treats an approved mentor with an empty profile as onboarding-incomplete', function () {
    $user = User::factory()->mentor()->approved()->create();

    $progress = OnboardingProgress::forUser($user);

    expect($progress->isComplete)->toBeFalse()
        ->and($progress->total)->toBe(8)
        ->and($progress->remaining)->toBe(8);
});

it('surfaces the completeness banner data on an approved but incomplete dashboard', function () {
    $user = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($user)->get('/entrepreneur/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Dashboard')
            ->where('onboarding.isComplete', false)
            ->where('onboarding.remaining', 12));
});

it('lets an approved entrepreneur open their onboarding wizard', function () {
    $user = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($user)->get('/entrepreneur/onboarding')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('entrepreneur/Onboarding'));
});

it('no longer exposes a submit-for-review endpoint', function () {
    $user = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($user)->post('/onboarding/submit')->assertNotFound();
});
