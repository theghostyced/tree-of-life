<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a user can log in with valid credentials', function () {
    $user = User::factory()->entrepreneur()->approved()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'correct-password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('login fails with the wrong password and reveals nothing specific', function () {
    User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->from('/login')->post('/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ])->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login is redirected by role and account status', function (string $factory, string $status, string $destination) {
    $user = User::factory()->{$factory}()->{$status}()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password-1234'),
    ]);

    $this->post('/login', [
        'email' => 'user@example.com',
        'password' => 'password-1234',
    ])->assertRedirect($destination);

    $this->assertAuthenticatedAs($user);
})->with([
    'admin' => ['admin', 'approved', '/admin/dashboard'],
    'approved entrepreneur' => ['entrepreneur', 'approved', '/entrepreneur/dashboard'],
    'pending entrepreneur' => ['entrepreneur', 'pending', '/entrepreneur/onboarding'],
    'draft entrepreneur' => ['entrepreneur', 'draft', '/entrepreneur/onboarding'],
    'approved mentor' => ['mentor', 'approved', '/mentor/dashboard'],
    'pending mentor' => ['mentor', 'pending', '/mentor/onboarding'],
]);

test('a deactivated user cannot establish a session', function () {
    User::factory()->entrepreneur()->deactivated()->create([
        'email' => 'gone@example.com',
        'password' => Hash::make('password-1234'),
    ]);

    $this->from('/login')->post('/login', [
        'email' => 'gone@example.com',
        'password' => 'password-1234',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

test('a user can log out', function () {
    $user = User::factory()->approved()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

test('login attempts are rate limited', function () {
    User::factory()->create([
        'email' => 'target@example.com',
        'password' => Hash::make('correct-password'),
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => 'target@example.com', 'password' => 'nope']);
    }

    $this->from('/login')->post('/login', [
        'email' => 'target@example.com',
        'password' => 'nope',
    ])->assertSessionHasErrors('email');

    // The lockout message differs from a plain "wrong credentials" error.
    expect(session('errors')->first('email'))->toContain('Too many');
});
