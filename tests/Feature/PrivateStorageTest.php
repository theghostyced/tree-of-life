<?php

use App\Actions\CreateUserInvitation;
use App\Enums\DocumentType;
use App\Enums\InvitationImportStatus;
use App\Jobs\ProcessInvitationImport;
use App\Models\InvitationImport;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Private uploads must follow `filesystems.private` rather than a hardcoded
 * disk. Production runs on an ephemeral container filesystem, so anything
 * written to the local disk there is lost on the next deploy.
 */
test('user documents are written to the configured private disk', function () {
    config(['filesystems.private' => 's3']);
    Storage::fake('s3');
    Storage::fake('local');

    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($user)->post('/onboarding/documents', [
        'document_type' => 'business_plan',
        'file' => UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf'),
    ])->assertRedirect();

    $document = UserDocument::where('user_id', $user->id)->sole();

    expect($document->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($document->path);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('a document is downloaded from the disk recorded on the row, not the current default', function () {
    Storage::fake('s3');
    Storage::fake('local');

    $user = User::factory()->entrepreneur()->create();
    $document = UserDocument::factory()->for($user)->create([
        'document_type' => DocumentType::BusinessPlan,
        'disk' => 's3',
        'original_name' => 'plan.pdf',
    ]);
    Storage::disk('s3')->put($document->path, 'archived-bytes');

    // The default moved on; rows written before the move must still resolve.
    config(['filesystems.private' => 'local']);

    $response = $this->actingAs($user)->get("/onboarding/documents/{$document->id}");

    $response->assertOk();
    expect($response->streamedContent())->toBe('archived-bytes');
});

test('replacing a document deletes the old file from its own disk', function () {
    config(['filesystems.private' => 's3']);
    Storage::fake('s3');

    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($user)->post('/onboarding/documents', [
        'document_type' => 'business_plan',
        'file' => UploadedFile::fake()->create('first.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $first = UserDocument::where('user_id', $user->id)->sole();

    $this->actingAs($user)->post('/onboarding/documents', [
        'document_type' => 'business_plan',
        'file' => UploadedFile::fake()->create('second.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $second = UserDocument::where('user_id', $user->id)->sole();

    expect($second->original_name)->toBe('second.pdf');
    Storage::disk('s3')->assertMissing($first->path);
    Storage::disk('s3')->assertExists($second->path);
});

test('invitation import uploads are written to the configured private disk', function () {
    config(['filesystems.private' => 's3']);
    Storage::fake('s3');
    Storage::fake('local');
    Queue::fake(); // The job deletes the upload when it finishes.

    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)->post('/admin/invitations/import', [
        'file' => UploadedFile::fake()->createWithContent(
            'invites.csv',
            "email,role,name\namara@example.com,entrepreneur,Amara Okafor\n",
        ),
    ])->assertRedirect();

    $import = InvitationImport::sole();

    Storage::disk('s3')->assertExists($import->storagePath());
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('the import job reads and cleans up on the configured private disk', function () {
    config(['filesystems.private' => 's3']);
    Storage::fake('s3');

    $admin = User::factory()->admin()->approved()->create();
    $import = InvitationImport::create([
        'imported_by' => $admin->id,
        'filename' => 'invites.csv',
        'total_rows' => 1,
    ]);

    Storage::disk('s3')->put(
        $import->storagePath(),
        "email,role,name\nkwame@example.com,mentor,Kwame Mensah\n",
    );

    (new ProcessInvitationImport($import))->handle(app(CreateUserInvitation::class));

    expect($import->fresh()->status)->toBe(InvitationImportStatus::Completed)
        ->and($import->fresh()->invited_count)->toBe(1);
    Storage::disk('s3')->assertMissing($import->storagePath());
});
