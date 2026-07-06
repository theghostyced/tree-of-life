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
