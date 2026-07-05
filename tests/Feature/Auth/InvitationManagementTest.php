<?php

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;

test('an approved admin can list invitations', function () {
    UserInvitation::factory()->count(3)->create();

    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get('/admin/invitations')
        ->assertSuccessful();
});

test('a non-admin cannot list invitations', function () {
    $this->actingAs(User::factory()->entrepreneur()->approved()->create())
        ->get('/admin/invitations')
        ->assertForbidden();
});

test('an approved admin can resend a pending invitation with a fresh token', function () {
    Mail::fake();
    $admin = User::factory()->admin()->approved()->create();
    $invitation = UserInvitation::factory()->pending()->create();
    $oldHash = $invitation->token_hash;

    $this->actingAs($admin)
        ->post("/admin/invitations/{$invitation->id}/resend")
        ->assertRedirect();

    expect($invitation->fresh()->token_hash)->not->toBe($oldHash)
        ->and($invitation->fresh()->expires_at)->toBeGreaterThan(now());

    Mail::assertQueued(UserInvitationMail::class, fn (UserInvitationMail $mail) => $mail->hasTo($invitation->email));
});

test('a non-pending invitation cannot be resent', function () {
    $admin = User::factory()->admin()->approved()->create();
    $accepted = UserInvitation::factory()->accepted()->create();

    $this->actingAs($admin)
        ->post("/admin/invitations/{$accepted->id}/resend")
        ->assertForbidden();
});

test('an approved admin can revoke a pending invitation', function () {
    $admin = User::factory()->admin()->approved()->create();
    $invitation = UserInvitation::factory()->pending()->create();

    $this->actingAs($admin)
        ->delete("/admin/invitations/{$invitation->id}")
        ->assertRedirect();

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

test('a non-pending invitation cannot be revoked', function () {
    $admin = User::factory()->admin()->approved()->create();
    $accepted = UserInvitation::factory()->accepted()->create();

    $this->actingAs($admin)
        ->delete("/admin/invitations/{$accepted->id}")
        ->assertForbidden();

    expect($accepted->fresh()->revoked_at)->toBeNull();
});

test('a non-admin cannot resend or revoke invitations', function () {
    $actor = User::factory()->mentor()->approved()->create();
    $invitation = UserInvitation::factory()->pending()->create();

    $this->actingAs($actor)
        ->post("/admin/invitations/{$invitation->id}/resend")
        ->assertForbidden();

    $this->actingAs($actor)
        ->delete("/admin/invitations/{$invitation->id}")
        ->assertForbidden();

    expect($invitation->fresh()->revoked_at)->toBeNull();
});
