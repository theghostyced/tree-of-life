# Admin Meetings Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give admins a read-only page listing every meeting booked across all pairings, grouped into Upcoming and Past, with a per-meeting detail page.

**Architecture:** Two Inertia-rendered pages backed by one `Admin\MeetingController`. Upcoming loads whole (naturally bounded); Past is paginated server-side with SQL filters. A new `App\Data\MeetingSummary` replaces the duplicated `mapMeeting()` in the mentor and entrepreneur controllers. A migration records who booked each meeting.

**Tech Stack:** Laravel 13, Inertia, Svelte 5 (runes), Tailwind, Pest 4, SQLite (local).

**Spec:** `docs/superpowers/specs/2026-08-01-admin-meetings-page-design.md`

## Global Constraints

- Timestamps crossing to the frontend are **epoch milliseconds** via `->getTimestampMs()`. Never ISO strings.
- Admin routes live in the existing `admin` prefix + `role:admin` group in `routes/web.php`, and additionally authorize via policy with `Gate::authorize`.
- Controllers are imported into `routes/web.php` with a role-prefixed alias: `use App\Http\Controllers\Admin\MeetingController as AdminMeetingController;`.
- Presenters live in `App\Data`, implement `Illuminate\Contracts\Support\Arrayable`, and expose a static `forX()` factory. Follow `app/Data/MentorCard.php`.
- Paginated Inertia payloads use `->paginate(self::PER_PAGE)->withQueryString()->through(...)` and ship a sibling `'filters' => [...]` array. Follow `app/Http/Controllers/Entrepreneur/MentorController.php:53-59`.
- Admin is **read-only** on meetings. No cancel, edit, or state transitions.
- Run tests with `php artisan test`. PHP is Herd's: ensure `/Users/admin/Library/Application Support/Herd/bin` is on `PATH`.
- Format PHP with `vendor/bin/pint` and frontend with `npm run format` before each commit.

---

### Task 1: Record who booked a meeting

**Files:**
- Create: `database/migrations/2026_08_01_000001_add_booked_by_user_id_to_meetings.php`
- Modify: `app/Models/Meeting.php` (add to `$fillable`, add `bookedBy()` relation)
- Modify: `app/Actions/Mentorship/BookMeeting.php:34-45` (set the column)
- Test: `tests/Feature/Mentorship/BookMeetingAttributionTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Meeting::$booked_by_user_id` (nullable int), `Meeting::bookedBy(): BelongsTo` returning `User`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mentorship/BookMeetingAttributionTest.php`:

```php
<?php

use App\Actions\Mentorship\BookMeeting;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;

test('booking a meeting records the entrepreneur as the booker', function () {
    $pairing = Pairing::factory()->create();
    $slot = MentorAvailabilitySlot::factory()->create([
        'mentor_user_id' => $pairing->mentor_user_id,
    ]);

    $meeting = app(BookMeeting::class)->handle($pairing, $slot);

    expect($meeting->booked_by_user_id)->toBe($pairing->entrepreneur_user_id)
        ->and($meeting->bookedBy->id)->toBe($pairing->entrepreneur_user_id);
});

test('meetings created before the column existed are backfilled to the entrepreneur', function () {
    $pairing = Pairing::factory()->create();
    $meeting = Meeting::factory()->for($pairing)->create(['booked_by_user_id' => null]);

    // Re-run the backfill statement the migration performs.
    DB::table('meetings')->whereNull('booked_by_user_id')->update([
        'booked_by_user_id' => DB::raw(
            '(select entrepreneur_user_id from pairings where pairings.id = meetings.pairing_id)'
        ),
    ]);

    expect($meeting->refresh()->booked_by_user_id)->toBe($pairing->entrepreneur_user_id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Mentorship/BookMeetingAttributionTest.php`
Expected: FAIL — `Column not found: booked_by_user_id` / `Property [booked_by_user_id] does not exist`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_01_000001_add_booked_by_user_id_to_meetings.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('booked_by_user_id')->nullable()->after('pairing_id')
                ->constrained('users')->nullOnDelete();
        });

        // Only entrepreneurs can book (entrepreneur.meetings.store is the sole
        // creation route), so every existing meeting was booked by its pairing's
        // entrepreneur. Nullable, so a pairing with a deleted entrepreneur is
        // simply left null rather than failing the migration.
        DB::table('meetings')->whereNull('booked_by_user_id')->update([
            'booked_by_user_id' => DB::raw(
                '(select entrepreneur_user_id from pairings where pairings.id = meetings.pairing_id)'
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropForeign(['booked_by_user_id']);
            $table->dropColumn('booked_by_user_id');
        });
    }
};
```

- [ ] **Step 4: Add the column to the model**

In `app/Models/Meeting.php`, add `'booked_by_user_id',` to `$fillable` immediately after `'pairing_id',`. Then add this relation next to `cancelledBy()`:

```php
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }
```

- [ ] **Step 5: Set it when booking**

In `app/Actions/Mentorship/BookMeeting.php`, inside the `Meeting::create([...])` array, add after `'pairing_id' => $pairing->id,`:

```php
            'booked_by_user_id' => $pairing->entrepreneur_user_id,
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Mentorship/`
Expected: PASS, no warnings.

- [ ] **Step 7: Run the full suite for regressions**

Run: `php artisan test`
Expected: all green. The new column is nullable and additive, so nothing should break.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app/Models/Meeting.php app/Actions/Mentorship/BookMeeting.php
git add database/migrations/2026_08_01_000001_add_booked_by_user_id_to_meetings.php \
        app/Models/Meeting.php app/Actions/Mentorship/BookMeeting.php \
        tests/Feature/Mentorship/BookMeetingAttributionTest.php
git commit -m "feat: record who booked a meeting"
```

---

### Task 2: Extract the shared meeting presenter

**Files:**
- Create: `app/Data/MeetingSummary.php`
- Modify: `app/Http/Controllers/Mentor/MeetingController.php:37-56`
- Modify: `app/Http/Controllers/Entrepreneur/MeetingController.php:124-143`
- Test: existing `tests/Feature/` mentor and entrepreneur meeting tests are the guard rail.

**Interfaces:**
- Consumes: `Meeting::durationMinutes()` (already exists on the model).
- Produces: `App\Data\MeetingSummary::forMeeting(Meeting $meeting): self` and `->toArray(): array` with keys `id, startsAt, endsAt, durationMinutes, timezone, sessionType, location, meetingLink, agenda, status`.

This is a **behaviour-preserving refactor**. The payload gains two keys (`durationMinutes`, `timezone`); it loses none. Existing tests must pass unchanged.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/MeetingSummaryTest.php`:

```php
<?php

use App\Data\MeetingSummary;
use App\Models\Meeting;

test('it carries the fields every role shares', function () {
    $starts = now()->addDay()->setTime(10, 0);
    $meeting = Meeting::factory()->make([
        'starts_at' => $starts,
        'ends_at' => $starts->copy()->addMinutes(90),
        'timezone' => 'Africa/Nairobi',
        'session_type' => 'virtual',
        'agenda' => 'Quarterly review',
    ]);
    $meeting->id = 42;

    $row = MeetingSummary::forMeeting($meeting)->toArray();

    expect($row['id'])->toBe(42)
        ->and($row['startsAt'])->toBe($starts->getTimestampMs())
        ->and($row['durationMinutes'])->toBe(90)
        ->and($row['timezone'])->toBe('Africa/Nairobi')
        ->and($row['sessionType'])->toBe('virtual')
        ->and($row['agenda'])->toBe('Quarterly review')
        ->and($row['status'])->toBe('confirmed');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/MeetingSummaryTest.php`
Expected: FAIL — `Class "App\Data\MeetingSummary" not found`.

- [ ] **Step 3: Create the presenter**

Create `app/Data/MeetingSummary.php`:

```php
<?php

namespace App\Data;

use App\Models\Meeting;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The fields of a meeting that every role sees. Roles differ only in how they
 * name the other party, so each controller spreads this and adds its own
 * naming rather than re-deriving the shared shape.
 *
 * @implements Arrayable<string, mixed>
 */
class MeetingSummary implements Arrayable
{
    public function __construct(
        public int $id,
        public int $startsAt,
        public int $endsAt,
        public int $durationMinutes,
        public string $timezone,
        public string $sessionType,
        public ?string $location,
        public ?string $meetingLink,
        public ?string $agenda,
        public string $status,
    ) {}

    public static function forMeeting(Meeting $meeting): self
    {
        return new self(
            id: $meeting->id,
            startsAt: $meeting->starts_at->getTimestampMs(),
            endsAt: $meeting->ends_at->getTimestampMs(),
            durationMinutes: $meeting->durationMinutes(),
            timezone: $meeting->timezone,
            sessionType: $meeting->session_type,
            location: $meeting->location,
            meetingLink: $meeting->meeting_link,
            agenda: $meeting->agenda,
            status: $meeting->status->value,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'durationMinutes' => $this->durationMinutes,
            'timezone' => $this->timezone,
            'sessionType' => $this->sessionType,
            'location' => $this->location,
            'meetingLink' => $this->meetingLink,
            'agenda' => $this->agenda,
            'status' => $this->status,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/MeetingSummaryTest.php`
Expected: PASS.

- [ ] **Step 5: Use it in the mentor controller**

In `app/Http/Controllers/Mentor/MeetingController.php`, add `use App\Data\MeetingSummary;` to the imports and replace the whole `mapMeeting()` body:

```php
    /**
     * @return array<string, mixed>
     */
    private function mapMeeting(Meeting $meeting): array
    {
        return [
            ...MeetingSummary::forMeeting($meeting)->toArray(),
            'counterpartName' => $meeting->pairing->entrepreneur->name,
            'reportSummary' => $meeting->report?->summary,
            'canReport' => $meeting->status === MeetingStatus::Completed
                && $meeting->report === null,
        ];
    }
```

- [ ] **Step 6: Use it in the entrepreneur controller**

In `app/Http/Controllers/Entrepreneur/MeetingController.php`, add `use App\Data\MeetingSummary;` and replace `mapMeeting()`'s body the same way, but with the mentor as the counterpart:

```php
    /**
     * @return array<string, mixed>
     */
    private function mapMeeting(Meeting $meeting): array
    {
        return [
            ...MeetingSummary::forMeeting($meeting)->toArray(),
            'counterpartName' => $meeting->pairing->mentor->name,
            'reportSummary' => $meeting->report?->summary,
        ];
    }
```

Before writing this, open the existing method and copy across **every** key it currently returns beyond the shared set. If it returns keys not shown here, keep them — this refactor must not drop a field.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`
Expected: all green, including the pre-existing mentor and entrepreneur meeting tests. If any fail, a key was dropped in step 5 or 6 — restore it rather than editing the test.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app/Data/MeetingSummary.php app/Http/Controllers/Mentor/MeetingController.php app/Http/Controllers/Entrepreneur/MeetingController.php
git add app/Data/MeetingSummary.php app/Http/Controllers/ tests/Unit/MeetingSummaryTest.php
git commit -m "refactor: extract shared MeetingSummary presenter"
```

---

### Task 3: Authorize admins to view meetings

**Files:**
- Modify: `app/Policies/MeetingPolicy.php`
- Test: `tests/Feature/Admin/MeetingPolicyTest.php`

**Interfaces:**
- Produces: `MeetingPolicy::viewAny(User $user): bool`, `MeetingPolicy::view(User $user, Meeting $meeting): bool`. Both true only for `UserRole::Admin`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/MeetingPolicyTest.php`:

```php
<?php

use App\Models\Meeting;
use App\Models\User;

test('an admin may view any meeting', function () {
    $admin = User::factory()->admin()->approved()->create();
    $meeting = Meeting::factory()->create();

    expect($admin->can('viewAny', Meeting::class))->toBeTrue()
        ->and($admin->can('view', $meeting))->toBeTrue();
});

test('a mentor may not use the admin meeting views', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    $meeting = Meeting::factory()->create();

    expect($mentor->can('viewAny', Meeting::class))->toBeFalse()
        ->and($mentor->can('view', $meeting))->toBeFalse();
});

test('an entrepreneur may not use the admin meeting views', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $meeting = Meeting::factory()->create();

    expect($entrepreneur->can('viewAny', Meeting::class))->toBeFalse()
        ->and($entrepreneur->can('view', $meeting))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/MeetingPolicyTest.php`
Expected: FAIL — the admin assertions return false, because no `viewAny`/`view` method exists and the policy denies by default.

- [ ] **Step 3: Add the policy methods**

In `app/Policies/MeetingPolicy.php`, add `use App\Enums\UserRole;` to the imports and these two methods above `submitReport()`:

```php
    /**
     * Admins monitor the whole programme, so they see every pairing's meetings.
     * Mentors and entrepreneurs reach their own meetings through their own
     * role-scoped controllers, not through these.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->role === UserRole::Admin;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Admin/MeetingPolicyTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint app/Policies/MeetingPolicy.php
git add app/Policies/MeetingPolicy.php tests/Feature/Admin/MeetingPolicyTest.php
git commit -m "feat: allow admins to view any meeting"
```

---

### Task 4: Admin meetings index endpoint

**Files:**
- Create: `app/Http/Controllers/Admin/MeetingController.php`
- Modify: `routes/web.php` (import alias + one route in the admin group)
- Test: `tests/Feature/Admin/MeetingsIndexTest.php`

**Interfaces:**
- Consumes: `MeetingSummary::forMeeting()` (Task 2), `Meeting::bookedBy()` (Task 1), `MeetingPolicy::viewAny()` (Task 3).
- Produces: route name `admin.meetings.index` at `GET /admin/meetings`, rendering `admin/meetings/Index` with props `upcoming` (array), `past` (paginator), `filters` (`{search, status}`). Row keys: `id, startsAt, endsAt, durationMinutes, timezone, sessionType, location, meetingLink, agenda, status, mentorName, entrepreneurName, bookedByName, hasReport`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/MeetingsIndexTest.php`:

```php
<?php

use App\Models\Meeting;
use App\Models\Pairing;
use App\Models\User;

function admin(): User
{
    return User::factory()->admin()->approved()->create();
}

test('an admin sees meetings from pairings they are not part of', function () {
    $meeting = Meeting::factory()->create();

    $this->actingAs(admin())
        ->get('/admin/meetings')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/meetings/Index')
            ->has('upcoming', 1)
            ->where('upcoming.0.id', $meeting->id));
});

test('a confirmed future meeting is upcoming and everything else is past', function () {
    $future = Meeting::factory()->create();                       // confirmed, tomorrow
    $completed = Meeting::factory()->completed()->create();       // yesterday
    $cancelled = Meeting::factory()->cancelled()->create();

    $this->actingAs(admin())
        ->get('/admin/meetings')
        ->assertInertia(fn ($page) => $page
            ->has('upcoming', 1)
            ->where('upcoming.0.id', $future->id)
            ->has('past.data', 2));
});

test('a row names both participants and who booked it', function () {
    $pairing = Pairing::factory()->create();
    $pairing->mentor->update(['name' => 'Grace Mentor']);
    $pairing->entrepreneur->update(['name' => 'Tara Founder']);
    Meeting::factory()->for($pairing)->create([
        'booked_by_user_id' => $pairing->entrepreneur_user_id,
    ]);

    $this->actingAs(admin())
        ->get('/admin/meetings')
        ->assertInertia(fn ($page) => $page
            ->where('upcoming.0.mentorName', 'Grace Mentor')
            ->where('upcoming.0.entrepreneurName', 'Tara Founder')
            ->where('upcoming.0.bookedByName', 'Tara Founder')
            ->where('upcoming.0.hasReport', false));
});

test('the status filter narrows past meetings', function () {
    Meeting::factory()->completed()->create();
    Meeting::factory()->cancelled()->create();

    $this->actingAs(admin())
        ->get('/admin/meetings?status=cancelled')
        ->assertInertia(fn ($page) => $page
            ->has('past.data', 1)
            ->where('past.data.0.status', 'cancelled')
            ->where('filters.status', 'cancelled'));
});

test('search matches on the mentor name', function () {
    $wanted = Pairing::factory()->create();
    $wanted->mentor->update(['name' => 'Grace Mentor']);
    Meeting::factory()->for($wanted)->completed()->create();
    Meeting::factory()->completed()->create();

    $this->actingAs(admin())
        ->get('/admin/meetings?search=Grace')
        ->assertInertia(fn ($page) => $page->has('past.data', 1));
});

test('search matches on the entrepreneur name', function () {
    $wanted = Pairing::factory()->create();
    $wanted->entrepreneur->update(['name' => 'Tara Founder']);
    Meeting::factory()->for($wanted)->completed()->create();
    Meeting::factory()->completed()->create();

    $this->actingAs(admin())
        ->get('/admin/meetings?search=Tara')
        ->assertInertia(fn ($page) => $page->has('past.data', 1));
});

test('past meetings are paginated at 25 per page', function () {
    Meeting::factory()->completed()->count(30)->create();

    $this->actingAs(admin())
        ->get('/admin/meetings')
        ->assertInertia(fn ($page) => $page->has('past.data', 25));
});

test('a mentor cannot reach the admin meetings page', function () {
    $this->actingAs(User::factory()->mentor()->approved()->create())
        ->get('/admin/meetings')
        ->assertForbidden();
});

test('a guest is redirected to login', function () {
    $this->get('/admin/meetings')->assertRedirect('/login');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/MeetingsIndexTest.php`
Expected: FAIL — 404, because the route does not exist.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/MeetingController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Data\MeetingSummary;
use App\Enums\MeetingStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Meeting::class);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        // Upcoming is naturally bounded, so it loads whole and ignores filters:
        // an admin should never lose sight of what is scheduled.
        $upcoming = $this->baseQuery()
            ->where('status', MeetingStatus::Confirmed)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->get()
            ->map($this->row(...))
            ->all();

        $past = $this->baseQuery()
            ->where(fn (Builder $q) => $q
                ->where('status', '!=', MeetingStatus::Confirmed)
                ->orWhere('starts_at', '<=', now()))
            ->when($search !== '', fn (Builder $q) => $q->whereHas(
                'pairing',
                fn (Builder $p) => $p
                    ->whereHas('mentor', fn (Builder $m) => $m->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('entrepreneur', fn (Builder $e) => $e->where('name', 'like', "%{$search}%")),
            ))
            ->when($status !== '', fn (Builder $q) => $q->where('status', $status))
            ->orderByDesc('starts_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through($this->row(...));

        return Inertia::render('admin/meetings/Index', [
            'upcoming' => $upcoming,
            'past' => $past,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status !== '' ? $status : null,
            ],
        ]);
    }

    /**
     * Names for both participants and the booker, plus whether a report exists
     * — withExists rather than a relation load, so no report bodies are pulled
     * into a list payload.
     *
     * @return Builder<Meeting>
     */
    private function baseQuery(): Builder
    {
        return Meeting::query()
            ->with([
                'pairing.mentor:id,name',
                'pairing.entrepreneur:id,name',
                'bookedBy:id,name',
            ])
            ->withExists('report as has_report');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Meeting $meeting): array
    {
        return [
            ...MeetingSummary::forMeeting($meeting)->toArray(),
            'mentorName' => $meeting->pairing->mentor->name,
            'entrepreneurName' => $meeting->pairing->entrepreneur->name,
            'bookedByName' => $meeting->bookedBy?->name,
            'hasReport' => (bool) $meeting->has_report,
        ];
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/web.php`, add to the imports (keeping them alphabetical):

```php
use App\Http\Controllers\Admin\MeetingController as AdminMeetingController;
```

Then inside the `Route::prefix('admin')->name('admin.')->middleware('role:admin')` group, after the `users.*` routes:

```php
        Route::get('/meetings', [AdminMeetingController::class, 'index'])->name('meetings.index');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/MeetingsIndexTest.php`
Expected: PASS, all 9 tests.

- [ ] **Step 6: Add the N+1 guard**

Append to `tests/Feature/Admin/MeetingsIndexTest.php`:

```php
test('the query count does not grow with the number of meetings', function () {
    Meeting::factory()->count(3)->create();

    DB::enableQueryLog();
    $this->actingAs(admin())->get('/admin/meetings');
    $few = count(DB::getQueryLog());
    DB::flushQueryLog();

    Meeting::factory()->count(12)->create();
    $this->actingAs(admin())->get('/admin/meetings');
    $many = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($many)->toBe($few);
});
```

- [ ] **Step 7: Run it**

Run: `php artisan test tests/Feature/Admin/MeetingsIndexTest.php`
Expected: PASS. If the counts differ, an eager load is missing from `baseQuery()`.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app/Http/Controllers/Admin/MeetingController.php routes/web.php
git add app/Http/Controllers/Admin/MeetingController.php routes/web.php tests/Feature/Admin/MeetingsIndexTest.php
git commit -m "feat: add admin meetings index endpoint"
```

---

### Task 5: Admin meeting detail endpoint

**Files:**
- Modify: `app/Http/Controllers/Admin/MeetingController.php` (add `show()`)
- Modify: `routes/web.php` (one route)
- Test: `tests/Feature/Admin/MeetingDetailTest.php`

**Interfaces:**
- Consumes: everything from Task 4, plus `MeetingPolicy::view()` (Task 3).
- Produces: route `admin.meetings.show` at `GET /admin/meetings/{meeting}`, rendering `admin/meetings/Show` with a single `meeting` prop. Adds keys beyond the index row: `confirmedAt, completedAt, cancelledAt, cancelledByName, reschedules[], report`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/MeetingDetailTest.php`:

```php
<?php

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\Pairing;
use App\Models\User;

test('the detail page shows the meeting and its participants', function () {
    $pairing = Pairing::factory()->create();
    $pairing->mentor->update(['name' => 'Grace Mentor']);
    $meeting = Meeting::factory()->for($pairing)->create([
        'agenda' => 'Quarterly review',
        'booked_by_user_id' => $pairing->entrepreneur_user_id,
    ]);

    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get("/admin/meetings/{$meeting->id}")
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('admin/meetings/Show')
            ->where('meeting.id', $meeting->id)
            ->where('meeting.mentorName', 'Grace Mentor')
            ->where('meeting.agenda', 'Quarterly review')
            ->where('meeting.durationMinutes', 60));
});

test('the detail page reports when no report was captured', function () {
    $meeting = Meeting::factory()->completed()->create();

    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get("/admin/meetings/{$meeting->id}")
        ->assertInertia(fn ($page) => $page->where('meeting.report', null));
});

test('the detail page includes the submitted report', function () {
    $report = MeetingReport::factory()->create(['summary' => 'Went well.']);

    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get("/admin/meetings/{$report->meeting_id}")
        ->assertInertia(fn ($page) => $page
            ->where('meeting.report.summary', 'Went well.')
            ->has('meeting.report.submittedByName')
            ->has('meeting.report.submittedAt'));
});

test('the detail page lists reschedule history', function () {
    $meeting = Meeting::factory()->create();
    MeetingReschedule::factory()->create([
        'meeting_id' => $meeting->id,
        'reason' => 'Clashed with a flight',
    ]);

    $this->actingAs(User::factory()->admin()->approved()->create())
        ->get("/admin/meetings/{$meeting->id}")
        ->assertInertia(fn ($page) => $page
            ->has('meeting.reschedules', 1)
            ->where('meeting.reschedules.0.reason', 'Clashed with a flight')
            ->has('meeting.reschedules.0.requestedByName'));
});

test('a mentor cannot reach the admin meeting detail page', function () {
    $meeting = Meeting::factory()->create();

    $this->actingAs(User::factory()->mentor()->approved()->create())
        ->get("/admin/meetings/{$meeting->id}")
        ->assertForbidden();
});
```

Before running, open `database/factories/MeetingRescheduleFactory.php` and confirm which fields it fills. If `requested_by_user_id` is not defaulted, set it explicitly in the test.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Admin/MeetingDetailTest.php`
Expected: FAIL — 404, the route does not exist.

- [ ] **Step 3: Add the show method**

In `app/Http/Controllers/Admin/MeetingController.php`, add `use App\Models\MeetingReschedule;` to the imports and this method after `index()`:

```php
    public function show(Meeting $meeting): Response
    {
        Gate::authorize('view', $meeting);

        $meeting->load([
            'pairing.mentor:id,name',
            'pairing.entrepreneur:id,name',
            'bookedBy:id,name',
            'cancelledBy:id,name',
            'report.submittedBy:id,name',
            'reschedules.requestedBy:id,name',
            'reschedules.reviewedBy:id,name',
        ]);

        return Inertia::render('admin/meetings/Show', [
            'meeting' => [
                ...MeetingSummary::forMeeting($meeting)->toArray(),
                'mentorName' => $meeting->pairing->mentor->name,
                'entrepreneurName' => $meeting->pairing->entrepreneur->name,
                'bookedByName' => $meeting->bookedBy?->name,
                'confirmedAt' => $meeting->confirmed_at?->getTimestampMs(),
                'completedAt' => $meeting->completed_at?->getTimestampMs(),
                'cancelledAt' => $meeting->cancelled_at?->getTimestampMs(),
                'cancelledByName' => $meeting->cancelledBy?->name,
                'report' => $meeting->report === null ? null : [
                    'summary' => $meeting->report->summary,
                    'submittedByName' => $meeting->report->submittedBy?->name,
                    'submittedAt' => $meeting->report->submitted_at?->getTimestampMs(),
                ],
                'reschedules' => $meeting->reschedules
                    ->map(fn (MeetingReschedule $r): array => [
                        'id' => $r->id,
                        'status' => $r->status instanceof \BackedEnum ? $r->status->value : $r->status,
                        'reason' => $r->reason,
                        'requestedByName' => $r->requestedBy?->name,
                        'reviewedByName' => $r->reviewedBy?->name,
                        'previousStartsAt' => $r->previous_starts_at?->getTimestampMs(),
                        'newStartsAt' => $r->new_starts_at?->getTimestampMs(),
                        'reviewedAt' => $r->reviewed_at?->getTimestampMs(),
                    ])
                    ->all(),
            ],
        ]);
    }
```

Before running, open `app/Models/MeetingReport.php` and `app/Models/MeetingReschedule.php` and confirm the relation names `submittedBy`, `requestedBy`, and `reviewedBy` exist. If any is missing, add it as a `BelongsTo` to `User` on the matching `*_user_id` column, mirroring `Meeting::cancelledBy()`.

- [ ] **Step 4: Register the route**

In `routes/web.php`, directly after the index route added in Task 4:

```php
        Route::get('/meetings/{meeting}', [AdminMeetingController::class, 'show'])->name('meetings.show');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Admin/`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Http/Controllers/Admin/MeetingController.php routes/web.php
git add app/Http/Controllers/Admin/MeetingController.php routes/web.php tests/Feature/Admin/MeetingDetailTest.php app/Models/
git commit -m "feat: add admin meeting detail endpoint"
```

---

### Task 6: Frontend meeting types and status chip

**Files:**
- Create: `resources/js/components/meetings/types.ts`
- Create: `resources/js/components/meetings/meeting-status.svelte`

**Interfaces:**
- Produces: TypeScript types `MeetingStatus`, `AdminMeetingRow`, `AdminMeetingDetail`, the `statusMeta` map, and a `<MeetingStatus status={...} />` component.

Read `resources/js/components/users/types.ts` and `resources/js/components/users/account-status.svelte` first and mirror their structure exactly — the same `statusMeta` shape with `label`, `dot`, `text`, `chip`, so state never relies on colour alone.

- [ ] **Step 1: Create the types**

Create `resources/js/components/meetings/types.ts`:

```ts
export type MeetingStatus = 'confirmed' | 'completed' | 'cancelled';

export type AdminMeetingRow = {
    id: number;
    startsAt: number;
    endsAt: number;
    durationMinutes: number;
    timezone: string;
    sessionType: string;
    location: string | null;
    meetingLink: string | null;
    agenda: string | null;
    status: MeetingStatus;
    mentorName: string;
    entrepreneurName: string;
    bookedByName: string | null;
    hasReport: boolean;
};

export type MeetingReschedule = {
    id: number;
    status: string;
    reason: string | null;
    requestedByName: string | null;
    reviewedByName: string | null;
    previousStartsAt: number | null;
    newStartsAt: number | null;
    reviewedAt: number | null;
};

export type AdminMeetingDetail = AdminMeetingRow & {
    confirmedAt: number | null;
    completedAt: number | null;
    cancelledAt: number | null;
    cancelledByName: string | null;
    report: {
        summary: string;
        submittedByName: string | null;
        submittedAt: number | null;
    } | null;
    reschedules: MeetingReschedule[];
};

// Status paired with a dot + label so it never relies on colour alone.
export const statusMeta: Record<
    MeetingStatus,
    { label: string; dot: string; text: string; chip: string }
> = {
    confirmed: {
        label: 'Confirmed',
        dot: 'bg-accent',
        text: 'text-accent-strong',
        chip: 'bg-accent/10 border-accent/25',
    },
    completed: {
        label: 'Completed',
        dot: 'bg-positive',
        text: 'text-positive-strong',
        chip: 'bg-positive/10 border-positive/25',
    },
    cancelled: {
        label: 'Cancelled',
        dot: 'bg-danger',
        text: 'text-danger-strong',
        chip: 'bg-danger/8 border-danger/20',
    },
};

export function formatDuration(minutes: number): string {
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`;
}
```

Confirm the colour tokens (`bg-accent`, `text-positive-strong`, …) exist by checking `resources/js/components/users/types.ts`. If a token used here is absent there, substitute the nearest one that file does use.

- [ ] **Step 2: Create the status chip**

Create `resources/js/components/meetings/meeting-status.svelte`, mirroring `users/account-status.svelte`:

```svelte
<script lang="ts">
    import { cn } from '@/lib/utils';
    import { statusMeta, type MeetingStatus } from './types';

    let { status }: { status: MeetingStatus } = $props();

    const meta = $derived(statusMeta[status]);
</script>

<span
    class={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium',
        meta.chip,
        meta.text,
    )}
>
    <span class={cn('size-1.5 rounded-full', meta.dot)}></span>
    {meta.label}
</span>
```

- [ ] **Step 3: Verify types compile**

Run: `npm run types:check`
Expected: no errors in `resources/js/components/meetings/`.

- [ ] **Step 4: Commit**

```bash
npm run format
git add resources/js/components/meetings/
git commit -m "feat: add meeting status types and chip"
```

---

### Task 7: Admin meetings index page

**Files:**
- Create: `resources/js/pages/admin/meetings/Index.svelte`
- Modify: `resources/js/components/layout/AdminLayout.svelte:29` (add `enabled: true`)

**Interfaces:**
- Consumes: props `upcoming: AdminMeetingRow[]`, `past` (Laravel paginator of `AdminMeetingRow`), `filters: {search, status}` from Task 4; types and `<MeetingStatus />` from Task 6.

Read `resources/js/pages/admin/users/Index.svelte` first for the page shell, and `resources/js/pages/entrepreneur/Mentors.svelte` for the server-driven search/pagination pattern (`router.get` with `preserveState`).

- [ ] **Step 1: Enable the nav link**

In `resources/js/components/layout/AdminLayout.svelte`, change line 29 from:

```js
        { label: 'Meetings', href: '/admin/meetings', icon: Calendar },
```

to:

```js
        {
            label: 'Meetings',
            href: '/admin/meetings',
            icon: Calendar,
            enabled: true,
        },
```

- [ ] **Step 2: Create the page**

Create `resources/js/pages/admin/meetings/Index.svelte`:

```svelte
<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import MeetingStatus from '@/components/meetings/meeting-status.svelte';
    import {
        formatDuration,
        type AdminMeetingRow,
        type MeetingStatus as Status,
    } from '@/components/meetings/types';

    type Paginated<T> = {
        data: T[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };

    let {
        upcoming = [],
        past,
        filters,
    }: {
        upcoming: AdminMeetingRow[];
        past: Paginated<AdminMeetingRow>;
        filters: { search: string | null; status: Status | null };
    } = $props();

    let search = $state(filters.search ?? '');
    let status = $state<Status | ''>(filters.status ?? '');

    // Filters live on the server, so every change is a partial reload that
    // preserves scroll and the focused input.
    function apply() {
        router.get(
            '/admin/meetings',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    let debounce: ReturnType<typeof setTimeout>;
    function onSearch() {
        clearTimeout(debounce);
        debounce = setTimeout(apply, 300);
    }

    const when = (ms: number, tz: string) =>
        new Intl.DateTimeFormat('en-GB', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: tz,
        }).format(new Date(ms));
</script>

<AdminLayout title="Meetings">
    <div class="mx-auto w-full max-w-6xl px-6 py-8">
        <h1 class="text-display font-semibold text-ink">Meetings</h1>

        <section class="mt-8">
            <h2 class="text-sm font-semibold tracking-wide text-ink-muted uppercase">
                Upcoming ({upcoming.length})
            </h2>

            {#if upcoming.length === 0}
                <p class="mt-3 text-sm text-ink-muted">
                    No meetings scheduled. Once an entrepreneur books a mentor's
                    slot, it appears here.
                </p>
            {:else}
                <ul class="mt-3 grid gap-3">
                    {#each upcoming as meeting (meeting.id)}
                        <li>
                            <a
                                href={`/admin/meetings/${meeting.id}`}
                                class="block rounded-lg border border-line bg-surface p-4 hover:border-accent/40"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <span class="font-medium text-ink">
                                        {when(meeting.startsAt, meeting.timezone)}
                                    </span>
                                    <MeetingStatus status={meeting.status} />
                                </div>
                                <p class="mt-1 text-sm text-ink-muted">
                                    {meeting.entrepreneurName} → {meeting.mentorName}
                                    · {formatDuration(meeting.durationMinutes)}
                                </p>
                            </a>
                        </li>
                    {/each}
                </ul>
            {/if}
        </section>

        <section class="mt-10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold tracking-wide text-ink-muted uppercase">
                    Past ({past.total})
                </h2>
                <div class="flex gap-2">
                    <input
                        bind:value={search}
                        oninput={onSearch}
                        placeholder="Search people…"
                        class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm"
                    />
                    <select
                        bind:value={status}
                        onchange={apply}
                        class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            {#if past.data.length === 0}
                <p class="mt-3 text-sm text-ink-muted">
                    No past meetings match those filters.
                </p>
            {:else}
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-ink-muted">
                            <tr>
                                <th class="py-2 font-medium">When</th>
                                <th class="py-2 font-medium">Participants</th>
                                <th class="py-2 font-medium">Length</th>
                                <th class="py-2 font-medium">Status</th>
                                <th class="py-2 font-medium">Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each past.data as meeting (meeting.id)}
                                <tr
                                    class="cursor-pointer border-t border-line hover:bg-surface"
                                    onclick={() =>
                                        router.visit(`/admin/meetings/${meeting.id}`)}
                                >
                                    <td class="py-2">
                                        {when(meeting.startsAt, meeting.timezone)}
                                    </td>
                                    <td class="py-2">
                                        {meeting.entrepreneurName} → {meeting.mentorName}
                                    </td>
                                    <td class="py-2">
                                        {formatDuration(meeting.durationMinutes)}
                                    </td>
                                    <td class="py-2">
                                        <MeetingStatus status={meeting.status} />
                                    </td>
                                    <td class="py-2">
                                        {meeting.hasReport ? 'Captured' : '—'}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <nav class="mt-4 flex flex-wrap gap-1">
                    {#each past.links as link}
                        <a
                            href={link.url ?? '#'}
                            class="rounded px-2 py-1 text-sm {link.active
                                ? 'bg-accent text-canvas'
                                : 'text-ink-muted'} {link.url
                                ? ''
                                : 'pointer-events-none opacity-40'}"
                        >
                            {@html link.label}
                        </a>
                    {/each}
                </nav>
            {/if}
        </section>
    </div>
</AdminLayout>
```

Class names here follow the tokens used in `admin/users/Index.svelte`. If any token does not exist in this project, substitute the equivalent used there rather than inventing one.

- [ ] **Step 3: Verify it compiles**

Run: `npm run types:check`
Expected: no errors.

- [ ] **Step 4: Verify in the browser**

Start the stack with `composer dev`, log in as an admin, and open http://localhost:8000/admin/meetings. Confirm the Meetings nav link is now active, Upcoming lists future meetings, search narrows Past, and clicking a row navigates.

- [ ] **Step 5: Commit**

```bash
npm run format
git add resources/js/pages/admin/meetings/Index.svelte resources/js/components/layout/AdminLayout.svelte
git commit -m "feat: add admin meetings index page"
```

---

### Task 8: Admin meeting detail page

**Files:**
- Create: `resources/js/pages/admin/meetings/Show.svelte`

**Interfaces:**
- Consumes: prop `meeting: AdminMeetingDetail` from Task 5; types from Task 6.

- [ ] **Step 1: Create the page**

Create `resources/js/pages/admin/meetings/Show.svelte`:

```svelte
<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeft } from '@lucide/svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import MeetingStatus from '@/components/meetings/meeting-status.svelte';
    import {
        formatDuration,
        type AdminMeetingDetail,
    } from '@/components/meetings/types';

    let { meeting }: { meeting: AdminMeetingDetail } = $props();

    const at = (ms: number | null, tz: string) =>
        ms === null
            ? '—'
            : new Intl.DateTimeFormat('en-GB', {
                  dateStyle: 'medium',
                  timeStyle: 'short',
                  timeZone: tz,
              }).format(new Date(ms));
</script>

<AdminLayout title="Meeting">
    <div class="mx-auto w-full max-w-3xl px-6 py-8">
        <Link
            href="/admin/meetings"
            class="inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink"
        >
            <ArrowLeft class="size-4" /> All meetings
        </Link>

        <div class="mt-4 flex items-start justify-between gap-4">
            <h1 class="text-display font-semibold text-ink">
                {at(meeting.startsAt, meeting.timezone)}
            </h1>
            <MeetingStatus status={meeting.status} />
        </div>

        <dl class="mt-6 grid gap-x-8 gap-y-3 sm:grid-cols-2">
            <div>
                <dt class="text-xs text-ink-muted uppercase">Entrepreneur</dt>
                <dd class="text-ink">{meeting.entrepreneurName}</dd>
            </div>
            <div>
                <dt class="text-xs text-ink-muted uppercase">Mentor</dt>
                <dd class="text-ink">{meeting.mentorName}</dd>
            </div>
            <div>
                <dt class="text-xs text-ink-muted uppercase">Booked by</dt>
                <dd class="text-ink">{meeting.bookedByName ?? 'Unknown'}</dd>
            </div>
            <div>
                <dt class="text-xs text-ink-muted uppercase">Length</dt>
                <dd class="text-ink">
                    {formatDuration(meeting.durationMinutes)}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-ink-muted uppercase">Type</dt>
                <dd class="text-ink">{meeting.sessionType}</dd>
            </div>
            <div>
                <dt class="text-xs text-ink-muted uppercase">Where</dt>
                <dd class="text-ink">
                    {#if meeting.meetingLink}
                        <a
                            href={meeting.meetingLink}
                            class="text-accent-strong underline"
                            rel="noreferrer noopener"
                            target="_blank">Meeting link</a
                        >
                    {:else}
                        {meeting.location ?? '—'}
                    {/if}
                </dd>
            </div>
        </dl>

        {#if meeting.agenda}
            <section class="mt-8">
                <h2 class="text-sm font-semibold text-ink">Agenda</h2>
                <p class="mt-1 text-sm text-ink-muted">{meeting.agenda}</p>
            </section>
        {/if}

        <section class="mt-8">
            <h2 class="text-sm font-semibold text-ink">Report</h2>
            {#if meeting.report}
                <p class="mt-1 text-sm text-ink">{meeting.report.summary}</p>
                <p class="mt-1 text-xs text-ink-muted">
                    {meeting.report.submittedByName ?? 'Unknown'} ·
                    {at(meeting.report.submittedAt, meeting.timezone)}
                </p>
            {:else}
                <p class="mt-1 text-sm text-ink-muted">
                    Not captured yet. The mentor writes this once the meeting is
                    marked completed.
                </p>
            {/if}
        </section>

        {#if meeting.reschedules.length > 0}
            <section class="mt-8">
                <h2 class="text-sm font-semibold text-ink">Reschedules</h2>
                <ul class="mt-2 grid gap-3">
                    {#each meeting.reschedules as r (r.id)}
                        <li class="rounded-lg border border-line p-3 text-sm">
                            <p class="text-ink">
                                {at(r.previousStartsAt, meeting.timezone)} →
                                {at(r.newStartsAt, meeting.timezone)}
                            </p>
                            <p class="mt-1 text-xs text-ink-muted">
                                Requested by {r.requestedByName ?? 'Unknown'} ·
                                {r.status}
                                {#if r.reviewedByName}
                                    · reviewed by {r.reviewedByName}
                                {/if}
                            </p>
                            {#if r.reason}
                                <p class="mt-1 text-xs text-ink-muted">
                                    "{r.reason}"
                                </p>
                            {/if}
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}
    </div>
</AdminLayout>
```

- [ ] **Step 2: Verify it compiles**

Run: `npm run types:check`
Expected: no errors.

- [ ] **Step 3: Verify in the browser**

With `composer dev` running, click a meeting from `/admin/meetings`. Confirm booked-by, duration, agenda, report state, and reschedule history all render.

- [ ] **Step 4: Commit**

```bash
npm run format
git add resources/js/pages/admin/meetings/Show.svelte
git commit -m "feat: add admin meeting detail page"
```

---

### Task 9: End-to-end browser tests

**Files:**
- Modify: `composer.json` (dev dependency)
- Create: `tests/Browser/AdminMeetingsTest.php`
- Modify: `tests/Pest.php` (bind the browser suite)
- Modify: `CLAUDE.md:455` (correct the stale `npm run test` line)

**Interfaces:**
- Consumes: the finished pages from Tasks 7 and 8.

The project has no browser test tooling today. Pest 4.7.4 is installed, and Pest 4 ships browser testing built on Playwright, so e2e joins the existing suite rather than adding a second runner.

- [ ] **Step 1: Install the plugin**

```bash
composer require pestphp/pest-plugin-browser --dev
npx playwright install
```

- [ ] **Step 2: Bind the browser suite**

In `tests/Pest.php`, after the existing `pest()->extend(TestCase::class)->in('Unit');` line, add:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');
```

- [ ] **Step 3: Write the browser tests**

Create `tests/Browser/AdminMeetingsTest.php`:

```php
<?php

use App\Models\Meeting;
use App\Models\Pairing;
use App\Models\User;

test('an admin reaches meetings from the nav and sees an upcoming booking', function () {
    $admin = User::factory()->admin()->approved()->create();
    $pairing = Pairing::factory()->create();
    $pairing->mentor->update(['name' => 'Grace Mentor']);
    $pairing->entrepreneur->update(['name' => 'Tara Founder']);
    Meeting::factory()->for($pairing)->create([
        'booked_by_user_id' => $pairing->entrepreneur_user_id,
    ]);

    $page = visit('/admin/dashboard')->actingAs($admin);

    $page->click('Meetings')
        ->assertUrlIs(config('app.url').'/admin/meetings')
        ->assertSee('Upcoming (1)')
        ->assertSee('Grace Mentor')
        ->assertSee('Tara Founder')
        ->assertNoJavascriptErrors();
});

test('search narrows the past table and survives the round trip', function () {
    $admin = User::factory()->admin()->approved()->create();

    $wanted = Pairing::factory()->create();
    $wanted->mentor->update(['name' => 'Grace Mentor']);
    Meeting::factory()->for($wanted)->completed()->create();

    $other = Pairing::factory()->create();
    $other->mentor->update(['name' => 'Sam Other']);
    Meeting::factory()->for($other)->completed()->create();

    visit('/admin/meetings')->actingAs($admin)
        ->type('Search people…', 'Grace')
        ->wait(1)
        ->assertSee('Grace Mentor')
        ->assertDontSee('Sam Other')
        ->assertNoJavascriptErrors();
});

test('opening a meeting shows who booked it and its length', function () {
    $admin = User::factory()->admin()->approved()->create();
    $pairing = Pairing::factory()->create();
    $pairing->entrepreneur->update(['name' => 'Tara Founder']);
    $meeting = Meeting::factory()->for($pairing)->create([
        'agenda' => 'Quarterly review',
        'booked_by_user_id' => $pairing->entrepreneur_user_id,
    ]);

    visit("/admin/meetings/{$meeting->id}")->actingAs($admin)
        ->assertSee('Booked by')
        ->assertSee('Tara Founder')
        ->assertSee('1 hr')
        ->assertSee('Quarterly review')
        ->assertNoJavascriptErrors();
});

test('a completed meeting with no report says so', function () {
    $admin = User::factory()->admin()->approved()->create();
    $meeting = Meeting::factory()->completed()->create();

    visit("/admin/meetings/{$meeting->id}")->actingAs($admin)
        ->assertSee('Not captured yet')
        ->assertNoJavascriptErrors();
});

test('a mentor sees no meetings link and is refused the page', function () {
    $mentor = User::factory()->mentor()->approved()->create();

    visit('/admin/meetings')->actingAs($mentor)
        ->assertSee('403');
});
```

Pest's browser API is `visit()`, `->click()`, `->type()`, `->assertSee()`. If a helper name here does not exist in the installed version, run `php artisan test tests/Browser --help` or check `vendor/pestphp/pest-plugin-browser/src` for the actual method names and adjust — keep the assertions, change only the syntax.

- [ ] **Step 4: Run the browser suite**

Run: `php artisan test tests/Browser/`
Expected: PASS. These need the app reachable at `config('app.url')`; start `composer dev` first if the plugin does not boot its own server.

- [ ] **Step 5: Fix the stale docs line**

In `CLAUDE.md`, in the "Useful checks" block around line 455, replace `npm run test` with:

```bash
php artisan test tests/Browser   # end-to-end, needs Playwright
```

- [ ] **Step 6: Run the whole suite**

Run: `php artisan test`
Expected: everything green.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock tests/Pest.php tests/Browser/ CLAUDE.md
git commit -m "test: add end-to-end coverage for admin meetings"
```

---

## Self-Review Notes

**Spec coverage:** migration + attribution → Task 1; presenter → Task 2; policy → Task 3; index payload, filters, pagination, N+1 → Task 4; detail payload, reschedules, report state → Task 5; frontend components → Tasks 6–8; nav enable → Task 7; feature tests → Tasks 1, 3, 4, 5; e2e → Task 9. No spec section is unimplemented.

**Known soft spots the implementer must verify rather than assume:**
- Relation names `submittedBy` / `requestedBy` / `reviewedBy` on `MeetingReport` and `MeetingReschedule` (Task 5, step 3) — may need adding.
- Tailwind colour tokens in Task 6 — copy from `users/types.ts` rather than trusting the names here.
- Pest browser helper syntax in Task 9 — check the installed plugin version.
- `MeetingReschedule::$status` may be a string or a backed enum; Task 5 handles both.
