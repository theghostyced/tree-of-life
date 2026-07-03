<?php

use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Support\Facades\Storage;

/** A stored document owned by the given user, with a real file behind it on the fake private disk. */
function storedDocumentFor(User $user): UserDocument
{
    $doc = UserDocument::factory()->for($user)->create(['disk' => 'local']);
    Storage::disk('local')->put($doc->path, 'binary-content');

    return $doc;
}

test('the owner can stream their own private document', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($user);

    $this->actingAs($user)->get("/onboarding/documents/{$doc->id}")->assertSuccessful();
});

test('a different user cannot access someone else\'s document', function () {
    Storage::fake('local');
    $owner = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($owner);

    $this->actingAs(User::factory()->entrepreneur()->create())
        ->get("/onboarding/documents/{$doc->id}")
        ->assertForbidden();
});

test('an admin may access any user document for review', function () {
    Storage::fake('local');
    $owner = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($owner);

    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get("/onboarding/documents/{$doc->id}")
        ->assertSuccessful();
});

test('a guest is redirected to login', function () {
    Storage::fake('local');
    $doc = storedDocumentFor(User::factory()->entrepreneur()->create());

    $this->get("/onboarding/documents/{$doc->id}")->assertRedirect('/login');
});

test('documents are stored as relative private paths, never public URLs', function () {
    $doc = UserDocument::factory()->create();

    expect($doc->path)->not->toStartWith('http')
        ->and($doc->path)->not->toStartWith('/storage')
        ->and($doc->path)->not->toContain('public');
});
