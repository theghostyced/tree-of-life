<?php

use App\Models\User;

test('a draft mentor can save profile fields incrementally', function () {
    $user = User::factory()->mentor()->create();

    $this->actingAs($user)->patch('/mentor/profile', [
        'primary_expertise' => 'Trade finance',
        'industry_focus' => ['agriculture', 'logistics'],
        'years_experience' => 12,
    ])->assertRedirect();

    $profile = $user->mentorProfile->fresh();
    expect($profile->primary_expertise)->toBe('Trade finance')
        ->and($profile->years_experience)->toBe(12)
        ->and($profile->industry_focus)->toContain('agriculture');
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
        ->patch('/mentor/profile', ['primary_expertise' => 'Nope'])
        ->assertForbidden();
});
