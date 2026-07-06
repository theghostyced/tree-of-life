<?php

use App\Enums\AccountStatus;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an admin can view the users list', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->entrepreneur()->create();
    User::factory()->mentor()->create();

    $this->actingAs($admin)->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->has('users', 3));
});

test('a non-admin cannot view the users list', function () {
    $this->actingAs(User::factory()->entrepreneur()->create())
        ->get('/admin/users')->assertForbidden();
});

test('an admin can open a user detail page', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($admin)->get("/admin/users/{$user->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Show')
            ->where('user.id', $user->id)
            ->where('user.email', $user->email));
});

test('an admin can revoke and restore a user\'s access', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($admin)->post("/admin/users/{$user->id}/deactivate")
        ->assertRedirect();
    expect($user->fresh()->account_status)->toBe(AccountStatus::Deactivated);

    $this->actingAs($admin)->post("/admin/users/{$user->id}/reactivate")
        ->assertRedirect();
    expect($user->fresh()->account_status)->toBe(AccountStatus::Approved);
});

test('an already-deactivated user cannot be deactivated again', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->entrepreneur()->deactivated()->create();

    $this->actingAs($admin)->post("/admin/users/{$user->id}/deactivate")
        ->assertForbidden();
});

test('an admin can soft-delete a user, hiding them from the app', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($admin)->delete("/admin/users/{$user->id}")
        ->assertRedirect();

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id)->trashed())->toBeTrue();
});

test('a soft-deleted user cannot authenticate or be acted on', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->entrepreneur()->create();
    $user->delete();

    // The route-model binding uses the default (non-trashed) scope, so a
    // deleted user is simply not found.
    $this->actingAs($admin)->get("/admin/users/{$user->id}")->assertNotFound();
});

test('an admin cannot deactivate or delete their own account', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post("/admin/users/{$admin->id}/deactivate")
        ->assertForbidden();
    $this->actingAs($admin)->delete("/admin/users/{$admin->id}")
        ->assertForbidden();

    expect($admin->fresh()->account_status)->toBe(AccountStatus::Approved)
        ->and($admin->fresh())->not->toBeNull();
});

test('a non-admin cannot perform user lifecycle actions', function () {
    $actor = User::factory()->entrepreneur()->create();
    $target = User::factory()->mentor()->approved()->create();

    $this->actingAs($actor)->post("/admin/users/{$target->id}/deactivate")->assertForbidden();
    $this->actingAs($actor)->delete("/admin/users/{$target->id}")->assertForbidden();

    expect($target->fresh()->account_status)->toBe(AccountStatus::Approved);
});

test('a mentors detail page lists their entrepreneurs', function () {
    $admin = User::factory()->admin()->approved()->create();
    $pairing = App\Models\Pairing::factory()->create();
    App\Models\Pairing::factory()->ended()->create([
        'mentor_user_id' => $pairing->mentor_user_id,
    ]);

    $this->actingAs($admin)
        ->get("/admin/users/{$pairing->mentor_user_id}")
        ->assertInertia(fn (Assert $page) => $page
            ->count('pairings.active', 1)
            ->count('pairings.ended', 1)
            ->where('pairings.active.0.name', $pairing->entrepreneur->name)
            ->where('pairings.active.0.userId', $pairing->entrepreneur_user_id));
});

test('an entrepreneurs detail page lists their mentor', function () {
    $admin = User::factory()->admin()->approved()->create();
    $pairing = App\Models\Pairing::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/users/{$pairing->entrepreneur_user_id}")
        ->assertInertia(fn (Assert $page) => $page
            ->count('pairings.active', 1)
            ->where('pairings.active.0.name', $pairing->mentor->name)
            ->where('pairings.active.0.userId', $pairing->mentor_user_id));
});

test('users without pairings share empty pairing lists', function () {
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)
        ->get("/admin/users/{$admin->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->count('pairings.active', 0)
            ->count('pairings.ended', 0));
});
