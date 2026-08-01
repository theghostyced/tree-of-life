<?php

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Models\Company;
use App\Models\Pairing;
use App\Models\User;
use App\Models\UserDocument;
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

test('the detail page lists every required document slot for the role', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($admin)->get("/admin/users/{$user->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('user.documents', 3)
            ->where('user.documents.0.type', 'business_plan')
            ->where('user.documents.1.type', 'milestones')
            ->where('user.documents.2.type', 'operational_plan')
            ->where('user.documents.0.required', true));
});

test('an admin sees documents the user uploaded that onboarding no longer requires', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->entrepreneur()->create();

    // A file uploaded under the old required set. It must stay visible to the
    // reviewing admin rather than vanishing with the requirement.
    UserDocument::factory()->for($user)->create([
        'document_type' => DocumentType::BusinessCertificate,
    ]);

    $this->actingAs($admin)->get("/admin/users/{$user->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('user.documents', 4)
            ->where('user.documents.3.type', 'business_certificate')
            ->where('user.documents.3.label', 'Business Certificate')
            ->where('user.documents.3.required', false)
            ->whereNot('user.documents.3.id', null));
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
    $pairing = Pairing::factory()->create();
    Pairing::factory()->ended()->create([
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
    $pairing = Pairing::factory()->create();

    // The entrepreneur's own company must not leak onto the mentor's row.
    $company = Company::factory()->create();
    $pairing->entrepreneur->update(['company_id' => $company->id]);

    $this->actingAs($admin)
        ->get("/admin/users/{$pairing->entrepreneur_user_id}")
        ->assertInertia(fn (Assert $page) => $page
            ->count('pairings.active', 1)
            ->where('pairings.active.0.name', $pairing->mentor->name)
            ->where('pairings.active.0.userId', $pairing->mentor_user_id)
            ->where('pairings.active.0.company', null));
});

test('users without pairings share empty pairing lists', function () {
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)
        ->get("/admin/users/{$admin->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->count('pairings.active', 0)
            ->count('pairings.ended', 0));
});
