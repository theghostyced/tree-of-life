# CSV Bulk Invitations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Admins can download a CSV template, upload a filled CSV, and have every row invited by a queued job — skipping rows whose email already belongs to a user or an active invitation — with live progress and a per-row skipped/invalid report on the invitations page.

**Architecture:** A new `invitation_imports` table + `InvitationImport` model track each upload. `POST /admin/invitations/import` validates the file, stores it on the private local disk, and dispatches the queued `ProcessInvitationImport` job, which streams the CSV with native `fgetcsv` and reuses `App\Actions\CreateUserInvitation` + `UserInvitationMail` per row. The invitations Index page polls (`usePoll`) while an import is active and renders a progress strip, then a summary + failed-row report.

**Tech Stack:** Laravel 13 (Pest tests, queued jobs, private local disk), Inertia v3 + Svelte 5 runes, Tailwind tokens, native PHP CSV parsing (no new Composer/npm dependencies).

## Global Constraints

- No new Composer or npm dependencies (spec: native `fgetcsv`, no `maatwebsite/excel`).
- Never hardcode colors — Tailwind tokens only (`bg-surface`, `text-ink`, `border-line`, `bg-elevated`, `text-muted`, `text-faint`, `bg-accent`, `text-on-accent`, `bg-error`, `bg-secondary-blue`, `bg-accent-orange`, `ring-accent/60`).
- Components never live under `resources/js/pages/` — new Svelte pieces go in `resources/js/components/invitations/`.
- Follow sibling idioms: Svelte 5 runes, Lucide icons `strokeWidth={1.75}`, `cn()` from `@/lib/utils`, Pest feature tests, PHP files pass `./vendor/bin/pint --test`.
- CSV contract (spec, verbatim): header exactly `email,role,name` (case-insensitive, order-sensitive); `role` ∈ `UserRole::invitable()` case-insensitive; `name` optional; upload mime csv/plain-text, max 10 MB.
- Row numbering in reports: the file line number — header is line 1, first data row is line 2 (matches what admins see in a spreadsheet).
- `row_errors` JSON caps at `1000` entries; counts keep incrementing past the cap.
- Counts persist every `25` processed rows.
- Skips are never errors: the job always continues to the next row.
- The main checkout has unrelated user work-in-progress. NEVER run `git add -A` / `git add .`; stage only the exact files each commit step names.
- Frontend verification: `pnpm types:check` must report `0 ERRORS` (pre-existing warnings in auth/onboarding files are expected); `npx eslint <changed files>` must be clean. Backend: `php artisan test --compact` all passing.
- Commit messages: imperative mood, no co-author trailers.

---

### Task 1: InvitationImport model, status enum, migration, factory

**Files:**
- Create: `app/Enums/InvitationImportStatus.php`
- Create: `app/Models/InvitationImport.php`
- Create: `database/migrations/2026_07_06_000001_create_invitation_imports_table.php`
- Create: `database/factories/InvitationImportFactory.php`
- Test: `tests/Feature/Admin/InvitationImportModelTest.php`

**Interfaces:**
- Consumes: `App\Models\User` (factory: `User::factory()->admin()->approved()`).
- Produces (later tasks rely on these exact names):
  - `InvitationImportStatus` string-backed enum, cases `Pending='pending'`, `Processing='processing'`, `Completed='completed'`, `Failed='failed'`, method `isTerminal(): bool`.
  - `InvitationImport` model: fillable `imported_by, filename, status, total_rows, invited_count, skipped_count, invalid_count, row_errors`; casts `status` → enum, `row_errors` → `array`; const `MAX_ROW_ERRORS = 1000`; method `storagePath(): string` returning `"invitation-imports/{id}.csv"`; relation `importer()` (BelongsTo User via `imported_by`).
  - Factory default: pending import, `total_rows` 0, counts 0, `row_errors` `[]`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/InvitationImportModelTest.php`:

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Admin/InvitationImportModelTest.php`
Expected: FAIL — `Class "App\Models\InvitationImport" not found`.

- [ ] **Step 3: Write the enum, migration, model, factory**

Create `app/Enums/InvitationImportStatus.php`:

```php
<?php

namespace App\Enums;

/**
 * Lifecycle of a CSV invitation import. Stored on invitation_imports.status.
 */
enum InvitationImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
```

Create `database/migrations/2026_07_06_000001_create_invitation_imports_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->string('filename');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('invited_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->json('row_errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitation_imports');
    }
};
```

Create `app/Models/InvitationImport.php`:

```php
<?php

namespace App\Models;

use App\Enums\InvitationImportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationImport extends Model
{
    /** @use HasFactory<\Database\Factories\InvitationImportFactory> */
    use HasFactory;

    /**
     * The skipped/invalid report stops growing at this many entries;
     * the counts keep incrementing past it.
     */
    public const MAX_ROW_ERRORS = 1000;

    protected $fillable = [
        'imported_by',
        'filename',
        'status',
        'total_rows',
        'invited_count',
        'skipped_count',
        'invalid_count',
        'row_errors',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationImportStatus::class,
            'row_errors' => 'array',
        ];
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * Where the uploaded CSV lives on the private local disk until the job finishes.
     */
    public function storagePath(): string
    {
        return "invitation-imports/{$this->id}.csv";
    }
}
```

Create `database/factories/InvitationImportFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\InvitationImportStatus;
use App\Models\InvitationImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvitationImport>
 */
class InvitationImportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'imported_by' => User::factory()->admin()->approved(),
            'filename' => 'invitations.csv',
            'status' => InvitationImportStatus::Pending,
            'total_rows' => 0,
            'invited_count' => 0,
            'skipped_count' => 0,
            'invalid_count' => 0,
            'row_errors' => [],
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Admin/InvitationImportModelTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Pint and commit**

```bash
./vendor/bin/pint --test app/Enums/InvitationImportStatus.php app/Models/InvitationImport.php database/migrations/2026_07_06_000001_create_invitation_imports_table.php database/factories/InvitationImportFactory.php tests/Feature/Admin/InvitationImportModelTest.php
git add app/Enums/InvitationImportStatus.php app/Models/InvitationImport.php database/migrations/2026_07_06_000001_create_invitation_imports_table.php database/factories/InvitationImportFactory.php tests/Feature/Admin/InvitationImportModelTest.php
git commit -m "Add InvitationImport model for CSV invitation imports"
```

---

### Task 2: ProcessInvitationImport queued job

**Files:**
- Create: `app/Jobs/ProcessInvitationImport.php`
- Test: `tests/Feature/Admin/ProcessInvitationImportTest.php`

**Interfaces:**
- Consumes: `InvitationImport` (Task 1: `storagePath()`, `MAX_ROW_ERRORS`, `InvitationImportStatus`), `App\Actions\CreateUserInvitation::handle(string $email, UserRole $role, User $invitedBy, ?int $companyId = null, ?string $name = null): array{0: UserInvitation, 1: string}`, `App\Mail\UserInvitationMail($invitation, $token)`, `UserRole::invitable()`.
- Produces: `ProcessInvitationImport` job, constructor `(public InvitationImport $import)`, queued (`ShouldQueue`), `$tries = 1`, `$timeout = 600`. Task 3 dispatches it; nothing else consumes its internals.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/ProcessInvitationImportTest.php`:

```php
<?php

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

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

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

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

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

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

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

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

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

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

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

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

    $import->refresh();
    expect($import->invalid_count)->toBe(InvitationImport::MAX_ROW_ERRORS + 5)
        ->and(count($import->row_errors))->toBe(InvitationImport::MAX_ROW_ERRORS);
});

test('the csv file is deleted when the job finishes', function () {
    $import = InvitationImport::factory()->create(['total_rows' => 1]);
    writeImportCsv($import, ['fresh@example.com,entrepreneur,']);

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

    Storage::disk('local')->assertMissing($import->storagePath());
});

test('a missing file marks the import failed', function () {
    $import = InvitationImport::factory()->create(['total_rows' => 1]);

    (new ProcessInvitationImport($import))->handle(app(App\Actions\CreateUserInvitation::class));

    expect($import->refresh()->status)->toBe(InvitationImportStatus::Failed);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/ProcessInvitationImportTest.php`
Expected: FAIL — `Class "App\Jobs\ProcessInvitationImport" not found`.

- [ ] **Step 3: Write the job**

Create `app/Jobs/ProcessInvitationImport.php`:

```php
<?php

namespace App\Jobs;

use App\Actions\CreateUserInvitation;
use App\Enums\InvitationImportStatus;
use App\Enums\UserRole;
use App\Mail\UserInvitationMail;
use App\Models\InvitationImport;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInvitationImport implements ShouldQueue
{
    use Queueable;

    /** Row problems are data, not retries; a rerun would double-count. */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public InvitationImport $import) {}

    public function handle(CreateUserInvitation $createInvitation): void
    {
        $this->import->update(['status' => InvitationImportStatus::Processing]);

        $disk = Storage::disk('local');
        $line = 1;

        try {
            $stream = $disk->readStream($this->import->storagePath());

            if ($stream === null) {
                throw new \RuntimeException('Import file is missing.');
            }

            fgetcsv($stream); // header, validated at upload time
            $processed = 0;
            $seen = [];

            while (($cells = fgetcsv($stream)) !== false) {
                $line++;

                // fgetcsv yields [null] for blank lines.
                if ($cells === [null] || (count($cells) === 1 && trim((string) $cells[0]) === '')) {
                    continue;
                }

                $this->processRow($createInvitation, $cells, $line, $seen);

                if (++$processed % 25 === 0) {
                    $this->import->save();
                }
            }

            fclose($stream);

            $this->import->status = InvitationImportStatus::Completed;
            $this->import->save();
        } catch (Throwable $e) {
            Log::error('Invitation import failed', [
                'import_id' => $this->import->id,
                'exception' => $e,
            ]);

            // Spec: a failed import records the row it died on. A crash marker
            // is not a skipped/invalid row, so it bypasses the counters.
            $errors = $this->import->row_errors ?? [];
            $errors[] = ['row' => $line, 'email' => '', 'reason' => 'import stopped unexpectedly at this row'];
            $this->import->row_errors = $errors;
            $this->import->status = InvitationImportStatus::Failed;
            $this->import->save();
        } finally {
            $disk->delete($this->import->storagePath());
        }
    }

    /**
     * Apply the spec's per-row rules, in order. Skips and invalids record a
     * report entry and never stop the import.
     *
     * @param  array<int, string|null>  $cells
     * @param  array<string, true>  $seen
     */
    private function processRow(CreateUserInvitation $createInvitation, array $cells, int $line, array &$seen): void
    {
        $email = strtolower(trim((string) ($cells[0] ?? '')));
        $roleRaw = strtolower(trim((string) ($cells[1] ?? '')));
        $name = trim((string) ($cells[2] ?? ''));
        $name = $name === '' ? null : $name;

        if ($email === '' || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->record('invalid_count', $line, $email, 'invalid email');

            return;
        }

        $role = UserRole::tryFrom($roleRaw);

        if ($role === null || ! in_array($role, UserRole::invitable(), true)) {
            $this->record('invalid_count', $line, $email, sprintf('unknown role "%s"', $roleRaw));

            return;
        }

        if ($name !== null && strlen($name) > 255) {
            $this->record('invalid_count', $line, $email, 'name too long');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->record('skipped_count', $line, $email, 'already a user');

            return;
        }

        $activeInvitation = UserInvitation::query()
            ->where('email', $email)
            ->where('role', $role->value)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($activeInvitation) {
            $this->record('skipped_count', $line, $email, 'already invited');

            return;
        }

        if (isset($seen[$email])) {
            $this->record('skipped_count', $line, $email, 'duplicate row in file');

            return;
        }

        $seen[$email] = true;

        [$invitation, $token] = $createInvitation->handle(
            email: $email,
            role: $role,
            invitedBy: $this->import->importer,
            name: $name,
        );

        Mail::to($invitation->email)->queue(new UserInvitationMail($invitation, $token));

        $this->import->invited_count++;
    }

    private function record(string $counter, int $line, string $email, string $reason): void
    {
        $this->import->{$counter}++;

        $errors = $this->import->row_errors ?? [];

        if (count($errors) < InvitationImport::MAX_ROW_ERRORS) {
            $errors[] = ['row' => $line, 'email' => $email, 'reason' => $reason];
            $this->import->row_errors = $errors;
        }
    }
}
```

Note: `$seen` is marked only when a row reaches the invite step, and checked after the user/active-invitation rules — matching the spec's rule order (an email that is "already a user" reports that reason on every occurrence, not "duplicate").

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/ProcessInvitationImportTest.php`
Expected: PASS (8 tests). If the `Queueable` trait import fails on this Laravel version, use the classic five imports (`Dispatchable, InteractsWithQueue, Queueable, SerializesModels` + `Illuminate\Bus\Queueable`) — check a sibling job or `php artisan make:job --help` first; there are no other jobs in `app/Jobs` yet, so match the framework skeleton (`php artisan make:job Tmp` then delete it) if unsure.

- [ ] **Step 5: Pint, full suite, commit**

```bash
./vendor/bin/pint --test app/Jobs/ProcessInvitationImport.php tests/Feature/Admin/ProcessInvitationImportTest.php
php artisan test --compact
git add app/Jobs/ProcessInvitationImport.php tests/Feature/Admin/ProcessInvitationImportTest.php
git commit -m "Add queued job that processes CSV invitation imports"
```

---

### Task 3: Template download, upload endpoint, and activeImport share

**Files:**
- Create: `app/Http/Controllers/Admin/InvitationImportController.php`
- Create: `app/Http/Requests/Admin/StoreInvitationImportRequest.php`
- Modify: `routes/web.php` (inside the existing `admin.` group, right after the `invitations.revoke` route)
- Modify: `app/Http/Controllers/Admin/InvitationController.php` (`index()` — add `activeImport` prop)
- Test: `tests/Feature/Admin/InvitationImportHttpTest.php`

**Interfaces:**
- Consumes: Task 1 model/enum, Task 2 job (dispatched, asserted with `Bus::fake` / `Queue::fake`).
- Produces:
  - `GET /admin/invitations/import/template` (name `admin.invitations.import.template`) → CSV download `invitations-template.csv`.
  - `POST /admin/invitations/import` (name `admin.invitations.import.store`), field `file` → redirect back with `status` flash.
  - `InvitationController@index` shares `activeImport`: `null` or `{id, filename, status, totalRows, invitedCount, skippedCount, invalidCount, rowErrors}` (camelCase; `rowErrors` = array of `{row, email, reason}`). Task 4 consumes this exact shape.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Admin/InvitationImportHttpTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/InvitationImportHttpTest.php`
Expected: FAIL — 404s (routes missing).

- [ ] **Step 3: Write request, controller, routes, index share**

Create `app/Http/Requests/Admin/StoreInvitationImportRequest.php`:

```php
<?php

namespace App\Http\Requests\Admin;

use App\Models\UserInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreInvitationImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UserInvitation::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    /**
     * The header must match the template exactly, and at least one data row
     * must follow it.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('file') || ! $this->file('file')) {
                    return;
                }

                $stream = fopen($this->file('file')->getRealPath(), 'rb');
                $header = fgetcsv($stream);
                $hasDataRow = false;

                while (($cells = fgetcsv($stream)) !== false) {
                    if ($cells !== [null] && trim((string) ($cells[0] ?? '')) !== '') {
                        $hasDataRow = true;
                        break;
                    }
                }

                fclose($stream);

                $normalized = array_map(
                    fn ($cell) => strtolower(trim((string) $cell)),
                    $header ?: [],
                );

                if ($normalized !== ['email', 'role', 'name']) {
                    $validator->errors()->add('file', 'The first line must be the template header: email,role,name.');
                } elseif (! $hasDataRow) {
                    $validator->errors()->add('file', 'The file has no data rows below the header.');
                }
            },
        ];
    }
}
```

Create `app/Http/Controllers/Admin/InvitationImportController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvitationImportRequest;
use App\Jobs\ProcessInvitationImport;
use App\Models\InvitationImport;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvitationImportController extends Controller
{
    public function template(): StreamedResponse
    {
        Gate::authorize('create', UserInvitation::class);

        $content = "email,role,name\n"
            ."amara@example.com,entrepreneur,Amara Okafor\n"
            ."kwame@example.com,mentor,\n";

        return response()->streamDownload(
            fn () => print($content),
            'invitations-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    public function store(StoreInvitationImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        // Count data rows (non-blank lines below the header) for progress totals.
        $stream = fopen($file->getRealPath(), 'rb');
        fgetcsv($stream);
        $totalRows = 0;
        while (($cells = fgetcsv($stream)) !== false) {
            if ($cells !== [null] && trim((string) ($cells[0] ?? '')) !== '') {
                $totalRows++;
            }
        }
        fclose($stream);

        $import = InvitationImport::create([
            'imported_by' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'total_rows' => $totalRows,
        ]);

        Storage::disk('local')->putFileAs(
            'invitation-imports',
            $file,
            "{$import->id}.csv",
        );

        ProcessInvitationImport::dispatch($import);

        return redirect()
            ->route('admin.invitations.index')
            ->with('status', "Import of {$import->filename} started.");
    }
}
```

In `routes/web.php`, add `use App\Http\Controllers\Admin\InvitationImportController;` to the imports and, inside the `admin.` group directly after the `invitations.revoke` line, add:

```php
Route::get('/invitations/import/template', [InvitationImportController::class, 'template'])->name('invitations.import.template');
Route::post('/invitations/import', [InvitationImportController::class, 'store'])->name('invitations.import.store');
```

(Route order note: `POST /invitations/import` and `DELETE /invitations/{invitation}` differ by method, so there is no binding collision.)

In `app/Http/Controllers/Admin/InvitationController.php` `index()`, add `use App\Models\InvitationImport;` and extend the render:

```php
        $latestImport = InvitationImport::query()->latest('id')->first();

        return Inertia::render('admin/invitations/Index', [
            'invitations' => $invitations,
            'activeImport' => $latestImport === null ? null : [
                'id' => $latestImport->id,
                'filename' => $latestImport->filename,
                'status' => $latestImport->status->value,
                'totalRows' => $latestImport->total_rows,
                'invitedCount' => $latestImport->invited_count,
                'skippedCount' => $latestImport->skipped_count,
                'invalidCount' => $latestImport->invalid_count,
                'rowErrors' => $latestImport->row_errors ?? [],
            ],
        ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Admin/InvitationImportHttpTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Pint, full suite, commit**

```bash
./vendor/bin/pint --test app/Http/Controllers/Admin/InvitationImportController.php app/Http/Requests/Admin/StoreInvitationImportRequest.php app/Http/Controllers/Admin/InvitationController.php tests/Feature/Admin/InvitationImportHttpTest.php
php artisan test --compact
git add app/Http/Controllers/Admin/InvitationImportController.php app/Http/Requests/Admin/StoreInvitationImportRequest.php app/Http/Controllers/Admin/InvitationController.php routes/web.php tests/Feature/Admin/InvitationImportHttpTest.php
git commit -m "Add CSV invitation import endpoints and template download"
```

**Caution:** `routes/web.php` carries unrelated uncommitted work-in-progress. Stage it anyway (the file must ship with your two routes), but make sure `git diff --cached routes/web.php` shows ONLY your two route lines and the import before committing. If it shows other hunks, use `git add -p routes/web.php` to stage only yours. If the user's WIP makes that impossible to untangle, stop and report DONE_WITH_CONCERNS naming the entangled hunks.

---

### Task 4: Invitations page — upload tab, progress strip, report

**Files:**
- Create: `resources/js/components/invitations/import-upload.svelte`
- Create: `resources/js/components/invitations/import-status.svelte`
- Modify: `resources/js/components/invitations/types.ts` (add import types)
- Modify: `resources/js/pages/admin/invitations/Index.svelte` (slide-over tabs, status strip, polling, new prop)

**Interfaces:**
- Consumes: `activeImport` page prop from Task 3 (exact camelCase shape), `usePoll` from `@inertiajs/svelte` v3, existing page idioms (`useForm`, `cn`, tokens, `focusRing`).
- Produces: `ImportUpload` component (props: `{ onUploaded: () => void }`), `ImportStatus` component (props: `{ import: ActiveImport }` plus `ondismiss: () => void`), `ActiveImport` type exported from `types.ts`.

- [ ] **Step 1: Add the types**

In `resources/js/components/invitations/types.ts`, append:

```ts
export type ImportRowError = { row: number; email: string; reason: string };

export type ActiveImport = {
    id: number;
    filename: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    totalRows: number;
    invitedCount: number;
    skippedCount: number;
    invalidCount: number;
    rowErrors: ImportRowError[];
};
```

- [ ] **Step 2: Create the upload tab component**

Create `resources/js/components/invitations/import-upload.svelte`:

```svelte
<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { Download, FileUp } from '@lucide/svelte';
    import { cn } from '@/lib/utils';

    let { onUploaded }: { onUploaded: () => void } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const form = useForm<{ file: File | null }>({ file: null });

    function pickFile(e: Event) {
        form.file = (e.currentTarget as HTMLInputElement).files?.[0] ?? null;
        form.clearErrors('file');
    }

    function submit(e: Event) {
        e.preventDefault();
        form.post('/admin/invitations/import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onUploaded();
            },
        });
    }
</script>

<form onsubmit={submit} class="flex flex-col gap-5">
    <div class="space-y-1.5 text-sm leading-relaxed text-muted">
        <p>
            Upload a CSV with the columns
            <code class="rounded bg-elevated px-1.5 py-0.5 text-[13px] text-ink"
                >email, role, name</code
            >. Each row becomes an invitation.
        </p>
        <p class="text-[13px] text-faint">
            Rows are skipped — never errors — when the email already belongs to
            a user or has an active invitation. You&rsquo;ll get a report of
            everything skipped or invalid.
        </p>
    </div>

    <a
        href="/admin/invitations/import/template"
        class={cn(
            'inline-flex w-fit items-center gap-2 rounded-md text-sm font-medium text-accent transition-colors hover:text-accent-strong',
            focusRing,
        )}
    >
        <Download class="size-4" strokeWidth={1.75} />
        Download the template
    </a>

    <label
        class={cn(
            'flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed border-line-strong bg-surface px-6 py-8 text-center transition-colors hover:border-accent/50',
            'focus-within:ring-2 focus-within:ring-accent/60',
        )}
    >
        <FileUp class="size-6 text-muted" strokeWidth={1.75} />
        <span class="text-sm text-ink">
            {form.file ? form.file.name : 'Choose a CSV file'}
        </span>
        <span class="text-xs text-faint">Up to 10 MB</span>
        <input
            type="file"
            accept=".csv,text/csv"
            onchange={pickFile}
            class="sr-only"
        />
    </label>

    {#if form.errors.file}
        <p class="text-sm text-error" role="alert">{form.errors.file}</p>
    {/if}

    <button
        type="submit"
        disabled={!form.file || form.processing}
        class={cn(
            'inline-flex items-center justify-center rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:-translate-y-px hover:bg-accent-strong active:translate-y-0 disabled:pointer-events-none disabled:opacity-50',
            focusRing,
        )}
    >
        {form.processing ? 'Uploading…' : 'Upload and invite'}
    </button>
</form>
```

Note: `text-error` requires the `--color-error` token, which exists in `resources/css/app.css`. If eslint or types flag anything, adjust to the sibling pattern in `Index.svelte`'s existing form — that file is the authority for `useForm` usage in this repo (v3 rune-mode, no `$` prefix).

- [ ] **Step 3: Create the status strip component**

Create `resources/js/components/invitations/import-status.svelte`:

```svelte
<script lang="ts">
    import { ChevronDown, X } from '@lucide/svelte';
    import { cn } from '@/lib/utils';
    import type { ActiveImport } from './types';

    let {
        import: activeImport,
        ondismiss,
    }: { import: ActiveImport; ondismiss: () => void } = $props();

    const processed = $derived(
        activeImport.invitedCount +
            activeImport.skippedCount +
            activeImport.invalidCount,
    );
    const running = $derived(
        activeImport.status === 'pending' ||
            activeImport.status === 'processing',
    );
    const pct = $derived(
        activeImport.totalRows > 0
            ? Math.min(100, Math.round((processed / activeImport.totalRows) * 100))
            : 0,
    );

    let reportOpen = $state(false);
</script>

<div class="rounded-lg border border-line bg-surface p-4">
    <div class="flex items-center gap-3">
        <div class="min-w-0 flex-1">
            {#if running}
                <p class="text-sm font-medium text-ink">
                    Importing {activeImport.filename}…
                </p>
                <p class="mt-0.5 text-xs text-muted">
                    {processed} of {activeImport.totalRows} rows
                </p>
            {:else if activeImport.status === 'completed'}
                <p class="text-sm font-medium text-ink">
                    {activeImport.filename} imported
                </p>
                <p class="mt-0.5 text-xs text-muted">
                    Invited {activeImport.invitedCount} · Skipped
                    {activeImport.skippedCount} · Invalid
                    {activeImport.invalidCount}
                </p>
            {:else}
                <p class="text-sm font-medium text-ink">
                    Import of {activeImport.filename} failed
                </p>
                <p class="mt-0.5 text-xs text-muted">
                    {processed} of {activeImport.totalRows} rows were processed
                    before the failure. Fix the file and upload it again.
                </p>
            {/if}
        </div>

        {#if !running}
            <button
                type="button"
                onclick={ondismiss}
                aria-label="Dismiss import result"
                class="flex size-8 shrink-0 items-center justify-center rounded-md text-muted outline-none transition-colors hover:bg-elevated hover:text-ink focus-visible:ring-2 focus-visible:ring-accent/60"
            >
                <X class="size-4" strokeWidth={1.75} />
            </button>
        {/if}
    </div>

    {#if running}
        <div
            class="mt-3 h-1.5 overflow-hidden rounded-full bg-elevated"
            role="progressbar"
            aria-valuenow={pct}
            aria-valuemin={0}
            aria-valuemax={100}
        >
            <div
                class="h-full rounded-full bg-accent transition-all duration-500"
                style="width: {pct}%"
            ></div>
        </div>
    {/if}

    {#if !running && activeImport.rowErrors.length > 0}
        <button
            type="button"
            onclick={() => (reportOpen = !reportOpen)}
            aria-expanded={reportOpen}
            class="mt-3 flex items-center gap-1.5 rounded text-xs font-medium text-muted outline-none transition-colors hover:text-ink focus-visible:ring-2 focus-visible:ring-accent/60"
        >
            <ChevronDown
                class={cn('size-3.5 transition-transform', reportOpen && 'rotate-180')}
                strokeWidth={1.75}
            />
            {activeImport.rowErrors.length} skipped or invalid
            {activeImport.rowErrors.length === 1 ? 'row' : 'rows'}
        </button>

        {#if reportOpen}
            <ul
                class="custom-scrollbar mt-2 max-h-56 divide-y divide-line overflow-y-auto rounded-md border border-line text-[13px]"
            >
                {#each activeImport.rowErrors as error (error.row)}
                    <li class="flex items-baseline gap-3 px-3 py-1.5">
                        <span class="shrink-0 tabular-nums text-faint"
                            >Row {error.row}</span
                        >
                        <span class="min-w-0 flex-1 truncate text-ink"
                            >{error.email}</span
                        >
                        <span class="shrink-0 text-muted">{error.reason}</span>
                    </li>
                {/each}
                {#if activeImport.skippedCount + activeImport.invalidCount > activeImport.rowErrors.length}
                    <li class="px-3 py-1.5 text-faint">
                        …and {activeImport.skippedCount +
                            activeImport.invalidCount -
                            activeImport.rowErrors.length} more
                    </li>
                {/if}
            </ul>
        {/if}
    {/if}
</div>
```

- [ ] **Step 4: Integrate into Index.svelte**

Read `resources/js/pages/admin/invitations/Index.svelte` first — it carries recent user edits; anchor by structure, not line numbers.

1. Add imports beside the existing component imports:

```ts
import { usePoll } from '@inertiajs/svelte';
import ImportStatus from '@/components/invitations/import-status.svelte';
import ImportUpload from '@/components/invitations/import-upload.svelte';
import type { ActiveImport } from '@/components/invitations/types';
```

2. Extend the props destructuring (it currently takes `invitations = []`):

```ts
let {
    invitations = [],
    activeImport = null,
}: {
    invitations: Invitation[];
    activeImport?: ActiveImport | null;
} = $props();
```

3. Below the props, add polling + dismissal state:

```ts
// ── CSV import ─────────────────────────────────────────────────────
let importDismissed = $state(false);
const importRunning = $derived(
    activeImport !== null &&
        (activeImport.status === 'pending' ||
            activeImport.status === 'processing'),
);

const poll = usePoll(
    2000,
    { only: ['activeImport', 'invitations'], async: true },
    { autoStart: false, keepAlive: true },
);

$effect(() => {
    if (importRunning) {
        importDismissed = false;
        poll.start();
    } else {
        poll.stop();
    }
});
```

(Verify the exact `usePoll` signature against `node_modules/@inertiajs/svelte/dist/index.d.ts` before assuming; adjust argument shape if it differs, keeping interval 2000 ms, partial reload of only `activeImport` + `invitations`, and manual start/stop.)

4. Slide-over mode tabs. Inside the invite slide-over (`{#if inviteOpen}` block), the panel currently renders the single-invite form directly. Add a mode toggle at the top of the panel body and wrap the existing form:

```ts
// with the other slide-over state:
let inviteMode = $state<'single' | 'csv'>('single');
```

In `openInvite()`, reset the mode: `inviteMode = 'single';`

In the panel markup, directly above the existing form, insert:

```svelte
<div class="flex gap-1 rounded-lg bg-elevated p-1" role="tablist">
    {#each [{ value: 'single', label: 'Send directly' }, { value: 'csv', label: 'Upload CSV' }] as mode (mode.value)}
        <button
            type="button"
            role="tab"
            aria-selected={inviteMode === mode.value}
            onclick={() => (inviteMode = mode.value as 'single' | 'csv')}
            class={cn(
                'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-accent/60',
                inviteMode === mode.value
                    ? 'bg-surface text-ink'
                    : 'text-muted hover:text-ink',
            )}
        >
            {mode.label}
        </button>
    {/each}
</div>
```

Wrap the existing single-invite `<form …>` in `{#if inviteMode === 'single'}…{:else}` and render the upload tab in the else branch:

```svelte
{:else}
    <ImportUpload
        onUploaded={() => {
            closeInvite();
            toastMsg('Import started — progress shows below.');
        }}
    />
{/if}
```

5. Status strip. Between the page header block and the status-filter tabs (`<!-- Status filter tabs -->` comment), insert:

```svelte
{#if activeImport && !importDismissed}
    <div class="mt-6">
        <ImportStatus
            import={activeImport}
            ondismiss={() => (importDismissed = true)}
        />
    </div>
{/if}
```

- [ ] **Step 5: Verify**

Run: `pnpm types:check`
Expected: `0 ERRORS` (pre-existing warnings in auth/onboarding files only).

Run: `npx eslint resources/js/components/invitations/import-upload.svelte resources/js/components/invitations/import-status.svelte resources/js/pages/admin/invitations/Index.svelte resources/js/components/invitations/types.ts`
Expected: exits clean (auto-fix import order with `--fix` if flagged).

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/invitations/import-upload.svelte resources/js/components/invitations/import-status.svelte resources/js/components/invitations/types.ts resources/js/pages/admin/invitations/Index.svelte
git commit -m "Add CSV upload tab and import progress to the invitations page"
```

**Caution:** `Index.svelte` may carry unrelated uncommitted user edits. Before committing, check `git diff --cached resources/js/pages/admin/invitations/Index.svelte` — if it contains hunks that are clearly not yours, stage selectively with `git add -p` or report DONE_WITH_CONCERNS.

---

### Task 5: Browser verification end-to-end

**Files:**
- None (verification only).

**Interfaces:**
- Consumes: the running app (`composer run dev` or `php artisan serve` + `pnpm dev`), admin login `admin@tolfund.com` / `password` (seeded), everything from Tasks 1–4.

- [ ] **Step 1: Prepare a mixed test CSV**

Write to a scratch location (never the repo):

```csv
email,role,name
csv-one@example.com,entrepreneur,Person One
csv-two@example.com,mentor,
admin@tolfund.com,admin,Existing Admin
csv-one@example.com,entrepreneur,Duplicate Row
bad-email,entrepreneur,
csv-three@example.com,director,
```

Expected outcome: 2 invited, 2 skipped (`already a user`, `duplicate row in file`), 2 invalid (`invalid email`, `unknown role "director"`).

- [ ] **Step 2: Verify in the browser**

Sign in as the admin, open `/admin/invitations`, then:

1. Open the invite slide-over → two tabs; "Send directly" still submits a single invitation end-to-end.
2. "Upload CSV" tab → template link downloads `invitations-template.csv` with the documented header.
3. Upload the mixed CSV → slide-over closes, toast appears, progress strip appears and reaches a terminal state (locally `QUEUE_CONNECTION=sync` processes inline — the strip may go straight to completed; that is correct).
4. Completed strip reads `Invited 2 · Skipped 2 · Invalid 2`; expanding the report lists the four problem rows with the expected reasons; the two new invitations appear in the table.
5. Upload the same file again → everything is skipped or invalid, nothing new invited (`already invited` for the two from step 3).
6. Wrong-file check: upload a `.png` → validation error shows in the slide-over, no strip change.
7. Keyboard: tab through the mode tabs, template link, file input, upload button — focus rings visible throughout.

- [ ] **Step 3: Report results**

Report any deviation for a fix before declaring the feature done. No commit.
