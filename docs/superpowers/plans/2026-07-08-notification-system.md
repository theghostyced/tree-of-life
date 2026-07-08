# Notification System — Implementation Plan

Executes the spec `docs/superpowers/specs/2026-07-08-notification-system-design.md`. Tasks are ordered; each ends with tests and a commit.

## Global constraints
- No hyphens in user facing copy (titles, bodies, subjects, buttons, empty states).
- Every Notification implements `ShouldQueue` (Redis).
- Reuse the chat realtime setup: broadcast on the notifiable private channel.
- Emails render through one professional Blade layout; light themed; optional hero illustration by absolute URL.
- Reuse existing sage tokens and shadcn components (bell panel).

## Tasks

### Task 1 — Foundation (backend)
- Migration: `notifications` table (standard Laravel).
- `App\Support\Notifications\NotificationData` helper (title, body, category, actions[], illustration?) → array payload, shared by every notification's `toArray`.
- `NotificationController`: `index` (paginated JSON, own notifications only), `read(id)`, `readAll`. Routes in the auth group.
- Shared Inertia prop `notifications = { unreadCount, recent }` (recent 15) in `HandleInertiaRequests`.
- Tests: endpoints (own only, mark read, mark all, pagination), shared prop shape.

### Task 2 — Bell UI (frontend)
- `NotificationBell.svelte` in the navbar: unread badge, responsive panel (dropdown mobile, right slide over sidebar desktop), day grouping, item with icon/title/body/relative time/unread dot/action buttons, mark one read on click, mark all read.
- Seed from the shared prop; wire realtime listener (Task 9 completes broadcast).
- Replace the static "Notifications" bell placeholder in `AppNavbar.svelte`.

### Task 3 — Meeting booked notifications
- `MeetingBooked` (to mentor) and `MeetingBookedConfirmation` (to entrepreneur), fired from `BookMeeting::handle`. Channels: database + mail (+ broadcast).
- Tests: `Notification::fake`, right recipients + channels + payload, no hyphens.

### Task 4 — Report notifications
- `ReportSubmitted` (all admins, in app only) + `ReportAvailable` (entrepreneur, in app only), fired from `SubmitMeetingReport`.
- Tests as above.

### Task 5 — Cancel / reschedule notifications
- Notify the other party from the cancel and reschedule flows. Channels: database + mail (+ broadcast). Tests.

### Task 6 — Email base layout + mailables
- `resources/views/emails/layout.blade.php` (professional, light, optional hero illustration via absolute URL, one CTA).
- `toMail` on every emailed notification (booked x2, reminders, cancel/reschedule, report due, onboarding). Subjects + bodies hyphen free.
- Tests: mail renders, contains CTA url, illustration url absolute.

### Task 7 — Onboarding completion
- Migration: `users.onboarding_completed_at` nullable.
- Detect the incomplete → complete transition in `ProfileController@update` and `DocumentController@store`; set the timestamp once; fire `OnboardingCompleted` (self: database + mail) and an admin in app notice.
- Tests: fires once, idempotent, correct channels.

### Task 8 — Scheduler reminders
- Migration: `meeting_notification_dispatches (id, meeting_id, kind, dispatched_at)` unique `(meeting_id, kind)`.
- Command `notifications:send-meeting-reminders`: scan confirmed meetings for `reminder_day` (24h before), `reminder_hour` (1h before), `report_due` (1h after start, no report yet); dispatch via the ledger idempotently. Schedule every 5 minutes in `routes/console.php`.
- `MeetingReminder` (day: mail only; hour: mail + database + broadcast) and `ReportDue` (database + mail + broadcast).
- Tests: each threshold triggers the right kind once; second scan does not double send.

### Task 9 — Realtime + history
- Confirm broadcast channel wiring; frontend echo listener prepends new notifications and bumps the badge; mark read clears it.
- History pagination (load older on scroll) via the `index` endpoint.
- Live browser verification.
