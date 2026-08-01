<?php

use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an entrepreneur uploads a required document to the private disk', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($user)->post('/onboarding/documents', [
        'document_type' => 'business_plan',
        'file' => UploadedFile::fake()->create('plan.pdf', 200, 'application/pdf'),
    ])->assertRedirect();

    $doc = UserDocument::where('user_id', $user->id)
        ->where('document_type', DocumentType::BusinessPlan)->first();

    expect($doc)->not->toBeNull()
        ->and($doc->original_name)->toBe('plan.pdf')
        ->and($doc->disk)->toBe('local')
        ->and($doc->path)->not->toContain('public');

    Storage::disk('local')->assertExists($doc->path);
});

/**
 * The stored mime type decides whether the file is later served inline for
 * preview, so it has to describe the real bytes. The browser-declared
 * Content-Type is attacker-controlled and must never be trusted for that.
 */
test('the stored mime type is detected from content, not the client claim', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();

    // Genuine PNG bytes, uploaded while claiming to be a PDF.
    $genuinePng = UploadedFile::fake()->image('milestones.png', 16, 16);
    $spoofed = new UploadedFile(
        $genuinePng->getPathname(),
        'milestones.png',
        'application/pdf',
        null,
        true,
    );

    $this->actingAs($user)->post('/onboarding/documents', [
        'document_type' => 'milestones',
        'file' => $spoofed,
    ])->assertRedirect();

    $doc = UserDocument::where('user_id', $user->id)
        ->where('document_type', DocumentType::Milestones)->first();

    expect($doc)->not->toBeNull()
        ->and($spoofed->getClientMimeType())->toBe('application/pdf')
        ->and($doc->mime_type)->toBe('image/png');
});

test('an oversized document is rejected and nothing is stored', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();

    // business_plan cap is 5 MB
    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->post('/onboarding/documents', [
            'document_type' => 'business_plan',
            'file' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
        ])->assertSessionHasErrors('file');

    expect(UserDocument::count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('a disallowed file type is rejected', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->post('/onboarding/documents', [
            'document_type' => 'business_plan',
            'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('file');

    expect(UserDocument::count())->toBe(0);
});

test('a user cannot upload a document type outside their role', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();

    // passport_photo is a mentor document type
    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->post('/onboarding/documents', [
            'document_type' => 'passport_photo',
            'file' => UploadedFile::fake()->create('passport.jpg', 100, 'image/jpeg'),
        ])->assertSessionHasErrors('document_type');

    expect(UserDocument::count())->toBe(0);
});

test('re-uploading a required document replaces the previous one and deletes its file', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();

    $this->actingAs($user)->post('/onboarding/documents', [
        'document_type' => 'business_plan',
        'file' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
    ]);
    $first = UserDocument::where('user_id', $user->id)
        ->where('document_type', DocumentType::BusinessPlan)->firstOrFail();

    $this->actingAs($user)->post('/onboarding/documents', [
        'document_type' => 'business_plan',
        'file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
    ]);

    $docs = UserDocument::where('user_id', $user->id)
        ->where('document_type', DocumentType::BusinessPlan)->get();

    expect($docs)->toHaveCount(1)
        ->and($docs->first()->original_name)->toBe('v2.pdf');

    Storage::disk('local')->assertMissing($first->path);
});

test('a guest cannot upload documents', function () {
    $this->post('/onboarding/documents', ['document_type' => 'business_plan'])
        ->assertRedirect('/login');

    expect(UserDocument::count())->toBe(0);
});
