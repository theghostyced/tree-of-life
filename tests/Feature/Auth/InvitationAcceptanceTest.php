<?php

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/** Create a pending invitation and return [model, rawToken]. Only the hash is stored. */
function pendingInvitation(array $overrides = []): array
{
    $raw = Str::random(64);
    $invitation = UserInvitation::factory()->pending()->create(array_merge([
        'token_hash' => hash('sha256', $raw),
    ], $overrides));

    return [$invitation, $raw];
}

test('a valid token renders the registration form with the invited email locked', function () {
    [$invitation, $token] = pendingInvitation([
        'email' => 'invitee@example.com',
        'role' => UserRole::Entrepreneur,
    ]);

    $this->get("/invitations/accept/{$token}")
        ->assertSuccessful()
        ->assertSee('invitee@example.com');
});

test('accepting a valid invitation creates the user with the invited email and role', function () {
    Notification::fake();
    [$invitation, $token] = pendingInvitation([
        'email' => 'invitee@example.com',
        'role' => UserRole::Entrepreneur,
    ]);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'New Founder',
        'password' => 'sup3r-secret-pw',
        'password_confirmation' => 'sup3r-secret-pw',
    ])->assertRedirect('/entrepreneur/onboarding');

    $user = User::firstWhere('email', 'invitee@example.com');

    // The invitation is the approval, so accepting it yields an approved account.
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Entrepreneur)
        ->and($user->account_status)->toBe(AccountStatus::Approved)
        ->and(Hash::check('sup3r-secret-pw', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);

    expect($invitation->fresh())
        ->accepted_at->not->toBeNull()
        ->accepted_user_id->toBe($user->id);
});

test('the invited email cannot be overridden by the acceptance payload', function () {
    [$invitation, $token] = pendingInvitation(['email' => 'invited@example.com']);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'Tamperer',
        'email' => 'attacker@example.com',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ]);

    expect(User::where('email', 'attacker@example.com')->exists())->toBeFalse()
        ->and(User::where('email', 'invited@example.com')->exists())->toBeTrue();
});

test('an accepted invitation marks the email verified and sends no verification email', function () {
    Notification::fake();
    [$invitation, $token] = pendingInvitation(['email' => 'invitee@example.com']);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'Verified Already',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ]);

    $user = User::firstWhere('email', 'invitee@example.com');

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();

    Notification::assertNotSentTo($user, VerifyEmail::class);
});

test('accepting an entrepreneur invitation creates the entrepreneur profile', function () {
    [$invitation, $token] = pendingInvitation([
        'email' => 'founder@example.com',
        'role' => UserRole::Entrepreneur,
    ]);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'Founder',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ]);

    $user = User::firstWhere('email', 'founder@example.com');

    expect(DB::table('entrepreneur_profiles')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('accepting an admin invitation results in an approved admin account', function () {
    [$invitation, $token] = pendingInvitation([
        'email' => 'admin@example.com',
        'role' => UserRole::Admin,
    ]);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'New Admin',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertRedirect('/admin/dashboard');

    $user = User::firstWhere('email', 'admin@example.com');

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->account_status)->toBe(AccountStatus::Approved);
});

test('acceptance requires a confirmed, sufficiently strong password', function (array $payload) {
    [$invitation, $token] = pendingInvitation(['email' => 'invitee@example.com']);

    $this->from("/invitations/accept/{$token}")
        ->post("/invitations/accept/{$token}", array_merge(['name' => 'X'], $payload))
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'invitee@example.com')->exists())->toBeFalse();
    expect($invitation->fresh()->accepted_at)->toBeNull();
})->with([
    'too short' => [['password' => 'short', 'password_confirmation' => 'short']],
    'unconfirmed' => [['password' => 'password-1234', 'password_confirmation' => 'different-1234']],
    'missing' => [[]],
]);

test('an unknown token is rejected and creates nothing', function () {
    $this->get('/invitations/accept/'.Str::random(64))->assertNotFound();
    expect(User::count())->toBe(0);
});

test('an expired invitation cannot be accepted', function () {
    [$invitation, $token] = pendingInvitation([
        'email' => 'late@example.com',
        'expires_at' => now()->subDay(),
    ]);

    $this->get("/invitations/accept/{$token}")->assertGone();
    $this->post("/invitations/accept/{$token}", [
        'name' => 'Late',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertGone();

    expect(User::where('email', 'late@example.com')->exists())->toBeFalse();
});

test('a revoked invitation cannot be accepted', function () {
    [$invitation, $token] = pendingInvitation([
        'email' => 'revoked@example.com',
        'revoked_at' => now()->subHour(),
    ]);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'Revoked',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertGone();

    expect(User::where('email', 'revoked@example.com')->exists())->toBeFalse();
});

test('an invitation token is single-use', function () {
    [$invitation, $token] = pendingInvitation(['email' => 'once@example.com']);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'First',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ]);
    $this->post('/logout');

    $this->post("/invitations/accept/{$token}", [
        'name' => 'Second',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertGone();

    expect(User::where('email', 'once@example.com')->count())->toBe(1);
});

test('the acceptance POST revalidates the token even if the GET page was valid', function () {
    [$invitation, $token] = pendingInvitation(['email' => 'racer@example.com']);

    // GET was fine; invitation is revoked before the POST.
    $this->get("/invitations/accept/{$token}")->assertSuccessful();
    $invitation->update(['revoked_at' => now()]);

    $this->post("/invitations/accept/{$token}", [
        'name' => 'Racer',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ])->assertGone();

    expect(User::where('email', 'racer@example.com')->exists())->toBeFalse();
});

/**
 * The 410 page is deliberately vague, because the visitor is unauthenticated
 * and holds only a token. The operator is a different audience: the log has to
 * say which of the three dead states fired, or a refused link is unanswerable
 * after the fact.
 */
test('a refused invitation link records which state refused it', function (string $state, string $expected) {
    Log::spy();
    [$invitation, $token] = pendingInvitation(['email' => 'invitee@example.com']);
    $invitation->forceFill(match ($state) {
        'expired' => ['expires_at' => now()->subDay()],
        'revoked' => ['revoked_at' => now()],
        'accepted' => ['accepted_at' => now()],
    })->save();

    $this->get("/invitations/accept/{$token}")->assertGone();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context): bool => $message === 'Invitation link refused'
            && $context['invitation_id'] === $invitation->id
            && $context['status'] === $expected
            && $context['method'] === 'GET'
    )->once();
})->with([
    ['expired', 'expired'],
    ['revoked', 'revoked'],
    ['accepted', 'accepted'],
]);

test('a refused invitation link never writes the token or the email to the log', function () {
    Log::spy();
    [$invitation, $token] = pendingInvitation(['email' => 'invitee@example.com']);
    $invitation->forceFill(['revoked_at' => now()])->save();

    $this->get("/invitations/accept/{$token}")->assertGone();

    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($token): bool {
        $encoded = json_encode($context);

        return ! str_contains($encoded, $token)
            && ! str_contains($encoded, hash('sha256', $token))
            && ! str_contains($encoded, 'invitee@example.com');
    })->once();
});

test('an unrecognised invitation token is recorded without echoing the token', function () {
    Log::spy();
    $token = Str::random(64);

    $this->get("/invitations/accept/{$token}")->assertNotFound();

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context): bool => $message === 'Invitation token not recognised'
            && ! str_contains(json_encode($context), $token)
    )->once();
});
