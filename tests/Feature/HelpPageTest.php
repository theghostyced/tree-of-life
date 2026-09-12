<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a signed-in user reads how the programme works inside the app', function (string $role) {
    $user = User::factory()->{$role}()->approved()->create();

    $this->actingAs($user)->get('/help/how-it-works')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('shared/HowItWorks'));
})->with(['admin', 'mentor', 'entrepreneur']);

test('guests are sent to sign in rather than shown the in-app help page', function () {
    $this->get('/help/how-it-works')->assertRedirect('/login');
});
