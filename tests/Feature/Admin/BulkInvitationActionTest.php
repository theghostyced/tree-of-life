<?php

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;

test('an admin can bulk-resend pending invitations, skipping the rest', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $a = UserInvitation::factory()->pending()->create();
    $b = UserInvitation::factory()->pending()->create();
    $accepted = UserInvitation::factory()->accepted()->create();

    $this->actingAs($admin)->post('/admin/invitations/bulk', [
        'action' => 'resend',
        'ids' => [$a->id, $b->id, $accepted->id],
    ])->assertRedirect();

    Mail::assertQueued(UserInvitationMail::class, 2);
    expect($a->fresh()->token_hash)->not->toBe($b->fresh()->token_hash);
});

test('an admin can bulk-revoke pending invitations', function () {
    $admin = User::factory()->admin()->create();
    $a = UserInvitation::factory()->pending()->create();
    $b = UserInvitation::factory()->pending()->create();

    $this->actingAs($admin)->post('/admin/invitations/bulk', [
        'action' => 'revoke',
        'ids' => [$a->id, $b->id],
    ])->assertRedirect();

    expect($a->fresh()->revoked_at)->not->toBeNull()
        ->and($b->fresh()->revoked_at)->not->toBeNull();
});

test('bulk invitation actions skip invitations that are not pending', function () {
    $admin = User::factory()->admin()->create();
    $accepted = UserInvitation::factory()->accepted()->create();

    $this->actingAs($admin)->post('/admin/invitations/bulk', [
        'action' => 'revoke',
        'ids' => [$accepted->id],
    ])->assertRedirect();

    expect($accepted->fresh()->revoked_at)->toBeNull();
});

test('bulk invitation actions validate the action and ids', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->from('/admin/invitations')
        ->post('/admin/invitations/bulk', ['action' => 'nuke', 'ids' => [1]])
        ->assertSessionHasErrors('action');

    $this->actingAs($admin)->from('/admin/invitations')
        ->post('/admin/invitations/bulk', ['action' => 'revoke', 'ids' => []])
        ->assertSessionHasErrors('ids');
});

test('a non-admin cannot run bulk invitation actions', function () {
    $inv = UserInvitation::factory()->pending()->create();

    $this->actingAs(User::factory()->entrepreneur()->create())
        ->post('/admin/invitations/bulk', ['action' => 'revoke', 'ids' => [$inv->id]])
        ->assertForbidden();

    expect($inv->fresh()->revoked_at)->toBeNull();
});
