# Notification System — Design Spec

**Date:** 2026-07-08
**Status:** For review

## Goal

Give Tolfund a notification system across in app and email, so admins, entrepreneurs, and mentors are told about the things that matter (bookings, meeting reminders, reports, onboarding), without flooding anyone. In app is always present; email is added only where it earns the inbox space.

## Copy rules

- No hyphens in any user facing copy. Reword compounds ("reminder a day before", "follow up", "one hour before").
- Warm, professional, concrete. Lead with the thing that happened and give one clear next action.

---

## 1. Events and channel matrix

Every event creates an in app notification. Email is added per the matrix below.

| Event | Recipient | In app | Email | When |
|---|---|:--:|:--:|---|
| Session booked (their time was booked) | Mentor | yes | yes | immediate |
| Session booked (confirmation) | Entrepreneur | yes | yes | immediate |
| Upcoming meeting, a day before | Both parties | no | yes | 24h before start |
| Upcoming meeting, one hour before | Both parties | yes | yes | 1h before start |
| Meeting cancelled | Other party | yes | yes | immediate |
| Meeting rescheduled | Other party | yes | yes | immediate |
| Report due (submit your report) | Mentor | yes | yes | 1h after start, once |
| Report submitted | All admins | yes | no | immediate |
| Report available (mentor filed it) | Entrepreneur | yes | no | immediate |
| Onboarding completed | The user (self) | yes | yes | immediate |
| User completed onboarding | All admins | yes | no | immediate |

**Actions.** Every in app item carries one or more action buttons (deep links). Emails carry a matching primary call to action button. Examples: View meeting, Submit report, Open chat, Review report, Finish setup.

Notes on the weighting:
- Report submitted and report available are in app only. Admins and entrepreneurs see these where they already work (dashboard, meetings list); emailing every one would be noise.
- Reminders split exactly as requested: a day before is email only, one hour before is email plus in app.
- Cancel and reschedule and the admin onboarding notice are proposed additions; easy to cut.

---

## 2. Architecture

### Laravel Notifications (the spine)
- One notification class per event under `app/Notifications/`.
- `via()` returns the channels for that event: `['database']`, `['database','mail']`, or `['database','mail','broadcast']`.
- `toArray()` produces the in app payload; `toMail()` builds the email; `toBroadcast()` pushes the realtime bell update.
- All notifications implement `ShouldQueue` so nothing blocks a request (Redis queue).
- `User` is already `Notifiable`. Add the standard `notifications` table migration.

### In app payload shape (`data` JSON)
```
{
  category: 'meeting' | 'report' | 'onboarding',
  title: string,          // hyphen free
  body: string,           // hyphen free
  actions: [{ label, url }],
  illustration?: string   // optional icon/illustration key
}
```

### Event driven notifications (immediate)
Fire from the existing Action classes and observers, queued:
- `BookMeeting` (already posts a system chat message) also notifies the mentor and entrepreneur.
- `SubmitMeetingReport` notifies all admins and the entrepreneur.
- Cancel and reschedule flows notify the other party.
- Onboarding completion: add an `onboarding_completed_at` timestamp on `users`, set once when `OnboardingProgress` first becomes complete (detected in `ProfileController@update` and `DocumentController@store`). Setting it fires the completion notification (self email plus in app) and the admin in app notice. The timestamp guarantees it fires once.

### Time based notifications (scheduler)
A command `notifications:send-meeting-reminders` runs every 5 minutes (scheduled in `routes/console.php`). It scans confirmed meetings crossing each threshold and dispatches:
- a day before (24h before start),
- one hour before start,
- report due (1h after start).

**Idempotency.** A ledger table `meeting_notification_dispatches (meeting_id, kind, dispatched_at)` with a unique index on `(meeting_id, kind)`. Before sending, insert the row; a duplicate key means already sent, so skip. `kind` is `reminder_day`, `reminder_hour`, `report_due`. This makes the every 5 minutes scan safe.

### Realtime bell
Use Laravel's broadcast notification channel (the notifiable's private `App.Models.User.{id}` channel, already registered from the scaffold). The frontend echo client listens for the broadcast notification event and updates the bell badge and panel live, reusing the chat realtime setup.

---

## 3. In app UX

### Shared data
Add a `notifications` block to the Inertia shared props (beside `auth.unreadMessages`): `{ unreadCount, recent: Notification[] }` (recent ~15). Present on every page so the bell is instant. Realtime events prepend and increment.

### The bell
- Navbar bell with an unread count badge.
- Click behaviour is responsive: a dropdown panel on mobile, a slide over sidebar (right drawer) on desktop.
- List newest first, grouped by day (Today, Yesterday, date). Each item: category icon, title, body, relative time, unread dot, and action buttons.
- Interactions: click an item marks it read and follows its primary action; a Mark all read control; unread items visually distinct.
- History scrolls in the panel; older pages load on scroll (paginated endpoint), same pattern as the chat load older.

### Endpoints
- `GET /notifications` (paginated, JSON) for older pages.
- `POST /notifications/{id}/read`, `POST /notifications/read-all`.

---

## 4. Email

### Base layout
One reusable Blade layout `resources/views/emails/layout.blade.php`, professional and on brand:
- Preheader text, header with the Tolfund mark, optional hero illustration, title, body, one primary call to action button, footer with a plain text fallback link and a short sign off.
- Renders well in light inboxes (email is not dark themed) while keeping the sage accent on the button and header rule.
- Table based, inline styles, tested against common clients (safe, conservative HTML).

### Illustrations
The layout accepts an optional illustration as an absolute public URL passed from the app (for example `asset('images/emails/booked.png')`, resolved to a full URL via `config('app.url')`). Images must be absolute and publicly reachable so email clients can load them. Reuse the app illustrations where they fit; keep each email to at most one image.

### Mailables
Each notification's `toMail()` returns a Mailable that renders the base layout with its content and optional illustration URL. Subjects and bodies follow the copy rules.

---

## 5. Data model summary

New:
- `notifications` (standard Laravel database notifications table).
- `meeting_notification_dispatches (id, meeting_id, kind, dispatched_at)`, unique `(meeting_id, kind)`.
- `users.onboarding_completed_at` (nullable timestamp).

---

## 6. Non goals (v1)

- No per user notification preferences or unsubscribe management (all recipients get the matrix as defined). Can come later.
- No digest email (each email is immediate). You scratched the digest.
- No SMS or web push.
- No escalating report reminders (one reminder only).

---

## 7. Testing

- Unit and feature tests per notification: correct recipients, correct channels (`Notification::fake()` assertions), payload shape, and copy has no hyphens.
- Scheduler command: meetings at each threshold trigger the right kind exactly once; running the scan twice does not double send (ledger).
- Onboarding transition fires once (idempotent via the timestamp).
- Endpoints: mark read, mark all read, pagination, authorization (a user only sees their own).
- Realtime: broadcast is dispatched on the notifiable channel.

---

## 8. Rollout order (informs the plan)

1. Foundation: `notifications` table, base notification plumbing, shared prop, bell UI shell.
2. Event notifications (booked, report submitted, report available, cancel/reschedule).
3. Email base layout plus mailables for the emailed events.
4. Onboarding completion (column, transition detection, notification, email).
5. Scheduler: reminder command, ledger, the three time based kinds.
6. Realtime bell wiring and history pagination.
