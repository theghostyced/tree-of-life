<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a mentor sees their name, read-only email, and AfCFTA text as their bio', function () {
    $user = User::factory()->mentor()->approved()->create(['name' => 'Grace Mentor', 'email' => 'grace@example.com']);
    $user->mentorProfile->update(['afcfta_knowledge' => 'Twenty years in trade.']);

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Profile')
            ->where('profile.name', 'Grace Mentor')
            ->where('profile.email', 'grace@example.com')
            ->where('profile.avatarUrl', null)
            ->where('bio.label', 'Your AfCFTA knowledge')
            ->where('bio.hint', 'Entrepreneurs read this when choosing a mentor.')
            ->where('bio.value', 'Twenty years in trade.'));
});

test('an entrepreneur edits their business description as their bio', function () {
    $user = User::factory()->entrepreneur()->approved()->create();
    $user->entrepreneurProfile->update(['business_description' => 'We weave textiles.']);

    $this->actingAs($user)->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('bio.label', 'What your business does')
            ->where('bio.hint', 'Shown to admins reviewing your account; it is the same text as in your onboarding profile.')
            ->where('bio.value', 'We weave textiles.'));
});

test('an admin has no bio field', function () {
    $user = User::factory()->admin()->approved()->create();

    $this->actingAs($user)->get('/profile')
        ->assertInertia(fn (Assert $page) => $page->where('bio', null));
});

test('updating the profile changes the name and the role bio but never the email', function () {
    $user = User::factory()->mentor()->approved()->create(['email' => 'keep@example.com']);

    $this->actingAs($user)->from('/profile')->patch('/profile', [
        'name' => 'Grace Updated',
        'email' => 'hijack@example.com',
        'bio' => 'New bio text.',
    ])->assertRedirect('/profile');

    $user->refresh();
    expect($user->name)->toBe('Grace Updated')
        ->and($user->email)->toBe('keep@example.com')
        ->and($user->mentorProfile->afcfta_knowledge)->toBe('New bio text.');
});

test('an entrepreneur bio update lands on the business description', function () {
    $user = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'bio' => 'We export tea.'])
        ->assertRedirect();

    expect($user->entrepreneurProfile->fresh()->business_description)->toBe('We export tea.');
});

test('the name is required', function () {
    $user = User::factory()->mentor()->approved()->create();

    $this->actingAs($user)->from('/profile')
        ->patch('/profile', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('guests cannot open or update the profile', function () {
    $this->get('/profile')->assertRedirect('/login');
    $this->patch('/profile', ['name' => 'x'])->assertRedirect('/login');
});
