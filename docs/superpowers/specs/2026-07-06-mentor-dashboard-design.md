# Mentorship Core Schema + Mentor Dashboard — Design

**Date:** 2026-07-06
**Status:** Approved

## Goal

Give mentors a real dashboard. Today `mentor/Dashboard.svelte` shows only
onboarding progress; the architecture guide (§10.8, §10.11–10.14) describes a
mentorship domain — pairings, availability, meetings, reschedules, per-meeting
reports — whose schema it explicitly leaves "to be designed." This spec designs
that core schema and builds the actions-first mentor dashboard on top of it.

The legacy `tolfund-api` repo is the field-list reference, not the template:
all money and funding artifacts (`funding_award_id`, `milestone_id`,
`MentorPairingPayment` amounts, subscriptions) are dropped per the product
reframe, and reports become per-meeting records instead of loose file uploads.

## Scope

**In:** the five mentorship tables + enums + models + factories + demo seeder;
the dashboard payload and page; exactly two write actions (review a reschedule,
submit a meeting report); a read-only pairings section on the admin user
detail page (mentors show their entrepreneurs, entrepreneurs show their
mentor).

**Out (later specs):** entrepreneur booking flow, availability management UI,
pairing pages, the in-app live chat, the entrepreneur mentor-selection flow
(entrepreneurs browse approved mentors and choose one; admins do not pair),
Google Calendar/Meet integration, notifications and reminders.

**Forward compatibility for known upcoming features:**
- **Google Meet bookings.** Booking (later spec) will create a Google Meet
  meeting on both participants' calendars. `meetings.meeting_link` is
  documented as the Meet URL and `meetings.google_event_id` (nullable string)
  exists from day one so no migration is needed when the integration lands.
  The dashboard's Join action simply opens `meeting_link`.
- **In-app live chat.** Each pairing page will carry a live chat
  (Reverb/Echo). `pairings` is the anchor entity a future
  `pairing_messages` table will reference; nothing else is required now.

## Data model

All tables new; all status columns are string-backed PHP enums following
`InvitationStatus` conventions.

### `pairings`
| column | type |
| --- | --- |
| `id` | bigint PK |
| `entrepreneur_user_id` | FK users, cascadeOnDelete |
| `mentor_user_id` | FK users, cascadeOnDelete |
| `status` | enum `PairingStatus`: `active` / `ended`, default active |
| `ended_at` | timestamp nullable |
| timestamps | |

Pairings are created by the entrepreneur selecting a mentor (guide §10.11);
no admin-provenance column is needed since `entrepreneur_user_id` is the
selector. Rule (revised during implementation, superseding the original
one-active-pairing rule): an entrepreneur may hold **several active
pairings at once**; the selection flow refuses only a duplicate of the same
still-active pair. The `(entrepreneur_user_id, status)` index supports the
dashboard and admin queries.

### `mentor_availability_slots`
| column | type |
| --- | --- |
| `mentor_user_id` | FK users, cascadeOnDelete |
| `day_of_week` | unsigned tinyint 0–6 (0 = Monday) |
| `start_time` / `end_time` | time |
| `timezone` | string |
| `session_type` | string (`virtual` / `in_person`) |
| `location` | string nullable |
| `meeting_link` | string nullable |
| `is_active` | boolean default true |
| timestamps | |

### `meetings`
| column | type |
| --- | --- |
| `pairing_id` | FK pairings, cascadeOnDelete |
| `mentor_availability_slot_id` | FK nullable, nullOnDelete |
| `starts_at` / `ends_at` | timestamp |
| `timezone` | string |
| `session_type` | string |
| `location` | string nullable |
| `meeting_link` | string nullable — the Google Meet URL once booking integration lands |
| `google_event_id` | string nullable — calendar event reference, unused until the booking spec |
| `agenda` | text nullable |
| `status` | enum `MeetingStatus`: `confirmed` / `completed` / `cancelled`, default confirmed |
| `outcome_summary` | text nullable |
| `confirmed_at` / `completed_at` / `cancelled_at` | timestamps nullable |
| `cancelled_by_user_id` | FK users nullable, nullOnDelete |
| timestamps | |

Mentor and entrepreneur are reached through the pairing; meetings carry no
duplicate user FKs. State machine: `confirmed → completed` (only after
`starts_at`), `confirmed → cancelled`.

### `meeting_reschedules`
| column | type |
| --- | --- |
| `meeting_id` | FK meetings, cascadeOnDelete |
| `requested_by_user_id` | FK users, cascadeOnDelete |
| `status` | enum `RescheduleStatus`: `pending` / `accepted` / `declined`, default pending |
| `reason` | text nullable |
| `previous_starts_at` / `previous_ends_at` | timestamps |
| `new_starts_at` / `new_ends_at` | timestamps |
| `reviewed_by_user_id` | FK users nullable, nullOnDelete |
| `reviewed_at` | timestamp nullable |
| timestamps | |

Accepting a pending reschedule updates the meeting's times to the proposed
ones and stamps review fields; declining only stamps review fields. Both only
while the meeting is `confirmed`.

### `meeting_reports`
| column | type |
| --- | --- |
| `meeting_id` | FK meetings, cascadeOnDelete, **unique** — one report per meeting |
| `submitted_by_user_id` | FK users, cascadeOnDelete |
| `summary` | text |
| `submitted_at` | timestamp |
| timestamps | |

### Models, factories, seeder

Eloquent models with relationships mirroring the FKs (`Pairing::mentor()`,
`Meeting::pairing()`, `Meeting::report()`, `Meeting::reschedules()`, …),
factories for realistic states (upcoming, completed-with-report,
completed-without-report, pending-reschedule), and a `MentorshipDemoSeeder`
that gives the seeded mentor a believable week so the dashboard renders with
life locally.

## Dashboard payload

`Mentor\DashboardController@index` shares shaped arrays (never raw models),
keeping the existing `onboarding` prop untouched:

- `attention`: `reschedules` (pending requests on the mentor's meetings that
  the mentor did not request themselves: id, mentee name, previous/new times,
  reason) and `missingReports` (completed meetings without a report: meeting
  id, mentee name, ended-at).
- `meetings`: confirmed meetings from now through the next 7 days (id, mentee
  name, starts/ends, session type, location, meeting link).
- `mentees`: active pairings (pairing id, entrepreneur name, company, last
  completed meeting date, next confirmed meeting date).
- `availability`: `activeCount` plus the weekly slot list (day, start, end,
  session type).
- `stats`: `menteeCount`, `completedCount`, `hoursMentored` (sum of completed
  meeting durations, rounded to halves).

## Write actions (the only mutations in this spec)

- `POST /mentor/reschedules/{reschedule}/accept` and
  `POST /mentor/reschedules/{reschedule}/decline` — policy: the authenticated
  mentor owns the meeting's pairing, the reschedule is pending, and they are
  not the requester. Accept applies the new times.
- `POST /mentor/meetings/{meeting}/report` — policy: mentor owns the pairing,
  meeting is `completed`, no report exists. Body: `summary` (required string,
  max 5000). Creates the `meeting_reports` row.

Both live in a new `Mentor\MeetingController` (or split
`RescheduleController`/`ReportController` if it reads cleaner at
implementation), with actions `ReviewMeetingReschedule` and
`SubmitMeetingReport` doing the state changes, form requests validating, and
policies on `Meeting`/`MeetingReschedule` enforcing ownership.

## Page (mentor/Dashboard.svelte + components/mentorship/*)

Keeps `MentorLayout`; the onboarding card stays while onboarding is
incomplete. Sections in priority order, per the actions-first decision:

1. **Needs your attention** — reschedule cards (mentee, old time struck
   through, proposed time, reason, Accept/Decline buttons with in-flight
   disabling) and missing-report rows (mentee, meeting date, "Write report"
   opens a slide-over with a summary textarea and submit). Section hidden
   entirely when nothing needs attention.
2. **This week** — the next 7 days of confirmed meetings, each with mentee
   name, day/time in the mentor's timezone, session type, and a Join link
   when `meeting_link` is set. Empty state: "No meetings booked this week."
3. **Your mentees** — active pairings with entrepreneur name, company, last
   and next meeting. Empty state teaches: entrepreneurs choose their mentor
   when they join, and new mentees appear here automatically; no action for
   the mentor to take.
4. **Availability** — quiet strip: active slot count and the weekly slots.
   Empty state notes availability setup is coming and that admins can help
   meanwhile (no dead buttons to a page that does not exist).

Reusable pieces land in `resources/js/components/mentorship/` (reschedule
card, report slide-over, meeting row) per the pages-vs-components rule.
Copy follows the trustworthy-grounded-warm voice, sentence case, no dashes.
All colors from tokens (`positive`/`danger` tones where states need them);
focus rings and disabled states per existing conventions; `animate-fade-in`
entrance only.

## Admin user detail page: pairings section

The admin user detail page (`admin/users/Show.svelte`) gains a **Mentorship**
section between Profile and Documents, fed by a new `pairings` prop from
`Admin\UserController@show`:

- For a **mentor**: their active pairings' entrepreneurs (name, company, since
  date, last completed meeting), each linking to that entrepreneur's admin
  detail page.
- For an **entrepreneur**: their active mentor (same shape, linking to the
  mentor's page). Ended pairings list beneath in a quieter tone with their
  ended date.
- For admins/employees, or when no pairings exist, the section shows a
  teaching empty state ("No mentorship pairings yet. Entrepreneurs choose
  their mentor when they join."); creating pairings stays out of scope for
  this spec.

The section reuses the existing card/dl idiom of the page and the
`components/users` conventions; rows link with the standard focus ring.

## Error handling

- Policy failures render the existing designed 403 page.
- Validation errors surface inline in the report slide-over via Inertia
  form errors.
- Accept/decline and report submission redirect back with a `status` flash
  and the page data refreshes via Inertia; the dashboard shows a toast,
  matching the invitations/users pages.

## Testing

Pest feature tests:
- Dashboard payload: each block shaped correctly for a seeded mentor
  (attention items, week window boundaries, stats math, mentees list).
- Reschedule accept updates meeting times and stamps review fields;
  decline stamps without changing times; both refuse non-pending requests,
  meetings the mentor does not own, and the requester reviewing their own
  request.
- Report submission creates the row; refuses duplicates, non-completed
  meetings, and foreign mentors.
- Entrepreneurs and admins get 403s on all three endpoints.
- Admin user detail: a mentor's page lists their active entrepreneurs, an
  entrepreneur's page lists their mentor, ended pairings appear with ended
  dates, and users without pairings get the empty state.

Browser verification: seeded mentor signs in, sees all four sections
populated, accepts a reschedule, declines one, submits a report, and each
empty state renders for a fresh mentor with no data.
