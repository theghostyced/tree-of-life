# Meeting Rescheduling — Design

**Date:** 2026-08-01
**Status:** Approved

## Goal

Let a mentor or an entrepreneur move a confirmed meeting, and tell the other
party it happened. Rescheduling is currently half-built: the review side exists,
but nothing can create a request.

## What already exists

- `meeting_reschedules` table with `requested_by_user_id`, `reason`, previous
  and new times, and review stamps. It is already role-agnostic.
- `MeetingReschedule` model and `RescheduleStatus` (pending / accepted / declined).
- `ReviewMeetingReschedule` — accepting applies the new times to the meeting,
  declining leaves it untouched; both stamp reviewer and time.
- `MeetingReschedulePolicy::review()` — mentor-only reviewer, pending request,
  confirmed meeting, never your own request.
- Mentor dashboard accept/decline card, and the `mentor.reschedules.accept` and
  `.decline` routes.
- `MeetingRescheduleReviewed` notification on `database`, `broadcast` and `mail`.

Rows are only ever produced by the factory and seeder today.

## What is missing

1. No way to create a request: no action, route, or UI for either role.
2. No notification when a reschedule is requested, only when one is reviewed.
3. No entrepreneur-facing reschedule affordance.

## Decisions

**Asymmetric flow.** The mentor owns the calendar, so a mentor reschedule
applies immediately and notifies the entrepreneur. An entrepreneur reschedule
is a proposal the mentor accepts or declines. The existing mentor-only review
policy is therefore correct as written, because only entrepreneurs create
pending requests.

*Consequence, accepted:* an entrepreneur has no veto over a mentor's move
beyond proposing a move back.

**New times come from published availability.** The requester picks any free
upcoming occurrence across that mentor's slots, validated with
`BookMeeting::freeOccurrences()`. A free-form picker would let someone propose
a time the mentor never offered and would make published availability stop
being the source of truth.

**Every time change is recorded.** Both paths write a `meeting_reschedules`
row and post a system message to the pairing's chat, matching how `BookMeeting`
announces a booking. A mentor-direct move is stored as already-accepted, with
the mentor as both requester and reviewer.

**No notice cutoff.** Any confirmed meeting that has not started can be moved.
A minimum-notice rule can be added later as a guard clause in the policy.

## Components

### `App\Actions\Mentorship\RescheduleMeeting`

```
handle(Meeting $meeting, User $actor, MentorAvailabilitySlot $slot,
       CarbonInterface $newStart, ?string $reason): MeetingReschedule
```

One entry point, two paths:

- **Actor is the mentor** — apply the new times, write an accepted row, post the
  chat message, notify the entrepreneur.
- **Actor is the entrepreneur** — write a pending row and notify the mentor. The
  meeting is untouched until the mentor accepts.

Single action because both paths share occurrence validation, previous-time
capture, and row creation. The proposed time is validated against
`BookMeeting::freeOccurrences($slot, $pairing)` in both cases.

### Notifications

Both on `['database', 'broadcast', 'mail']`, matching `MeetingRescheduleReviewed`:

- `MeetingRescheduleRequested` → mentor, when an entrepreneur proposes.
- `MeetingRescheduled` → entrepreneur, when the mentor moves it directly.

### Policy

`MeetingPolicy::reschedule(User $user, Meeting $meeting)` — the actor is on the
meeting's pairing, the pairing is active, the meeting is `Confirmed` and starts
in the future, and an entrepreneur may not stack a second pending request on the
same meeting.

### Routes

- `POST entrepreneur/meetings/{meeting}/reschedule`
- `POST mentor/meetings/{meeting}/reschedule`

Existing accept and decline routes are unchanged.

### UI

A "Reschedule" action on upcoming meetings for both roles, reusing
`BookCallCalendar` scoped to that pairing, plus a reason field: required for an
entrepreneur's proposal, optional for a mentor's move, since the mentor's
counterparty gets a notification rather than a vote. The entrepreneur's meeting
shows a "Reschedule requested" state while one is pending.

### Chat

`PostSystemMessage` posts `🔁 Call moved to {when}` whenever times actually
change: on a mentor-direct move and on mentor acceptance. Acceptance does not
post today, so `ReviewMeetingReschedule` gains that call. Kept defensive, as
`BookMeeting` is, so a missing conversation never fails a reschedule.

## Failure handling

- Proposed occurrence already taken → `422`, matching booking.
- Concurrent review → `lockForUpdate`, as `ReviewMeetingReschedule` already does.
- Past, cancelled, or not-your-meeting → `403` from the policy.
- Second pending request from an entrepreneur → `403`.

## Testing

- Entrepreneur proposal: pending row created, mentor notified, meeting unchanged.
- Mentor move: meeting times updated, accepted row written, entrepreneur
  notified, chat message posted.
- Mentor acceptance: existing coverage, extended for the chat message.
- Policy: non-participant, past meeting, cancelled meeting, duplicate pending
  request.
- Notifications: assert the mail channel, following `RescheduleNotificationsTest`.
- A proposed time that is not a free occurrence is rejected.
