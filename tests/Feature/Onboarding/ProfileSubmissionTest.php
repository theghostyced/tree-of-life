<?php

use App\Enums\AccountStatus;
use App\Enums\DocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserDocument;

/** A draft entrepreneur with every required field and document present — ready to submit. */
function completeEntrepreneur(): User
{
    $user = User::factory()->entrepreneur()->draft()->create(['phone_number' => '+2547'.fake()->numerify('########')]);

    $user->entrepreneurProfile()->update([
        'business_name' => 'Acme Textiles',
        'business_description' => 'Woven goods for export.',
        'business_email' => 'biz'.$user->id.'@example.com',
        'business_phone_number' => '+2548'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
        'sector' => ['manufacturing'],
        'years_in_operation' => 4,
        'employee_count' => 12,
    ]);

    foreach (DocumentType::requiredFor(UserRole::Entrepreneur) as $type) {
        UserDocument::factory()->for($user)->create(['document_type' => $type]);
    }

    return $user;
}

test('an entrepreneur cannot submit an incomplete profile', function () {
    $user = User::factory()->entrepreneur()->draft()->create();

    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->post('/onboarding/submit')
        ->assertSessionHasErrors();

    expect($user->fresh())
        ->account_status->toBe(AccountStatus::Draft)
        ->profile_submitted_at->toBeNull();
});

test('a profile missing only its documents still cannot be submitted', function () {
    $user = completeEntrepreneur();
    $user->documents()->delete();

    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->post('/onboarding/submit')
        ->assertSessionHasErrors();

    expect($user->fresh()->account_status)->toBe(AccountStatus::Draft);
});

test('submitting a complete profile moves the account from draft to pending', function () {
    $user = completeEntrepreneur();

    $this->actingAs($user)->post('/onboarding/submit')->assertRedirect();

    expect($user->fresh())
        ->account_status->toBe(AccountStatus::Pending)
        ->profile_submitted_at->not->toBeNull();
});

test('an already-pending profile cannot be resubmitted', function () {
    $user = completeEntrepreneur();
    $user->update(['account_status' => AccountStatus::Pending, 'profile_submitted_at' => now()]);

    $this->actingAs($user)->from('/entrepreneur/dashboard')
        ->post('/onboarding/submit')
        ->assertForbidden();
});

test('an admin approves a pending account', function () {
    $admin = User::factory()->admin()->approved()->create();
    $user = User::factory()->entrepreneur()->pending()->create();

    $this->actingAs($admin)->post("/admin/users/{$user->id}/approve")->assertRedirect();

    expect($user->fresh()->account_status)->toBe(AccountStatus::Approved);
});

test('an admin rejects a pending account with a reason, and the user may resubmit', function () {
    $admin = User::factory()->admin()->approved()->create();
    $user = completeEntrepreneur();
    $user->update(['account_status' => AccountStatus::Pending, 'profile_submitted_at' => now()]);

    $this->actingAs($admin)
        ->post("/admin/users/{$user->id}/reject", ['reason' => 'Business certificate is illegible.'])
        ->assertRedirect();

    expect($user->fresh()->account_status)->toBe(AccountStatus::Rejected);

    // A rejected user can revise and resubmit: rejected -> pending.
    $this->actingAs($user->fresh())->post('/onboarding/submit')->assertRedirect();

    expect($user->fresh()->account_status)->toBe(AccountStatus::Pending);
});

test('rejection requires a reason', function () {
    $admin = User::factory()->admin()->approved()->create();
    $user = User::factory()->entrepreneur()->pending()->create();

    $this->actingAs($admin)->from('/admin/dashboard')
        ->post("/admin/users/{$user->id}/reject", [])
        ->assertSessionHasErrors('reason');

    expect($user->fresh()->account_status)->toBe(AccountStatus::Pending);
});

test('a non-admin cannot approve or reject accounts', function () {
    $user = User::factory()->entrepreneur()->pending()->create();
    $actor = User::factory()->mentor()->approved()->create();

    $this->actingAs($actor)->post("/admin/users/{$user->id}/approve")->assertForbidden();
    $this->actingAs($actor)->post("/admin/users/{$user->id}/reject", ['reason' => 'x'])->assertForbidden();

    expect($user->fresh()->account_status)->toBe(AccountStatus::Pending);
});
