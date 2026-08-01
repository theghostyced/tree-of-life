# Google Calendar Sync — Design

**Date:** 2026-08-01
**Status:** Approved

## Goal

When a meeting is booked or moved in Tolfund, it appears on the participants'
Google Calendars with a real per-meeting Meet link, without the entrepreneur
having to connect anything.

## What the research settled

- **Workspace is not required.** The Calendar API treats personal Gmail and
  Workspace accounts identically.
- **Only the organiser authenticates.** There is no API key or service-account
  route into a consumer calendar; service accounts with domain-wide delegation
  work only inside a Workspace domain we control. Creating an event with
  `attendees` and `sendUpdates: "all"` makes Google email the invitation, and
  for Gmail users it lands in their calendar with RSVP buttons.
- **Mentors are the natural organiser**, because they already own the
  availability. Entrepreneurs connect nothing.
- **The calendar scope is "sensitive"**, so production use requires OAuth app
  verification. Until verified, the project is capped at **100 users for its
  lifetime**, and that cap cannot be reset.

## Decisions

**Push only.** Bookings and reschedules write events out. Availability continues
to come solely from the slots mentors publish in Tolfund; we do not read their
Google calendar. Accepted consequence: a mentor's private commitments are
invisible to Tolfund, so a slot can be booked over one.

**Connecting is mandatory for mentors.** A mentor with no live Google
connection has no bookable availability. This keeps one code path and
guarantees every meeting syncs. Accepted consequences: mentors whose employer
blocks OAuth cannot participate, and because every mentor must connect, the
100-user unverified cap is a hard ceiling on mentor count until verification
completes.

**Existing slots are hidden, never destroyed.** Slots belonging to an
unconnected mentor stay in the database and stop being offered. Connecting
restores them immediately. Meetings already booked are untouched and still
happen.

**Least-privilege scope.** We request `calendar.events`, not full `calendar`.
We create and update our own events and never read the mentor's calendar.
Reading busy times later would need a re-consent, which is acceptable given
push-only.

**Sync is queued, not inline.** A booking is never rejected because Google is
unreachable. The meeting is created in Tolfund immediately and the calendar
event is pushed by a queued job with retries. `BookMeeting` already treats chat
posting as best-effort for the same reason: an outage in a secondary system
must not take bookings down.

## Components

### `google_accounts` table

One row per user: `user_id` (unique), `google_user_id`, `email`, `access_token`
and `refresh_token` (both encrypted casts), `expires_at`, `scopes`,
`connected_at`, `revoked_at`, timestamps.

A connection is live when `revoked_at` is null and a refresh token is present.

### OAuth flow

Laravel Socialite's Google provider with `access_type=offline` and
`prompt=consent`, so a refresh token is always issued rather than only on first
consent.

- `GET  /mentor/google/connect`  — redirect to Google
- `GET  /mentor/google/callback` — exchange code, upsert `google_accounts`
- `DELETE /mentor/google`        — disconnect, which hides their slots again

### `GoogleCalendarSync`

Three operations against `google/apiclient`:

- `create(Meeting): array{eventId: string, meetLink: ?string}`
- `update(Meeting): void` — patches the existing `google_event_id`
- `cancel(Meeting): void`

Event payload: summary naming both participants, description linking back to
the meeting in Tolfund, start/end with the meeting's timezone, the entrepreneur
as an attendee, `sendUpdates: "all"`, and `conferenceData.createRequest` with
`hangoutsMeet` for a per-meeting Meet link.

### Token lifecycle

A `GoogleToken` service refreshes when `expires_at` is near. On `invalid_grant`
(user revoked access from their Google account settings) it sets `revoked_at`.
Revocation therefore collapses into the existing disconnected state: the same
gate hides their slots, with no extra status to reason about.

### Gating, at two levels

Both are required, because they protect different things:

1. `AvailabilityOptions::forPairing()` returns an empty list for a mentor
   without a live connection, so nothing is **offered**.
2. `BookMeeting` and `RescheduleMeeting` re-check, so nothing can be **forced**
   past the UI by posting a slot id directly.

The mentor Availability page explains why nothing is bookable and offers a
Connect button. Slots render as present but inactive rather than disappearing,
so mentors can see the configuration they will get back.

### Wiring

The three places a meeting's time is created or changed:

- `BookMeeting` — dispatch create; store `google_event_id` and use the returned
  Meet link as `meeting_link`.
- `RescheduleMeeting` (mentor-direct path) — dispatch update.
- `ReviewMeetingReschedule` (on accept) — dispatch update.

Cancellation dispatches cancel.

### Replaces a live bug

Every meeting currently falls back to `config('services.meet.default_link')`, a
single static Meet room shared by the whole platform, so two pairs meeting at
the same time join the same call. A per-event conference link removes that
class of problem.

## Failure handling

- Google unreachable or 5xx → job retries with backoff; the meeting exists in
  Tolfund throughout.
- Permanent failure → the meeting is flagged as unsynced and the mentor is
  notified, rather than failing silently.
- `invalid_grant` → mark revoked; slots hide; mentor is prompted to reconnect.
- A meeting whose `google_event_id` is null when an update is requested falls
  back to create, so a booking that failed to sync self-heals on reschedule.

## Testing

- A faked Google client asserting the outgoing payload: attendee, timezone,
  `sendUpdates`, and the conference create request.
- Slots hidden for an unconnected mentor and restored on connect, asserted at
  both gates (offered list, and direct booking attempt).
- Reschedule patches the existing `google_event_id` rather than creating a
  second event.
- A revoked token degrades the mentor to disconnected.
- The queued job retries and then flags the meeting.
- No test performs real network I/O.

## Operational requirements

- Separate Google Cloud projects for test and production, per Google's
  recommendation.
- OAuth verification before launch: privacy policy, homepage on a verified
  domain, demo video. Days to weeks.
- **The 100-user cap gates mentor onboarding** and cannot be reset, so
  verification must start well before the mentor cohort approaches it.
- In testing mode refresh tokens expire after 7 days, so connections break
  weekly until the app is published.

## Out of scope

- Reading mentors' busy times to hide clashing slots (would need re-consent).
- Entrepreneur-side Google connection.
- Calendar providers other than Google.
- Two-way RSVP sync back into Tolfund.
