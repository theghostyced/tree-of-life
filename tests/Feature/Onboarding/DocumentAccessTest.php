<?php

use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Support\Facades\Storage;

/** A stored document owned by the given user, with a real file behind it on the fake private disk. */
function storedDocumentFor(User $user, ?string $mimeType = null, ?string $originalName = null): UserDocument
{
    $doc = UserDocument::factory()->for($user)->create(array_filter([
        'disk' => 'local',
        'mime_type' => $mimeType,
        'original_name' => $originalName,
    ]));
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

/*
 * Inline preview. The preview route serves the same private bytes as the
 * download route, so it must be gated identically: making a file viewable must
 * never make it more reachable.
 */

test('the preview route serves the file inline so it can render in a frame', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($user, 'application/pdf');

    $response = $this->actingAs($user)->get("/onboarding/documents/{$doc->id}/preview");

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($response->headers->get('content-disposition'))
        ->toStartWith('inline')
        ->toContain($doc->original_name);
});

test('the download route still forces an attachment, unlike preview', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($user, 'application/pdf');

    $response = $this->actingAs($user)->get("/onboarding/documents/{$doc->id}");

    expect($response->headers->get('content-disposition'))->toStartWith('attachment');
});

test('preview is refused to a user who does not own the document', function () {
    Storage::fake('local');
    $doc = storedDocumentFor(User::factory()->entrepreneur()->create(), 'application/pdf');

    $this->actingAs(User::factory()->entrepreneur()->create())
        ->get("/onboarding/documents/{$doc->id}/preview")
        ->assertForbidden();
});

test('an admin may preview any user document for review', function () {
    Storage::fake('local');
    $doc = storedDocumentFor(User::factory()->mentor()->create(), 'application/pdf');

    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get("/onboarding/documents/{$doc->id}/preview")
        ->assertSuccessful();
});

test('a guest cannot preview a document', function () {
    Storage::fake('local');
    $doc = storedDocumentFor(User::factory()->entrepreneur()->create(), 'application/pdf');

    $this->get("/onboarding/documents/{$doc->id}/preview")->assertRedirect('/login');
});

test('a file no browser can render inline is not previewable', function (string $mime, bool $previewable) {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($user, $mime);

    expect($doc->isPreviewable())->toBe($previewable);

    $this->actingAs($user)
        ->get("/onboarding/documents/{$doc->id}/preview")
        ->assertStatus($previewable ? 200 : 404);
})->with([
    'pdf' => ['application/pdf', true],
    'png' => ['image/png', true],
    'jpeg' => ['image/jpeg', true],
    'word document' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', false],
]);

/**
 * SVG is active content: it can carry a <script> that runs on our own origin
 * with the viewer's session. nosniff does not help, because the type is
 * declared rather than sniffed.
 */
test('an svg is never previewable, even though it is an image', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($user, 'image/svg+xml');

    expect($doc->isPreviewable())->toBeFalse();

    $this->actingAs($user)
        ->get("/onboarding/documents/{$doc->id}/preview")
        ->assertNotFound();
});

test('a non-ascii filename is rfc 5987 encoded in the content disposition', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($user, 'application/pdf', 'Résumé.pdf');

    $disposition = $this->actingAs($user)
        ->get("/onboarding/documents/{$doc->id}/preview")
        ->headers->get('content-disposition');

    expect($disposition)->toContain("filename*=utf-8''");
});

test('a filename containing crlf cannot inject a response header', function () {
    Storage::fake('local');
    $user = User::factory()->entrepreneur()->create();
    $doc = storedDocumentFor($user, 'application/pdf', "invoice\r\nX-Injected: yes.pdf");

    $disposition = $this->actingAs($user)
        ->get("/onboarding/documents/{$doc->id}/preview")
        ->headers->get('content-disposition');

    expect($disposition)->not->toContain("\r")
        ->and($disposition)->not->toContain("\n")
        ->and($disposition)->not->toContain('X-Injected: yes');
});

test('documents are stored as relative private paths, never public URLs', function () {
    $doc = UserDocument::factory()->create();

    expect($doc->path)->not->toStartWith('http')
        ->and($doc->path)->not->toStartWith('/storage')
        ->and($doc->path)->not->toContain('public');
});
