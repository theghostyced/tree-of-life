<?php

use App\Enums\AccountStatus;
use App\Models\User;

test('an admin can bulk-revoke access, skipping their own account', function () {
    $admin = User::factory()->admin()->create();
    $a = User::factory()->entrepreneur()->approved()->create();
    $b = User::factory()->mentor()->approved()->create();

    $this->actingAs($admin)->post('/admin/users/bulk', [
        'action' => 'deactivate',
        'ids' => [$a->id, $b->id, $admin->id],
    ])->assertRedirect();

    expect($a->fresh()->account_status)->toBe(AccountStatus::Deactivated)
        ->and($b->fresh()->account_status)->toBe(AccountStatus::Deactivated)
        ->and($admin->fresh()->account_status)->toBe(AccountStatus::Approved);
});

test('an admin can bulk-restore deactivated users', function () {
    $admin = User::factory()->admin()->create();
    $a = User::factory()->entrepreneur()->deactivated()->create();
    $b = User::factory()->mentor()->deactivated()->create();

    $this->actingAs($admin)->post('/admin/users/bulk', [
        'action' => 'reactivate',
        'ids' => [$a->id, $b->id],
    ])->assertRedirect();

    expect($a->fresh()->account_status)->toBe(AccountStatus::Approved)
        ->and($b->fresh()->account_status)->toBe(AccountStatus::Approved);
});

test('an admin can bulk-delete users but not themselves', function () {
    $admin = User::factory()->admin()->create();
    $a = User::factory()->entrepreneur()->create();

    $this->actingAs($admin)->post('/admin/users/bulk', [
        'action' => 'delete',
        'ids' => [$a->id, $admin->id],
    ])->assertRedirect();

    expect(User::find($a->id))->toBeNull()
        ->and(User::find($admin->id))->not->toBeNull();
});

test('bulk actions validate the action and ids', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->from('/admin/users')
        ->post('/admin/users/bulk', ['action' => 'nuke', 'ids' => [1]])
        ->assertSessionHasErrors('action');

    $this->actingAs($admin)->from('/admin/users')
        ->post('/admin/users/bulk', ['action' => 'delete', 'ids' => []])
        ->assertSessionHasErrors('ids');
});

test('a non-admin cannot run bulk actions', function () {
    $target = User::factory()->mentor()->approved()->create();

    $this->actingAs(User::factory()->entrepreneur()->create())
        ->post('/admin/users/bulk', ['action' => 'deactivate', 'ids' => [$target->id]])
        ->assertForbidden();

    expect($target->fresh()->account_status)->toBe(AccountStatus::Approved);
});
