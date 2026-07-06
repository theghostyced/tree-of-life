<?php

use App\Enums\InvitationImportStatus;
use App\Models\InvitationImport;
use App\Models\User;

test('an invitation import casts its status and row errors', function () {
    $import = InvitationImport::factory()->create([
        'status' => InvitationImportStatus::Processing,
        'row_errors' => [['row' => 2, 'email' => 'a@b.com', 'reason' => 'already a user']],
    ]);

    $import->refresh();

    expect($import->status)->toBe(InvitationImportStatus::Processing)
        ->and($import->row_errors)->toBe([['row' => 2, 'email' => 'a@b.com', 'reason' => 'already a user']])
        ->and($import->storagePath())->toBe("invitation-imports/{$import->id}.csv")
        ->and($import->importer)->toBeInstanceOf(User::class);
});

test('terminal statuses are completed and failed', function () {
    expect(InvitationImportStatus::Pending->isTerminal())->toBeFalse()
        ->and(InvitationImportStatus::Processing->isTerminal())->toBeFalse()
        ->and(InvitationImportStatus::Completed->isTerminal())->toBeTrue()
        ->and(InvitationImportStatus::Failed->isTerminal())->toBeTrue();
});
