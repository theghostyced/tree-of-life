# Tolfund Technical Architecture & Developer Guide

**Target Architecture: Full-Stack Laravel Application with Svelte**

**Document Purpose**  
This document defines the long-term technical architecture for the rebuilt Tolfund platform. It replaces the previous split Laravel API + Next.js frontend approach with a cohesive full-stack Laravel application using Svelte for rich interactive interfaces.

It is intended to guide implementation, onboarding, maintenance, debugging, security review, and future extension. It deliberately prioritizes durability, clarity, correctness, and operational quality over speed of initial delivery.

**Audience**
- Backend and frontend developers
- Product engineers and technical leads
- DevOps / deployment engineers
- QA engineers
- Future maintainers or contractors

**Related Business Reference**
- Tolfund User Journeys, Features, and Process Flows

**Recommended Stack**
- **Application Framework**: Laravel 13, PHP 8.3+
- **Frontend Rendering**: Inertia 3 with Svelte 5 page components, using Laravel controllers and server-side routing
- **Frontend Build Tool**: Vite
- **Frontend Language**: TypeScript
- **Styling**: Tailwind CSS 4 with a small shared design system
- **Authentication**: Laravel session authentication for browser users; Sanctum only for future API/mobile access
- **Authorization**: Laravel Policies, Gates, middleware, and explicit domain checks
- **Real-time**: Laravel Reverb + Laravel Echo + Pusher protocol client
- **Database**: MySQL 8+ recommended
- **Queue**: Laravel queue workers, database or Redis-backed depending on deployment maturity
- **Mail**: Resend via Laravel mail integration
- **Testing**: Pest, Laravel feature tests, Svelte component tests where useful, and browser tests for critical flows
- **Storage**: Laravel filesystem disks with strict separation between public assets and private documents

**Framework Documentation Basis**  
This guide aligns with Laravel 13's official Svelte starter kit structure and Inertia 3's Laravel/Svelte setup model.

---

## Table of Contents

1. [Architecture Decision Summary](#1-architecture-decision-summary)
2. [High-Level Architecture](#2-high-level-architecture)
3. [Project Structure](#3-project-structure)
4. [Dependency and Package Strategy](#4-dependency-and-package-strategy)
5. [Application Layers](#5-application-layers)
6. [Database Schema and Domain Models](#6-database-schema-and-domain-models)
7. [Authentication, Sessions, and Authorization](#7-authentication-sessions-and-authorization)
8. [Frontend Architecture with Svelte](#8-frontend-architecture-with-svelte)
9. [Routing and Controller Design](#9-routing-and-controller-design)
10. [Business Process Architecture](#10-business-process-architecture)
11. [Real-time Notifications and Broadcasting](#11-real-time-notifications-and-broadcasting)
12. [Files, Storage, and Document Access](#12-files-storage-and-document-access)
13. [Email and Notification Delivery](#13-email-and-notification-delivery)
14. [Payments and Financial Controls (out of scope)](#14-payments-and-financial-controls)
15. [Configuration and Environment Variables](#15-configuration-and-environment-variables)
16. [Testing Strategy](#16-testing-strategy)
17. [Development Workflow](#17-development-workflow)
18. [Deployment and Operations](#18-deployment-and-operations)
19. [Security and Data Protection](#19-security-and-data-protection)
20. [Conventions, Gotchas, and Anti-Patterns](#20-conventions-gotchas-and-anti-patterns)
21. [Extensibility Patterns](#21-extensibility-patterns)
22. [Migration from the Previous Split Architecture](#22-migration-from-the-previous-split-architecture)

---

## 1. Architecture Decision Summary

The previous architecture separated Tolfund into:

```text
tlf/
├── tolfund-api/      # Laravel JSON API
└── tlf-frontend/     # Next.js frontend
```

The new recommended architecture is a single full-stack Laravel application:

```text
tol-fund/
├── app/              # Laravel domain, HTTP, actions, policies, services
├── bootstrap/
├── config/
├── database/
├── resources/
│   ├── views/        # Inertia root Blade template + email templates
│   ├── js/           # Inertia, Svelte pages, layouts, components, types
│   └── css/
├── routes/
│   ├── web.php       # Primary browser routes
│   ├── api.php       # Reserved for future external API/mobile clients
│   └── channels.php
├── storage/
├── tests/
├── composer.json
├── package.json
└── vite.config.ts
```

### 1.1 Critical Changes from the Previous Approach

| Area | Previous Approach | New Recommended Approach |
|---|---|---|
| Browser app | Next.js App Router | Laravel 13 Svelte starter kit structure with Inertia 3 |
| Data layer | Next.js Server Actions proxying Laravel API | Laravel controllers return Inertia pages, redirects, streamed files, or small JSON responses when truly needed |
| Browser auth | Sanctum token stored in httpOnly cookies by Next.js | Native Laravel session auth with CSRF protection |
| API auth | Sanctum tokens for browser frontend | Sanctum reserved for future mobile/API integrations |
| Route protection | Laravel middleware + Next.js middleware | Laravel middleware, policies, and route model binding only |
| Real-time auth | Next.js API route proxies broadcasting auth | Laravel `/broadcasting/auth` using session auth |
| File streaming | Next.js route handlers call Laravel API | Laravel signed/authorized download routes stream private files |
| Frontend state | Server Components + server actions | Inertia page props + shared props + Svelte stores for local UI state |

### 1.2 Why This Change Is Better for Tolfund

Tolfund is a workflow-heavy mentorship and meeting-support platform. It is invitation-only and connects entrepreneurs with mentors, then supports that relationship through scheduled meetings and a report for every meeting. The platform handles onboarding, profile completion, mentor–entrepreneur pairing, mentor availability, meeting scheduling and rescheduling, per-meeting reporting, documents, notifications, and auditability. It does not move money and runs no funding workflow. These responsibilities belong close to Laravel's domain and persistence layer.

A full-stack Laravel application gives the project:

- One routing authority for web access.
- One session and CSRF model.
- Less duplicated middleware logic.
- Simpler deployment.
- Better policy enforcement.
- Easier file authorization.
- Cleaner real-time channel authorization.
- Fewer cross-framework contracts to keep in sync.

Svelte is still a strong fit, but it should be used where it adds value: complex forms, dashboards, interactive review screens, booking flows, notifications, and real-time interfaces.

---

## 2. High-Level Architecture

Tolfund should be built as a modular Laravel monolith. "Monolith" here does not mean unstructured. It means one deployable application with clear internal boundaries.

```text
Browser
  |
  | HTML, CSS, JS, form posts, fetch requests, WebSockets
  v
Laravel Web App
  |
  | Controllers, Form Requests, Actions, Policies, Services
  v
Domain Layer
  |
  | Eloquent Models, State Transitions, Notifications, Jobs
  v
Database / Storage / Queue / Mail / Reverb
```

### 2.1 Rendering Model

Use Inertia as the browser application layer.

1. **Laravel routes and controllers**
   - Own URL structure, middleware, authorization, validation, redirects, and domain orchestration.
   - Return `Inertia::render(...)` for application pages.
   - Return downloads/streams for protected files.
   - Return small JSON responses only for cases that are not naturally Inertia page visits.

2. **Inertia root Blade template**
   - `resources/views/app.blade.php` is the single root document for the Inertia app.
   - It includes Inertia head/body directives and Vite assets.
   - It is not the place for role-specific application layout composition.

3. **Svelte page components**
   - Live under `resources/js/pages`.
   - Receive typed page props from Laravel through Inertia.
   - Use Svelte layouts for role-specific shells, navigation, sidebars, and page chrome.

4. **Inertia forms and visits**
   - Use Inertia form helpers and router visits for normal application mutations.
   - Let Laravel return redirects, validation errors, flash messages, and updated props.
   - Use partial reloads, deferred props, and polling where they materially improve dashboard performance.

5. **Targeted JSON endpoints**
   - Keep these limited to interactions that are awkward as page visits, such as availability occurrence lookup, notification fallback fetches, or real-time adjunct data.

This keeps the app aligned with Laravel's official Svelte starter kit model: classic Laravel server-side routing and controllers, with a modern Svelte SPA experience powered by Inertia.

### 2.2 Backend Responsibility

Laravel is the source of truth for:

- User identity and session state.
- Role and account status access control.
- Mentor–entrepreneur pairing and membership.
- Profile completion and document integrity.
- Mentor discovery, pairing, and mentorship access.
- Per-meeting reports and meeting progress.
- Recurring meeting scheduling and conflict detection.
- Generated calendar invites and meeting link metadata.
- File upload validation and secure streaming.
- Notification persistence and broadcasting.
- Operational configuration.

### 2.3 Frontend Responsibility

Svelte is responsible for:

- Responsive interactive UI.
- Client-side form ergonomics.
- Optimistic UI where safe.
- Real-time notification rendering.
- Calendar/booking interactions.
- Complex admin review screens.
- Local display state, filters, tabs, tables, modals, and wizards.

Svelte must not become the authority for permissions, status transitions, or eligibility. It can hide unavailable actions for usability, but Laravel must enforce every rule.

---

## 3. Project Structure

Recommended Laravel structure:

```text
tol-fund/
├── app/
│   ├── Actions/
│   │   ├── Auth/
│   │   ├── Pairing/
│   │   ├── Mentorship/
│   │   ├── Meetings/
│   │   └── Profiles/
│   ├── Console/
│   │   └── Commands/
│   ├── Data/
│   │   └── ViewModels/
│   ├── Domain/
│   │   ├── Pairing/
│   │   ├── Mentorship/
│   │   ├── Meetings/
│   │   └── Profiles/
│   ├── Enums/
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   ├── Auth/
│   │   │   ├── Entrepreneur/
│   │   │   ├── Mentor/
│   │   │   └── Webhooks/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   │   ├── Admin/
│   │   │   ├── Pairing/
│   │   │   ├── Mentorship/
│   │   │   ├── Meetings/
│   │   │   └── Profiles/
│   │   └── Resources/
│   ├── Jobs/
│   ├── Mail/
│   ├── Models/
│   ├── Notifications/
│   ├── Policies/
│   ├── Providers/
│   └── Services/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.ts
│   │   ├── bootstrap.ts
│   │   ├── echo.ts
│   │   ├── components/
│   │   ├── layouts/
│   │   ├── lib/
│   │   ├── pages/
│   │   │   ├── admin/
│   │   │   ├── entrepreneur/
│   │   │   ├── mentor/
│   │   │   └── shared/
│   │   ├── stores/
│   │   ├── types/
│   │   └── utils/
│   └── views/
│       ├── app.blade.php
│       └── emails/
├── routes/
│   ├── web.php
│   ├── api.php
│   ├── channels.php
│   └── console.php
├── tests/
│   ├── Feature/
│   ├── Browser/
│   └── Unit/
└── vite.config.ts
```

### 3.1 Controllers

Controllers should be thin. They should:

- Authorize the request.
- Validate through Form Requests.
- Call an Action or domain service.
- Return a view, redirect, stream, or JSON response.

Avoid putting major business state transitions directly in controllers. The previous architecture had substantial transformation and workflow logic inside API controllers. For the new long-term architecture, promote important business workflows into Actions and domain services.

### 3.2 Actions

Use single-purpose action classes for meaningful workflow operations:

```text
app/Actions/Invitations/CreateUserInvitation.php
app/Actions/Invitations/AcceptUserInvitation.php
app/Actions/Pairing/PairEntrepreneurWithMentor.php
app/Actions/Mentorship/SetMentorAvailability.php
app/Actions/Meetings/ScheduleMeeting.php
app/Actions/Meetings/RescheduleMeeting.php
app/Actions/Meetings/SubmitMeetingReport.php
app/Actions/Profiles/SubmitEntrepreneurProfile.php
```

Actions make workflow rules testable without forcing tests through full HTTP layers.

### 3.3 Inertia Page Data

Use data objects, API resources, or View Models when pages need assembled, formatted data:

```text
app/Data/ViewModels/AdminPairingViewModel.php
app/Data/ViewModels/EntrepreneurMeetingsDashboardViewModel.php
app/Data/ViewModels/MentorBookingDashboardViewModel.php
app/Data/ViewModels/AdminMeetingReportsViewModel.php
```

These classes should format data for Inertia page props, but they should not mutate state. Use them to avoid passing raw Eloquent models directly into Svelte pages.

### 3.4 Enums

Important statuses should be PHP backed enums, not loose strings scattered across controllers:

```text
app/Enums/UserRole.php
app/Enums/AccountStatus.php
app/Enums/PairingStatus.php
app/Enums/MeetingStatus.php
app/Enums/MeetingReportStatus.php
app/Enums/DocumentType.php
```

The frontend should mirror these as generated or manually maintained TypeScript union types.

---

## 4. Dependency and Package Strategy

### 4.1 PHP Dependencies

Recommended core packages:

- `laravel/framework`
- `inertiajs/inertia-laravel`
- `laravel/sanctum`
- `laravel/reverb`
- `resend/resend-laravel`
- `laravel/tinker`

Recommended development packages:

- `pestphp/pest`
- `pestphp/pest-plugin-laravel`
- `laravel/pint`
- `laravel/pail`
- `laravel/sail` if local Docker is desired
- `mockery/mockery`
- `fakerphp/faker`

### 4.2 JavaScript Dependencies

Recommended runtime packages:

- `svelte`
- `@inertiajs/svelte`
- `@sveltejs/vite-plugin-svelte`
- `typescript`
- `laravel-echo`
- `pusher-js`
- `lucide-svelte`
- `clsx`
- `tailwind-merge`
- `shadcn-svelte`

Optional, only when needed:

- `zod` for client-side schema validation that mirrors server rules.
- `date-fns` or `luxon` for robust date/time display.
- A table library only if native Svelte patterns become insufficient.

### 4.3 Styling

Use Tailwind CSS 4 with a restrained design token layer:

- Color tokens for role-neutral status states.
- Typography scale.
- Form controls.
- Buttons.
- Tables.
- Badges.
- Modal/dialog primitives.
- Empty states.
- Toasts/alerts.

Do not build the application as a collection of visually unrelated one-off screens. Tolfund is an operational platform and should feel consistent, calm, dense enough for repeated use, and clear under pressure.

---

## 5. Application Layers

### 5.1 HTTP Layer

The HTTP layer includes:

- Routes.
- Controllers.
- Form Requests.
- Middleware.
- Response classes where useful.

Its job is request handling, not domain ownership.

### 5.2 Domain Workflow Layer

The domain workflow layer includes:

- Actions.
- Domain services.
- State transition helpers.
- Mentorship eligibility checks.
- Booking conflict detection.
- Per-meeting report handling.

This is where Tolfund's most important business rules live.

### 5.3 Persistence Layer

Eloquent models should own:

- Relationships.
- Casts.
- Query scopes.
- Small computed helpers.

They should not become giant workflow objects. For example, `Meeting` can expose `isEditable()` or `scopeAwaitingReport()`, but scheduling or reporting on a meeting should live in an Action because it touches availability, notifications, timestamps, and possibly downstream state.

### 5.4 Presentation Layer

Presentation includes:

- Inertia Svelte pages and layouts.
- The Inertia root Blade template.
- Svelte components.
- Page data objects / View Models.
- TypeScript types.
- Frontend stores.

Presentation can format statuses, but it cannot decide whether access is granted.

---

## 6. Database Schema and Domain Models

The following model set should be preserved from the previous application, with improvements to enum usage, indexes, and state transition ownership.

### 6.1 Core Users and Profiles

`users`
- `id`
- `name`
- `email`
- `phone_number`
- `role`
- `account_status`
- `email_verified_at`
- `profile_submitted_at`
- `account_status_changed_at`
- timestamps

Relationships:
- one `mentor_profile`
- one `entrepreneur_profile`
- many mentor–entrepreneur pairings
- many meetings as mentor
- many meetings as entrepreneur
- many notifications
- many feedback items

`user_invitations`
- `id`
- `email`
- `role`
- `token_hash`
- `invited_by`
- `accepted_user_id` nullable
- `expires_at`
- `accepted_at` nullable
- `revoked_at` nullable
- timestamps

Invitation records are the only valid entry point into registration. They should be role-bound and email-bound. Store only a hash of the token, never the raw token. The raw token exists only in the emailed registration link.

`entrepreneur_profiles`
- `user_id` (unique FK, cascade delete)
- `business_name`
- `business_description`
- `business_email` (unique)
- `business_phone_number` (unique; must differ from the user's personal `phone_number`)
- `sector` (string array, JSON)
- `years_in_operation` (unsigned integer)
- `employee_count` (unsigned integer)
- timestamps

Every column except `user_id` is nullable at rest so a `draft` profile can be saved incrementally. Completeness is enforced at submission time, not by database constraints (see "Profile submission" below).

`mentor_profiles`
- `user_id` (unique FK, cascade delete)
- `primary_expertise`
- `industry_focus` (string array, JSON)
- `years_experience` (unsigned integer)
- `afcfta_knowledge` (text)
- `availability`
- timestamps

Same nullable-at-rest, required-at-submission rule as `entrepreneur_profiles`.

`user_documents`

Private onboarding and role documents live in a normalized table rather than as path columns on the profile, so each document has its own authorization, audit trail, and re-upload history.
- `id`
- `user_id`
- `document_type` (backed `DocumentType` enum)
- `disk`
- `path` (relative path only; never a public URL)
- `original_name`
- `mime_type`
- `size`
- `uploaded_at`
- timestamps

Required document sets by role (via `DocumentType`):
- Entrepreneur: `business_certificate`, `business_registration_documents`, `business_plan` — pdf/png/jpg/jpeg/docx, max 5 MB each.
- Mentor: `passport_photo`, `identification_card`, and one or more `certification` documents — pdf/png/jpg/jpeg (docx also allowed for certifications), max 2 MB each.

Store documents on a private disk and serve them only through authenticated, authorized streaming routes gated by `UserDocumentPolicy` (see 7.8 and 12). Validate type, size, and MIME on upload; overwriting a required document replaces the prior row rather than mutating a shared column.

#### Profile submission

Entrepreneur and mentor accounts begin at `account_status = draft` after invitation acceptance (their email is already verified by acceptance). They complete profile fields and upload required documents incrementally, then submit for review. Submission is a completeness gate, not a set of database constraints:

- A domain action (`SubmitEntrepreneurProfile` / `SubmitMentorProfile`) computes a `missing_items` list from the role's required fields and required `DocumentType`s.
- Submission is allowed only when `missing_items` is empty.
- On submission, set `profile_submitted_at` and move `account_status` from `draft` to `pending`. Admin approval moves `pending -> approved`; rejection moves `pending -> rejected` with reviewer notes; a rejected user may revise and resubmit (`rejected -> pending`).
- This submission concerns profile/document readiness only.

### 6.2 Mentor–Entrepreneur Pairing and Cohorts

> **Out of scope (data model to be designed).** The former "funded companies / support programs" model — externally funded teams, support cohorts, offline financing references, and committee-recorded funding decisions — has been removed from Tolfund's scope. Tolfund now pairs an entrepreneur with a mentor. How pairings (and any grouping/cohort concept) are stored is still to be designed and is intentionally not specified here.

### 6.3 Meetings and Reports

> **Out of scope (data model to be designed).** The former milestones, milestone updates/comments, support reports, and quarterly investor/regulator assessments have been removed. Tolfund now records scheduled meetings and a report for every meeting. That data model — meetings, their lifecycle, and per-meeting reports — is still to be designed and is intentionally not specified here.

### 6.4 Mentorship (Availability and Meetings)

> **Out of scope (data model to be designed).** Mentor availability, meeting scheduling, rescheduling, and per-meeting reports are in scope for the product, but their concrete schema — previously coupled to funded companies and milestones — is still to be designed and is intentionally not specified here.

### 6.5 Payments and Subscriptions

> **Out of scope.** Tolfund does not move money and runs no payment workflow. The former annual subscription, payment references, and fee configuration have been removed entirely; there is no subscription or payment data model.

### 6.6 Platform Configuration

`platform_settings`
- key
- value
- updated_by
- timestamps

Important keys:
- `default_mentorship_meeting_frequency`
- `default_mentorship_meeting_duration_minutes`
- `meeting_provider`
- `calendar_provider`

Keep this pattern. Scheduling defaults and provider settings must be live-configurable by admins without deployment.

### 6.7 Feedback

`feedback_items`
- submitted by
- responded by nullable
- kind
- topic
- subject
- message
- response
- status
- responded at

### 6.8 Indexes and Constraints

Add explicit indexes for:

- `user_invitations.email`
- `user_invitations.role`
- `user_invitations.token_hash`
- `user_invitations.expires_at`
- `user_invitations.accepted_at`
- `user_invitations.revoked_at`
- `users.role`
- `users.account_status`
- `users.email`
- `users.phone_number`

Add indexes for the lookup-heavy fields of the mentorship/meetings domain (pairing, availability, meetings, reports) once that data model is designed — for example participant foreign keys, statuses, and meeting start/end times. Those tables are out of scope here (see 6.2–6.5) and are intentionally not enumerated.

Where supported and appropriate, add unique constraints that protect business invariants:

- One active unaccepted invitation per email and role.
- Unique setting key in `platform_settings`.

Some invariants, such as "no overlapping meeting for either participant", cannot be fully represented by a simple MySQL unique index and must be enforced by transactional application logic.

---

## 7. Authentication, Sessions, and Authorization

### 7.1 Browser Authentication

Use native Laravel session authentication for the web application.

Recommended:

- Laravel's session guard for browser users.
- CSRF protection on all state-changing requests.
- Secure, httpOnly session cookies.
- SameSite=Lax unless deployment requires stricter cross-site behavior.
- Email verification through Laravel's verification contract, customized for branded emails if needed. However, an invited user (admin, entrepreneur, mentor, or employee) already proves control of their email by receiving and accepting the single-use invitation link that is delivered only to that address. Treat their email as verified on acceptance (`email_verified_at = now()`) rather than sending a second, redundant verification email. Require verification only if a user later changes their email. Note that email verification does not protect against a mistyped-but-valid invite address; that is an admin input concern handled by review, not by a verification step.

This is a major change from the previous token-based browser session. The old Next.js httpOnly token facade should be removed because there is no longer a separate frontend server that needs to proxy API requests.

### 7.2 Sanctum

Keep Sanctum installed, but use it intentionally:

- Future mobile app.
- External API clients.
- Internal integrations.
- Personal access tokens if ever needed.

Do not use Sanctum tokens as the primary browser session mechanism in the full-stack Laravel app.

### 7.3 Invitation-Only Registration

Tolfund must not expose public registration for any role.

There should be no general `/register`, `/mentor/register`, `/entrepreneur/register`, or `/admin/register` page that a visitor can discover and use. Account creation starts only from an invitation issued by an authorized admin, by email and role.

This applies to:

- Entrepreneurs.
- Mentors.
- Admins.

The recommended invitation flow:

1. Admin opens an invitation screen.
2. Admin enters the invitee email, intended role, optional name, and optional note.
3. System creates a `user_invitations` record.
4. System generates a high-entropy raw token.
5. System stores only `hash(token)` in the database.
6. System sends an email containing a registration link:

```text
/invitations/accept/{token}
```

7. Invitee clicks the link.
8. Laravel hashes the provided token and finds a matching invitation.
9. Laravel verifies the invitation is valid:
   - Token exists.
   - Token has not expired.
   - Token has not been accepted.
   - Token has not been revoked.
   - Role is valid.
10. If valid, Laravel shows the registration form for that invitation only.
11. The form should display the invited email as locked/read-only.
12. User sets password and completes the minimum account fields.
13. Laravel creates the `users` record with the invitation's email and role.
14. Laravel creates the matching role profile.
15. Laravel marks the invitation as accepted and links `accepted_user_id`.
16. Laravel logs the new user in through the session guard.
17. User is redirected to role-specific profile completion/onboarding.

The registration POST must revalidate the invitation token. Do not rely on the GET page check alone.

Recommended actions:

```text
CreateUserInvitation
SendUserInvitationEmail
ValidateUserInvitation
AcceptUserInvitation
RevokeUserInvitation
ResendUserInvitation
ExpireOldInvitations
```

Recommended invitation statuses can be derived from timestamps:

```text
pending     = not accepted, not revoked, not expired
accepted    = accepted_at is not null
revoked     = revoked_at is not null
expired     = expires_at is in the past
```

On successful invitation acceptance:

- Create `users` row.
- Create matching `entrepreneur_profiles` or `mentor_profiles` row when applicable.
- Admin users do not need entrepreneur/mentor profiles.
- Set `account_status = draft` for entrepreneurs and mentors.
- Set admin account status according to policy, normally `approved`.
- Set `email_verified_at = now()`. Acceptance already proves control of the email, so no separate verification email is sent to invited users (see 7.1).
- Log the user in through the session guard.
- Redirect to onboarding/profile completion.

#### Entrepreneur-invited employees

> **Out of scope.** The previous "funded company" model let an approved entrepreneur delegate invitations to employees of their company. Tolfund's corrected scope has only three roles (admin, mentor, entrepreneur) and no company/employee concept; delegated employee invitations have been removed. All invitations are admin-issued.

### 7.4 Invitation Administration

Admins need an invitation management area.

Capabilities:

- Invite entrepreneur.
- Invite mentor.
- Invite admin.
- View pending invitations.
- Resend invitation.
- Revoke invitation.
- See accepted invitations and linked users.
- Filter by role, status, and email.

Security rules:

- Only approved admins can create invitations for the admin, mentor, or entrepreneur roles.
- Admin invitation creation may require a stronger permission, such as `manage-admin-invitations`.
- Invitation emails should not reveal whether an account already exists beyond safe, generic messages.
- Invitation tokens must be single-use.
- Invitation tokens must expire.
- Revoked invitations must never be accepted.
- Accepted invitations must never be reused.
- Registration must use the invited email. Users cannot change it during acceptance.
- If an invited email already belongs to an existing user, the system must reject the invite or handle it through an explicit account-linking process. Do not silently merge accounts.

### 7.5 Login

Use role-aware login behavior:

- Shared login can accept entrepreneur or mentor.
- Admin login should remain separate for operational clarity.
- After login, redirect based on role, email verification, and account status.

Recommended redirects:

| Condition | Redirect |
|---|---|
| Admin | `/admin/dashboard` |
| Email not verified | `/email/verify` |
| Mentor not approved | `/mentor/onboarding` |
| Entrepreneur not approved | `/entrepreneur/onboarding` |
| Mentor approved | `/mentor/dashboard` |
| Entrepreneur approved | `/entrepreneur/dashboard` |

### 7.6 Route Naming

Correct the previous pervasive misspelling `entreprenuer` to `entrepreneur` in the new build.

This is a breaking change from the old frontend, but the rebuild is the right time to fix it. Keep legacy redirects if old links might exist:

```text
/entreprenuer/* -> /entrepreneur/*
```

Do not carry misspelled domain language into a new long-term codebase.

### 7.7 Middleware

Recommended middleware:

- `auth`
- `verified`
- `role:admin`
- `role:mentor`
- `role:entrepreneur`
- `account.active`
- `profile.editable`
- `entrepreneur.has-mentor`

The middleware should handle broad access gates. Fine-grained ownership and workflow decisions should live in policies and Actions.

### 7.8 Policies

Create policies for important models:

- `UserInvitationPolicy`
- `MentorPairingPolicy`
- `MeetingPolicy`
- `MeetingReportPolicy`
- `MentorAvailabilitySlotPolicy`
- `FeedbackItemPolicy`
- `UserDocumentPolicy`

Policies should answer questions such as:

- Can this user view this pairing?
- Can this mentor or entrepreneur schedule or reschedule this meeting?
- Can this mentor submit the report for this meeting?
- Can this user download this private document?
- Can this admin invite another admin?
- Can this invitation be resent or revoked?

### 7.9 Account Status Lifecycle

Account statuses:

```text
draft -> pending -> approved
                 -> rejected
approved -> deactivated
rejected -> pending
```

Only approved users should access full role capabilities. Draft, pending, and rejected users should remain limited to onboarding, profile, document management, email verification, and clearly allowed pre-approval views.

---

## 8. Frontend Architecture with Svelte

### 8.1 Inertia + Svelte Integration Pattern

Use the Laravel 13 Svelte starter kit pattern: Inertia 3, Svelte 5, TypeScript, Tailwind 4, Vite, and shadcn-svelte.

Laravel controllers return Inertia pages:

```php
use Inertia\Inertia;
use Inertia\Response;

public function show(Pairing $pairing): Response
{
    $this->authorize('view', $pairing);

    return Inertia::render('entrepreneur/pairing/Show', [
        'pairing' => PairingData::from($pairing),
        'meetings' => MeetingData::collection($pairing->meetings),
    ]);
}
```

Svelte pages receive props directly from Inertia:

```svelte
<script lang="ts">
    import AppLayout from '@/layouts/app/AppLayout.svelte';
    import type { Pairing, Meeting } from '@/types/mentorship';

    interface Props {
        pairing: Pairing;
        meetings: Meeting[];
    }

    let { pairing, meetings }: Props = $props();
</script>

<AppLayout>
    <!-- page UI -->
</AppLayout>
```

The Inertia root template should remain minimal:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @routes
        @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.svelte"])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
```

Role-specific layout composition belongs in Svelte layout components, not Blade templates.

### 8.2 Frontend Directory Structure

```text
resources/js/
├── app.ts
├── bootstrap.ts
├── echo.ts
├── components/
│   ├── ui/
│   ├── layout/
│   ├── forms/
│   ├── pairing/
│   ├── mentorship/
│   ├── meetings/
│   └── notifications/
├── layouts/
│   ├── app/
│   │   ├── AppLayout.svelte
│   │   ├── AppSidebarLayout.svelte
│   │   └── AppHeaderLayout.svelte
│   ├── auth/
│   │   ├── AuthLayout.svelte
│   │   ├── AuthSimpleLayout.svelte
│   │   └── AuthSplitLayout.svelte
│   └── role/
│       ├── AdminLayout.svelte
│       ├── EntrepreneurLayout.svelte
│       └── MentorLayout.svelte
├── lib/
│   ├── dates.ts
│   ├── status.ts
│   ├── utils.ts
│   └── validation.ts
├── pages/
│   ├── admin/
│   ├── entrepreneur/
│   ├── mentor/
│   ├── invitations/
│   ├── auth/
│   └── shared/
├── stores/
│   ├── session.ts
│   ├── notifications.ts
│   └── ui.ts
├── types/
│   ├── auth.ts
│   ├── pairing.ts
│   ├── mentorship.ts
│   ├── meetings.ts
│   └── shared.ts
```

Keep this close to the official Laravel 13 Svelte starter kit layout:

- `components/` for reusable Svelte components.
- `layouts/` for app, auth, and role-specific shells.
- `lib/` for utilities, shared configuration, and Svelte rune modules.
- `pages/` for Inertia page components.
- `types/` for TypeScript definitions.

### 8.3 Data Flow

Prefer this order:

1. Laravel controller loads page data.
2. Action or query object performs domain work.
3. Data object or View Model formats stable Inertia props.
4. Controller returns `Inertia::render(page, props)`.
5. Svelte page renders from props.
6. User interactions use Inertia visits/forms for mutations.
7. Laravel redirects back with validation errors, flash messages, or fresh props.
8. Svelte keeps only local UI state in stores.

Use shared Inertia props for global state:

- Authenticated user summary.
- Current role.
- Account status.
- Email verification status.
- Flash messages.
- Notification unread count.
- App/platform settings that are safe to expose.

Shared props should be defined in the Inertia middleware, not manually repeated in every controller.

### 8.4 Form Handling

Use server validation as the authority.

Use Inertia form helpers for most forms:

- Invitation acceptance.
- Login.
- Profile editing.
- Meeting report submission.
- Meeting scheduling and rescheduling.
- Admin review decisions.
- Mentor availability and booking.

Client-side validation is for usability only. Laravel Form Requests remain authoritative.

Laravel should respond to mutations with redirects. Inertia will preserve validation errors, old input behavior, flash messages, and updated page props.

Use direct `fetch` only for narrow adjunct interactions such as:

- Availability occurrence previews.
- Notification fallback loading.
- File metadata lookup.
- Autosave drafts if a full Inertia visit would be too disruptive.

### 8.5 CSRF

Inertia requests run through Laravel's normal web middleware and CSRF protection.

For rare direct `fetch` mutation calls, send:

- `X-CSRF-TOKEN`
- `Accept: application/json`
- `Content-Type: application/json` where appropriate

File uploads should use `FormData` and must not manually set `Content-Type`.

### 8.6 TypeScript Types

Keep TypeScript types close to the frontend, but make them mirror backend Inertia page props.

For critical payloads, document the contract in both places:

- PHP data object or View Model class.
- TypeScript type.
- Feature test asserting shape for important endpoints.

Recommended shared page type:

```ts
export interface SharedPageProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            role: 'admin' | 'mentor' | 'entrepreneur';
            account_status: string;
            email_verified: boolean;
        } | null;
    };
    flash: {
        success?: string;
        error?: string;
    };
    notifications: {
        unread_count: number;
    };
}
```

### 8.7 Inertia Performance Patterns

Use Inertia features deliberately:

- Partial reloads for large dashboards.
- Deferred props for expensive secondary data.
- Polling for low-frequency updates when WebSockets are unnecessary.
- Remembered state for filters, tabs, and review queues.
- Lazy-loaded page components through Vite imports.

Do not pass entire Eloquent models to Inertia. Always pass explicit arrays/data objects with only the fields the page needs.

### 8.8 UI Design Direction

Tolfund should feel like a professional operational platform:

- Clear role-specific navigation.
- Dense but readable dashboards.
- Strong status visibility.
- Tables for review queues.
- Timeline patterns for audit trails.
- Focused forms with progress indication.
- Minimal decorative UI.
- Accessible color contrast.
- Explicit empty/error/loading states.

Use cards only for repeated content or genuinely framed tools. Avoid nesting cards inside cards.

---

## 9. Routing and Controller Design

### 9.1 Web Routes

The browser application should primarily use `routes/web.php`.

Recommended route groups:

```php
Route::middleware('guest')->group(function () {
    // public pages, login, password reset
    // invitation acceptance pages only; no public registration route
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('role:admin')
        ->group(base_path('routes/web/admin.php'));

    Route::prefix('mentor')
        ->name('mentor.')
        ->middleware('role:mentor')
        ->group(base_path('routes/web/mentor.php'));

    Route::prefix('entrepreneur')
        ->name('entrepreneur.')
        ->middleware('role:entrepreneur')
        ->group(base_path('routes/web/entrepreneur.php'));
});
```

For long-term maintainability, split role routes:

```text
routes/web/admin.php
routes/web/mentor.php
routes/web/entrepreneur.php
routes/web/auth.php
routes/web/invitations.php
```

### 9.2 API Routes

Keep `routes/api.php` small and intentional.

Use it for:

- Future mobile/external API.
- Webhooks.
- Public integration endpoints.

Do not put all browser behavior in `api.php` just because Svelte exists. Inertia pages and form visits should use `web.php` routes.

### 9.3 Inertia and JSON Web Endpoints

Most browser pages should return Inertia responses:

```php
Route::get('/pairing/{pairing}', [EntrepreneurPairingController::class, 'show'])
    ->name('pairing.show');
```

Some web routes may return JSON for narrow adjunct interactions:

```php
Route::get('/availability/{slot}/occurrences', PreviewAvailabilityOccurrencesController::class)
    ->name('availability.occurrences');
```

For mutations, prefer redirects from Inertia form submissions. Use JSON only when the interaction is not naturally a page visit or redirect cycle.

The controller can return:

- `Inertia::render(...)` for pages.
- Redirects for Inertia form submissions.
- Streams/downloads for protected documents.
- JSON for narrow adjunct interactions.

### 9.4 Route Model Binding

Use route model binding, but always pair it with policies:

```php
public function show(Pairing $pairing)
{
    $this->authorize('view', $pairing);

    return Inertia::render('entrepreneur/pairing/Show', [
        'pairing' => PairingData::from($pairing, auth()->user()),
    ]);
}
```

### 9.5 Response Shape

Use consistent JSON response shapes:

Success:

```json
{
  "ok": true,
  "message": "Meeting report submitted.",
  "data": {}
}
```

Validation:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."]
  }
}
```

Domain rejection:

```json
{
  "ok": false,
  "code": "meeting_not_editable",
  "message": "This meeting cannot be edited after it has taken place."
}
```

---

## 10. Business Process Architecture

This section preserves the core user journeys from the previous product definition while aligning implementation to the full-stack Laravel architecture.

### 10.1 Roles

Roles:

- `admin`
- `mentor`
- `entrepreneur`

Admins operate the platform: they invite and vet people, manage accounts, pair entrepreneurs with mentors, and oversee whether meetings are happening and reports are being captured. Mentors — called instructors or trainers in the business process documents — complete a profile, set their availability, meet with paired entrepreneurs on a schedule, and write a short report after each meeting. Entrepreneurs onboard onto the portal, complete a profile, get paired with a mentor, schedule meetings, and read the report from each meeting.

The platform role stays `mentor` in code, routes, and types even where business documents say "instructor" or "trainer". Do not introduce a second role name for the same concept; UI copy may say "instructor" where the audience expects it.

### 10.2 Invitation-Only Account Creation Journey

No user self-registers on Tolfund. Every account begins with an admin invitation.

The product journey:

1. Admin identifies a qualified entrepreneur, mentor, or admin.
2. Admin sends an invitation to that person's email address and assigns the intended role.
3. Invitee receives an email with a special registration link.
4. Invitee opens the link.
5. System verifies the invitation token.
6. If the token is invalid, expired, revoked, or already used, the user sees a safe error page and cannot continue.
7. If the token is valid, the user is allowed to create an account for the invited email and role only.
8. User sets password and completes required basic account details.
9. System creates the account, consumes the invitation, logs the user in, and redirects them to onboarding.
10. Entrepreneur and mentor users complete their profile and documents before gaining full platform access.
11. Admin invitees gain admin access according to the admin account policy after accepting the invitation.

Important business rules:

- The registration form is not public.
- The registration form is reachable only through a valid invitation token.
- The invited email cannot be changed during registration.
- The invited role cannot be changed during registration.
- Invitation links are single-use.
- Invitation links expire.
- Admins can revoke invitations before acceptance.
- Admins can resend invitations without creating duplicate active invites.
- A user who guesses or discovers a registration URL without a valid token cannot create an account.

This flow replaces the previous open role-specific registration assumption and should be treated as a core security requirement.

### 10.3 Profile Completion and Vetting

Entrepreneur and mentor users begin in `draft`.

They must:

- Verify email.
- Complete required profile fields.
- Upload required documents.
- Submit profile for review.

Submission:

- Validated by a Form Request and domain completeness checker.
- Sets `account_status = pending`.
- Sets `profile_submitted_at`.
- Notifies admins.

Admin review:

- Approve -> `account_status = approved`.
- Reject -> `account_status = rejected`.
- Deactivate -> `account_status = deactivated`.

Profile editing:

- Allowed in `draft` and `rejected`.
- Blocked in `pending`.
- Limited in `approved`, depending on fields and audit requirements.

Recommended implementation:

```text
SubmitEntrepreneurProfile
SubmitMentorProfile
ApproveUserAccount
RejectUserAccount
DeactivateUser
```

### 10.4 Financing and Funding Decisions

> **Out of scope.** The funding workflow (offline financing, applications, awards, milestones, disbursements, payments) has been removed from Tolfund's scope. The platform records no funding decisions and stores no funded-company records. The system now covers mentor–entrepreneur pairing, scheduling, meetings, and per-meeting reports; that domain's data model is still to be designed.

### 10.5 Programs and Cohorts

> **Out of scope.** Support programs and funded-company cohorts belonged to the removed funding workflow and are not part of Tolfund's corrected scope.

### 10.6 Support Lifecycle

> **Out of scope.** The funded-company support lifecycle (onboarding → active → completed, driven by financing and milestones) has been removed. The relationship Tolfund now tracks is a mentor–entrepreneur pairing supported by scheduled meetings and reports; that lifecycle and data model are still to be designed.

### 10.7 Milestone Lifecycle

> **Out of scope.** Milestones (and their review chain, progress updates, and completion) belonged to the removed funding workflow and are not part of Tolfund's corrected scope.

### 10.8 Meetings and Reports

Tolfund does not move money. Accountability happens through meetings and the report captured for each one.

Report rules:

- After each scheduled meeting, the mentor writes a short report on that meeting.
- The report is linked to the meeting (and thereby to the paired mentor and entrepreneur).
- Submitted reports appear on the entrepreneur's dashboard and on the mentor's dashboard.
- The interaction between entrepreneur and mentor is not refereed; it is monitored through the reports submitted on the portal.
- Meetings that have taken place without a report should be visible to admins, with reminder notifications.

Recommended implementation:

```text
ScheduleMeeting
SubmitMeetingReport
ReviewMeetingReport
```

### 10.9 Mentorship Access

Entrepreneur mentorship access requires:

1. An `approved` account.
2. An active pairing with a mentor (see 10.11).

If missing:

- Return a clear response in JSON flows.
- Redirect to a "pairing required" page in browser flows.

### 10.10 Payments and Subscriptions

> **Out of scope.** Tolfund does not move money and runs no payment or subscription workflow. The former annual subscription and any payment gate have been removed entirely.

### 10.11 Mentor Discovery and Pairing

Admins pair entrepreneurs with mentors. Discovery tooling can help admins choose a good match:

- Browse approved mentors.
- View relevance based on sector overlap and expertise.

Pairing rules:

- There is no pairing fee and no payment gate of any kind.
- Pairing is an admin action; an admin assigns a mentor to an entrepreneur.
- An entrepreneur has one active mentor at a time unless admins explicitly allow more.
- Draft, pending, rejected, or deactivated users can neither be paired nor pair.

Recommended implementation:

```text
RankMentorsForEntrepreneur
PairEntrepreneurWithMentor
UnpairEntrepreneurFromMentor
```

### 10.12 Mentor Availability and Bookings

Mentors define recurring weekly availability slots.

Slots include:

- Day of week.
- Start time.
- End time.
- Timezone.
- Session type.
- Location or meeting link.

Entrepreneurs book generated occurrences.

Booking rules:

- The mentor must be paired with the entrepreneur (admin pairing).
- Meeting must be in the future.
- Occurrence must match the availability slot.
- Neither participant can have overlapping meetings.
- Meetings follow the configured cadence — quarterly or half-yearly — driven by `default_mentorship_meeting_frequency` in `platform_settings`.

Recommended implementation:

```text
CreateAvailabilitySlot
UpdateAvailabilitySlot
DeleteAvailabilitySlot
BuildUpcomingAvailabilityOccurrences
ResolveAvailabilityOccurrence
ScheduleMeeting
DetectMeetingConflict
```

### 10.13 Rescheduling and Completion

Rescheduling:

- Entrepreneur can request reschedule for future confirmed sessions.
- Mentor can accept with new time/details or decline.
- Mentor can propose direct changes where business rules allow.
- All changes are recorded.

Completion:

- Mentor can mark confirmed session as completed after start time.
- Notes should be allowed only after start time.
- Outcome summary is stored.
- Notifications are sent.

### 10.14 Dedicated Pairing Page

Each mentor–entrepreneur pairing gets a dedicated page on the portal where:

- The entrepreneur and the mentor interact on the subject matter of the meetings (threaded discussion).
- Upcoming and past meetings for the pairing are listed.
- The mentor submits the report for each meeting.
- Submitted reports surface on both dashboards.

Access is private to the paired entrepreneur, the paired mentor, and admins. Admins monitor through the reports rather than refereeing the interaction; moderation happens only when a feedback item flags an issue.

### 10.15 Feedback

Entrepreneurs and mentors can submit feedback.

Feedback kinds:

- complaint
- suggestion
- flag_issue
- question
- appreciation

Admins can respond. Response changes status from `submitted` to `responded`.

---

## 11. Real-time Notifications and Broadcasting

### 11.1 Backend

Use Laravel notifications with database and broadcast channels.

Base notification pattern:

- Persist to database.
- Broadcast via Reverb.
- Include stable `kind`.
- Include `action_url` where appropriate.
- Include role-safe display payload.

### 11.2 Channels

Use private user channels:

```php
Broadcast::channel('users.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
```

Role-specific operational channels can be added carefully:

```php
Broadcast::channel('admin.pairings', function (User $user) {
    return $user->role === UserRole::Admin;
});
```

Do not expose broad channels unless payloads are safe for every subscriber.

### 11.3 Frontend Echo Setup

Svelte should initialize Echo from `resources/js/echo.ts`.

Since the app uses Laravel sessions, broadcasting auth should use Laravel's normal `/broadcasting/auth` endpoint with CSRF/session cookies.

### 11.4 Notification UI

Provide:

- Notification bell in role layout.
- Full notification page.
- Mark one as read.
- Mark all as read.
- Real-time append/update.
- Fallback fetch on page load.

---

## 12. Files, Storage, and Document Access

### 12.1 Storage Disks

Use separate disk intentions:

- `public` for intentionally public assets such as brand or informational hero images.
- `local` or private disk for user documents, reports, IDs, certificates, and business plans.

### 12.2 Private Documents

Private files must never be linked directly from public storage.

Use authorized streaming routes:

```text
/documents/mentor/{user}/passport-photo
/documents/entrepreneur/{user}/business-plan
/admin/users/{user}/documents/{document}
/meetings/{meeting}/report/download
```

Every document route must:

- Require authentication.
- Authorize with a policy.
- Verify the file exists.
- Stream with appropriate headers.
- Avoid exposing raw storage paths.

### 12.3 Upload Paths

Recommended path conventions:

```text
registrations/mentor/{userId}/
registrations/entrepreneur/{userId}/
funded-companies/{companyId}/
support-programs/{programId}/
support-reports/{companyId}/
quarterly-assessments/{assessmentId}/
mentorship-reports/{mentorUserId}/
```

Store relative paths only.

### 12.4 Validation

Validate:

- File type.
- File size.
- Required presence.
- Image dimensions where relevant.
- MIME type and extension.

For sensitive documents, prefer conservative file types such as PDF, JPG, PNG, and DOCX only if truly needed.

---

## 13. Email and Notification Delivery

### 13.1 Provider

Use Resend through Laravel mail.

Required mail types:

- Invitation emails.
- Email verification (only when an email is changed later; invited users are verified on acceptance).
- Password reset.
- Account approved/rejected.
- Support/onboarding status notices.
- Mentorship session reminders.
- Report submission reminders.
- Critical subscription notices.

### 13.2 Email Verification

Use Laravel's email verification features, customized for:

- Branded email template.
- Correct frontend route inside Laravel.
- Expiration.
- Resend rate limiting.

The previous custom token table can be kept if it provides useful control, but the simpler long-term preference is to use Laravel's standard signed verification URLs unless a specific product need requires custom token records.

### 13.3 Queues

All non-trivial emails and broadcasts should be queued.

Queue jobs should be monitored in production. Failed jobs must be visible operationally.

---

## 14. Payments and Financial Controls

### 14.1 Current Payment Model

The previous product model uses recorded payment references rather than a fully integrated provider.

The annual subscription is the platform's only payment. Financing amounts are disbursed offline by TLF and never move through the platform. There is no mentor pairing fee.

Preserve support for:

- Annual subscription payment, through the portal or recorded from an offsite payment.
- Admin-configurable fees via `platform_settings`.

### 14.2 Long-Term Payment Abstraction

Design a payment abstraction early:

```text
app/Contracts/PaymentGateway.php
app/Services/Payments/ManualPaymentGateway.php
app/Services/Payments/StripePaymentGateway.php
app/Services/Payments/FlutterwavePaymentGateway.php
```

Even if the first implementation is manual/reference-based, the domain should not hardcode a single provider.

### 14.3 Financial Audit Rules

Subscription payments should be auditable.

Record:

- Amount.
- Currency.
- Payment reference.
- Provider reference when available.
- Actor.
- Timestamp.
- Status.
- Expiration.

Never update payment records destructively in ways that erase history. Prefer status changes and audit comments.

---

## 15. Configuration and Environment Variables

Recommended variables:

```env
APP_NAME=Tolfund
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tolfund
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=720
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=database
QUEUE_CONNECTION=database

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ALLOWED_ORIGINS=http://localhost:8000

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

MAIL_MAILER=resend
RESEND_API_KEY=
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"

FILESYSTEM_DISK=local
```

Notes:

- There is no `FRONTEND_URL` requirement in the new architecture because Laravel owns the browser app.
- Keep `APP_URL` correct because signed URLs, email links, and asset URLs depend on it.
- Use database-backed sessions and queues initially for operational simplicity, then move queues/cache to Redis if load requires it.

---

## 16. Testing Strategy

Testing should focus on business correctness, not just controller happy paths.

### 16.1 Unit Tests

Use unit tests for:

- Status enum helpers.
- Subscription fee and expiry calculations.
- Mentor ranking.
- Date/time occurrence generation.
- Booking conflict detection.
- Quarterly report period grouping.

### 16.2 Feature Tests

Use Laravel feature tests for:

- Invitation creation, resend, revoke, expiry, and acceptance.
- Rejection of public registration attempts.
- Registration and verification through valid invitations only.
- Profile submission requirements.
- Admin approval/rejection.
- Funded company creation and external funding decision recording.
- Support status lifecycle transitions.
- Milestone review chain.
- Support report submission from both entrepreneur and instructor sides.
- Quarterly assessment preparation and publication.
- Subscription gate (no access without active support and paid subscription).
- Instructor request/assignment rules.
- Booking creation and conflict rejection.
- Reschedule flow.
- File download authorization.
- Feedback response.
- Notification persistence.

### 16.3 Browser Tests

Use browser tests for the most valuable end-to-end flows:

- Admin invites entrepreneur -> entrepreneur accepts invite -> profile submission -> admin approval.
- Admin invites mentor -> mentor accepts invite -> profile submission -> admin approval.
- Admin invites admin -> admin accepts invite -> admin dashboard access.
- Admin creates funded company -> records external funding decision -> support becomes active.
- Entrepreneur creates milestone -> mentor reviews -> admin approves.
- Entrepreneur submits 100% update -> mentor approves -> milestone completes.
- Entrepreneur subscribes -> requests instructor -> books session.
- Both sides submit reports -> reports appear on both dashboards -> admin collates quarterly assessment.

### 16.4 Svelte Tests

Use Svelte component tests selectively for:

- Complex multi-step forms.
- Booking calendar interactions.
- Milestone review UI state.
- Notification store behavior.

Avoid over-investing in brittle tests for purely presentational components.

### 16.5 Test Data

Factories should exist for:

- Admin users.
- Mentor users with profile states.
- Entrepreneur users with profile states.
- Support programs.
- Funded companies with support states.
- Company memberships.
- Milestones.
- Support reports.
- Quarterly assessments.
- Subscriptions.
- Availability slots.
- Sessions.

Use named factory states:

```php
User::factory()->admin()
User::factory()->mentor()->approved()
User::factory()->entrepreneur()->pending()
FundedCompany::factory()->activeSupport()
SupportReport::factory()->submitted()
Milestone::factory()->mentorReview()
EntrepreneurSubscription::factory()->paid()
```

---

## 17. Development Workflow

### 17.1 Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

### 17.2 Running Locally

Recommended terminals:

```bash
php artisan serve
php artisan queue:listen
php artisan reverb:start
npm run dev
```

Or define a composer script:

```json
{
  "scripts": {
    "dev": [
      "Composer\\Config::disableProcessTimeout",
      "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74,#86efac\" \"php artisan serve\" \"php artisan queue:listen\" \"php artisan reverb:start\" \"npm run dev\""
    ]
  }
}
```

### 17.3 Formatting

PHP:

```bash
./vendor/bin/pint
```

JavaScript/TypeScript/Svelte:

```bash
npm run format
```

### 17.4 Testing

```bash
php artisan test
npm run test
npm run build
```

At minimum, run PHP tests and frontend build before merging.

---

## 18. Deployment and Operations

### 18.1 Deployment Units

The full application deploys as one Laravel app.

Required runtime processes:

- PHP-FPM or Laravel Octane if chosen later.
- Web server.
- Queue worker.
- Reverb server.
- Scheduler.

### 18.2 Deployment Steps

Typical release:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
```

### 18.3 Scheduler

Use Laravel scheduler for:

- Session reminders.
- Subscription expiry reminders.
- Report submission reminders (per configured cadence).
- Quarter-end assessment preparation reminders for admins.
- Support program end-date checks if explicit closure records are needed.
- Cleanup of temporary uploads.

### 18.4 Observability

Production should provide:

- Application logs.
- Queue failure visibility.
- Mail delivery visibility.
- Reverb health.
- Error tracking.
- Database backups.
- Storage backups.

---

## 19. Security and Data Protection

### 19.1 Core Security Rules

- Use Laravel sessions and CSRF for browser requests.
- Enforce authorization server-side for every protected action.
- Do not expose public registration routes.
- Require valid, single-use, non-expired invitation tokens for all account creation.
- Store invitation token hashes only; never store raw invitation tokens.
- Never trust role, status, amount, or ownership from the client.
- Never expose private storage paths.
- Validate all uploads.
- Rate-limit auth, invitation acceptance, invitation resend, verification resend, password reset, and sensitive mutation endpoints.
- Use transactions for multi-record workflow changes.
- Avoid mass assignment risks through explicit fillable fields and Form Requests.

### 19.2 Sensitive Documents

Tolfund handles IDs, certificates, business plans, pitch decks, and reports.

Controls:

- Private disk storage.
- Authorized streaming routes.
- Audit log for admin downloads if required.
- No direct public URLs.
- Conservative file type limits.

### 19.3 Auditability

Add audit logs for high-value actions:

- Account approval/rejection.
- Invitation creation, resend, revocation, expiry, and acceptance.
- External funding decision recording and changes.
- Funded company support status changes.
- Subscription/payment status changes.
- Mentor (instructor) assignment.
- Session completion.
- Quarterly assessment preparation and publication.

This can start as structured domain records and later move to a dedicated `audit_logs` table if needed.

---

## 20. Conventions, Gotchas, and Anti-Patterns

### 20.1 Conventions

- Use `entrepreneur`, not `entreprenuer`, in all new routes, code, and UI.
- Use invitation-only registration for every role.
- Use enums for statuses.
- Use Actions for state transitions.
- Use Policies for ownership checks.
- Use Form Requests for validation.
- Use explicit Inertia page data objects or View Models for page payloads.
- Keep role layout composition in Svelte layouts, not Blade templates.
- Prefer Inertia forms, redirects, flash messages, and validation error bags over custom `fetch` flows.
- Use database settings for live-configurable fees.
- Use private document streaming.
- Use transactions around workflow changes involving multiple records.

### 20.2 Anti-Patterns to Avoid

- Recreating the old Next.js server action layer inside Svelte.
- Putting all browser interactions in `routes/api.php`.
- Building a custom Svelte mount registry instead of using Inertia.
- Passing raw Eloquent models directly to Inertia pages.
- Repeating shared auth/account props in every controller instead of using Inertia middleware.
- Turning every form into a bespoke JSON endpoint instead of using Inertia forms.
- Letting Svelte decide business permissions.
- Hardcoding fee amounts.
- Collecting, reviewing, or deciding financing applications inside the platform.
- Attaching money movement to milestones; financing is disbursed offline by TLF.
- Storing public URLs for private documents.
- Duplicating account access rules across many components.
- Encoding important statuses as unvalidated strings.
- Approving milestones or changing support status outside a transaction when multiple records are touched.
- Adding broad real-time channels with sensitive payloads.
- Adding public registration for any role.
- Trusting invitation details from hidden form fields instead of reloading the invitation server-side.

### 20.3 Compatibility Redirects

Because the previous frontend used `/entreprenuer`, add redirects for old links:

```php
Route::redirect('/entreprenuer', '/entrepreneur');
Route::redirect('/entreprenuer/{any}', '/entrepreneur/{any}')
    ->where('any', '.*');
```

Keep these as compatibility routes only. Do not use the misspelling internally.

---

## 21. Extensibility Patterns

### 21.1 Adding a New Role-Gated Feature

1. Define routes under the correct role group.
2. Create controller.
3. Create Form Request if input is accepted.
4. Create or update Policy.
5. Add Action for meaningful state changes.
6. Add explicit Inertia page data object or View Model.
7. Add Svelte page under `resources/js/pages` and use the correct Svelte layout.
8. Add tests for authorized, unauthorized, valid, invalid, and edge states.

### 21.2 Adding a New Status

1. Update PHP enum.
2. Update database validation if applicable.
3. Update transition Action rules.
4. Update policies if status affects permissions.
5. Update Inertia page data objects / View Models.
6. Update TypeScript union type.
7. Update UI status labels/badges.
8. Add migration if existing records need transformation.
9. Add tests for transitions and forbidden paths.

### 21.3 Adding a New Payment Provider

1. Implement `PaymentGateway`.
2. Add provider config.
3. Add webhook controller.
4. Validate webhook signatures.
5. Store provider references.
6. Make payment completion idempotent.
7. Add tests for duplicate webhook delivery.

### 21.4 Adding a New Document Type

1. Add database column or normalized document record.
2. Add upload validation.
3. Add storage path convention.
4. Add policy rule.
5. Add streaming route.
6. Add admin review display.
7. Add tests for upload, replacement, deletion, and unauthorized download.

---

## 22. Migration from the Previous Split Architecture

### 22.1 What to Keep

Keep the core business model:

- Roles.
- Account vetting.
- Support programs.
- Funded companies with external financing references.
- Milestones.
- Support reports and quarterly assessments.
- Annual subscriptions.
- Instructor assignment.
- Bookings.
- Reschedules.
- Feedback.
- Notifications.
- Platform settings.

Keep the strong parts of the previous implementation:

- Middleware gates.
- Secure file streaming.
- Reverb-based notifications.
- Platform settings for fees.
- Milestone review chain.
- Booking conflict detection.
- Tests around business flows.

### 22.2 What to Change

Change:

- Next.js frontend removed.
- Server Actions removed.
- Browser Sanctum token session removed.
- Next.js middleware removed.
- Next.js API route handlers removed.
- In-platform funding application, award, and disbursement pipeline removed; financing is applied for offline and decided by the Investment Committee, and the platform records the outcome only.
- Mentor pairing fee removed; the annual subscription is the only payment gate.
- Frontend route spelling corrected to `/entrepreneur`.
- Business logic moved out of large controllers into Actions/services.
- Response transformation moved into explicit Inertia page data objects, View Models, or resources.
- Authorization centralized through policies.

### 22.3 Suggested Implementation Order

1. Scaffold Laravel 13 app with the Svelte starter kit, Inertia 3, Svelte 5, TypeScript, Tailwind 4, and shadcn-svelte.
2. Establish auth, roles, sessions, invitation-only registration, verification, and layouts.
3. Build invitation administration, users, profiles, document upload, and admin vetting.
4. Build support programs and funded company records with external funding decisions.
5. Build milestones and the review chain.
6. Build annual subscription payment and gating.
7. Build instructor discovery, requests, and assignment.
8. Build mentor availability and booking.
9. Build the dedicated training page and support reports.
10. Build quarterly assessments and the collated investor/regulator report.
11. Build notifications and Reverb integration.
12. Build feedback.
13. Harden tests, audit logs, and deployment setup.

### 22.4 Quality Bar

For each feature, do not consider it complete until it has:

- Server-side authorization.
- Server-side validation.
- Clear state transition rules.
- Tests for success and denial cases.
- Empty/loading/error UI states where applicable.
- Notification behavior if user-facing.
- Auditability for sensitive operations.
- No direct exposure of private files.

---

## End of Guide

This architecture intentionally moves Tolfund toward a cohesive Laravel-centered application with Svelte as a focused interactive layer. The most important principle is that Laravel owns the business truth: identity, authorization, funded company support state, mentorship eligibility, subscription payments, reports, files, and audit history.

Svelte should make the product faster and more pleasant to use, but it should not duplicate or weaken the domain model. For a platform that handles externally funded companies, private documents, instructor relationships, and subscription-gated workflows, this separation is the right long-term foundation.
