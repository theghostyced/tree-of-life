<?php

use App\Models\User;

test('guests are redirected to login from protected areas', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with([
    '/admin/dashboard',
    '/entrepreneur/dashboard',
    '/mentor/dashboard',
]);

test('the admin area is reachable only by admins', function () {
    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get('/admin/dashboard')->assertSuccessful();

    $this->actingAs(User::factory()->entrepreneur()->approved()->create())
        ->get('/admin/dashboard')->assertForbidden();

    $this->actingAs(User::factory()->mentor()->approved()->create())
        ->get('/admin/dashboard')->assertForbidden();
});

test('a role cannot reach another role dashboard', function () {
    $this->actingAs(User::factory()->entrepreneur()->approved()->create())
        ->get('/mentor/dashboard')->assertForbidden();

    $this->actingAs(User::factory()->mentor()->approved()->create())
        ->get('/entrepreneur/dashboard')->assertForbidden();
});

test('approved accounts reach their dashboard', function () {
    $this->actingAs(User::factory()->entrepreneur()->approved()->create())
        ->get('/entrepreneur/dashboard')->assertSuccessful();

    $this->actingAs(User::factory()->mentor()->approved()->create())
        ->get('/mentor/dashboard')->assertSuccessful();
});

test('unapproved accounts can still reach their dashboard and onboarding', function (string $status) {
    $user = User::factory()->entrepreneur()->{$status}()->create();

    $this->actingAs($user)->get('/entrepreneur/dashboard')->assertSuccessful();
    $this->actingAs($user)->get('/entrepreneur/onboarding')->assertSuccessful();
})->with(['draft', 'pending', 'rejected']);

test('a deactivated account is shut out and signed off', function () {
    $user = User::factory()->entrepreneur()->deactivated()->create();

    $this->actingAs($user)->get('/entrepreneur/dashboard')
        ->assertRedirect('/login');
});
