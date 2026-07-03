<?php

use App\Enums\UserRole;
use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;

test('an approved admin can invite an entrepreneur, mentor, or admin', function (string $role) {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)
        ->post('/admin/invitations', [
            'email' => 'invitee@example.com',
            'role' => $role,
            'name' => 'Invited Person',
        ])
        ->assertRedirect();

    $invitation = UserInvitation::firstWhere('email', 'invitee@example.com');

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(UserRole::from($role))
        ->and($invitation->invited_by)->toBe($admin->id)
        ->and($invitation->accepted_at)->toBeNull()
        ->and($invitation->revoked_at)->toBeNull()
        ->and($invitation->expires_at)->toBeGreaterThan(now());

    Mail::assertQueued(UserInvitationMail::class, fn ($mail) => $mail->hasTo('invitee@example.com'));
})->with(['entrepreneur', 'mentor', 'admin']);

test('the invitation email links to the tokenized accept route', function () {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)->post('/admin/invitations', [
        'email' => 'invitee@example.com',
        'role' => 'entrepreneur',
    ]);

    Mail::assertQueued(UserInvitationMail::class, function (UserInvitationMail $mail) {
        return str_contains($mail->content()->with['acceptUrl'], '/invitations/accept/')
            && strlen($mail->token) >= 32;
    });
});

test('only the token hash is persisted, never a plaintext token', function () {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)->post('/admin/invitations', [
        'email' => 'invitee@example.com',
        'role' => 'entrepreneur',
    ]);

    $invitation = UserInvitation::firstWhere('email', 'invitee@example.com');

    expect($invitation->token_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and(array_key_exists('token', $invitation->getAttributes()))->toBeFalse();
});

test('a guest cannot create invitations', function () {
    $this->post('/admin/invitations', ['email' => 'x@example.com', 'role' => 'entrepreneur'])
        ->assertRedirect('/login');

    expect(UserInvitation::count())->toBe(0);
});

test('a non-admin cannot create invitations', function (string $factory) {
    $user = User::factory()->{$factory}()->approved()->create();

    $this->actingAs($user)
        ->post('/admin/invitations', ['email' => 'x@example.com', 'role' => 'entrepreneur'])
        ->assertForbidden();

    expect(UserInvitation::count())->toBe(0);
})->with(['entrepreneur', 'mentor']);

test('an unapproved admin cannot create invitations', function () {
    $admin = User::factory()->admin()->pending()->create();

    $this->actingAs($admin)
        ->post('/admin/invitations', ['email' => 'x@example.com', 'role' => 'entrepreneur'])
        ->assertForbidden();

    expect(UserInvitation::count())->toBe(0);
});

test('invitation creation validates the email and role', function (array $payload) {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)
        ->from('/admin/invitations')
        ->post('/admin/invitations', $payload)
        ->assertRedirect('/admin/invitations')
        ->assertSessionHasErrors();

    expect(UserInvitation::count())->toBe(0);
})->with([
    'missing email' => [['role' => 'entrepreneur']],
    'invalid email' => [['email' => 'not-an-email', 'role' => 'entrepreneur']],
    'missing role' => [['email' => 'a@example.com']],
    'unknown role' => [['email' => 'a@example.com', 'role' => 'wizard']],
]);

test('two active invitations for the same email and role cannot coexist', function () {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();
    UserInvitation::factory()->pending()->create([
        'email' => 'invitee@example.com',
        'role' => UserRole::Entrepreneur,
    ]);

    $this->actingAs($admin)
        ->from('/admin/invitations')
        ->post('/admin/invitations', ['email' => 'invitee@example.com', 'role' => 'entrepreneur'])
        ->assertSessionHasErrors();

    expect(UserInvitation::where('email', 'invitee@example.com')->count())->toBe(1);
});

test('an email that already belongs to a user cannot be re-invited', function () {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();
    User::factory()->entrepreneur()->create(['email' => 'existing@example.com']);

    $this->actingAs($admin)
        ->from('/admin/invitations')
        ->post('/admin/invitations', ['email' => 'existing@example.com', 'role' => 'entrepreneur'])
        ->assertSessionHasErrors();

    expect(UserInvitation::where('email', 'existing@example.com')->count())->toBe(0);
});
