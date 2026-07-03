<?php

use App\Models\User;

test('public registration routes are not discoverable for any role', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/register',
    '/admin/register',
    '/mentor/register',
    '/entrepreneur/register',
    '/employee/register',
]);

test('a guest cannot self-register by posting to a registration endpoint', function () {
    $this->post('/register', [
        'name' => 'Sneaky Signup',
        'email' => 'sneaky@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::where('email', 'sneaky@example.com')->exists())->toBeFalse();
});

test('an account can never be created without consuming a valid invitation', function () {
    $unknownToken = str_repeat('a', 64);

    $this->get("/invitations/accept/{$unknownToken}")->assertNotFound();

    $this->post("/invitations/accept/{$unknownToken}", [
        'name' => 'Ghost',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::count())->toBe(0);
});
