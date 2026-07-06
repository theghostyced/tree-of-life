<?php

use App\Enums\AccountStatus;
use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\UserInvitation;

test('user roles have stable string values', function () {
    expect(UserRole::Admin->value)->toBe('admin')
        ->and(UserRole::Mentor->value)->toBe('mentor')
        ->and(UserRole::Entrepreneur->value)->toBe('entrepreneur')
        ->and(UserRole::Employee->value)->toBe('employee');
});

test('the invitation matrix encodes who may invite whom', function (string $inviter, string $invitee, bool $allowed) {
    expect(UserRole::from($inviter)->canInvite(UserRole::from($invitee)))->toBe($allowed);
})->with([
    'admin -> admin' => ['admin', 'admin', true],
    'admin -> mentor' => ['admin', 'mentor', true],
    'admin -> entrepreneur' => ['admin', 'entrepreneur', true],
    'admin -> employee' => ['admin', 'employee', true],
    'entrepreneur -> employee' => ['entrepreneur', 'employee', true],
    'entrepreneur -> mentor' => ['entrepreneur', 'mentor', false],
    'entrepreneur -> entrepreneur' => ['entrepreneur', 'entrepreneur', false],
    'entrepreneur -> admin' => ['entrepreneur', 'admin', false],
    'mentor -> anyone' => ['mentor', 'entrepreneur', false],
    'employee -> anyone' => ['employee', 'employee', false],
]);

test('account status transitions follow the lifecycle', function (string $from, string $to, bool $allowed) {
    expect(AccountStatus::from($from)->canTransitionTo(AccountStatus::from($to)))->toBe($allowed);
})->with([
    'approved -> deactivated' => ['approved', 'deactivated', true],
    'deactivated -> approved' => ['deactivated', 'approved', true],
    'approved -> approved' => ['approved', 'approved', false],
    'deactivated -> deactivated' => ['deactivated', 'deactivated', false],
]);

test('invitation status is derived from its timestamps', function () {
    $pending = new UserInvitation(['expires_at' => now()->addDay()]);
    expect($pending->status())->toBe(InvitationStatus::Pending)
        ->and($pending->isPending())->toBeTrue();

    $accepted = new UserInvitation(['expires_at' => now()->addDay(), 'accepted_at' => now()]);
    expect($accepted->status())->toBe(InvitationStatus::Accepted)
        ->and($accepted->isPending())->toBeFalse();

    $revoked = new UserInvitation(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
    expect($revoked->status())->toBe(InvitationStatus::Revoked);

    $expired = new UserInvitation(['expires_at' => now()->subDay()]);
    expect($expired->status())->toBe(InvitationStatus::Expired)
        ->and($expired->isPending())->toBeFalse();
});
