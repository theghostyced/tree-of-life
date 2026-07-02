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
14. [Payments and Financial Controls](#14-payments-and-financial-controls)
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

Tolfund is a workflow-heavy support platform for already funded teams and companies. Funding applications, award decisions, and approval committees happen outside the system. The platform begins after a team or company has been funded and chooses to use Tolfund for onboarding, profile completion, mentor discovery, reporting, scheduled support meetings, documents, notifications, and auditability. These responsibilities belong close to Laravel's domain and persistence layer.

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
- Funded company onboarding and membership.
- Profile completion and document integrity.
- Mentor discovery, assignment, and mentorship access.
- Reports, milestones, and support progress.
- Recurring support meeting scheduling and conflict detection.
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

Svelte must not become the authority for permissions, money, status transitions, or eligibility. It can hide unavailable actions for usability, but Laravel must enforce every rule.

---

## 3. Project Structure

Recommended Laravel structure:

```text
tol-fund/
├── app/
│   ├── Actions/
│   │   ├── Auth/
│   │   ├── Funding/
│   │   ├── Mentorship/
│   │   ├── Milestones/
│   │   └── Profiles/
│   ├── Console/
│   │   └── Commands/
│   ├── Data/
│   │   └── ViewModels/
│   ├── Domain/
│   │   ├── Funding/
│   │   ├── Mentorship/
│   │   ├── Milestones/
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
│   │   │   ├── Funding/
│   │   │   ├── Mentorship/
│   │   │   ├── Milestones/
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
app/Actions/FundedCompanies/CreateFundedCompany.php
app/Actions/FundedCompanies/InviteFundedCompanyMember.php
app/Actions/Invitations/CreateUserInvitation.php
app/Actions/Invitations/AcceptUserInvitation.php
app/Actions/Milestones/SubmitMilestoneForMentorReview.php
app/Actions/Milestones/ApproveMilestoneAsAdmin.php
app/Actions/Mentorship/AssignMentorToFundedCompany.php
app/Actions/Mentorship/ScheduleRecurringMentorshipMeeting.php
app/Actions/Mentorship/BookMentorshipSession.php
app/Actions/Profiles/SubmitEntrepreneurProfile.php
```

Actions make workflow rules testable without forcing tests through full HTTP layers.

### 3.3 Inertia Page Data

Use data objects, API resources, or View Models when pages need assembled, formatted data:

```text
app/Data/ViewModels/AdminFundingApplicationViewModel.php
app/Data/ViewModels/EntrepreneurAwardViewModel.php
app/Data/ViewModels/MentorBookingDashboardViewModel.php
```

These classes should format data for Inertia page props, but they should not mutate state. Use them to avoid passing raw Eloquent models directly into Svelte pages.

### 3.4 Enums

Important statuses should be PHP backed enums, not loose strings scattered across controllers:

```text
app/Enums/UserRole.php
app/Enums/AccountStatus.php
app/Enums/FundingApplicationStatus.php
app/Enums/FundingAwardStatus.php
app/Enums/MilestoneStatus.php
app/Enums/MilestoneUpdateStatus.php
app/Enums/MentorshipSessionStatus.php
app/Enums/PaymentStatus.php
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
- Payment eligibility checks.
- Booking conflict detection.
- Allocation validation.

This is where Tolfund's most important business rules live.

### 5.3 Persistence Layer

Eloquent models should own:

- Relationships.
- Casts.
- Query scopes.
- Small computed helpers.

They should not become giant workflow objects. For example, `Milestone` can expose `isEditable()` or `scopeAwaitingMentorReview()`, but approving a milestone should live in an Action because it touches comments, notifications, allocation rules, timestamps, and possibly downstream state.

### 5.4 Presentation Layer

Presentation includes:

- Inertia Svelte pages and layouts.
- The Inertia root Blade template.
- Svelte components.
- Page data objects / View Models.
- TypeScript types.
- Frontend stores.

Presentation can format statuses and amounts, but it cannot decide whether money is released or access is granted.

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
- many funded company memberships
- many mentorship sessions as mentor
- many mentorship sessions as entrepreneur
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
- business identity
- business contacts
- sector list
- operating history
- document paths

`mentor_profiles`
- expertise
- industry focus
- experience
- AfCFTA knowledge
- availability description
- document paths

### 6.2 Funded Companies and Support Cohorts

Tolfund must not collect or review funding applications inside the platform. Funding approval and award decisions happen outside the system. The platform stores externally funded teams or companies only after they choose to receive support through Tolfund and an admin creates their record.

`support_programs`
- title
- description
- sector
- target audience
- support benefits
- documents expected
- reporting expectations
- status
- starts at / ends at nullable
- hero image path nullable

`funded_companies`
- support program id nullable
- name
- registration number nullable
- sector
- country
- external funding reference nullable
- externally funded at nullable
- support status
- primary entrepreneur user id nullable
- assigned mentor user id nullable
- onboarding completed at nullable
- support started at nullable
- support completed at nullable
- notes
- timestamps

`funded_company_members`
- funded company id
- user id
- role within company
- is primary contact
- invited by
- joined at nullable
- timestamps

Supported company member roles should include at least:

- founder / entrepreneur
- assistant
- operations contact

Admins create the funded company, add the funded entrepreneur and their assistant or other company contacts, and send invitation emails. The invited users complete their account and profile before gaining full support access.

### 6.3 Milestones and Disbursements

`milestones`
- funded company id
- title
- description
- measurable goal
- deadline
- status
- submitted/reviewed/completed timestamps

`milestone_updates`
- milestone id
- author user id
- summary
- progress percent
- evidence links
- status
- reviewed by
- reviewed at

`milestone_comments`
- milestone id
- milestone update id nullable
- author user id
- body
- scope
- decision/status context

`support_reports`
- funded company id
- author user id
- mentor user id nullable
- report period start
- report period end
- summary
- challenges
- next steps
- report path nullable
- report metadata
- submitted at nullable
- reviewed at nullable
- status

### 6.4 Mentorship

`mentorship_requests`
- funded company id
- mentor user id
- status
- accepted at

`mentor_availability_slots`
- mentor user id
- day of week
- start time
- end time
- timezone
- session type
- location
- meeting link
- active flag

`mentorship_sessions`
- funded company id
- mentor user id
- entrepreneur user id
- milestone id nullable
- availability slot id nullable
- starts at
- ends at
- timezone
- session type
- location
- meeting link
- calendar event id nullable
- meeting provider nullable
- recurrence rule nullable
- recurring series id nullable
- agenda
- status
- notes
- outcome summary

`mentorship_session_reschedules`
- session id
- requested by
- reviewed by nullable
- old/new start and end times
- reason
- status

`mentor_mentorship_reports`
- mentor user id
- entrepreneur user id nullable
- funded company id nullable
- report path
- report metadata

### 6.5 Platform Configuration

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

### 6.6 Feedback

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

### 6.7 Indexes and Constraints

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
- `support_programs.status`
- `funded_companies.support_program_id`
- `funded_companies.primary_entrepreneur_user_id`
- `funded_companies.assigned_mentor_user_id`
- `funded_companies.support_status`
- `funded_company_members.funded_company_id`
- `funded_company_members.user_id`
- `funded_company_members.role_within_company`
- `milestones.funded_company_id`
- `milestones.status`
- `milestone_updates.milestone_id`
- `support_reports.funded_company_id`
- `support_reports.status`
- `mentorship_sessions.mentor_user_id`
- `mentorship_sessions.entrepreneur_user_id`
- `mentorship_sessions.funded_company_id`
- `mentorship_sessions.starts_at`
- `mentorship_sessions.ends_at`
- `mentorship_sessions.recurring_series_id`

Where supported and appropriate, add unique constraints that protect business invariants:

- One active unaccepted invitation per email and role.
- One primary contact per funded company where practical.
- One active accepted membership per user per funded company.
- Unique setting key in `platform_settings`.

Some invariants, such as "no overlapping mentorship session for either participant", cannot be fully represented by a simple MySQL unique index and must be enforced by transactional application logic.

---

## 7. Authentication, Sessions, and Authorization

### 7.1 Browser Authentication

Use native Laravel session authentication for the web application.

Recommended:

- Laravel's session guard for browser users.
- CSRF protection on all state-changing requests.
- Secure, httpOnly session cookies.
- SameSite=Lax unless deployment requires stricter cross-site behavior.
- Email verification through Laravel's verification contract, customized for branded emails if needed.

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

There should be no general `/register`, `/mentor/register`, `/entrepreneur/register`, or `/admin/register` page that a visitor can discover and use. Account creation starts only when an authorized admin invites a specific person by email and role.

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
- Send verification email if email verification remains required.
- Log the user in through the session guard.
- Redirect to onboarding/profile completion.

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

- Only approved admins can create invitations.
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
- `entrepreneur.has-active-award`
- `entrepreneur.has-active-subscription`

The middleware should handle broad access gates. Fine-grained ownership and workflow decisions should live in policies and Actions.

### 7.8 Policies

Create policies for important models:

- `UserInvitationPolicy`
- `FundingProgramPolicy`
- `FundingApplicationPolicy`
- `FundingAwardPolicy`
- `MilestonePolicy`
- `MilestoneUpdatePolicy`
- `MentorshipSessionPolicy`
- `MentorAvailabilitySlotPolicy`
- `FeedbackItemPolicy`
- `UserDocumentPolicy`

Policies should answer questions such as:

- Can this user view this award?
- Can this entrepreneur edit this draft application?
- Can this mentor review this milestone?
- Can this admin approve this funding application?
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

public function show(FundingAward $award): Response
{
    $this->authorize('view', $award);

    return Inertia::render('entrepreneur/awards/Show', [
        'award' => EntrepreneurAwardData::from($award),
        'milestones' => MilestoneData::collection($award->milestones),
    ]);
}
```

Svelte pages receive props directly from Inertia:

```svelte
<script lang="ts">
    import AppLayout from '@/layouts/app/AppLayout.svelte';
    import type { Award, Milestone } from '@/types/funding';

    interface Props {
        award: Award;
        milestones: Milestone[];
    }

    let { award, milestones }: Props = $props();
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
│   ├── funding/
│   ├── mentorship/
│   ├── milestones/
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
│   ├── money.ts
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
│   ├── funding.ts
│   ├── mentorship.ts
│   ├── milestones.ts
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
- Funding applications.
- Milestone creation and update submission.
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
Route::get('/awards/{award}', [EntrepreneurAwardController::class, 'show'])
    ->name('awards.show');
```

Some web routes may return JSON for narrow adjunct interactions:

```php
Route::post('/milestones/{milestone}/updates', SubmitMilestoneUpdateController::class)
    ->name('milestones.updates.store');
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
public function show(FundingAward $award)
{
    $this->authorize('view', $award);

    return Inertia::render('entrepreneur/awards/Show', [
        'award' => EntrepreneurAwardData::from($award, auth()->user()),
    ]);
}
```

### 9.5 Response Shape

Use consistent JSON response shapes:

Success:

```json
{
  "ok": true,
  "message": "Milestone submitted for review.",
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
  "code": "milestone_not_editable",
  "message": "This milestone cannot be edited while it is under review."
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

Admins operate the platform. Mentors support entrepreneurs and review progress. Entrepreneurs apply for funding, manage milestones, pay for mentorship access, and book sessions.

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
- Sets `application_submitted_at`.
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
ApproveUserApplication
RejectUserApplication
DeactivateUser
```

### 10.4 Funding Programs

Admins can create, edit, publish, and close funding programs.

Funding program fields:

- Title.
- Amount.
- Grant type.
- Target audience.
- Sector.
- Overview.
- Financial benefits.
- Technical benefits.
- Required/provided documents.
- Commitments.
- Deadline.
- Optional hero image.

Closure:

- Automatically considered closed when deadline has passed.
- Can be manually closed.
- Closed programs should not accept new submitted applications.

Recommended implementation:

```text
CreateFundingProgram
UpdateFundingProgram
CloseFundingProgram
```

### 10.5 Funding Applications

Entrepreneurs can:

- Browse programs.
- Start an application.
- Save as draft.
- Upload pitch deck.
- Submit for admin review.
- View history.

Application statuses:

```text
draft -> pending -> approved
                 -> rejected
```

On approval:

- Create `FundingAward`.
- Set application reviewed metadata.
- Notify entrepreneur.

On rejection:

- Store reviewer notes.
- Notify entrepreneur.

Recommended implementation:

```text
CreateDraftFundingApplication
SaveFundingApplicationDraft
SubmitFundingApplication
ApproveFundingApplication
RejectFundingApplication
```

### 10.6 Funding Awards

A Funding Award represents an approved funding relationship.

Award status:

```text
active -> completed
```

An award has:

- Entrepreneur.
- Program.
- Amount.
- Optional assigned mentor.
- Milestones.
- Disbursements.
- Related mentorship sessions.

Completion:

- Award is completed when all required milestones are completed.
- Completion should be calculated through a domain action, not ad hoc controller code.

### 10.7 Milestone Lifecycle

Milestone statuses:

```text
draft
mentor_review
mentor_changes_requested
admin_review
admin_changes_requested
active
completed
```

Flow:

1. Entrepreneur creates milestone as draft.
2. Entrepreneur submits for mentor review.
3. Mentor approves or requests changes.
4. Admin approves or requests changes.
5. Admin sets planned disbursement amount during approval.
6. Active milestone accepts progress updates.
7. Mentor reviews progress updates.
8. Approved update with `progress_percent >= 100` completes milestone.
9. Completion can trigger disbursement release.

Recommended implementation:

```text
CreateMilestone
UpdateMilestone
SubmitMilestoneForMentorReview
ReviewMilestoneAsMentor
ReviewMilestoneAsAdmin
SubmitMilestoneUpdate
ReviewMilestoneUpdateAsMentor
ReleaseDisbursementForCompletedMilestone
RefreshAwardCompletion
```

### 10.8 Disbursement Rules

Disbursement is a critical financial workflow and must be protected by transactional logic.

Rules:

- Planned disbursement totals cannot exceed the award/program amount.
- Released amount cannot exceed planned amount.
- Planned amount cannot be reduced below already released amount.
- A milestone should not create duplicate automatic disbursements for the same completion update.
- Disbursement creation should happen inside the same transaction as milestone completion where possible.

### 10.9 Mentorship Access

Entrepreneur mentorship access requires:

1. At least one active funding award.
2. Active paid annual mentorship subscription.

If missing:

- Return clear response in JSON flows.
- Redirect to subscription or award-required page in browser flows.
- Use `402 Payment Required` for subscription gate in JSON endpoints where appropriate.

### 10.10 Subscription

Annual mentorship subscription:

- Configured by `platform_settings`.
- Paid once per year.
- Creates `EntrepreneurSubscription`.
- Active if `status = paid` and `expires_at` is in the future.

Payment integration can start with manual/reference-based records, but the architecture should allow a payment provider later through a `PaymentGateway` interface.

### 10.11 Mentor Discovery and Pairing

Entrepreneurs with mentorship access can:

- Browse approved mentors.
- View relevance based on sector overlap.
- Pay one-time pairing fee.
- Create active pairing for 3 months.

Pairing rules:

- Cannot pay for an already active pairing with the same mentor.
- Pairing fee comes from `platform_settings`.
- Successful pairing should assign the mentor to the most recent active unmentored award when possible.

Recommended implementation:

```text
RankMentorsForEntrepreneur
CreateMentorPairingPayment
AssignMentorToEligibleAward
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

- Entrepreneur must have active pairing with the mentor.
- Session must be in the future.
- Occurrence must match the availability slot.
- Neither participant can have overlapping sessions.
- Optional milestone must belong to the award.

Recommended implementation:

```text
CreateAvailabilitySlot
UpdateAvailabilitySlot
DeleteAvailabilitySlot
BuildUpcomingAvailabilityOccurrences
ResolveAvailabilityOccurrence
BookMentorshipSession
DetectMentorshipSessionConflict
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

### 10.14 Feedback

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
Broadcast::channel('admin.applications', function (User $user) {
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

- `public` for intentionally public assets such as funding program hero images.
- `local` or private disk for user documents, pitch decks, reports, IDs, certificates, and business plans.

### 12.2 Private Documents

Private files must never be linked directly from public storage.

Use authorized streaming routes:

```text
/documents/mentor/{user}/passport-photo
/documents/entrepreneur/{user}/business-plan
/admin/funding-applications/{application}/pitch-deck
/mentor/reports/{report}/download
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
funding-applications/{applicationId}/
funding-programs/{programId}/
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

- Email verification.
- Password reset.
- Account approved/rejected.
- Funding application decision.
- Mentorship session reminders.
- Critical payment/subscription notices.

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

Preserve support for:

- Annual subscription payment.
- Mentor pairing payment.
- Admin-configurable fees.

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

Payments, subscriptions, pairings, and disbursements should be auditable.

Record:

- Amount.
- Currency.
- Payment reference.
- Provider reference when available.
- Actor.
- Timestamp.
- Status.
- Expiration.

For disbursements, never update records destructively in ways that erase history. Prefer status changes and audit comments.

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
- Fee calculations.
- Mentor ranking.
- Date/time occurrence generation.
- Booking conflict detection.
- Allocation calculations.

### 16.2 Feature Tests

Use Laravel feature tests for:

- Invitation creation, resend, revoke, expiry, and acceptance.
- Rejection of public registration attempts.
- Registration and verification through valid invitations only.
- Profile submission requirements.
- Admin approval/rejection.
- Funding application lifecycle.
- Funding award creation.
- Milestone review chain.
- Disbursement creation.
- Subscription gate.
- Pairing payment rules.
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
- Admin creates program -> entrepreneur applies -> admin approves -> award appears.
- Entrepreneur creates milestone -> mentor reviews -> admin approves.
- Entrepreneur submits 100% update -> mentor approves -> disbursement appears.
- Entrepreneur subscribes -> pairs with mentor -> books session.

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
- Funding programs.
- Funding applications.
- Awards.
- Milestones.
- Subscriptions.
- Pairing payments.
- Availability slots.
- Sessions.

Use named factory states:

```php
User::factory()->admin()
User::factory()->mentor()->approved()
User::factory()->entrepreneur()->pending()
FundingApplication::factory()->submitted()
FundingAward::factory()->active()
Milestone::factory()->mentorReview()
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
- Pairing expiry reminders.
- Funding program deadline checks if explicit closure records are needed.
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
- Funding application approval/rejection.
- Planned disbursement changes.
- Disbursement release.
- Subscription/payment status changes.
- Mentor assignment.
- Session completion.

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
- Storing public URLs for private documents.
- Duplicating account access rules across many components.
- Encoding important statuses as unvalidated strings.
- Approving milestones or releasing disbursements outside a transaction.
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
- Funding programs.
- Funding applications.
- Awards.
- Milestones.
- Disbursements.
- Mentorship subscriptions.
- Pairing payments.
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
- Frontend route spelling corrected to `/entrepreneur`.
- Business logic moved out of large controllers into Actions/services.
- Response transformation moved into explicit Inertia page data objects, View Models, or resources.
- Authorization centralized through policies.

### 22.3 Suggested Implementation Order

1. Scaffold Laravel 13 app with the Svelte starter kit, Inertia 3, Svelte 5, TypeScript, Tailwind 4, and shadcn-svelte.
2. Establish auth, roles, sessions, invitation-only registration, verification, and layouts.
3. Build invitation administration, users, profiles, document upload, and admin vetting.
4. Build funding programs and applications.
5. Build funding awards and milestones.
6. Build disbursement logic.
7. Build mentorship subscription and pairing.
8. Build mentor availability and booking.
9. Build notifications and Reverb integration.
10. Build feedback.
11. Add operational admin reporting.
12. Harden tests, audit logs, and deployment setup.

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

This architecture intentionally moves Tolfund toward a cohesive Laravel-centered application with Svelte as a focused interactive layer. The most important principle is that Laravel owns the business truth: identity, authorization, funding state, mentorship eligibility, payments, disbursements, files, and audit history.

Svelte should make the product faster and more pleasant to use, but it should not duplicate or weaken the domain model. For a platform that handles funding, private documents, mentor relationships, and payment-gated workflows, this separation is the right long-term foundation.
