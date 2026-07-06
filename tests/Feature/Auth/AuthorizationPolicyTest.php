<?php

use App\Models\User;
use App\Models\UserDocument;
use App\Models\UserInvitation;

/*
 * UserInvitationPolicy — managing invitations is an approved-admin capability.
 */
test('an approved admin may view and create invitations', function () {
    $admin = User::factory()->admin()->approved()->create();

    expect($admin->can('viewAny', UserInvitation::class))->toBeTrue()
        ->and($admin->can('create', UserInvitation::class))->toBeTrue();
});

test('non-admins may not manage invitations', function (string $factory) {
    $user = User::factory()->{$factory}()->approved()->create();

    expect($user->can('viewAny', UserInvitation::class))->toBeFalse()
        ->and($user->can('create', UserInvitation::class))->toBeFalse();
})->with(['entrepreneur', 'mentor', 'employee']);

test('a deactivated admin may not manage invitations', function () {
    $admin = User::factory()->admin()->deactivated()->create();

    expect($admin->can('create', UserInvitation::class))->toBeFalse();
});

test('an invitation may be revoked only while it is still pending', function () {
    $admin = User::factory()->admin()->approved()->create();

    $pending = UserInvitation::factory()->pending()->create();
    $accepted = UserInvitation::factory()->accepted()->create();
    $revoked = UserInvitation::factory()->revoked()->create();

    expect($admin->can('revoke', $pending))->toBeTrue()
        ->and($admin->can('revoke', $accepted))->toBeFalse()
        ->and($admin->can('revoke', $revoked))->toBeFalse();
});

test('a non-admin cannot revoke an invitation', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $pending = UserInvitation::factory()->pending()->create();

    expect($entrepreneur->can('revoke', $pending))->toBeFalse();
});

/*
 * UserDocumentPolicy — a private document is visible to its owner and to admins.
 */
test('a document is viewable by its owner and by admins, and no one else', function () {
    $owner = User::factory()->entrepreneur()->create();
    $document = UserDocument::factory()->for($owner)->create();

    $admin = User::factory()->admin()->approved()->create();
    $stranger = User::factory()->entrepreneur()->create();

    expect($owner->can('view', $document))->toBeTrue()
        ->and($admin->can('view', $document))->toBeTrue()
        ->and($stranger->can('view', $document))->toBeFalse();
});

test('a document may be deleted by its owner but not by a stranger', function () {
    $owner = User::factory()->entrepreneur()->create();
    $document = UserDocument::factory()->for($owner)->create();

    expect($owner->can('delete', $document))->toBeTrue()
        ->and(User::factory()->entrepreneur()->create()->can('delete', $document))->toBeFalse();
});
