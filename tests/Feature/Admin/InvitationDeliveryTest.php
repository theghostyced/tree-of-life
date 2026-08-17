<?php

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;

/**
 * The queue broker is a separate service, so it can be unreachable while mail
 * delivery itself is perfectly healthy. These cover what the admin sees when
 * that happens: never an exception page, and never a silently lost invitation.
 */
test('an invitation still reaches the invitee when the queue broker is down', function () {
    $admin = User::factory()->admin()->approved()->create();

    // Mail::fake() records at the Mailer, above the queue driver, so a broken
    // connection never reaches it. Mocking the facade is what actually
    // exercises the queue-refused path. `to()` returns the mock so the
    // chained call lands on it.
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('queue')->once()->andThrow(new RedisException('Connection refused'));
    Mail::shouldReceive('sendNow')->once()->with(Mockery::type(UserInvitationMail::class));

    $this->actingAs($admin)
        ->post('/admin/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'entrepreneur',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation = UserInvitation::firstWhere('email', 'invitee@example.com');
    expect($invitation->delivered_at)->not->toBeNull()
        ->and($invitation->delivery_failed_at)->toBeNull();
});

test('a delivery that fails outright is recorded rather than thrown at the admin', function () {
    $admin = User::factory()->admin()->approved()->create();

    // Both legs down: the broker refuses the push and the transport refuses
    // the inline send. `to()` returns the mock itself so the chained call lands.
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('queue')->andThrow(new RedisException('Connection refused'));
    Mail::shouldReceive('sendNow')->andThrow(new RuntimeException('mail transport down'));

    $this->actingAs($admin)
        ->post('/admin/invitations', [
            'email' => 'invitee@example.com',
            'role' => 'entrepreneur',
        ])
        ->assertRedirect();

    $invitation = UserInvitation::firstWhere('email', 'invitee@example.com');

    expect($invitation)->not->toBeNull()
        ->and($invitation->delivery_failed_at)->not->toBeNull()
        ->and($invitation->delivered_at)->toBeNull()
        ->and($invitation->delivery_error)->toContain('mail transport down');
});

test('the admin can retry an invitation whose delivery failed', function () {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();
    $invitation = UserInvitation::factory()->create([
        'email' => 'invitee@example.com',
        'invited_by' => $admin->id,
        'delivery_failed_at' => now(),
        'delivery_error' => 'Connection refused',
    ]);

    $this->actingAs($admin)
        ->post("/admin/invitations/{$invitation->id}/resend")
        ->assertRedirect();

    Mail::assertQueued(UserInvitationMail::class, fn ($mail) => $mail->hasTo('invitee@example.com'));

    expect($invitation->fresh()->delivery_failed_at)->toBeNull()
        ->and($invitation->fresh()->delivered_at)->not->toBeNull();
});

test('a normal send still queues and is marked delivered', function () {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)->post('/admin/invitations', [
        'email' => 'invitee@example.com',
        'role' => 'entrepreneur',
    ])->assertRedirect();

    Mail::assertQueued(UserInvitationMail::class);

    expect(UserInvitation::firstWhere('email', 'invitee@example.com'))
        ->delivery_failed_at->toBeNull()
        ->delivered_at->not->toBeNull();
});

test('a queued send rejected by the provider marks the invitation, not just the log', function () {
    $admin = User::factory()->admin()->approved()->create();
    $invitation = UserInvitation::factory()->create([
        'email' => 'invitee@example.com',
        'invited_by' => $admin->id,
        'delivered_at' => now(),
    ]);

    // What the worker does when Resend rejects the message: the HTTP request is
    // long gone, so this hook is the only thing that can record it.
    (new UserInvitationMail($invitation, 'raw-token'))
        ->failed(new RuntimeException('The treeoflifefund.org domain is not verified.'));

    expect($invitation->fresh())
        ->delivered_at->toBeNull()
        ->delivery_failed_at->not->toBeNull()
        ->delivery_error->toContain('domain is not verified');
});
