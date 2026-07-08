<?php

use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function makeNotification(User $user, array $overrides = []): string
{
    $id = (string) Str::uuid();
    $user->notifications()->create(array_merge([
        'id' => $id,
        'type' => 'App\\Notifications\\Test',
        'data' => [
            'category' => 'meeting',
            'title' => 'A call was booked',
            'body' => 'Grace booked a call with you.',
            'actions' => [['label' => 'View meeting', 'url' => '/entrepreneur/meetings']],
            'illustration' => null,
        ],
    ], $overrides));

    return $id;
}

test('a user sees only their own notifications', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    makeNotification($user);
    makeNotification($other);

    $this->actingAs($user)->getJson('/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.title', 'A call was booked')
        ->assertJsonPath('notifications.0.actions.0.label', 'View meeting')
        ->assertJsonPath('unreadCount', 1);
});

test('a user can mark a single notification read', function () {
    $user = User::factory()->create();
    $id = makeNotification($user);

    $this->actingAs($user)->postJson("/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('a user can mark all notifications read', function () {
    $user = User::factory()->create();
    makeNotification($user);
    makeNotification($user);

    $this->actingAs($user)->postJson('/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('a user cannot mark another users notification read', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $id = makeNotification($other);

    $this->actingAs($user)->postJson("/notifications/{$id}/read")->assertNotFound();
    expect($other->unreadNotifications()->count())->toBe(1);
});

test('the shared inertia prop exposes the unread count and recent notifications', function () {
    $user = completeEntrepreneur();
    makeNotification($user);

    $this->actingAs($user)->get('/entrepreneur/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 1)
            ->has('notifications.recent', 1)
            ->where('notifications.recent.0.title', 'A call was booked'));
});
