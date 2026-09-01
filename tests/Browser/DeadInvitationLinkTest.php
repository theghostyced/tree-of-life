<?php

use App\Models\UserInvitation;
use Illuminate\Support\Str;

/**
 * The invitee arrives from an email client with no history to go back to, so
 * the page has to carry its own way forward. Only `status` crosses the wire to
 * the Inertia page, which is why the button can only be checked in a browser.
 */
it('offers a sign-in route out of a dead invitation link', function (string $state) {
    $token = Str::random(64);
    UserInvitation::factory()->{$state}()->create([
        'email' => 'invitee@example.com',
        'token_hash' => hash('sha256', $token),
    ]);

    visit("/invitations/accept/{$token}")
        ->assertPresent('html[data-hydrated=true]')
        ->assertNoJavaScriptErrors()
        ->assertSee('This invitation link is no longer active')
        ->assertPresent('a[href="/login"]')
        ->assertSee('Sign in')
        ->assertDontSee('invitee@example.com');
})->with(['expired', 'revoked', 'accepted']);
