<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the admin dashboard renders program metrics', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->mentor()->create();
    User::factory()->entrepreneur()->create();

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('stats.people', 3)
            ->where('stats.mentors', 1)
            ->where('stats.entrepreneurs', 1)
            ->has('readiness')
            ->has('recent', 3)
            ->has('pendingInvitations'));
});

test('a non-admin cannot see the admin dashboard', function () {
    $this->actingAs(User::factory()->entrepreneur()->create())
        ->get('/admin/dashboard')->assertForbidden();
});
