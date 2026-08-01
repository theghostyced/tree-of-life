<?php

use App\Enums\DocumentType;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Support\Facades\Storage;

function reviewableDocument(
    User $user,
    DocumentType $type,
    string $mimeType,
    string $originalName,
): UserDocument {
    $document = UserDocument::factory()->for($user)->create([
        'document_type' => $type,
        'disk' => 'local',
        'mime_type' => $mimeType,
        'original_name' => $originalName,
    ]);

    Storage::disk('local')->put($document->path, '%PDF-1.4 stand-in bytes');

    return $document;
}

it('opens a document in a modal and closes it again', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->approved()->create();
    $founder = User::factory()->entrepreneur()->approved()->create(['name' => 'Fatou Founder']);

    $plan = reviewableDocument($founder, DocumentType::BusinessPlan, 'application/pdf', 'business-plan.pdf');
    $operations = reviewableDocument($founder, DocumentType::OperationalPlan, 'application/pdf', 'operational-plan.pdf');

    $this->actingAs($admin);

    $page = visit("/admin/users/{$founder->id}")
        ->assertPresent('html[data-hydrated=true]');

    $page->assertSee('Fatou Founder')
        ->assertSee('business-plan.pdf')
        ->assertPresent("@view-document-{$plan->id}")
        ->assertNotPresent('dialog[open]')
        ->assertNotPresent('@document-preview-frame');

    $page->click("@view-document-{$plan->id}")
        ->assertPresent('dialog[open]')
        ->assertPresent('@document-preview-frame')
        ->assertAttributeContains(
            '@document-preview-frame',
            'src',
            "/onboarding/documents/{$plan->id}/preview",
        )
        ->assertPresent('@dialog-download-document')
        // The modal is a layer over the page, not a navigation away from it.
        ->assertPathIs("/admin/users/{$founder->id}");

    $page->click('@close-document-preview')
        ->assertNotPresent('dialog[open]')
        ->assertNotPresent('@document-preview-frame')
        ->assertNoJavaScriptErrors();

    // A different row opens its own file, not the previously viewed one.
    $page->click("@view-document-{$operations->id}")
        ->assertPresent('dialog[open]')
        ->assertAttributeContains(
            '@document-preview-frame',
            'src',
            "/onboarding/documents/{$operations->id}/preview",
        )
        ->assertNoJavaScriptErrors();
});

it('offers download but not view for a file no browser can render', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->approved()->create();
    $founder = User::factory()->entrepreneur()->approved()->create();

    $word = reviewableDocument(
        $founder,
        DocumentType::BusinessPlan,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'business-plan.docx',
    );

    $this->actingAs($admin);

    visit("/admin/users/{$founder->id}")
        ->assertPresent('html[data-hydrated=true]')
        ->assertSee('business-plan.docx')
        ->assertPresent("@download-document-{$word->id}")
        ->assertNotPresent("@view-document-{$word->id}")
        ->assertNoJavaScriptErrors();
});

it('shows a retired document type as no longer required, still reviewable', function () {
    Storage::fake('local');

    $admin = User::factory()->admin()->approved()->create();
    $founder = User::factory()->entrepreneur()->approved()->create();

    $certificate = reviewableDocument(
        $founder,
        DocumentType::BusinessCertificate,
        'application/pdf',
        'certificate.pdf',
    );

    $this->actingAs($admin);

    visit("/admin/users/{$founder->id}")
        ->assertPresent('html[data-hydrated=true]')
        ->assertSee('Business Certificate')
        ->assertSee('No longer required')
        ->assertPresent("@view-document-{$certificate->id}")
        ->assertPresent("@download-document-{$certificate->id}")
        // The three current requirements are still listed as empty slots.
        ->assertSee('Milestones')
        ->assertSee('Not uploaded')
        ->assertNoJavaScriptErrors();
});

it('refuses the review page to a signed-in non-admin', function () {
    Storage::fake('local');

    $founder = User::factory()->entrepreneur()->approved()->create();
    reviewableDocument($founder, DocumentType::BusinessPlan, 'application/pdf', 'business-plan.pdf');

    $this->actingAs(User::factory()->mentor()->approved()->create());

    visit("/admin/users/{$founder->id}")
        ->assertPresent('html[data-hydrated=true]')
        ->assertDontSee('business-plan.pdf')
        ->assertDontSee('Revoke access');
});
