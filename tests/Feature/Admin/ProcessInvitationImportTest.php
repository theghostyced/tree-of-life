<?php

use App\Actions\CreateUserInvitation;
use App\Enums\InvitationImportStatus;
use App\Jobs\ProcessInvitationImport;
use App\Mail\UserInvitationMail;
use App\Models\InvitationImport;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Write a CSV (header + rows) to the import's storage path on the faked disk.
 *
 * @param  array<int, string>  $rows
 */
function writeImportCsv(InvitationImport $import, array $rows): void
{
    Storage::disk('local')->put(
        $import->storagePath(),
        implode("\n", ['email,role,name', ...$rows]),
    );
}

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
});

test('valid rows are invited and mailed', function () {
    $import = InvitationImport::factory()->create(['total_rows' => 2]);
    writeImportCsv($import, [
        'amara@example.com,entrepreneur,Amara Okafor',
        'kwame@example.com,MENTOR,',
    ]);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    $import->refresh();
    expect($import->status)->toBe(InvitationImportStatus::Completed)
        ->and($import->invited_count)->toBe(2)
        ->and($import->skipped_count)->toBe(0)
        ->and($import->invalid_count)->toBe(0)
        ->and($import->row_errors)->toBe([])
        ->and(UserInvitation::where('email', 'amara@example.com')->exists())->toBeTrue()
        ->and(UserInvitation::where('email', 'kwame@example.com')->first()->role->value)->toBe('mentor');

    Mail::assertQueued(UserInvitationMail::class, 2);
});

test('rows whose email already belongs to a user are skipped', function () {
    User::factory()->entrepreneur()->approved()->create(['email' => 'taken@example.com']);
    $import = InvitationImport::factory()->create(['total_rows' => 2]);
    writeImportCsv($import, [
        'taken@example.com,entrepreneur,',
        'fresh@example.com,entrepreneur,',
    ]);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    $import->refresh();
    expect($import->invited_count)->toBe(1)
        ->and($import->skipped_count)->toBe(1)
        ->and($import->row_errors)->toBe([
            ['row' => 2, 'email' => 'taken@example.com', 'reason' => 'already a user'],
        ]);
    Mail::assertQueued(UserInvitationMail::class, 1);
});

test('rows with an active invitation for the same role are skipped', function () {
    UserInvitation::factory()->create(['email' => 'invited@example.com']); // pending entrepreneur
    $import = InvitationImport::factory()->create(['total_rows' => 1]);
    writeImportCsv($import, ['invited@example.com,entrepreneur,']);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    $import->refresh();
    expect($import->skipped_count)->toBe(1)
        ->and($import->row_errors[0]['reason'])->toBe('already invited');
    Mail::assertNothingQueued();
});

test('invalid emails and unknown roles are recorded as invalid', function () {
    $import = InvitationImport::factory()->create(['total_rows' => 2]);
    writeImportCsv($import, [
        'not-an-email,entrepreneur,',
        'ok@example.com,principal,',
    ]);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    $import->refresh();
    expect($import->invalid_count)->toBe(2)
        ->and($import->invited_count)->toBe(0)
        ->and($import->row_errors)->toBe([
            ['row' => 2, 'email' => 'not-an-email', 'reason' => 'invalid email'],
            ['row' => 3, 'email' => 'ok@example.com', 'reason' => 'unknown role "principal"'],
        ]);
});

test('duplicate emails within the file are skipped and blank lines ignored', function () {
    $import = InvitationImport::factory()->create(['total_rows' => 2]);
    writeImportCsv($import, [
        'twice@example.com,entrepreneur,',
        '',
        'twice@example.com,mentor,',
    ]);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    $import->refresh();
    expect($import->invited_count)->toBe(1)
        ->and($import->skipped_count)->toBe(1)
        ->and($import->row_errors[0])->toBe(
            ['row' => 4, 'email' => 'twice@example.com', 'reason' => 'duplicate row in file'],
        );
});

test('the row error report caps while counts keep climbing', function () {
    $rows = array_map(fn (int $i) => "bad-row-{$i},entrepreneur,", range(1, InvitationImport::MAX_ROW_ERRORS + 5));
    $import = InvitationImport::factory()->create(['total_rows' => count($rows)]);
    writeImportCsv($import, $rows);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    $import->refresh();
    expect($import->invalid_count)->toBe(InvitationImport::MAX_ROW_ERRORS + 5)
        ->and(count($import->row_errors))->toBe(InvitationImport::MAX_ROW_ERRORS);
});

test('the csv file is deleted when the job finishes', function () {
    $import = InvitationImport::factory()->create(['total_rows' => 1]);
    writeImportCsv($import, ['fresh@example.com,entrepreneur,']);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    Storage::disk('local')->assertMissing($import->storagePath());
});

test('a missing file marks the import failed', function () {
    $import = InvitationImport::factory()->create(['total_rows' => 1]);

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    expect($import->refresh()->status)->toBe(InvitationImportStatus::Failed);
});
