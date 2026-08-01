# Google Calendar Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a meeting is booked or moved in Tolfund, it appears on the participants' Google Calendars with a per-meeting Meet link, with only the mentor ever authenticating.

**Architecture:** Mentors connect Google via OAuth (Socialite, `calendar.events` scope, offline access). A `CalendarClient` interface wraps the Google API so tests never touch the network. `GoogleCalendarSync` builds event payloads; a queued job performs the call so a Google outage never fails a booking. A mentor without a live connection has no bookable availability, enforced both where slots are offered and where bookings are made.

**Tech Stack:** Laravel 13, `laravel/socialite`, `google/apiclient`, Pest 4, Inertia + Svelte 5.

## Global Constraints

- Push only. Never read the mentor's calendar. Scope is exactly `https://www.googleapis.com/auth/calendar.events`.
- A mentor with no live Google connection has **no bookable availability**. Enforced in two places: where occurrences are listed, and where a booking is made.
- Existing slots are **hidden, never destroyed or deactivated**. Connecting restores them.
- Booking must **never fail** because Google is unreachable. Sync is a queued job with retries.
- No test performs real network I/O. Bind `FakeCalendarClient` in tests.
- Tokens are stored with Laravel `encrypted` casts. Never log a token value.
- New dependencies (`laravel/socialite`, `google/apiclient`) are production deps and were approved as part of the spec. Add nothing else.
- Run `vendor/bin/pint --dirty --format agent` before every commit. PHP tests: `php artisan test --compact --filter=<Name>`.
- Repo may have unrelated uncommitted work. NEVER `git add -A` / `.` / `-a`; stage only named files. Imperative commit messages, no co-author trailers.
- All paths relative to repo root `/Users/admin/Documents/Projects/UNDP/TreeOfLife/tol-fund`.
- **Cancellation is supported but not wired.** `GoogleCalendarSync::cancel()` and
  `SyncMeetingToCalendar(cancel: true)` exist and are tested, but the app has no
  meeting-cancellation flow today (no route, no action, nothing sets
  `MeetingStatus::Cancelled`). Wire the cancel dispatch when that feature is
  built. Do not build a cancellation feature as part of this plan.

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/*_create_google_accounts_table.php` | Token storage schema |
| `app/Models/GoogleAccount.php` | Connection record, liveness |
| `database/factories/GoogleAccountFactory.php` | Test data |
| `app/Models/User.php` (modify) | `googleAccount()`, `hasCalendarConnected()` |
| `app/Services/Google/CalendarClient.php` | Interface the app depends on |
| `app/Services/Google/GoogleCalendarClient.php` | Real implementation |
| `app/Services/Google/FakeCalendarClient.php` | Records calls, used by tests |
| `app/Services/Google/GoogleCalendarSync.php` | Builds payloads, decides create vs patch |
| `app/Jobs/SyncMeetingToCalendar.php` | Queued push with retries and failure flag |
| `app/Http/Controllers/Mentor/GoogleAccountController.php` | connect / callback / disconnect |
| `app/Support/AvailabilityOptions.php` (modify) | Gate: offer nothing when disconnected |
| `app/Actions/Mentorship/BookMeeting.php` (modify) | Gate + dispatch create |
| `app/Actions/Mentorship/RescheduleMeeting.php` (modify) | Gate + dispatch update |
| `app/Actions/Mentorship/ReviewMeetingReschedule.php` (modify) | Dispatch update on accept |
| `resources/js/pages/mentor/Availability.svelte` (modify) | Connect prompt |

---

### Task 1: Connection storage

**Files:**
- Create: `database/migrations/2026_08_01_000001_create_google_accounts_table.php`
- Create: `app/Models/GoogleAccount.php`
- Create: `database/factories/GoogleAccountFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Google/GoogleAccountTest.php`

**Interfaces:**
- Produces: `User::googleAccount(): HasOne`, `User::hasCalendarConnected(): bool`, `GoogleAccount::isLive(): bool`, `GoogleAccountFactory` with a `revoked()` state.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Google/GoogleAccountTest.php`:

```php
<?php

use App\Models\GoogleAccount;
use App\Models\User;

test('a mentor with a live connection counts as calendar-connected', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    GoogleAccount::factory()->for($mentor)->create();

    expect($mentor->refresh()->hasCalendarConnected())->toBeTrue();
});

test('a mentor with no connection is not calendar-connected', function () {
    $mentor = User::factory()->mentor()->approved()->create();

    expect($mentor->hasCalendarConnected())->toBeFalse();
});

test('a revoked connection does not count as connected', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    GoogleAccount::factory()->for($mentor)->revoked()->create();

    expect($mentor->refresh()->hasCalendarConnected())->toBeFalse();
});

test('tokens are stored encrypted, never as plain text', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    GoogleAccount::factory()->for($mentor)->create(['refresh_token' => 'super-secret']);

    $raw = DB::table('google_accounts')->where('user_id', $mentor->id)->value('refresh_token');

    expect($raw)->not->toBe('super-secret')
        ->and($mentor->googleAccount->refresh_token)->toBe('super-secret');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=GoogleAccountTest`
Expected: FAIL, `Class "App\Models\GoogleAccount" not found`.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_08_01_000001_create_google_accounts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_user_id');
            $table->string('email');
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->timestamp('connected_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_accounts');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/GoogleAccount.php`:

```php
<?php

namespace App\Models;

use Database\Factories\GoogleAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleAccount extends Model
{
    /** @use HasFactory<GoogleAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'google_user_id', 'email', 'access_token',
        'refresh_token', 'expires_at', 'scopes', 'connected_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Usable for API calls: not revoked, and able to refresh itself. */
    public function isLive(): bool
    {
        return $this->revoked_at === null && $this->refresh_token !== null;
    }
}
```

- [ ] **Step 5: Create the factory**

Create `database/factories/GoogleAccountFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleAccount>
 */
class GoogleAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->mentor()->approved(),
            'google_user_id' => (string) fake()->randomNumber(9, true),
            'email' => fake()->unique()->safeEmail(),
            'access_token' => 'access-'.fake()->uuid(),
            'refresh_token' => 'refresh-'.fake()->uuid(),
            'expires_at' => now()->addHour(),
            'scopes' => 'https://www.googleapis.com/auth/calendar.events',
            'connected_at' => now(),
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }
}
```

- [ ] **Step 6: Add the relation and helper to User**

In `app/Models/User.php`, add the import `use Illuminate\Database\Eloquent\Relations\HasOne;` if absent, and add these methods to the class:

```php
    /** @return HasOne<GoogleAccount, $this> */
    public function googleAccount(): HasOne
    {
        return $this->hasOne(GoogleAccount::class);
    }

    /** Whether this user can have events written to their Google Calendar. */
    public function hasCalendarConnected(): bool
    {
        return (bool) $this->googleAccount?->isLive();
    }
```

- [ ] **Step 7: Run the test**

Run: `php artisan test --compact --filter=GoogleAccountTest`
Expected: PASS, 4 tests.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_01_000001_create_google_accounts_table.php \
        app/Models/GoogleAccount.php database/factories/GoogleAccountFactory.php \
        app/Models/User.php tests/Feature/Google/GoogleAccountTest.php
git commit -m "Store a mentor's Google connection"
```

---

### Task 2: The calendar client seam

**Files:**
- Create: `app/Services/Google/CalendarClient.php`
- Create: `app/Services/Google/FakeCalendarClient.php`
- Create: `app/Services/Google/GoogleCalendarClient.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/services.php`
- Test: `tests/Feature/Google/FakeCalendarClientTest.php`

**Interfaces:**
- Produces: `CalendarClient::createEvent(GoogleAccount $account, array $event): array{id: string, meetLink: ?string}`, `CalendarClient::patchEvent(GoogleAccount $account, string $eventId, array $event): void`, `CalendarClient::deleteEvent(GoogleAccount $account, string $eventId): void`. `FakeCalendarClient` exposes public `array $created`, `array $patched`, `array $deleted` and `?string $throw`.

- [ ] **Step 1: Install the dependencies**

```bash
composer require laravel/socialite google/apiclient --no-interaction
```

Expected: both resolve against Laravel 13 without conflicts.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Google/FakeCalendarClientTest.php`:

```php
<?php

use App\Models\GoogleAccount;
use App\Services\Google\CalendarClient;
use App\Services\Google\FakeCalendarClient;

test('the container resolves a calendar client', function () {
    expect(app(CalendarClient::class))->toBeInstanceOf(CalendarClient::class);
});

test('the fake records what it was asked to create', function () {
    $fake = new FakeCalendarClient();
    $account = GoogleAccount::factory()->create();

    $result = $fake->createEvent($account, ['summary' => 'Call with Tara']);

    expect($fake->created)->toHaveCount(1)
        ->and($fake->created[0]['summary'])->toBe('Call with Tara')
        ->and($result['id'])->not->toBeEmpty()
        ->and($result['meetLink'])->toStartWith('https://meet.google.com/');
});

test('the fake can be told to fail, so callers can be tested against errors', function () {
    $fake = new FakeCalendarClient();
    $fake->throw = 'transient';
    $account = GoogleAccount::factory()->create();

    expect(fn () => $fake->createEvent($account, []))->toThrow(RuntimeException::class);
});
```

- [ ] **Step 3: Run it and watch it fail**

Run: `php artisan test --compact --filter=FakeCalendarClientTest`
Expected: FAIL, `Interface "App\Services\Google\CalendarClient" not found`.

- [ ] **Step 4: Create the interface**

Create `app/Services/Google/CalendarClient.php`:

```php
<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;

interface CalendarClient
{
    /**
     * @param  array<string, mixed>  $event
     * @return array{id: string, meetLink: string|null}
     */
    public function createEvent(GoogleAccount $account, array $event): array;

    /**
     * @param  array<string, mixed>  $event
     */
    public function patchEvent(GoogleAccount $account, string $eventId, array $event): void;

    public function deleteEvent(GoogleAccount $account, string $eventId): void;
}
```

- [ ] **Step 5: Create the fake**

Create `app/Services/Google/FakeCalendarClient.php`:

```php
<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use RuntimeException;

class FakeCalendarClient implements CalendarClient
{
    /** @var list<array<string, mixed>> */
    public array $created = [];

    /** @var list<array{eventId: string, event: array<string, mixed>}> */
    public array $patched = [];

    /** @var list<string> */
    public array $deleted = [];

    /** Set to any message to make every call throw, for failure-path tests. */
    public ?string $throw = null;

    public function createEvent(GoogleAccount $account, array $event): array
    {
        $this->guard();
        $this->created[] = $event;

        return [
            'id' => 'evt_'.count($this->created),
            'meetLink' => 'https://meet.google.com/fake-'.count($this->created),
        ];
    }

    public function patchEvent(GoogleAccount $account, string $eventId, array $event): void
    {
        $this->guard();
        $this->patched[] = ['eventId' => $eventId, 'event' => $event];
    }

    public function deleteEvent(GoogleAccount $account, string $eventId): void
    {
        $this->guard();
        $this->deleted[] = $eventId;
    }

    private function guard(): void
    {
        if ($this->throw !== null) {
            throw new RuntimeException($this->throw);
        }
    }
}
```

- [ ] **Step 6: Create the real client**

Create `app/Services/Google/GoogleCalendarClient.php`:

```php
<?php

namespace App\Services\Google;

use App\Models\GoogleAccount;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleCalendarClient implements CalendarClient
{
    private const CALENDAR_ID = 'primary';

    public function createEvent(GoogleAccount $account, array $event): array
    {
        $created = $this->service($account)->events->insert(
            self::CALENDAR_ID,
            new Event($event),
            ['sendUpdates' => 'all', 'conferenceDataVersion' => 1],
        );

        return [
            'id' => $created->getId(),
            'meetLink' => $created->getHangoutLink(),
        ];
    }

    public function patchEvent(GoogleAccount $account, string $eventId, array $event): void
    {
        $this->service($account)->events->patch(
            self::CALENDAR_ID,
            $eventId,
            new Event($event),
            ['sendUpdates' => 'all', 'conferenceDataVersion' => 1],
        );
    }

    public function deleteEvent(GoogleAccount $account, string $eventId): void
    {
        $this->service($account)->events->delete(
            self::CALENDAR_ID,
            $eventId,
            ['sendUpdates' => 'all'],
        );
    }

    private function service(GoogleAccount $account): Calendar
    {
        $client = new Client();
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setAccessToken([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
            'expires_in' => max(0, (int) now()->diffInSeconds($account->expires_at, false)),
            'created' => now()->subSeconds(1)->timestamp,
        ]);

        if ($client->isAccessTokenExpired() && $account->refresh_token !== null) {
            $fresh = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);

            if (isset($fresh['error'])) {
                $account->update(['revoked_at' => now()]);

                throw new CalendarConnectionRevoked($account->user_id);
            }

            $account->update([
                'access_token' => $fresh['access_token'],
                'expires_at' => now()->addSeconds((int) ($fresh['expires_in'] ?? 3600)),
            ]);
        }

        return new Calendar($client);
    }
}
```

Create `app/Services/Google/CalendarConnectionRevoked.php`:

```php
<?php

namespace App\Services\Google;

use RuntimeException;

class CalendarConnectionRevoked extends RuntimeException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct("Google connection for user {$userId} was revoked.");
    }
}
```

- [ ] **Step 7: Bind it and add config**

In `config/services.php`, add:

```php
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
```

In `app/Providers/AppServiceProvider.php`, inside `register()`:

```php
        $this->app->bind(
            \App\Services\Google\CalendarClient::class,
            \App\Services\Google\GoogleCalendarClient::class,
        );
```

Add to `.env.example`:

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/mentor/google/callback"
```

- [ ] **Step 8: Run the test**

Run: `php artisan test --compact --filter=FakeCalendarClientTest`
Expected: PASS, 3 tests.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add composer.json composer.lock config/services.php .env.example \
        app/Services/Google app/Providers/AppServiceProvider.php \
        tests/Feature/Google/FakeCalendarClientTest.php
git commit -m "Add a calendar client seam so sync is testable without network"
```

---

### Task 3: Connecting and disconnecting Google

**Files:**
- Create: `app/Http/Controllers/Mentor/GoogleAccountController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Google/ConnectGoogleAccountTest.php`

**Interfaces:**
- Consumes: `GoogleAccount` (Task 1).
- Produces: routes `mentor.google.connect`, `mentor.google.callback`, `mentor.google.disconnect`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Google/ConnectGoogleAccountTest.php`:

```php
<?php

use App\Models\GoogleAccount;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $email = 'mentor@gmail.com'): SocialiteUser
{
    $user = new SocialiteUser();
    $user->id = '1234567890';
    $user->email = $email;
    $user->token = 'access-token';
    $user->refreshToken = 'refresh-token';
    $user->expiresIn = 3600;

    return $user;
}

test('the callback stores the connection for the signed-in mentor', function () {
    $mentor = User::factory()->mentor()->approved()->create();

    Socialite::shouldReceive('driver->stateless->user')->andReturn(fakeGoogleUser());

    $this->actingAs($mentor)->get('/mentor/google/callback')->assertRedirect();

    $account = $mentor->refresh()->googleAccount;

    expect($account)->not->toBeNull()
        ->and($account->email)->toBe('mentor@gmail.com')
        ->and($account->refresh_token)->toBe('refresh-token')
        ->and($mentor->hasCalendarConnected())->toBeTrue();
});

test('reconnecting revives a previously revoked connection', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    GoogleAccount::factory()->for($mentor)->revoked()->create();

    Socialite::shouldReceive('driver->stateless->user')->andReturn(fakeGoogleUser());

    $this->actingAs($mentor)->get('/mentor/google/callback')->assertRedirect();

    expect($mentor->refresh()->hasCalendarConnected())->toBeTrue()
        ->and(GoogleAccount::where('user_id', $mentor->id)->count())->toBe(1);
});

test('a mentor can disconnect, which ends the connection', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    GoogleAccount::factory()->for($mentor)->create();

    $this->actingAs($mentor)->delete('/mentor/google')->assertRedirect();

    expect($mentor->refresh()->hasCalendarConnected())->toBeFalse();
});

test('an entrepreneur cannot reach the connect flow', function () {
    $this->actingAs(User::factory()->entrepreneur()->approved()->create())
        ->get('/mentor/google/connect')
        ->assertForbidden();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=ConnectGoogleAccountTest`
Expected: FAIL, 404 on `/mentor/google/callback`.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Mentor/GoogleAccountController.php`:

```php
<?php

namespace App\Http\Controllers\Mentor;

use App\Http\Controllers\Controller;
use App\Models\GoogleAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleAccountController extends Controller
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    public function connect(): SymfonyRedirect
    {
        return Socialite::driver('google')
            ->scopes([self::SCOPE])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->stateless()
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        GoogleAccount::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'google_user_id' => (string) $googleUser->getId(),
                'email' => (string) $googleUser->getEmail(),
                'access_token' => $googleUser->token,
                // Google only returns a refresh token on first consent; keep the
                // stored one when this grant did not include a new one.
                'refresh_token' => $googleUser->refreshToken
                    ?: $request->user()->googleAccount?->refresh_token,
                'expires_at' => now()->addSeconds((int) ($googleUser->expiresIn ?? 3600)),
                'scopes' => self::SCOPE,
                'connected_at' => now(),
                'revoked_at' => null,
            ],
        );

        return redirect()->route('mentor.availability.index')
            ->with('status', 'Google Calendar connected.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->googleAccount?->update(['revoked_at' => now()]);

        return back()->with('status', 'Google Calendar disconnected.');
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/web.php`, add the import next to the other mentor controllers:

```php
use App\Http\Controllers\Mentor\GoogleAccountController;
```

Inside the `Route::prefix('mentor')->name('mentor.')->middleware('role:mentor')` group, **outside** the `account.active` sub-group (a mentor must be able to connect before being fully active):

```php
        Route::get('/google/connect', [GoogleAccountController::class, 'connect'])->name('google.connect');
        Route::get('/google/callback', [GoogleAccountController::class, 'callback'])->name('google.callback');
        Route::delete('/google', [GoogleAccountController::class, 'disconnect'])->name('google.disconnect');
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=ConnectGoogleAccountTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Mentor/GoogleAccountController.php routes/web.php \
        tests/Feature/Google/ConnectGoogleAccountTest.php
git commit -m "Let a mentor connect and disconnect their Google Calendar"
```

---

### Task 4: Building the event payload

**Files:**
- Create: `app/Services/Google/GoogleCalendarSync.php`
- Test: `tests/Feature/Google/GoogleCalendarSyncTest.php`

**Interfaces:**
- Consumes: `CalendarClient` (Task 2), `User::hasCalendarConnected()` (Task 1).
- Produces: `GoogleCalendarSync::push(Meeting $meeting): void` and `GoogleCalendarSync::cancel(Meeting $meeting): void`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Google/GoogleCalendarSyncTest.php`:

```php
<?php

use App\Models\GoogleAccount;
use App\Models\Meeting;
use App\Models\Pairing;
use App\Services\Google\CalendarClient;
use App\Services\Google\FakeCalendarClient;
use App\Services\Google\GoogleCalendarSync;

beforeEach(function () {
    $this->fake = new FakeCalendarClient();
    app()->instance(CalendarClient::class, $this->fake);

    $this->pairing = Pairing::factory()->create();
    GoogleAccount::factory()->for($this->pairing->mentor)->create();
    $this->meeting = Meeting::factory()->create(['pairing_id' => $this->pairing->id]);
});

test('a first push creates the event and records its id and meet link', function () {
    app(GoogleCalendarSync::class)->push($this->meeting);

    $this->meeting->refresh();

    expect($this->fake->created)->toHaveCount(1)
        ->and($this->meeting->google_event_id)->toBe('evt_1')
        ->and($this->meeting->meeting_link)->toBe('https://meet.google.com/fake-1');
});

test('the event invites the entrepreneur and requests a meet link', function () {
    app(GoogleCalendarSync::class)->push($this->meeting);

    $event = $this->fake->created[0];

    expect($event['attendees'][0]['email'])->toBe($this->pairing->entrepreneur->email)
        ->and($event['start']['timeZone'])->toBe($this->meeting->timezone)
        ->and($event['conferenceData']['createRequest']['conferenceSolutionKey']['type'])
        ->toBe('hangoutsMeet');
});

test('a second push patches the existing event rather than creating another', function () {
    $sync = app(GoogleCalendarSync::class);
    $sync->push($this->meeting);
    $sync->push($this->meeting->refresh());

    expect($this->fake->created)->toHaveCount(1)
        ->and($this->fake->patched)->toHaveCount(1)
        ->and($this->fake->patched[0]['eventId'])->toBe('evt_1');
});

test('cancelling deletes the event and clears the stored id', function () {
    $sync = app(GoogleCalendarSync::class);
    $sync->push($this->meeting);
    $sync->cancel($this->meeting->refresh());

    expect($this->fake->deleted)->toBe(['evt_1'])
        ->and($this->meeting->refresh()->google_event_id)->toBeNull();
});

test('nothing is pushed for a mentor who is not connected', function () {
    $pairing = Pairing::factory()->create();
    $meeting = Meeting::factory()->create(['pairing_id' => $pairing->id]);

    app(GoogleCalendarSync::class)->push($meeting);

    expect($this->fake->created)->toBeEmpty();
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=GoogleCalendarSyncTest`
Expected: FAIL, `Class "App\Services\Google\GoogleCalendarSync" not found`.

- [ ] **Step 3: Write the service**

Create `app/Services/Google/GoogleCalendarSync.php`:

```php
<?php

namespace App\Services\Google;

use App\Models\Meeting;

class GoogleCalendarSync
{
    public function __construct(private readonly CalendarClient $client) {}

    /**
     * Create the event, or patch it if this meeting already has one. A meeting
     * whose earlier sync failed has no event id, so it self-heals here.
     */
    public function push(Meeting $meeting): void
    {
        $account = $meeting->pairing->mentor->googleAccount;

        if ($account === null || ! $account->isLive()) {
            return;
        }

        if ($meeting->google_event_id !== null) {
            $this->client->patchEvent($account, $meeting->google_event_id, $this->payload($meeting));

            return;
        }

        $created = $this->client->createEvent($account, $this->payload($meeting));

        $meeting->update([
            'google_event_id' => $created['id'],
            'meeting_link' => $created['meetLink'] ?: $meeting->meeting_link,
        ]);
    }

    public function cancel(Meeting $meeting): void
    {
        $account = $meeting->pairing->mentor->googleAccount;

        if ($account === null || ! $account->isLive() || $meeting->google_event_id === null) {
            return;
        }

        $this->client->deleteEvent($account, $meeting->google_event_id);
        $meeting->update(['google_event_id' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Meeting $meeting): array
    {
        $pairing = $meeting->pairing;

        return [
            'summary' => "Tolfund: {$pairing->mentor->name} and {$pairing->entrepreneur->name}",
            'description' => "Mentorship call arranged through Tolfund.\n\n"
                .url('/mentor/meetings'),
            'start' => [
                'dateTime' => $meeting->starts_at->toRfc3339String(),
                'timeZone' => $meeting->timezone,
            ],
            'end' => [
                'dateTime' => $meeting->ends_at->toRfc3339String(),
                'timeZone' => $meeting->timezone,
            ],
            'attendees' => [
                ['email' => $pairing->entrepreneur->email],
            ],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => "tolfund-meeting-{$meeting->id}",
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
        ];
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact --filter=GoogleCalendarSyncTest`
Expected: PASS, 5 tests.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Google/GoogleCalendarSync.php tests/Feature/Google/GoogleCalendarSyncTest.php
git commit -m "Build Google Calendar event payloads for meetings"
```

---

### Task 5: Pushing on a queue, so Google never blocks a booking

**Files:**
- Create: `database/migrations/2026_08_01_000002_add_calendar_sync_columns_to_meetings.php`
- Create: `app/Jobs/SyncMeetingToCalendar.php`
- Test: `tests/Feature/Google/SyncMeetingToCalendarJobTest.php`

**Interfaces:**
- Consumes: `GoogleCalendarSync` (Task 4).
- Produces: `SyncMeetingToCalendar::dispatch(Meeting $meeting, bool $cancel = false)`; `meetings.calendar_synced_at`, `meetings.calendar_sync_failed_at`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Google/SyncMeetingToCalendarJobTest.php`:

```php
<?php

use App\Jobs\SyncMeetingToCalendar;
use App\Models\GoogleAccount;
use App\Models\Meeting;
use App\Models\Pairing;
use App\Services\Google\CalendarClient;
use App\Services\Google\FakeCalendarClient;

beforeEach(function () {
    $this->fake = new FakeCalendarClient();
    app()->instance(CalendarClient::class, $this->fake);

    $this->pairing = Pairing::factory()->create();
    GoogleAccount::factory()->for($this->pairing->mentor)->create();
    $this->meeting = Meeting::factory()->create(['pairing_id' => $this->pairing->id]);
});

test('a successful run records when the meeting was synced', function () {
    (new SyncMeetingToCalendar($this->meeting))->handle(app(\App\Services\Google\GoogleCalendarSync::class));

    expect($this->meeting->refresh()->calendar_synced_at)->not->toBeNull()
        ->and($this->meeting->calendar_sync_failed_at)->toBeNull();
});

test('a failing run flags the meeting instead of losing the problem', function () {
    $this->fake->throw = 'Google is down';

    $job = new SyncMeetingToCalendar($this->meeting);
    $job->failed(new RuntimeException('Google is down'));

    expect($this->meeting->refresh()->calendar_sync_failed_at)->not->toBeNull();
});

test('the cancel variant deletes the event', function () {
    (new SyncMeetingToCalendar($this->meeting))->handle(app(\App\Services\Google\GoogleCalendarSync::class));
    $this->meeting->refresh();

    (new SyncMeetingToCalendar($this->meeting, cancel: true))
        ->handle(app(\App\Services\Google\GoogleCalendarSync::class));

    expect($this->fake->deleted)->toHaveCount(1);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=SyncMeetingToCalendarJobTest`
Expected: FAIL, `Class "App\Jobs\SyncMeetingToCalendar" not found`.

- [ ] **Step 3: Add the meeting columns**

Create `database/migrations/2026_08_01_000002_add_calendar_sync_columns_to_meetings.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->timestamp('calendar_synced_at')->nullable()->after('google_event_id');
            $table->timestamp('calendar_sync_failed_at')->nullable()->after('calendar_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['calendar_synced_at', 'calendar_sync_failed_at']);
        });
    }
};
```

In `app/Models/Meeting.php`, add both names to `$fillable` and to `casts()` as `'datetime'`.

- [ ] **Step 4: Write the job**

Create `app/Jobs/SyncMeetingToCalendar.php`:

```php
<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\Google\CalendarConnectionRevoked;
use App\Services\Google\GoogleCalendarSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncMeetingToCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public Meeting $meeting,
        public bool $cancel = false,
    ) {}

    public function handle(GoogleCalendarSync $sync): void
    {
        if ($this->cancel) {
            $sync->cancel($this->meeting);

            return;
        }

        $sync->push($this->meeting);

        $this->meeting->update([
            'calendar_synced_at' => now(),
            'calendar_sync_failed_at' => null,
        ]);
    }

    /**
     * A revoked connection is not retryable: the mentor must reconnect, and
     * their slots are already hidden by that revocation.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHour();
    }

    public function failed(?Throwable $exception): void
    {
        $this->meeting->update(['calendar_sync_failed_at' => now()]);

        if ($exception instanceof CalendarConnectionRevoked) {
            return;
        }

        report($exception);
    }
}
```

- [ ] **Step 5: Run the test**

Run: `php artisan test --compact --filter=SyncMeetingToCalendarJobTest`
Expected: PASS, 3 tests.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_01_000002_add_calendar_sync_columns_to_meetings.php \
        app/Jobs/SyncMeetingToCalendar.php app/Models/Meeting.php \
        tests/Feature/Google/SyncMeetingToCalendarJobTest.php
git commit -m "Push calendar events on a queue so Google cannot block a booking"
```

---

### Task 6: No connection, no bookable availability

**Files:**
- Modify: `app/Support/AvailabilityOptions.php`
- Modify: `app/Actions/Mentorship/BookMeeting.php`
- Modify: `app/Actions/Mentorship/RescheduleMeeting.php`
- Test: `tests/Feature/Google/CalendarGateTest.php`

**Interfaces:**
- Consumes: `User::hasCalendarConnected()` (Task 1).
- Produces: no new API; two enforcement points.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Google/CalendarGateTest.php`:

```php
<?php

use App\Actions\Mentorship\BookMeeting;
use App\Models\GoogleAccount;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Support\AvailabilityOptions;

beforeEach(function () {
    $this->pairing = Pairing::factory()->create();
    $this->slot = MentorAvailabilitySlot::factory()->create([
        'mentor_user_id' => $this->pairing->mentor_user_id,
        'day_of_week' => 1,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);
});

test('an unconnected mentor offers no occurrences even with active slots', function () {
    expect(AvailabilityOptions::forPairing($this->pairing))->toBeEmpty();
});

test('connecting restores the very same slots, which were never destroyed', function () {
    GoogleAccount::factory()->for($this->pairing->mentor)->create();

    expect(AvailabilityOptions::forPairing($this->pairing->refresh()))->not->toBeEmpty()
        ->and($this->slot->fresh()->is_active)->toBeTrue();
});

test('a revoked connection hides the slots again', function () {
    GoogleAccount::factory()->for($this->pairing->mentor)->revoked()->create();

    expect(AvailabilityOptions::forPairing($this->pairing->refresh()))->toBeEmpty();
});

test('booking is refused for an unconnected mentor even if a slot id is posted directly', function () {
    $entrepreneur = $this->pairing->entrepreneur;

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/meetings', [
            'slot_id' => $this->slot->id,
        ])->assertStatus(422);

    expect($this->pairing->meetings()->count())->toBe(0);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=CalendarGateTest`
Expected: FAIL on the first test, because occurrences are still returned.

- [ ] **Step 3: Gate the offered list**

In `app/Support/AvailabilityOptions.php`, at the top of `forPairing()`:

```php
        // A mentor without a live Google connection has no bookable availability.
        // Their slots stay in the database and return the moment they connect.
        if (! $pairing->mentor->hasCalendarConnected()) {
            return [];
        }
```

- [ ] **Step 4: Gate the booking itself**

In `app/Actions/Mentorship/BookMeeting.php`, at the top of `handle()`, before any occurrence lookup:

```php
        abort_unless(
            $pairing->mentor->hasCalendarConnected(),
            422,
            'This mentor has not connected their calendar yet.',
        );
```

In `app/Actions/Mentorship/RescheduleMeeting.php`, at the top of `handle()`, after `$pairing = $meeting->pairing;`:

```php
        abort_unless(
            $pairing->mentor->hasCalendarConnected(),
            422,
            'This mentor has not connected their calendar yet.',
        );
```

- [ ] **Step 5: Fix the existing suites this breaks**

Booking and rescheduling tests create pairings without a Google account, so they now fail. Add a connection in the `beforeEach` of each affected file:

In `tests/Feature/Mentorship/MeetingBookingTest.php` and `tests/Feature/Mentorship/MeetingRescheduleTest.php`, after the pairing is created:

```php
    \App\Models\GoogleAccount::factory()->for($this->pairing->mentor)->create();
```

For `tests/Pest.php`'s `availableMentor()` helper, add a connection so mentor-directory tests keep passing:

```php
    \App\Models\GoogleAccount::factory()->for($mentor)->create();
```

- [ ] **Step 6: Run the affected suites**

Run: `php artisan test --compact --filter="CalendarGate|MeetingBooking|MeetingReschedule|MentorDirectory"`
Expected: all PASS.

- [ ] **Step 7: Run everything, including the browser suite**

```bash
php artisan test --compact
php artisan test --group=browser
```

Expected: all PASS. This gate changes a global assumption, so any suite that
assumed bookable slots without a connection needs the same factory line. The
browser suite must be run separately and requires the dev server to be stopped:
a running Vite writes `public/hot`, which makes browser tests load assets from
the dev server and fail confusingly.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/AvailabilityOptions.php app/Actions/Mentorship/BookMeeting.php \
        app/Actions/Mentorship/RescheduleMeeting.php tests/Pest.php \
        tests/Feature/Google/CalendarGateTest.php \
        tests/Feature/Mentorship/MeetingBookingTest.php \
        tests/Feature/Mentorship/MeetingRescheduleTest.php
git commit -m "Require a connected calendar before availability is bookable"
```

---

### Task 7: Sync on book, reschedule and accept

**Files:**
- Modify: `app/Actions/Mentorship/BookMeeting.php`
- Modify: `app/Actions/Mentorship/RescheduleMeeting.php`
- Modify: `app/Actions/Mentorship/ReviewMeetingReschedule.php`
- Test: `tests/Feature/Google/MeetingCalendarWiringTest.php`

**Interfaces:**
- Consumes: `SyncMeetingToCalendar` (Task 5).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Google/MeetingCalendarWiringTest.php`:

```php
<?php

use App\Actions\Mentorship\BookMeeting;
use App\Jobs\SyncMeetingToCalendar;
use App\Models\GoogleAccount;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->pairing = Pairing::factory()->create();
    GoogleAccount::factory()->for($this->pairing->mentor)->create();
    $this->slot = MentorAvailabilitySlot::factory()->create([
        'mentor_user_id' => $this->pairing->mentor_user_id,
        'day_of_week' => 1,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);
});

test('booking queues a calendar push', function () {
    app(BookMeeting::class)->handle($this->pairing->refresh(), $this->slot);

    Queue::assertPushed(SyncMeetingToCalendar::class, fn ($job) => $job->cancel === false);
});

test('a mentor moving a call queues a push for the same meeting', function () {
    $meeting = app(BookMeeting::class)->handle($this->pairing->refresh(), $this->slot);
    Queue::assertPushed(SyncMeetingToCalendar::class, 1);

    $next = \App\Actions\Mentorship\BookMeeting::freeOccurrences($this->slot, $this->pairing->refresh())->first();

    app(\App\Actions\Mentorship\RescheduleMeeting::class)->handle(
        meeting: $meeting,
        actor: $this->pairing->mentor,
        slot: $this->slot,
        newStart: $next,
    );

    Queue::assertPushed(SyncMeetingToCalendar::class, 2);
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=MeetingCalendarWiringTest`
Expected: FAIL, no `SyncMeetingToCalendar` pushed.

- [ ] **Step 3: Dispatch after booking**

In `app/Actions/Mentorship/BookMeeting.php`, add the import `use App\Jobs\SyncMeetingToCalendar;` and, immediately before `return $meeting;`:

```php
        SyncMeetingToCalendar::dispatch($meeting);
```

- [ ] **Step 4: Dispatch after a mentor-direct move**

In `app/Actions/Mentorship/RescheduleMeeting.php`, add the import `use App\Jobs\SyncMeetingToCalendar;` and, inside the `if ($byMentor)` block after `self::announce(...)`:

```php
            SyncMeetingToCalendar::dispatch($meeting->refresh());
```

- [ ] **Step 5: Dispatch after an accepted request**

In `app/Actions/Mentorship/ReviewMeetingReschedule.php`, add the import `use App\Jobs\SyncMeetingToCalendar;` and, inside the existing `if ($accept)` block after the announce call:

```php
            SyncMeetingToCalendar::dispatch($reschedule->meeting->refresh());
```

- [ ] **Step 6: Run the test**

Run: `php artisan test --compact --filter=MeetingCalendarWiringTest`
Expected: PASS, 2 tests.

- [ ] **Step 7: Run everything**

Run: `php artisan test --compact`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Actions/Mentorship/BookMeeting.php app/Actions/Mentorship/RescheduleMeeting.php \
        app/Actions/Mentorship/ReviewMeetingReschedule.php \
        tests/Feature/Google/MeetingCalendarWiringTest.php
git commit -m "Sync meetings to Google when booked, moved or accepted"
```

---

### Task 8: The mentor-facing connect prompt

**Files:**
- Modify: `app/Http/Controllers/Mentor/AvailabilityController.php`
- Modify: `resources/js/pages/mentor/Availability.svelte`
- Test: `tests/Feature/Google/AvailabilityConnectPromptTest.php`

**Interfaces:**
- Consumes: `User::hasCalendarConnected()` (Task 1), routes from Task 3.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Google/AvailabilityConnectPromptTest.php`:

```php
<?php

use App\Models\GoogleAccount;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an unconnected mentor is told their slots are not bookable', function () {
    $mentor = User::factory()->mentor()->approved()->create();

    $this->actingAs($mentor)->get('/mentor/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('calendarConnected', false));
});

test('a connected mentor sees the connected state', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    GoogleAccount::factory()->for($mentor)->create();

    $this->actingAs($mentor)->get('/mentor/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendarConnected', true)
            ->where('calendarEmail', $mentor->googleAccount->email));
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --compact --filter=AvailabilityConnectPromptTest`
Expected: FAIL, property `calendarConnected` does not exist.

- [ ] **Step 3: Pass the state to the page**

In `app/Http/Controllers/Mentor/AvailabilityController.php`, add these two keys to the existing `Inertia::render('mentor/Availability', [...])` array:

```php
            'calendarConnected' => $request->user()->hasCalendarConnected(),
            'calendarEmail' => $request->user()->googleAccount?->email,
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact --filter=AvailabilityConnectPromptTest`
Expected: PASS, 2 tests.

- [ ] **Step 5: Add the prompt to the page**

In `resources/js/pages/mentor/Availability.svelte`, add to the props destructure:

```ts
    let {
        slots = [],
        calendarConnected = false,
        calendarEmail = null,
    }: {
        slots: Slot[];
        calendarConnected?: boolean;
        calendarEmail?: string | null;
    } = $props();
```

(Keep any existing props already in that destructure; add only the two new ones.)

Immediately inside the page's main container, above the slot list:

```svelte
{#if !calendarConnected}
    <div class="mb-6 rounded-xl border border-accent/40 bg-accent-soft p-5">
        <p class="text-sm font-semibold text-ink">
            Connect Google Calendar to take bookings
        </p>
        <p class="mt-1 max-w-[65ch] text-sm text-muted">
            Your hours below are saved, but nobody can book them until Tolfund
            can put the meeting on your calendar. Connecting also gives every
            call its own Google Meet link.
        </p>
        <a
            href="/mentor/google/connect"
            data-test="connect-google"
            class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-on-accent transition-colors hover:bg-accent-strong focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:outline-none"
        >
            Connect Google Calendar
        </a>
    </div>
{:else}
    <div class="mb-6 flex items-center justify-between rounded-xl border border-line bg-panel/40 px-5 py-4">
        <p class="text-sm text-muted">
            Calendar connected as <span class="text-ink">{calendarEmail}</span>
        </p>
        <button
            type="button"
            data-test="disconnect-google"
            onclick={() => router.delete('/mentor/google', { preserveScroll: true })}
            class="rounded-md px-2.5 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-elevated hover:text-ink focus-visible:ring-2 focus-visible:ring-accent/60 focus-visible:outline-none"
        >
            Disconnect
        </button>
    </div>
{/if}
```

Ensure `import { router } from '@inertiajs/svelte';` is present at the top of the script block.

- [ ] **Step 6: Verify the frontend**

```bash
npx prettier --write resources/js/pages/mentor/Availability.svelte
npx eslint resources/js/pages/mentor/Availability.svelte
npm run types:check
npm run build
```

Expected: eslint prints nothing, types report `0 ERRORS`, build succeeds.

- [ ] **Step 7: Run everything**

Run: `php artisan test --compact`
Expected: all PASS.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Mentor/AvailabilityController.php \
        resources/js/pages/mentor/Availability.svelte \
        tests/Feature/Google/AvailabilityConnectPromptTest.php
git commit -m "Prompt mentors to connect Google before their hours are bookable"
```

---

### Task 9: Operational readiness notes

**Files:**
- Modify: `docs/Tolfund_Laravel_Svelte_Technical_Architecture_and_Developer_Guide.md`

- [ ] **Step 1: Document the setup and the cap**

Add a subsection to the guide, anchored wherever integrations or configuration are discussed:

```markdown
### Google Calendar

Mentors must connect Google before their availability is bookable. Meetings are
pushed to the mentor's calendar by a queued job; the entrepreneur is invited by
Google and connects nothing.

Setup:

1. Create two Google Cloud projects, one for local/staging and one for production.
2. Enable the Google Calendar API on each.
3. Create an OAuth client (Web application) with redirect URI
   `<APP_URL>/mentor/google/callback`.
4. Set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` and `GOOGLE_REDIRECT_URI`.
5. Request only the `calendar.events` scope.

The scope is classed as sensitive, so production requires OAuth verification
(privacy policy, verified domain, demo video). Until verified the project is
limited to **100 users for its lifetime and the cap cannot be reset**. Because
connecting is mandatory for mentors, that cap is a hard ceiling on mentor
onboarding: start verification well before approaching it. In testing mode
refresh tokens expire after 7 days, so connections break weekly until the app
is published.
```

- [ ] **Step 2: Commit**

```bash
git add docs/Tolfund_Laravel_Svelte_Technical_Architecture_and_Developer_Guide.md
git commit -m "Document Google Calendar setup and the unverified user cap"
```
