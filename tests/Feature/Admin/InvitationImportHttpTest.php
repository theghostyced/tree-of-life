<?php

use App\Jobs\ProcessInvitationImport;
use App\Models\InvitationImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->approved()->create();
});

function csvUpload(string $content, string $name = 'invites.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

test('the template downloads with the documented header and examples', function () {
    $response = $this->actingAs($this->admin)->get('/admin/invitations/import/template');

    $response->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=invitations-template.csv');

    $content = $response->streamedContent();
    expect($content)->toStartWith("email,role,name\n")
        ->and($content)->toContain('amara@example.com,entrepreneur,Amara Okafor')
        ->and($content)->toContain('kwame@example.com,mentor,');
});

test('a valid upload stores the file, creates a pending import, and dispatches the job', function () {
    Queue::fake();

    $csv = "email,role,name\none@example.com,entrepreneur,One\ntwo@example.com,mentor,\n";

    $this->actingAs($this->admin)
        ->post('/admin/invitations/import', ['file' => csvUpload($csv)])
        ->assertRedirect();

    $import = InvitationImport::sole();
    expect($import->filename)->toBe('invites.csv')
        ->and($import->total_rows)->toBe(2)
        ->and($import->imported_by)->toBe($this->admin->id);

    Storage::disk('local')->assertExists($import->storagePath());
    Queue::assertPushed(ProcessInvitationImport::class, fn ($job) => $job->import->is($import));
});

test('rows with an empty email cell still count toward total rows', function () {
    Queue::fake();

    $csv = "email,role,name\n,mentor,Someone\n";

    $this->actingAs($this->admin)
        ->post('/admin/invitations/import', ['file' => csvUpload($csv)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(InvitationImport::sole()->total_rows)->toBe(1);
});

test('uploads with a wrong header are rejected', function () {
    $csv = "name,email,role\nOne,one@example.com,entrepreneur\n";

    $this->actingAs($this->admin)
        ->post('/admin/invitations/import', ['file' => csvUpload($csv)])
        ->assertSessionHasErrors('file');

    expect(InvitationImport::count())->toBe(0);
});

test('uploads with no data rows are rejected', function () {
    $this->actingAs($this->admin)
        ->post('/admin/invitations/import', ['file' => csvUpload("email,role,name\n")])
        ->assertSessionHasErrors('file');
});

test('non-csv uploads are rejected', function () {
    $this->actingAs($this->admin)
        ->post('/admin/invitations/import', ['file' => UploadedFile::fake()->image('invites.png')])
        ->assertSessionHasErrors('file');
});

test('the invitations index shares the latest import as activeImport', function () {
    $import = InvitationImport::factory()->create([
        'imported_by' => $this->admin->id,
        'filename' => 'cohort.csv',
        'total_rows' => 10,
    ]);

    $this->actingAs($this->admin)
        ->get('/admin/invitations')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/invitations/Index')
            ->where('activeImport.id', $import->id)
            ->where('activeImport.filename', 'cohort.csv')
            ->where('activeImport.status', 'pending')
            ->where('activeImport.totalRows', 10));
});

test('the invitations index shares null when no import exists', function () {
    $this->actingAs($this->admin)
        ->get('/admin/invitations')
        ->assertInertia(fn (Assert $page) => $page->where('activeImport', null));
});

test('non-admins cannot reach the import endpoints', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($entrepreneur)->get('/admin/invitations/import/template')->assertForbidden();
    $this->actingAs($entrepreneur)
        ->post('/admin/invitations/import', ['file' => csvUpload("email,role,name\na@b.com,mentor,\n")])
        ->assertForbidden();
});
