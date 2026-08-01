# Admin Meetings Page — Design

**Date:** 2026-08-01
**Status:** Approved, pending implementation plan

## Goal

Give admins one place to see every meeting booked across all pairings, and to
drill into any single meeting for its full story.

`PRODUCT.md:11` frames the admin role as keeping "an eye on whether meetings are
happening and reports are being captured". This page is that surface.

`AdminLayout.svelte:29` already carries a `Meetings` nav entry pointing at
`/admin/meetings`, without the `enabled: true` flag its siblings have. The slot
was planned; only the route is missing.

## Decisions

| Question | Decision |
| --- | --- |
| Admin capability | Read-only, with a per-meeting detail page. No cancel, no edit. |
| List organisation | Grouped into Upcoming and Past. |
| Volume | Upcoming loads whole; Past is paginated server-side, 25/page. |
| Initiator | Record it — add `booked_by_user_id` rather than inferring it. |
| Mapping code | Extract a shared presenter instead of a third `mapMeeting()` copy. |

## Non-goals

- No admin mutation of meetings. The meeting state machine
  (`confirmed → completed | cancelled`) stays owned by mentor and entrepreneur.
- No report browsing. `AdminLayout` has a separate planned `Reports` page; this
  page shows only whether a report exists, plus its summary on the detail view.
- No changes to the mentor or entrepreneur meeting pages' behaviour.

## Data

`meetings` has no record of who booked. `BookMeeting::handle()` sets
`pairing_id`, times, and status, but no actor. Today only
`entrepreneur.meetings.store` creates meetings — mentors can view, report, and
review reschedules — so the initiator is always the entrepreneur, implicitly.

Migration `add_booked_by_user_id_to_meetings`:

```php
$table->foreignId('booked_by_user_id')->nullable()->after('pairing_id')
      ->constrained('users')->nullOnDelete();
```

- Backfill existing rows to the pairing's `entrepreneur_user_id`.
- `BookMeeting::handle()` sets `'booked_by_user_id' => $pairing->entrepreneur_user_id`.
- Nullable so the backfill cannot fail on orphaned rows, and so
  `nullOnDelete()` is representable.

Reschedules already record attribution (`requested_by_user_id`,
`reviewed_by_user_id`); no change needed there.

## Routes and authorization

Inside the existing `admin` prefix + `role:admin` group in `routes/web.php`:

```php
Route::get('/meetings', [AdminMeetingController::class, 'index'])->name('meetings.index');
Route::get('/meetings/{meeting}', [AdminMeetingController::class, 'show'])->name('meetings.show');
```

`MeetingPolicy` currently defines only `submitReport`. It gains:

- `viewAny(User $user): bool` — admin only
- `view(User $user, Meeting $meeting): bool` — admin only

Authorized with `Gate::authorize`, matching `Admin\UserController`. The
`role:admin` middleware and the policy are deliberately redundant: middleware
gates the section, the policy gates the record.

## Shared presenter

`Mentor\MeetingController:37` and `Entrepreneur\MeetingController:124` each hold
a private `mapMeeting()`. They are identical except `counterpartName`, which
resolves to the pairing's mentor in one and its entrepreneur in the other. A
third copy for admin would triplicate the shape.

Extract `App\Data\MeetingSummary`, following the existing presenter convention
(`App\Data\MentorCard` — an `Arrayable` with a static `forX()` factory):

- `MeetingSummary::forMeeting(Meeting $meeting): self` carries the fields all
  three roles share: `id, startsAt, endsAt, durationMinutes, timezone,
  sessionType, location, meetingLink, agenda, status`.
- Each controller spreads `->toArray()` and adds its own naming: mentor and
  entrepreneur add `counterpartName` and their report fields; admin adds
  `mentorName`, `entrepreneurName`, `bookedByName`, `hasReport`.

Timestamps stay epoch-milliseconds (`getTimestampMs()`), matching every other
Inertia payload in the app.

This is a behaviour-preserving refactor. The existing mentor and entrepreneur
feature tests are the guard rail and must pass unchanged.

## Index payload

```
upcoming: MeetingRow[]           // status=confirmed AND starts_at > now, ascending, all
past:     Paginator<MeetingRow>  // everything else, starts_at descending, 25/page
filters:  { search: string|null, status: string|null }
```

`MeetingRow`: `id, startsAt, endsAt, durationMinutes, timezone, mentorName,
entrepreneurName, bookedByName, sessionType, status, hasReport`.

Query notes:

- Filters apply in SQL, not in Svelte. `search` matches mentor **or**
  entrepreneur name through the pairing; `status` maps to `MeetingStatus`.
- Filters apply to Past only. Upcoming is small and always shown whole, so an
  admin never loses sight of what is scheduled.
- Eager-load `pairing.mentor:id,name`, `pairing.entrepreneur:id,name`,
  `bookedBy:id,name`; use `withExists('report')` so no report bodies are pulled
  into a list payload.
- `filters` is echoed back so the UI can render current state and build
  pagination links that preserve them.

The existing `meetings` indexes (`['status','starts_at']`) already serve both
the upcoming and past orderings.

## Detail payload

Everything in `MeetingRow`, plus:

- `agenda`, `location`, `meetingLink`
- Timeline: `confirmedAt`, `completedAt`, `cancelledAt`, and `cancelledByName`
- `bookedByName` and the pairing both participants belong to
- Reschedule history: for each, `requestedByName`, `reviewedByName`, `status`,
  `reason`, `previousStartsAt`, `newStartsAt`
- Report presence: `summary`, `submittedByName`, `submittedAt` — or an explicit
  "not captured" state

## Frontend

Mirrors the Users feature layout:

```
resources/js/pages/admin/meetings/Index.svelte
resources/js/pages/admin/meetings/Show.svelte
resources/js/components/meetings/types.ts
resources/js/components/meetings/columns.ts
resources/js/components/meetings/meeting-status.svelte
```

- `AdminLayout.svelte:29` gains `enabled: true`.
- Upcoming renders as cards — few, scannable, status-first per `DESIGN.md:112`.
- Past uses the existing `DataTable` with server-driven pagination; search and
  status filter round-trip through Inertia rather than filtering client-side.
- `meeting-status.svelte` pairs a dot with a label so state never relies on
  colour alone, matching `users/account-status.svelte`.
- Empty states teach the next action per `DESIGN.md:226` — "No meetings
  scheduled yet", not a bare "Nothing here."
- Row click navigates to the detail page.

## Testing

### Feature tests (Pest, `tests/Feature/Admin/MeetingsTest.php`)

- Admin sees meetings from pairings they are not part of.
- Mentor and entrepreneur receive 403 on both index and show.
- Guests are redirected to login.
- Upcoming/past partition is correct around `now()`: a confirmed future meeting
  is upcoming; confirmed-past, completed, and cancelled are all past.
- Status filter narrows Past correctly.
- Name search matches on mentor name and on entrepreneur name.
- Pagination returns 25 per page and preserves active filters on page 2.
- Detail exposes reschedule history and report state.
- No N+1: assert query count is constant as the meeting count grows.

### Migration test

- Backfill sets `booked_by_user_id` to the pairing's entrepreneur for rows
  created before the migration.
- `BookMeeting` sets it on new bookings.

### End-to-end tests (Pest 4 browser plugin)

The project has no browser test tooling today — no Playwright, Cypress, or
Vitest, and `package.json` defines no `test` script. Pest 4.7.4 is installed and
Pest 4 ships browser testing built on Playwright, so e2e lives in the same suite
with the same factories and `actingAs`, rather than adding a second runner.

Setup:

```bash
composer require pestphp/pest-plugin-browser --dev
npx playwright install
```

`tests/Browser/AdminMeetingsTest.php`:

- Admin logs in, opens `/admin/meetings` from the nav, and sees a seeded meeting
  in Upcoming.
- Upcoming and Past groups render with the right meetings in each.
- Typing in search filters the Past table and the result survives the round trip.
- Changing the status filter narrows Past; both filters persist across pagination.
- Clicking a row opens the detail page, showing booked-by, duration, agenda, and
  reschedule history.
- A completed meeting with no report shows the "not captured" state.
- A mentor logging in does not see the Meetings link and is refused at
  `/admin/meetings`.

Browser tests cover the wiring — nav, round-trip filters, navigation — that
feature tests cannot. Assertions on business rules stay in the feature tests,
which are faster and more precise.

## Risks

- **Presenter refactor touches working pages.** Mitigated by keeping it
  behaviour-preserving and requiring the existing mentor and entrepreneur tests
  to pass untouched.
- **e2e adds tooling and CI time.** Playwright binaries are a few hundred MB and
  browser tests need a running app. Worth confirming CI can host them before
  the suite grows.
- **Backfill assumes entrepreneurs are the only bookers.** True today, enforced
  by routes. If mentor-initiated booking lands later, historical rows stay
  attributed to the entrepreneur — correct for the data we have.

## Out of scope, worth noting

`CLAUDE.md:455` lists `npm run test`, which does not exist in `package.json`.
Worth correcting when the e2e suite lands so the documented commands are real.
