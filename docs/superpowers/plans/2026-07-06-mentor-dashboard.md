# Mentorship Core Schema + Mentor Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the mentorship domain (pairings, availability slots, meetings, reschedules, per-meeting reports) and build the actions-first mentor dashboard plus a read-only pairings section on the admin user detail page.

**Architecture:** Five new tables with string-backed enums and thin Eloquent models; `Mentor\DashboardController` shares shaped arrays (attention, meetings, mentees, availability, stats) alongside the existing `onboarding` prop; two mutations (review reschedule, submit report) via single-purpose actions guarded by policies; Svelte page composed from new `components/mentorship/*` pieces. Pairings are created by entrepreneurs selecting a mentor (guide §10.11) — the selection flow itself is a later spec; a demo seeder provides data meanwhile.

**Tech Stack:** Laravel 13 (Pest, Policies, backed enums), Inertia v3 + Svelte 5 runes, Tailwind tokens, no new dependencies.

## Global Constraints

- No new Composer or npm dependencies.
- Colors only from theme tokens (`bg-canvas/panel/surface/elevated`, `text-ink/muted/faint`, `border-line/line-strong`, `bg-accent`, `text-on-accent`, `positive`/`positive-strong`, `danger`/`danger-strong`, `ring-accent/60`). Never hardcode hex/rgb/hsl.
- Reusable Svelte pieces live in `resources/js/components/mentorship/`, never under `resources/js/pages/`.
- Copy: trustworthy, grounded, warm; sentence case; **no em/en dashes in copy** (user rule); statuses always pair color with a text label.
- Meeting state machine (spec, verbatim): `confirmed → completed` only after `starts_at`; `confirmed → cancelled`. Reschedule review only while the meeting is `confirmed`.
- One report per meeting (`meeting_reports.meeting_id` unique). One active pairing per entrepreneur (index `(entrepreneur_user_id, status)`; enforcement lands with the future `SelectMentor` action).
- `meetings.google_event_id` and `meeting_link` exist now for the future Google Meet booking integration; the dashboard only reads `meeting_link`.
- Follow sibling idioms exactly: enums like `App\Enums\InvitationImportStatus`; actions like `App\Actions\CreateUserInvitation`; policies like `App\Policies\UserPolicy` (`isAdmin()`, `isNot()` helpers); controllers share shaped arrays, never raw models; Pest tests like `tests/Feature/Admin/UserManagementTest.php`; frontend runes + `cn()` + Lucide `strokeWidth={1.75}`.
- The working tree may carry unrelated user WIP. NEVER `git add -A` / `git add .` / `git commit -a`; stage only the files each commit step names. Imperative commit messages, no co-author trailers.
- Verification gates per task: `./vendor/bin/pint --test <changed php files>`, `php artisan test --compact <task test files>`, and for frontend tasks `pnpm types:check` (0 ERRORS; pre-existing warnings elsewhere are fine) + `npx eslint <changed js/svelte files>`.
- All paths relative to repo root `/Users/admin/Documents/Projects/UNDP/TreeOfLife/tol-fund`.

---

### Task 1: Mentorship schema, enums, models, factories

**Files:**
- Create: `app/Enums/PairingStatus.php`, `app/Enums/MeetingStatus.php`, `app/Enums/RescheduleStatus.php`
- Create: `database/migrations/2026_07_06_000003_create_mentorship_tables.php`
- Create: `app/Models/Pairing.php`, `app/Models/MentorAvailabilitySlot.php`, `app/Models/Meeting.php`, `app/Models/MeetingReschedule.php`, `app/Models/MeetingReport.php`
- Create: `database/factories/PairingFactory.php`, `database/factories/MentorAvailabilitySlotFactory.php`, `database/factories/MeetingFactory.php`, `database/factories/MeetingRescheduleFactory.php`, `database/factories/MeetingReportFactory.php`
- Test: `tests/Feature/Mentorship/MentorshipModelsTest.php`

**Interfaces:**
- Consumes: `App\Models\User` and factory states `mentor()`, `entrepreneur()`, `approved()`.
- Produces (later tasks rely on exact names):
  - Enums: `PairingStatus::Active|Ended`, `MeetingStatus::Confirmed|Completed|Cancelled`, `RescheduleStatus::Pending|Accepted|Declined` (string-backed, lowercase values).
  - `Pairing`: `entrepreneur()`, `mentor()`, `meetings()`; casts `status`, `ended_at`.
  - `MentorAvailabilitySlot`: `mentor()`; casts `is_active` bool; columns per spec.
  - `Meeting`: `pairing()`, `availabilitySlot()`, `report()` hasOne, `reschedules()` hasMany, `cancelledBy()`; casts `status`, `starts_at`, `ends_at`, `confirmed_at`, `completed_at`, `cancelled_at`; helper `durationMinutes(): int`.
  - `MeetingReschedule`: `meeting()`, `requestedBy()`, `reviewedBy()`; casts `status` + the four proposed/previous timestamps + `reviewed_at`.
  - `MeetingReport`: `meeting()`, `submittedBy()`; casts `submitted_at`.
  - Factory defaults: PairingFactory active with fresh mentor/entrepreneur users; MeetingFactory `confirmed`, starts tomorrow 10:00 UTC for 60 minutes, belongs to a Pairing; states `completed()` (yesterday, completed status + completed_at), `cancelled()`; MeetingRescheduleFactory pending, requested by the pairing's entrepreneur, proposing +2 days; MeetingReportFactory linked to a completed meeting, submitted by the mentor.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mentorship/MentorshipModelsTest.php`:

```php
<?php

use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;

test('a pairing links a mentor and an entrepreneur', function () {
    $pairing = Pairing::factory()->create();

    expect($pairing->status)->toBe(PairingStatus::Active)
        ->and($pairing->mentor->isMentor())->toBeTrue()
        ->and($pairing->entrepreneur->isEntrepreneur())->toBeTrue();
});

test('a meeting belongs to a pairing and reaches its people through it', function () {
    $meeting = Meeting::factory()->create();

    expect($meeting->status)->toBe(MeetingStatus::Confirmed)
        ->and($meeting->pairing)->toBeInstanceOf(Pairing::class)
        ->and($meeting->pairing->mentor)->toBeInstanceOf(User::class)
        ->and($meeting->durationMinutes())->toBe(60);
});

test('a completed meeting can have exactly one report', function () {
    $report = MeetingReport::factory()->create();

    expect($report->meeting->status)->toBe(MeetingStatus::Completed)
        ->and($report->meeting->report->is($report))->toBeTrue();

    MeetingReport::factory()->create(['meeting_id' => $report->meeting_id]);
})->throws(Illuminate\Database\QueryException::class);

test('a reschedule tracks proposed times and its requester', function () {
    $reschedule = MeetingReschedule::factory()->create();

    expect($reschedule->status)->toBe(RescheduleStatus::Pending)
        ->and($reschedule->requestedBy->is($reschedule->meeting->pairing->entrepreneur))->toBeTrue()
        ->and($reschedule->new_starts_at->gt($reschedule->previous_starts_at))->toBeTrue();
});

test('availability slots belong to a mentor and cast is_active', function () {
    $slot = MentorAvailabilitySlot::factory()->create();

    expect($slot->mentor->isMentor())->toBeTrue()
        ->and($slot->is_active)->toBeTrue()
        ->and($slot->day_of_week)->toBeInt();
});
```

Note: `User::isMentor()` / `isEntrepreneur()` — check `app/Models/User.php` first; if those helpers do not exist, compare `role` to `UserRole::Mentor` / `UserRole::Entrepreneur` in the test instead (do not add helpers unless they already exist as siblings like `isAdmin()`).

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Mentorship/MentorshipModelsTest.php`
Expected: FAIL — `Class "App\Models\Pairing" not found`.

- [ ] **Step 3: Write enums, migration, models, factories**

Create `app/Enums/PairingStatus.php`:

```php
<?php

namespace App\Enums;

/**
 * Lifecycle of a mentor pairing. Created when an entrepreneur selects a
 * mentor; ended when the relationship stops.
 */
enum PairingStatus: string
{
    case Active = 'active';
    case Ended = 'ended';
}
```

Create `app/Enums/MeetingStatus.php`:

```php
<?php

namespace App\Enums;

/**
 * Meeting state machine: confirmed -> completed (only after starts_at),
 * confirmed -> cancelled.
 */
enum MeetingStatus: string
{
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
```

Create `app/Enums/RescheduleStatus.php`:

```php
<?php

namespace App\Enums;

/**
 * A reschedule request is reviewed by the counterparty of whoever asked.
 */
enum RescheduleStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
```

Create `database/migrations/2026_07_06_000003_create_mentorship_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pairings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrepreneur_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('mentor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['entrepreneur_user_id', 'status']);
            $table->index(['mentor_user_id', 'status']);
        });

        Schema::create('mentor_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mentor_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Monday .. 6 = Sunday
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone');
            $table->string('session_type'); // virtual | in_person
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['mentor_user_id', 'is_active']);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pairing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mentor_availability_slot_id')->nullable()->constrained('mentor_availability_slots')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('timezone');
            $table->string('session_type');
            $table->string('location')->nullable();
            // The Google Meet URL once the booking integration lands.
            $table->string('meeting_link')->nullable();
            // Calendar event reference; unused until the booking spec.
            $table->string('google_event_id')->nullable();
            $table->text('agenda')->nullable();
            $table->string('status')->default('confirmed');
            $table->text('outcome_summary')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pairing_id', 'status']);
            $table->index(['status', 'starts_at']);
        });

        Schema::create('meeting_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('previous_starts_at');
            $table->timestamp('previous_ends_at');
            $table->timestamp('new_starts_at');
            $table->timestamp('new_ends_at');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_id', 'status']);
        });

        Schema::create('meeting_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('summary');
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reports');
        Schema::dropIfExists('meeting_reschedules');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('mentor_availability_slots');
        Schema::dropIfExists('pairings');
    }
};
```

Create `app/Models/Pairing.php`:

```php
<?php

namespace App\Models;

use App\Enums\PairingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pairing extends Model
{
    /** @use HasFactory<\Database\Factories\PairingFactory> */
    use HasFactory;

    protected $fillable = [
        'entrepreneur_user_id',
        'mentor_user_id',
        'status',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PairingStatus::class,
            'ended_at' => 'datetime',
        ];
    }

    public function entrepreneur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entrepreneur_user_id');
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
```

Create `app/Models/MentorAvailabilitySlot.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MentorAvailabilitySlot extends Model
{
    /** @use HasFactory<\Database\Factories\MentorAvailabilitySlotFactory> */
    use HasFactory;

    protected $fillable = [
        'mentor_user_id',
        'day_of_week',
        'start_time',
        'end_time',
        'timezone',
        'session_type',
        'location',
        'meeting_link',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_user_id');
    }
}
```

Create `app/Models/Meeting.php`:

```php
<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meeting extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'pairing_id',
        'mentor_availability_slot_id',
        'starts_at',
        'ends_at',
        'timezone',
        'session_type',
        'location',
        'meeting_link',
        'google_event_id',
        'agenda',
        'status',
        'outcome_summary',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(Pairing::class);
    }

    public function availabilitySlot(): BelongsTo
    {
        return $this->belongsTo(MentorAvailabilitySlot::class, 'mentor_availability_slot_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(MeetingReport::class);
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(MeetingReschedule::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }
}
```

Create `app/Models/MeetingReschedule.php`:

```php
<?php

namespace App\Models;

use App\Enums\RescheduleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingReschedule extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingRescheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'requested_by_user_id',
        'status',
        'reason',
        'previous_starts_at',
        'previous_ends_at',
        'new_starts_at',
        'new_ends_at',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RescheduleStatus::class,
            'previous_starts_at' => 'datetime',
            'previous_ends_at' => 'datetime',
            'new_starts_at' => 'datetime',
            'new_ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
```

Create `app/Models/MeetingReport.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingReport extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingReportFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_id',
        'submitted_by_user_id',
        'summary',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
```

Create `database/factories/PairingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\PairingStatus;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pairing>
 */
class PairingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entrepreneur_user_id' => User::factory()->entrepreneur()->approved(),
            'mentor_user_id' => User::factory()->mentor()->approved(),
            'status' => PairingStatus::Active,
            'ended_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => PairingStatus::Ended,
            'ended_at' => now()->subDays(3),
        ]);
    }
}
```

Create `database/factories/MentorAvailabilitySlotFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\MentorAvailabilitySlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorAvailabilitySlot>
 */
class MentorAvailabilitySlotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentor_user_id' => User::factory()->mentor()->approved(),
            'day_of_week' => fake()->numberBetween(0, 4),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'timezone' => 'Africa/Nairobi',
            'session_type' => 'virtual',
            'location' => null,
            'meeting_link' => null,
            'is_active' => true,
        ];
    }
}
```

Create `database/factories/MeetingFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Pairing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = now()->addDay()->setTime(10, 0);

        return [
            'pairing_id' => Pairing::factory(),
            'mentor_availability_slot_id' => null,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'timezone' => 'Africa/Nairobi',
            'session_type' => 'virtual',
            'location' => null,
            'meeting_link' => null,
            'google_event_id' => null,
            'agenda' => fake()->sentence(),
            'status' => MeetingStatus::Confirmed,
            'outcome_summary' => null,
            'confirmed_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
        ];
    }

    public function completed(): static
    {
        $starts = now()->subDay()->setTime(10, 0);

        return $this->state(fn () => [
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => MeetingStatus::Completed,
            'completed_at' => $starts->copy()->addHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
```

Create `database/factories/MeetingRescheduleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReschedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingReschedule>
 */
class MeetingRescheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'requested_by_user_id' => null, // resolved in configure()
            'status' => RescheduleStatus::Pending,
            'reason' => fake()->sentence(),
            'previous_starts_at' => null,
            'previous_ends_at' => null,
            'new_starts_at' => null,
            'new_ends_at' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function configure(): static
    {
        // Derive requester and times from the meeting so states stay coherent.
        return $this->afterMaking(function (MeetingReschedule $reschedule) {
            $meeting = Meeting::find($reschedule->meeting_id) ?? Meeting::factory()->create();
            $reschedule->meeting_id = $meeting->id;
            $reschedule->requested_by_user_id ??= $meeting->pairing->entrepreneur_user_id;
            $reschedule->previous_starts_at ??= $meeting->starts_at;
            $reschedule->previous_ends_at ??= $meeting->ends_at;
            $reschedule->new_starts_at ??= $meeting->starts_at->copy()->addDays(2);
            $reschedule->new_ends_at ??= $meeting->ends_at->copy()->addDays(2);
        });
    }
}
```

Create `database/factories/MeetingReportFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingReport>
 */
class MeetingReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory()->completed(),
            'submitted_by_user_id' => null, // resolved in configure()
            'summary' => fake()->paragraph(),
            'submitted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MeetingReport $report) {
            $meeting = Meeting::find($report->meeting_id);
            $report->submitted_by_user_id ??= $meeting?->pairing->mentor_user_id
                ?? \App\Models\User::factory()->mentor()->approved()->create()->id;
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Mentorship/MentorshipModelsTest.php`
Expected: PASS (5 tests). If a factory `configure()` hook misbehaves on `create()` (ids not yet persisted), switch that factory to resolve in `afterCreating` instead and update accordingly; the test outcomes are the contract, not the hook choice.

- [ ] **Step 5: Pint, full suite, commit**

```bash
./vendor/bin/pint --test app/Enums/PairingStatus.php app/Enums/MeetingStatus.php app/Enums/RescheduleStatus.php app/Models/Pairing.php app/Models/MentorAvailabilitySlot.php app/Models/Meeting.php app/Models/MeetingReschedule.php app/Models/MeetingReport.php database/migrations/2026_07_06_000003_create_mentorship_tables.php database/factories/PairingFactory.php database/factories/MentorAvailabilitySlotFactory.php database/factories/MeetingFactory.php database/factories/MeetingRescheduleFactory.php database/factories/MeetingReportFactory.php tests/Feature/Mentorship/MentorshipModelsTest.php
php artisan test --compact
git add app/Enums/PairingStatus.php app/Enums/MeetingStatus.php app/Enums/RescheduleStatus.php app/Models/Pairing.php app/Models/MentorAvailabilitySlot.php app/Models/Meeting.php app/Models/MeetingReschedule.php app/Models/MeetingReport.php database/migrations/2026_07_06_000003_create_mentorship_tables.php database/factories/PairingFactory.php database/factories/MentorAvailabilitySlotFactory.php database/factories/MeetingFactory.php database/factories/MeetingRescheduleFactory.php database/factories/MeetingReportFactory.php tests/Feature/Mentorship/MentorshipModelsTest.php
git commit -m "Add mentorship core schema: pairings, availability, meetings, reports"
```

---

### Task 2: Dashboard payload + demo seeder

**Files:**
- Modify: `app/Http/Controllers/Mentor/DashboardController.php`
- Create: `database/seeders/MentorshipDemoSeeder.php`
- Test: `tests/Feature/Mentorship/MentorDashboardTest.php`

**Interfaces:**
- Consumes: Task 1 models/factories; existing `OnboardingProgress::forUser()`.
- Produces the Inertia props Task 4 renders (exact camelCase shape):

```
onboarding: (unchanged)
attention: {
  reschedules: [{ id, menteeName, previousStartsAt, newStartsAt, newEndsAt, reason }],
  missingReports: [{ meetingId, menteeName, endedAt }],
}
meetings: [{ id, menteeName, startsAt, endsAt, sessionType, location, meetingLink }]
mentees: [{ pairingId, name, company, lastMeetingAt, nextMeetingAt }]
availability: { activeCount, slots: [{ id, dayOfWeek, startTime, endTime, sessionType }] }
stats: { menteeCount, completedCount, hoursMentored }
```

All timestamps are epoch milliseconds (`getTimestampMs()`, matching the admin users page); times are strings `HH:MM`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Mentorship/MentorDashboardTest.php`:

```php
<?php

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->mentor = User::factory()->mentor()->approved()->create();
});

function pairingFor(User $mentor): Pairing
{
    return Pairing::factory()->create(['mentor_user_id' => $mentor->id]);
}

test('the dashboard shares attention items for pending reschedules and missing reports', function () {
    $pairing = pairingFor($this->mentor);
    $meeting = Meeting::factory()->create(['pairing_id' => $pairing->id]);
    MeetingReschedule::factory()->create(['meeting_id' => $meeting->id]);

    $done = Meeting::factory()->completed()->create(['pairing_id' => $pairing->id]);

    $reported = Meeting::factory()->completed()->create(['pairing_id' => $pairing->id]);
    MeetingReport::factory()->create(['meeting_id' => $reported->id]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('mentor/Dashboard')
            ->count('attention.reschedules', 1)
            ->count('attention.missingReports', 1)
            ->where('attention.missingReports.0.meetingId', $done->id));
});

test('reschedules the mentor requested themselves are not attention items', function () {
    $pairing = pairingFor($this->mentor);
    $meeting = Meeting::factory()->create(['pairing_id' => $pairing->id]);
    MeetingReschedule::factory()->create([
        'meeting_id' => $meeting->id,
        'requested_by_user_id' => $this->mentor->id,
    ]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page->count('attention.reschedules', 0));
});

test('the week list holds only confirmed meetings in the next seven days', function () {
    $pairing = pairingFor($this->mentor);
    Meeting::factory()->create(['pairing_id' => $pairing->id]); // tomorrow
    Meeting::factory()->create([
        'pairing_id' => $pairing->id,
        'starts_at' => now()->addDays(9),
        'ends_at' => now()->addDays(9)->addHour(),
    ]);
    Meeting::factory()->cancelled()->create(['pairing_id' => $pairing->id]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page->count('meetings', 1));
});

test('mentees, availability, and stats are shaped', function () {
    $pairing = pairingFor($this->mentor);
    Meeting::factory()->completed()->create(['pairing_id' => $pairing->id]);
    MentorAvailabilitySlot::factory()->create(['mentor_user_id' => $this->mentor->id]);
    MentorAvailabilitySlot::factory()->create([
        'mentor_user_id' => $this->mentor->id,
        'is_active' => false,
    ]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->count('mentees', 1)
            ->where('mentees.0.name', $pairing->entrepreneur->name)
            ->where('availability.activeCount', 1)
            ->count('availability.slots', 1)
            ->where('stats.menteeCount', 1)
            ->where('stats.completedCount', 1)
            ->where('stats.hoursMentored', 1));
});

test('another mentors data never leaks in', function () {
    $other = Pairing::factory()->create();
    Meeting::factory()->create(['pairing_id' => $other->id]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->count('meetings', 0)
            ->count('mentees', 0));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Mentorship/MentorDashboardTest.php`
Expected: FAIL — missing props (`attention` not shared).

- [ ] **Step 3: Rewrite the controller**

Replace the body of `app/Http/Controllers/Mentor/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Mentor;

use App\Data\OnboardingProgress;
use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Enums\RescheduleStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $mentor = $request->user();

        return Inertia::render('mentor/Dashboard', [
            'onboarding' => OnboardingProgress::forUser($mentor)->toArray(),
            'attention' => [
                'reschedules' => $this->pendingReschedules($mentor),
                'missingReports' => $this->missingReports($mentor),
            ],
            'meetings' => $this->weekMeetings($mentor),
            'mentees' => $this->mentees($mentor),
            'availability' => $this->availability($mentor),
            'stats' => $this->stats($mentor),
        ]);
    }

    /**
     * Pending requests on this mentor's meetings that someone else raised.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingReschedules(User $mentor): array
    {
        return MeetingReschedule::query()
            ->where('status', RescheduleStatus::Pending)
            ->whereNot('requested_by_user_id', $mentor->id)
            ->whereHas('meeting', fn ($q) => $q
                ->where('status', MeetingStatus::Confirmed)
                ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id)))
            ->with('meeting.pairing.entrepreneur:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (MeetingReschedule $r): array => [
                'id' => $r->id,
                'menteeName' => $r->meeting->pairing->entrepreneur->name,
                'previousStartsAt' => $r->previous_starts_at->getTimestampMs(),
                'newStartsAt' => $r->new_starts_at->getTimestampMs(),
                'newEndsAt' => $r->new_ends_at->getTimestampMs(),
                'reason' => $r->reason,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function missingReports(User $mentor): array
    {
        return Meeting::query()
            ->where('status', MeetingStatus::Completed)
            ->whereDoesntHave('report')
            ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id))
            ->with('pairing.entrepreneur:id,name')
            ->orderByDesc('ends_at')
            ->get()
            ->map(fn (Meeting $m): array => [
                'meetingId' => $m->id,
                'menteeName' => $m->pairing->entrepreneur->name,
                'endedAt' => $m->ends_at->getTimestampMs(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weekMeetings(User $mentor): array
    {
        return Meeting::query()
            ->where('status', MeetingStatus::Confirmed)
            ->whereBetween('starts_at', [now(), now()->addDays(7)])
            ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id))
            ->with('pairing.entrepreneur:id,name')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Meeting $m): array => [
                'id' => $m->id,
                'menteeName' => $m->pairing->entrepreneur->name,
                'startsAt' => $m->starts_at->getTimestampMs(),
                'endsAt' => $m->ends_at->getTimestampMs(),
                'sessionType' => $m->session_type,
                'location' => $m->location,
                'meetingLink' => $m->meeting_link,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mentees(User $mentor): array
    {
        return Pairing::query()
            ->where('mentor_user_id', $mentor->id)
            ->where('status', PairingStatus::Active)
            ->with(['entrepreneur:id,name', 'entrepreneur.company:id,owner_user_id,name'])
            ->get()
            ->map(function (Pairing $pairing): array {
                $last = $pairing->meetings()
                    ->where('status', MeetingStatus::Completed)
                    ->latest('ends_at')->first();
                $next = $pairing->meetings()
                    ->where('status', MeetingStatus::Confirmed)
                    ->where('starts_at', '>', now())
                    ->oldest('starts_at')->first();

                return [
                    'pairingId' => $pairing->id,
                    'name' => $pairing->entrepreneur->name,
                    'company' => $pairing->entrepreneur->company?->name,
                    'lastMeetingAt' => $last?->ends_at->getTimestampMs(),
                    'nextMeetingAt' => $next?->starts_at->getTimestampMs(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function availability(User $mentor): array
    {
        $slots = MentorAvailabilitySlot::query()
            ->where('mentor_user_id', $mentor->id)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return [
            'activeCount' => $slots->count(),
            'slots' => $slots->map(fn (MentorAvailabilitySlot $s): array => [
                'id' => $s->id,
                'dayOfWeek' => $s->day_of_week,
                'startTime' => substr((string) $s->start_time, 0, 5),
                'endTime' => substr((string) $s->end_time, 0, 5),
                'sessionType' => $s->session_type,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(User $mentor): array
    {
        $completed = Meeting::query()
            ->where('status', MeetingStatus::Completed)
            ->whereHas('pairing', fn ($p) => $p->where('mentor_user_id', $mentor->id))
            ->get();

        $minutes = $completed->sum(fn (Meeting $m) => $m->durationMinutes());

        return [
            'menteeCount' => Pairing::query()
                ->where('mentor_user_id', $mentor->id)
                ->where('status', PairingStatus::Active)
                ->count(),
            'completedCount' => $completed->count(),
            'hoursMentored' => round($minutes / 60 * 2) / 2,
        ];
    }
}
```

Note on `entrepreneur.company`: check `app/Models/User.php` for the actual company relationship name/keys before relying on the eager-load; if the relation differs (for example `company()` via `company_id`), adjust the `with()` and the accessor to the real shape. The admin `UserController@show` already reads `$user->company?->name`; mirror whatever it does.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Mentorship/MentorDashboardTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Write the demo seeder**

Create `database/seeders/MentorshipDemoSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only: gives the seeded demo mentor a believable week so the
 * dashboard renders with life. Idempotent per mentor (skips if the mentor
 * already has pairings).
 */
class MentorshipDemoSeeder extends Seeder
{
    public function run(): void
    {
        $mentor = User::query()->where('email', 'grace@example.com')->first()
            ?? User::factory()->mentor()->approved()->create([
                'name' => 'Grace Mentor',
                'email' => 'grace@example.com',
            ]);

        if (Pairing::query()->where('mentor_user_id', $mentor->id)->exists()) {
            return;
        }

        $pairings = Pairing::factory()->count(3)->create(['mentor_user_id' => $mentor->id]);

        MentorAvailabilitySlot::factory()->create(['mentor_user_id' => $mentor->id, 'day_of_week' => 1]);
        MentorAvailabilitySlot::factory()->create(['mentor_user_id' => $mentor->id, 'day_of_week' => 3, 'start_time' => '14:00', 'end_time' => '15:00']);

        // Upcoming meetings this week.
        Meeting::factory()->create(['pairing_id' => $pairings[0]->id]);
        Meeting::factory()->create([
            'pairing_id' => $pairings[1]->id,
            'starts_at' => now()->addDays(3)->setTime(14, 0),
            'ends_at' => now()->addDays(3)->setTime(15, 0),
            'meeting_link' => 'https://meet.google.com/demo-link',
        ]);

        // A pending reschedule from a mentee.
        $toMove = Meeting::factory()->create(['pairing_id' => $pairings[2]->id]);
        MeetingReschedule::factory()->create(['meeting_id' => $toMove->id]);

        // History: one reported, one awaiting its report.
        $reported = Meeting::factory()->completed()->create(['pairing_id' => $pairings[0]->id]);
        MeetingReport::factory()->create(['meeting_id' => $reported->id]);
        Meeting::factory()->completed()->create(['pairing_id' => $pairings[1]->id]);

        // An ended pairing for the admin detail page.
        Pairing::factory()->ended()->create(['mentor_user_id' => $mentor->id]);
    }
}
```

Run: `php artisan db:seed --class=MentorshipDemoSeeder` — expect silent success (verify with `php artisan tinker --execute="echo App\Models\Pairing::count();"` printing at least 4).

- [ ] **Step 6: Pint, full suite, commit**

```bash
./vendor/bin/pint --test app/Http/Controllers/Mentor/DashboardController.php database/seeders/MentorshipDemoSeeder.php tests/Feature/Mentorship/MentorDashboardTest.php
php artisan test --compact
git add app/Http/Controllers/Mentor/DashboardController.php database/seeders/MentorshipDemoSeeder.php tests/Feature/Mentorship/MentorDashboardTest.php
git commit -m "Share the mentor dashboard payload and seed demo mentorship data"
```

---

### Task 3: Reschedule review + report submission (actions, policies, routes)

**Files:**
- Create: `app/Actions/Mentorship/ReviewMeetingReschedule.php`, `app/Actions/Mentorship/SubmitMeetingReport.php`
- Create: `app/Policies/MeetingPolicy.php`, `app/Policies/MeetingReschedulePolicy.php`
- Create: `app/Http/Controllers/Mentor/RescheduleController.php`, `app/Http/Controllers/Mentor/MeetingReportController.php`
- Create: `app/Http/Requests/Mentor/SubmitMeetingReportRequest.php`
- Modify: `routes/web.php` (inside the existing `mentor.` group, after the dashboard route)
- Test: `tests/Feature/Mentorship/MentorMeetingActionsTest.php`

**Interfaces:**
- Consumes: Task 1 models/enums.
- Produces:
  - `POST /mentor/reschedules/{reschedule}/accept` → name `mentor.reschedules.accept`
  - `POST /mentor/reschedules/{reschedule}/decline` → name `mentor.reschedules.decline`
  - `POST /mentor/meetings/{meeting}/report` → name `mentor.meetings.report.store`, body `{ summary: string }`
  - `ReviewMeetingReschedule::handle(MeetingReschedule $reschedule, User $reviewer, bool $accept): void`
  - `SubmitMeetingReport::handle(Meeting $meeting, User $submitter, string $summary): MeetingReport`
  - `MeetingReschedulePolicy::review(User $user, MeetingReschedule $reschedule): bool`
  - `MeetingPolicy::submitReport(User $user, Meeting $meeting): bool`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Mentorship/MentorMeetingActionsTest.php`:

```php
<?php

use App\Enums\MeetingStatus;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\Pairing;
use App\Models\User;

beforeEach(function () {
    $this->mentor = User::factory()->mentor()->approved()->create();
    $this->pairing = Pairing::factory()->create(['mentor_user_id' => $this->mentor->id]);
});

function pendingRescheduleFor(Pairing $pairing): MeetingReschedule
{
    $meeting = Meeting::factory()->create(['pairing_id' => $pairing->id]);

    return MeetingReschedule::factory()->create(['meeting_id' => $meeting->id]);
}

test('accepting a reschedule applies the proposed times', function () {
    $reschedule = pendingRescheduleFor($this->pairing);

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertRedirect();

    $reschedule->refresh();
    expect($reschedule->status)->toBe(RescheduleStatus::Accepted)
        ->and($reschedule->reviewed_by_user_id)->toBe($this->mentor->id)
        ->and($reschedule->reviewed_at)->not->toBeNull()
        ->and($reschedule->meeting->starts_at->equalTo($reschedule->new_starts_at))->toBeTrue()
        ->and($reschedule->meeting->ends_at->equalTo($reschedule->new_ends_at))->toBeTrue();
});

test('declining a reschedule stamps the review without moving the meeting', function () {
    $reschedule = pendingRescheduleFor($this->pairing);
    $original = $reschedule->meeting->starts_at;

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/decline")
        ->assertRedirect();

    $reschedule->refresh();
    expect($reschedule->status)->toBe(RescheduleStatus::Declined)
        ->and($reschedule->meeting->starts_at->equalTo($original))->toBeTrue();
});

test('a reviewed reschedule cannot be reviewed again', function () {
    $reschedule = pendingRescheduleFor($this->pairing);
    $reschedule->update(['status' => RescheduleStatus::Declined]);

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertForbidden();
});

test('the requester cannot review their own reschedule', function () {
    $meeting = Meeting::factory()->create(['pairing_id' => $this->pairing->id]);
    $reschedule = MeetingReschedule::factory()->create([
        'meeting_id' => $meeting->id,
        'requested_by_user_id' => $this->mentor->id,
    ]);

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertForbidden();
});

test('a foreign mentor cannot review someone elses reschedule', function () {
    $reschedule = pendingRescheduleFor(Pairing::factory()->create());

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertForbidden();
});

test('a mentor reports on their completed meeting', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'We refined the pitch.'])
        ->assertRedirect();

    expect(MeetingReport::sole())
        ->summary->toBe('We refined the pitch.')
        ->submitted_by_user_id->toBe($this->mentor->id);
});

test('reports are refused for meetings that are not completed', function () {
    $meeting = Meeting::factory()->create(['pairing_id' => $this->pairing->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'Too early.'])
        ->assertForbidden();
});

test('a second report on the same meeting is refused', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);
    MeetingReport::factory()->create(['meeting_id' => $meeting->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'Again.'])
        ->assertForbidden();
});

test('report summaries are required and bounded', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => ''])
        ->assertSessionHasErrors('summary');

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => str_repeat('a', 5001)])
        ->assertSessionHasErrors('summary');
});

test('entrepreneurs and admins get 403s on the mentor endpoints', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);
    $reschedule = pendingRescheduleFor($this->pairing);

    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $admin = User::factory()->admin()->approved()->create();

    foreach ([$entrepreneur, $admin] as $user) {
        $this->actingAs($user)->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'x'])->assertForbidden();
        $this->actingAs($user)->post("/mentor/reschedules/{$reschedule->id}/accept")->assertForbidden();
    }
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Mentorship/MentorMeetingActionsTest.php`
Expected: FAIL — 404s (routes missing).

- [ ] **Step 3: Write actions, policies, request, controllers, routes**

Create `app/Actions/Mentorship/ReviewMeetingReschedule.php`:

```php
<?php

namespace App\Actions\Mentorship;

use App\Enums\RescheduleStatus;
use App\Models\MeetingReschedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewMeetingReschedule
{
    /**
     * Accepting applies the proposed times to the meeting; declining leaves
     * the meeting untouched. Both stamp who reviewed and when.
     */
    public function handle(MeetingReschedule $reschedule, User $reviewer, bool $accept): void
    {
        DB::transaction(function () use ($reschedule, $reviewer, $accept) {
            $reschedule->update([
                'status' => $accept ? RescheduleStatus::Accepted : RescheduleStatus::Declined,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            if ($accept) {
                $reschedule->meeting->update([
                    'starts_at' => $reschedule->new_starts_at,
                    'ends_at' => $reschedule->new_ends_at,
                ]);
            }
        });
    }
}
```

Create `app/Actions/Mentorship/SubmitMeetingReport.php`:

```php
<?php

namespace App\Actions\Mentorship;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;

class SubmitMeetingReport
{
    public function handle(Meeting $meeting, User $submitter, string $summary): MeetingReport
    {
        return MeetingReport::create([
            'meeting_id' => $meeting->id,
            'submitted_by_user_id' => $submitter->id,
            'summary' => $summary,
            'submitted_at' => now(),
        ]);
    }
}
```

Create `app/Policies/MeetingReschedulePolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\MeetingStatus;
use App\Enums\RescheduleStatus;
use App\Models\MeetingReschedule;
use App\Models\User;

class MeetingReschedulePolicy
{
    /**
     * Only the mentor on the meeting's pairing may review, only while the
     * request is pending and the meeting still confirmed, and never their
     * own request.
     */
    public function review(User $user, MeetingReschedule $reschedule): bool
    {
        return $reschedule->status === RescheduleStatus::Pending
            && $reschedule->meeting->status === MeetingStatus::Confirmed
            && $reschedule->meeting->pairing->mentor_user_id === $user->id
            && $reschedule->requested_by_user_id !== $user->id;
    }
}
```

Create `app/Policies/MeetingPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    /**
     * The pairing's mentor reports on a completed meeting, once.
     */
    public function submitReport(User $user, Meeting $meeting): bool
    {
        return $meeting->status === MeetingStatus::Completed
            && $meeting->pairing->mentor_user_id === $user->id
            && $meeting->report()->doesntExist();
    }
}
```

(Laravel 13 auto-discovers `App\Policies\{Model}Policy`; no registration needed — `UserPolicy` works the same way here.)

Create `app/Http/Requests/Mentor/SubmitMeetingReportRequest.php`:

```php
<?php

namespace App\Http\Requests\Mentor;

use Illuminate\Foundation\Http\FormRequest;

class SubmitMeetingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meeting = $this->route('meeting');

        return $this->user()?->can('submitReport', $meeting) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:5000'],
        ];
    }
}
```

Create `app/Http/Controllers/Mentor/RescheduleController.php`:

```php
<?php

namespace App\Http\Controllers\Mentor;

use App\Actions\Mentorship\ReviewMeetingReschedule;
use App\Http\Controllers\Controller;
use App\Models\MeetingReschedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RescheduleController extends Controller
{
    public function accept(Request $request, MeetingReschedule $reschedule): RedirectResponse
    {
        Gate::authorize('review', $reschedule);

        app(ReviewMeetingReschedule::class)->handle($reschedule, $request->user(), accept: true);

        return back()->with('status', 'Meeting moved to the new time.');
    }

    public function decline(Request $request, MeetingReschedule $reschedule): RedirectResponse
    {
        Gate::authorize('review', $reschedule);

        app(ReviewMeetingReschedule::class)->handle($reschedule, $request->user(), accept: false);

        return back()->with('status', 'Reschedule request declined.');
    }
}
```

Create `app/Http/Controllers/Mentor/MeetingReportController.php`:

```php
<?php

namespace App\Http\Controllers\Mentor;

use App\Actions\Mentorship\SubmitMeetingReport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mentor\SubmitMeetingReportRequest;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;

class MeetingReportController extends Controller
{
    public function store(SubmitMeetingReportRequest $request, Meeting $meeting): RedirectResponse
    {
        app(SubmitMeetingReport::class)->handle(
            meeting: $meeting,
            submitter: $request->user(),
            summary: $request->validated('summary'),
        );

        return back()->with('status', 'Report submitted.');
    }
}
```

In `routes/web.php`, add to the imports `use App\Http\Controllers\Mentor\MeetingReportController;` and `use App\Http\Controllers\Mentor\RescheduleController;`, then inside the existing `Route::prefix('mentor')->name('mentor.')->middleware('role:mentor')` group, after the dashboard route, add:

```php
Route::post('/reschedules/{reschedule}/accept', [RescheduleController::class, 'accept'])->name('reschedules.accept');
Route::post('/reschedules/{reschedule}/decline', [RescheduleController::class, 'decline'])->name('reschedules.decline');
Route::post('/meetings/{meeting}/report', [MeetingReportController::class, 'store'])->name('meetings.report.store');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Mentorship/MentorMeetingActionsTest.php`
Expected: PASS (10 tests).

- [ ] **Step 5: Pint, full suite, commit**

```bash
./vendor/bin/pint --test app/Actions/Mentorship/ReviewMeetingReschedule.php app/Actions/Mentorship/SubmitMeetingReport.php app/Policies/MeetingPolicy.php app/Policies/MeetingReschedulePolicy.php app/Http/Controllers/Mentor/RescheduleController.php app/Http/Controllers/Mentor/MeetingReportController.php app/Http/Requests/Mentor/SubmitMeetingReportRequest.php tests/Feature/Mentorship/MentorMeetingActionsTest.php
php artisan test --compact
git add app/Actions/Mentorship/ReviewMeetingReschedule.php app/Actions/Mentorship/SubmitMeetingReport.php app/Policies/MeetingPolicy.php app/Policies/MeetingReschedulePolicy.php app/Http/Controllers/Mentor/RescheduleController.php app/Http/Controllers/Mentor/MeetingReportController.php app/Http/Requests/Mentor/SubmitMeetingReportRequest.php routes/web.php tests/Feature/Mentorship/MentorMeetingActionsTest.php
git commit -m "Let mentors review reschedules and submit meeting reports"
```

**Caution:** before committing, `git diff --cached routes/web.php` must show only your three routes and two imports; if unrelated hunks appear, stage with `git add -p`.

---

### Task 4: Mentor dashboard page and mentorship components

**Files:**
- Create: `resources/js/components/mentorship/types.ts`, `resources/js/components/mentorship/reschedule-card.svelte`, `resources/js/components/mentorship/report-slide-over.svelte`, `resources/js/components/mentorship/meeting-row.svelte`
- Modify: `resources/js/pages/mentor/Dashboard.svelte` (keep the existing onboarding card block exactly as is; add the four sections below it)

**Interfaces:**
- Consumes: Task 2's exact prop shape (epoch-ms timestamps), Task 3's routes, existing `MentorLayout`, `cn()`, tokens, the toast pattern from `pages/admin/users/Index.svelte`.
- Produces: no downstream consumers; the page is the deliverable.

**IMPORTANT — read before writing:** `resources/js/pages/mentor/Dashboard.svelte` is live user-edited code; read the whole file first and anchor by structure. Preserve the `{#if !onboarding.isComplete}` card. This task's markup below is the contract for content, hierarchy, states, and copy; adapt class details to sibling idioms where the file differs, and note any deviation in your report.

- [ ] **Step 1: Create the shared types**

Create `resources/js/components/mentorship/types.ts`:

```ts
export type AttentionReschedule = {
    id: number;
    menteeName: string;
    previousStartsAt: number;
    newStartsAt: number;
    newEndsAt: number;
    reason: string | null;
};

export type MissingReport = {
    meetingId: number;
    menteeName: string;
    endedAt: number;
};

export type WeekMeeting = {
    id: number;
    menteeName: string;
    startsAt: number;
    endsAt: number;
    sessionType: string;
    location: string | null;
    meetingLink: string | null;
};

export type Mentee = {
    pairingId: number;
    name: string;
    company: string | null;
    lastMeetingAt: number | null;
    nextMeetingAt: number | null;
};

export type AvailabilitySlot = {
    id: number;
    dayOfWeek: number;
    startTime: string;
    endTime: string;
    sessionType: string;
};

export const DAY_NAMES = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
] as const;

export function meetingTime(ms: number): string {
    return new Date(ms).toLocaleString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
```

- [ ] **Step 2: Create the reschedule card**

Create `resources/js/components/mentorship/reschedule-card.svelte`:

```svelte
<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { CalendarClock } from '@lucide/svelte';
    import { cn } from '@/lib/utils';
    import { meetingTime, type AttentionReschedule } from './types';

    let {
        reschedule,
        onReviewed,
    }: {
        reschedule: AttentionReschedule;
        onReviewed: (accepted: boolean) => void;
    } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    let acting = $state(false);

    function review(accepted: boolean) {
        router.post(
            `/mentor/reschedules/${reschedule.id}/${accepted ? 'accept' : 'decline'}`,
            {},
            {
                preserveScroll: true,
                onStart: () => (acting = true),
                onFinish: () => (acting = false),
                onSuccess: () => onReviewed(accepted),
            },
        );
    }
</script>

<div class="rounded-xl border border-line bg-panel/40 p-5">
    <div class="flex items-start gap-3">
        <CalendarClock
            class="mt-0.5 size-4 shrink-0 text-glow-amber"
            strokeWidth={1.75}
        />
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-ink">
                {reschedule.menteeName} asked to move your meeting
            </p>
            <p class="mt-1 text-sm text-muted">
                <span class="line-through">{meetingTime(reschedule.previousStartsAt)}</span>
                <span class="mx-1.5 text-faint" aria-hidden="true">&rarr;</span>
                <span class="text-ink">{meetingTime(reschedule.newStartsAt)}</span>
            </p>
            {#if reschedule.reason}
                <p class="mt-1.5 text-[13px] text-faint">
                    "{reschedule.reason}"
                </p>
            {/if}
        </div>
    </div>
    <div class="mt-4 flex items-center gap-2">
        <button
            type="button"
            disabled={acting}
            onclick={() => review(true)}
            class={cn(
                'inline-flex items-center rounded-lg bg-accent px-3.5 py-2 text-sm font-semibold text-on-accent shadow-btn transition-all hover:bg-accent-strong disabled:pointer-events-none disabled:opacity-50',
                focusRing,
            )}
        >
            Accept new time
        </button>
        <button
            type="button"
            disabled={acting}
            onclick={() => review(false)}
            class={cn(
                'inline-flex items-center rounded-lg border border-line bg-surface px-3.5 py-2 text-sm font-medium text-muted transition-colors hover:border-line-strong hover:text-ink disabled:pointer-events-none disabled:opacity-50',
                focusRing,
            )}
        >
            Decline
        </button>
    </div>
</div>
```

- [ ] **Step 3: Create the report slide-over**

Create `resources/js/components/mentorship/report-slide-over.svelte`:

```svelte
<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import { X } from '@lucide/svelte';
    import { fade, fly } from 'svelte/transition';
    import { cn } from '@/lib/utils';
    import { meetingTime, type MissingReport } from './types';

    let {
        meeting,
        onClose,
        onSubmitted,
    }: {
        meeting: MissingReport;
        onClose: () => void;
        onSubmitted: () => void;
    } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';

    const reduce =
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const form = useForm<{ summary: string }>({ summary: '' });

    function submit(e: Event) {
        e.preventDefault();
        form.post(`/mentor/meetings/${meeting.meetingId}/report`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSubmitted();
            },
        });
    }
</script>

<div class="fixed inset-0 z-50">
    <button
        type="button"
        aria-label="Close report panel"
        onclick={onClose}
        transition:fade={{ duration: reduce ? 0 : 180 }}
        class="absolute inset-0 bg-black/50"
    ></button>
    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="report-title"
        transition:fly={{ x: 32, duration: reduce ? 0 : 240 }}
        class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col border-l border-line bg-panel shadow-card"
    >
        <div class="flex items-start justify-between border-b border-line px-6 py-5">
            <div>
                <h2 id="report-title" class="text-lg font-semibold text-ink">
                    Meeting report
                </h2>
                <p class="mt-1 text-sm text-muted">
                    {meeting.menteeName} · {meetingTime(meeting.endedAt)}
                </p>
            </div>
            <button
                type="button"
                onclick={onClose}
                aria-label="Close"
                class={cn(
                    'rounded-lg p-1.5 text-muted transition-colors hover:bg-elevated hover:text-ink',
                    focusRing,
                )}
            >
                <X class="size-4" strokeWidth={1.75} />
            </button>
        </div>

        <form onsubmit={submit} class="flex flex-1 flex-col gap-4 px-6 py-6">
            <label class="block">
                <span class="text-sm font-medium text-ink">
                    What happened in this meeting?
                </span>
                <textarea
                    bind:value={form.summary}
                    rows="8"
                    maxlength="5000"
                    placeholder="What you covered, decisions made, and the next steps you agreed on."
                    class={cn(
                        'auth-input mt-2 w-full rounded-lg px-3 py-2.5 text-[15px] text-ink placeholder:text-muted',
                        focusRing,
                    )}
                ></textarea>
            </label>
            {#if form.errors.summary}
                <p class="text-sm text-danger-strong" role="alert">
                    {form.errors.summary}
                </p>
            {/if}
            <div class="mt-auto flex items-center justify-end gap-2">
                <button
                    type="button"
                    onclick={onClose}
                    class={cn(
                        'rounded-lg px-4 py-2.5 text-sm font-medium text-muted transition-colors hover:bg-elevated hover:text-ink',
                        focusRing,
                    )}
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={!form.summary.trim() || form.processing}
                    class={cn(
                        'inline-flex items-center rounded-lg bg-accent px-4 py-2.5 text-sm font-semibold text-on-accent shadow-btn transition-all hover:bg-accent-strong disabled:pointer-events-none disabled:opacity-50',
                        focusRing,
                    )}
                >
                    {form.processing ? 'Submitting' : 'Submit report'}
                </button>
            </div>
        </form>
    </div>
</div>
```

- [ ] **Step 4: Create the meeting row**

Create `resources/js/components/mentorship/meeting-row.svelte`:

```svelte
<script lang="ts">
    import { MapPin, Video } from '@lucide/svelte';
    import { cn } from '@/lib/utils';
    import { meetingTime, type WeekMeeting } from './types';

    let { meeting }: { meeting: WeekMeeting } = $props();

    const focusRing =
        'outline-none focus-visible:ring-2 focus-visible:ring-accent/60';
</script>

<li class="flex items-center gap-4 py-3.5">
    {#if meeting.sessionType === 'virtual'}
        <Video class="size-4 shrink-0 text-accent" strokeWidth={1.75} />
    {:else}
        <MapPin class="size-4 shrink-0 text-accent" strokeWidth={1.75} />
    {/if}
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-ink">{meeting.menteeName}</p>
        <p class="mt-0.5 text-[13px] text-muted">
            {meetingTime(meeting.startsAt)}
            {#if meeting.location}
                <span class="text-faint">· {meeting.location}</span>
            {/if}
        </p>
    </div>
    {#if meeting.meetingLink}
        <a
            href={meeting.meetingLink}
            target="_blank"
            rel="noopener noreferrer"
            class={cn(
                'shrink-0 rounded-md px-2.5 py-1.5 text-xs font-semibold text-accent transition-colors hover:bg-elevated',
                focusRing,
            )}
        >
            Join
        </a>
    {/if}
</li>
```

- [ ] **Step 5: Extend Dashboard.svelte**

Read `resources/js/pages/mentor/Dashboard.svelte` in full first. Extend the script to accept the new props and manage the report slide-over and toast:

```ts
import RescheduleCard from '@/components/mentorship/reschedule-card.svelte';
import ReportSlideOver from '@/components/mentorship/report-slide-over.svelte';
import MeetingRow from '@/components/mentorship/meeting-row.svelte';
import {
    DAY_NAMES,
    meetingTime,
    type AttentionReschedule,
    type AvailabilitySlot,
    type Mentee,
    type MissingReport,
    type WeekMeeting,
} from '@/components/mentorship/types';

let {
    onboarding,
    attention,
    meetings = [],
    mentees = [],
    availability,
    stats,
}: {
    onboarding: Onboarding;
    attention: { reschedules: AttentionReschedule[]; missingReports: MissingReport[] };
    meetings: WeekMeeting[];
    mentees: Mentee[];
    availability: { activeCount: number; slots: AvailabilitySlot[] };
    stats: { menteeCount: number; completedCount: number; hoursMentored: number };
} = $props();

const needsAttention = $derived(
    attention.reschedules.length + attention.missingReports.length > 0,
);

let reportFor = $state<MissingReport | null>(null);

// Toast, matching pages/admin/users/Index.svelte exactly:
let toast = $state<string | null>(null);
let toastTimer: ReturnType<typeof setTimeout> | undefined;
function toastMsg(m: string) {
    toast = m;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toast = null), 3400);
}
```

(The existing `Onboarding` type and `pct` logic stay.) Below the onboarding card block, add the four sections in this order, then the slide-over and toast at the end of the layout:

```svelte
{#if needsAttention}
    <section class="mt-8" aria-labelledby="attention-heading">
        <h2 id="attention-heading" class="text-sm font-semibold text-ink">
            Needs your attention
        </h2>
        <div class="mt-3 grid gap-4 lg:grid-cols-2">
            {#each attention.reschedules as reschedule (reschedule.id)}
                <RescheduleCard
                    {reschedule}
                    onReviewed={(accepted) =>
                        toastMsg(
                            accepted
                                ? 'Meeting moved to the new time.'
                                : 'Reschedule request declined.',
                        )}
                />
            {/each}
            {#each attention.missingReports as missing (missing.meetingId)}
                <div class="rounded-xl border border-line bg-panel/40 p-5">
                    <p class="text-sm font-medium text-ink">
                        Your meeting with {missing.menteeName} needs a report
                    </p>
                    <p class="mt-1 text-[13px] text-muted">
                        Held {meetingTime(missing.endedAt)}
                    </p>
                    <button
                        type="button"
                        onclick={() => (reportFor = missing)}
                        class={cn(
                            'mt-4 inline-flex items-center rounded-lg bg-accent px-3.5 py-2 text-sm font-semibold text-on-accent shadow-btn transition-all hover:bg-accent-strong',
                            focusRing,
                        )}
                    >
                        Write report
                    </button>
                </div>
            {/each}
        </div>
    </section>
{/if}

<section class="mt-8" aria-labelledby="week-heading">
    <h2 id="week-heading" class="text-sm font-semibold text-ink">This week</h2>
    {#if meetings.length}
        <ul class="mt-3 divide-y divide-line rounded-xl border border-line bg-panel/40 px-5">
            {#each meetings as meeting (meeting.id)}
                <MeetingRow {meeting} />
            {/each}
        </ul>
    {:else}
        <p class="mt-3 rounded-xl border border-line bg-panel/40 p-5 text-sm text-muted">
            No meetings booked this week.
        </p>
    {/if}
</section>

<section class="mt-8" aria-labelledby="mentees-heading">
    <h2 id="mentees-heading" class="text-sm font-semibold text-ink">
        Your mentees
    </h2>
    {#if mentees.length}
        <ul class="mt-3 divide-y divide-line rounded-xl border border-line bg-panel/40 px-5">
            {#each mentees as mentee (mentee.pairingId)}
                <li class="flex items-center gap-4 py-3.5">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-ink">{mentee.name}</p>
                        {#if mentee.company}
                            <p class="mt-0.5 truncate text-[13px] text-muted">{mentee.company}</p>
                        {/if}
                    </div>
                    <p class="shrink-0 text-[13px] text-muted">
                        {mentee.nextMeetingAt
                            ? `Next ${meetingTime(mentee.nextMeetingAt)}`
                            : mentee.lastMeetingAt
                              ? `Last met ${meetingTime(mentee.lastMeetingAt)}`
                              : 'No meetings yet'}
                    </p>
                </li>
            {/each}
        </ul>
    {:else}
        <p class="mt-3 rounded-xl border border-line bg-panel/40 p-5 text-sm text-muted">
            No mentees yet. Entrepreneurs choose their mentor when they join,
            and new mentees appear here automatically.
        </p>
    {/if}
</section>

<section class="mt-8 mb-12" aria-labelledby="availability-heading">
    <h2 id="availability-heading" class="text-sm font-semibold text-ink">
        Availability
    </h2>
    {#if availability.activeCount}
        <ul class="mt-3 flex flex-wrap gap-2">
            {#each availability.slots as slot (slot.id)}
                <li
                    class="rounded-full border border-line bg-elevated px-3 py-1.5 text-xs text-muted"
                >
                    <span class="font-medium text-ink">{DAY_NAMES[slot.dayOfWeek]}</span>
                    {slot.startTime} to {slot.endTime}
                </li>
            {/each}
        </ul>
    {:else}
        <p class="mt-3 rounded-xl border border-line bg-panel/40 p-5 text-sm text-muted">
            No availability set yet. Availability management is coming soon;
            an admin can help set your slots in the meantime.
        </p>
    {/if}
</section>
```

And after the main content, inside the layout:

```svelte
{#if reportFor}
    <ReportSlideOver
        meeting={reportFor}
        onClose={() => (reportFor = null)}
        onSubmitted={() => {
            reportFor = null;
            toastMsg('Report submitted.');
        }}
    />
{/if}

{#if toast}
    <div class="fixed bottom-6 left-1/2 z-[60] -translate-x-1/2">
        <div
            role="status"
            class="flex items-center gap-2.5 rounded-lg border border-line-strong bg-elevated px-4 py-2.5 text-sm text-ink shadow-card"
        >
            {toast}
        </div>
    </div>
{/if}
```

The `stats` prop is shared but intentionally not rendered as a hero-metric row; weave the numbers into the section headers only if it reads naturally (for example "Your mentees" count chip matching the invitations tab-count idiom). Otherwise leave stats unrendered for a future pass; do not build a stat-tile row (banned pattern).

- [ ] **Step 6: Verify**

Run: `pnpm types:check` — expected `0 ERRORS` (pre-existing warnings elsewhere fine).
Run: `npx eslint resources/js/components/mentorship/types.ts resources/js/components/mentorship/reschedule-card.svelte resources/js/components/mentorship/report-slide-over.svelte resources/js/components/mentorship/meeting-row.svelte resources/js/pages/mentor/Dashboard.svelte` — expected clean (use `--fix` + prettier for ordering).

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/mentorship/types.ts resources/js/components/mentorship/reschedule-card.svelte resources/js/components/mentorship/report-slide-over.svelte resources/js/components/mentorship/meeting-row.svelte resources/js/pages/mentor/Dashboard.svelte
git commit -m "Build the actions-first mentor dashboard"
```

---

### Task 5: Admin user detail pairings section

**Files:**
- Modify: `app/Http/Controllers/Admin/UserController.php` (`show()` — add a `pairings` prop)
- Modify: `resources/js/pages/admin/users/Show.svelte` (Mentorship section between Profile and Documents)
- Test: append to `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: Task 1 `Pairing` model; existing admin show payload.
- Produces prop `pairings`: `{ active: [{ id, userId, name, company, since, lastMeetingAt }], ended: [{ id, userId, name, company, since, endedAt }] }` — counterpart users relative to the viewed user (mentor pages list entrepreneurs; entrepreneur pages list mentors); timestamps epoch ms.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Admin/UserManagementTest.php`:

```php
test('a mentors detail page lists their entrepreneurs', function () {
    $admin = User::factory()->admin()->approved()->create();
    $pairing = App\Models\Pairing::factory()->create();
    App\Models\Pairing::factory()->ended()->create([
        'mentor_user_id' => $pairing->mentor_user_id,
    ]);

    $this->actingAs($admin)
        ->get("/admin/users/{$pairing->mentor_user_id}")
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->count('pairings.active', 1)
            ->count('pairings.ended', 1)
            ->where('pairings.active.0.name', $pairing->entrepreneur->name)
            ->where('pairings.active.0.userId', $pairing->entrepreneur_user_id));
});

test('an entrepreneurs detail page lists their mentor', function () {
    $admin = User::factory()->admin()->approved()->create();
    $pairing = App\Models\Pairing::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/users/{$pairing->entrepreneur_user_id}")
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->count('pairings.active', 1)
            ->where('pairings.active.0.name', $pairing->mentor->name)
            ->where('pairings.active.0.userId', $pairing->mentor_user_id));
});

test('users without pairings share empty pairing lists', function () {
    $admin = User::factory()->admin()->approved()->create();

    $this->actingAs($admin)
        ->get("/admin/users/{$admin->id}")
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->count('pairings.active', 0)
            ->count('pairings.ended', 0));
});
```

(Match the file's existing import style — if it already imports `AssertableInertia as Assert`, use `Assert` instead of the FQCN.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Admin/UserManagementTest.php`
Expected: the three new tests FAIL (`pairings` prop missing).

- [ ] **Step 3: Extend the controller**

In `app/Http/Controllers/Admin/UserController.php`, add `use App\Enums\MeetingStatus;`, `use App\Enums\PairingStatus;`, `use App\Models\Pairing;` and extend `show()`'s render array with `'pairings' => $this->pairingsFor($user),`, then add:

```php
    /**
     * Pairings shaped relative to the viewed user: a mentor's page lists
     * their entrepreneurs, an entrepreneur's page lists their mentors.
     *
     * @return array{active: array<int, array<string, mixed>>, ended: array<int, array<string, mixed>>}
     */
    private function pairingsFor(User $user): array
    {
        $pairings = Pairing::query()
            ->where(fn ($q) => $q
                ->where('mentor_user_id', $user->id)
                ->orWhere('entrepreneur_user_id', $user->id))
            ->with(['mentor:id,name', 'entrepreneur:id,name', 'entrepreneur.company:id,owner_user_id,name'])
            ->latest()
            ->get();

        $shape = function (Pairing $pairing) use ($user): array {
            $counterpart = $pairing->mentor_user_id === $user->id
                ? $pairing->entrepreneur
                : $pairing->mentor;

            $lastMeeting = $pairing->meetings()
                ->where('status', MeetingStatus::Completed)
                ->latest('ends_at')
                ->first();

            return [
                'id' => $pairing->id,
                'userId' => $counterpart->id,
                'name' => $counterpart->name,
                'company' => $pairing->entrepreneur->company?->name,
                'since' => $pairing->created_at->getTimestampMs(),
                'lastMeetingAt' => $lastMeeting?->ends_at->getTimestampMs(),
                'endedAt' => $pairing->ended_at?->getTimestampMs(),
            ];
        };

        return [
            'active' => $pairings->where('status', PairingStatus::Active)->map($shape)->values()->all(),
            'ended' => $pairings->where('status', PairingStatus::Ended)->map($shape)->values()->all(),
        ];
    }
```

(Company relation caveat as in Task 2: mirror how this controller already resolves `$user->company?->name` and adjust the eager-load keys to the real relationship.)

- [ ] **Step 4: Extend Show.svelte**

Read the file first (live user-edited). Add to the script:

```ts
import { relative } from '@/components/users/types';

type PairingEntry = {
    id: number;
    userId: number;
    name: string;
    company: string | null;
    since: number;
    lastMeetingAt: number | null;
    endedAt: number | null;
};

let {
    user,
    pairings,
}: {
    user: User;
    pairings: { active: PairingEntry[]; ended: PairingEntry[] };
} = $props();
```

(`relative` is already imported in this file; merge rather than duplicate.) Between the Profile and Documents sections, add:

```svelte
<!-- Mentorship -->
<section class="rounded-xl border border-line bg-panel/40 p-6">
    <h2 class="text-sm font-semibold text-ink">Mentorship</h2>
    {#if pairings.active.length || pairings.ended.length}
        {#if pairings.active.length}
            <ul class="mt-4 divide-y divide-line">
                {#each pairings.active as entry (entry.id)}
                    <li class="flex items-center gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <Link
                                href={`/admin/users/${entry.userId}`}
                                class={cn(
                                    'truncate text-sm font-medium text-ink transition-colors hover:text-accent',
                                    focusRing,
                                )}
                            >
                                {entry.name}
                            </Link>
                            <p class="mt-0.5 text-xs text-muted">
                                {entry.company ? `${entry.company} · ` : ''}Paired {relative(entry.since)}
                            </p>
                        </div>
                        <p class="shrink-0 text-xs text-muted">
                            {entry.lastMeetingAt
                                ? `Last met ${relative(entry.lastMeetingAt)}`
                                : 'No meetings yet'}
                        </p>
                    </li>
                {/each}
            </ul>
        {/if}
        {#if pairings.ended.length}
            <p class="mt-4 text-xs text-faint">Ended</p>
            <ul class="mt-1 divide-y divide-line">
                {#each pairings.ended as entry (entry.id)}
                    <li class="flex items-center gap-3 py-2.5">
                        <Link
                            href={`/admin/users/${entry.userId}`}
                            class={cn(
                                'min-w-0 flex-1 truncate text-sm text-muted transition-colors hover:text-ink',
                                focusRing,
                            )}
                        >
                            {entry.name}
                        </Link>
                        <p class="shrink-0 text-xs text-faint">
                            {entry.endedAt ? `Ended ${relative(entry.endedAt)}` : 'Ended'}
                        </p>
                    </li>
                {/each}
            </ul>
        {/if}
    {:else}
        <p class="mt-4 text-sm text-muted">
            No mentorship pairings yet. Entrepreneurs choose their mentor when
            they join.
        </p>
    {/if}
</section>
```

- [ ] **Step 5: Verify and commit**

```bash
php artisan test --compact tests/Feature/Admin/UserManagementTest.php
./vendor/bin/pint --test app/Http/Controllers/Admin/UserController.php
pnpm types:check
npx eslint resources/js/pages/admin/users/Show.svelte
php artisan test --compact
git add app/Http/Controllers/Admin/UserController.php resources/js/pages/admin/users/Show.svelte tests/Feature/Admin/UserManagementTest.php
git commit -m "Show mentorship pairings on the admin user detail page"
```

---

### Task 6: Browser verification end-to-end

**Files:** none (verification only).

**Interfaces:** consumes the running app (`composer run dev` or existing server on port 8000), the demo seed (`php artisan db:seed --class=MentorshipDemoSeeder`), and the seeded mentor `grace@example.com` / `password` (verify the seeded demo password; the admin seeder uses `password`).

- [ ] **Step 1: Seed and sign in**

Run the seeder, sign in as the mentor, open `/mentor/dashboard`.

- [ ] **Step 2: Verify the checklist**

1. All four sections render with the seeded data: one reschedule card, one missing-report card, two meetings this week (one with a Join link opening the demo Meet URL), three mentees, two availability pills.
2. Accept the pending reschedule: toast appears, card leaves the attention section, the meeting shows its new time in This week.
3. Open Write report, submit a summary: slide-over closes, toast, card gone. Escape and the backdrop both close the slide-over.
4. Empty states: sign in as a fresh mentor with no data (create one via tinker or factory) and confirm all sections show their teaching empty states and no attention section renders.
5. Keyboard: tab order reaches accept/decline/report/join controls with visible focus rings; the slide-over traps interaction sensibly and Escape closes.
6. Mobile 390px: sections stack, no horizontal overflow, cards remain readable.
7. Admin side: as the admin, open Grace's detail page — Mentorship section lists active entrepreneurs with links and the ended pairing beneath; open an entrepreneur's page — their mentor listed; open the admin's own page — empty state.

- [ ] **Step 3: Report results**

Report deviations for a fix wave before declaring done. No commit.
