<?php

use App\Models\User;

test('a draft mentor can save profile fields incrementally', function () {
    $user = User::factory()->mentor()->create();

    $this->actingAs($user)->patch('/mentor/profile', [
        'expertise_areas' => ['Trade finance', 'Strategy'],
        'industry_focus' => ['agriculture', 'logistics'],
        'years_experience' => 12,
    ])->assertRedirect();

    $profile = $user->mentorProfile->fresh();
    expect($profile->expertise_areas)->toBe(['Trade finance', 'Strategy'])
        ->and($profile->years_experience)->toBe(12)
        ->and($profile->industry_focus)->toContain('agriculture');
});

test('a mentor may list at most three areas of expertise', function () {
    $user = User::factory()->mentor()->create();

    $this->actingAs($user)->from('/mentor/onboarding')
        ->patch('/mentor/profile', [
            'expertise_areas' => ['Trade finance', 'Strategy', 'Operations', 'Fundraising'],
        ])
        ->assertSessionHasErrors('expertise_areas');

    expect($user->mentorProfile->fresh()->expertise_areas)->toBeNull();
});

test('expertise areas must be a list of strings', function () {
    $user = User::factory()->mentor()->create();

    $this->actingAs($user)->from('/mentor/onboarding')
        ->patch('/mentor/profile', ['expertise_areas' => 'Trade finance'])
        ->assertSessionHasErrors('expertise_areas');
});

test('industry focus must be a list of strings', function () {
    $user = User::factory()->mentor()->create();

    $this->actingAs($user)->from('/mentor/onboarding')
        ->patch('/mentor/profile', ['industry_focus' => 'not-an-array'])
        ->assertSessionHasErrors('industry_focus');
});

test('years of experience must be a non-negative integer', function () {
    $user = User::factory()->mentor()->create();

    $this->actingAs($user)->from('/mentor/onboarding')
        ->patch('/mentor/profile', ['years_experience' => -3])
        ->assertSessionHasErrors('years_experience');
});

test('an entrepreneur cannot edit a mentor profile', function () {
    $entrepreneur = User::factory()->entrepreneur()->create();

    $this->actingAs($entrepreneur)
        ->patch('/mentor/profile', ['expertise_areas' => ['Nope']])
        ->assertForbidden();
});

test('the onboarding page shows saved expertise and describes each required document', function () {
    $user = User::factory()->mentor()->create();
    $user->mentorProfile->update(['expertise_areas' => ['Trade finance', 'Strategy']]);

    $this->actingAs($user)->get('/mentor/onboarding')
        ->assertInertia(fn ($page) => $page
            ->component('mentor/Onboarding')
            ->where('profile.expertise_areas', ['Trade finance', 'Strategy'])
            ->has('requiredDocuments', 3)
            ->where('requiredDocuments.1.type', 'identification_card')
            ->whereNot('requiredDocuments.1.description', null)
            ->whereNot('requiredDocuments.2.description', null));
});
