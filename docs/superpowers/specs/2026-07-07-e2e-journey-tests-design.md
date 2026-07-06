# Browser E2E Journey Tests — Design

**Date:** 2026-07-07
**Status:** Approved

## Goal

Prove the platform's core loop end to end in a real browser: an admin invites
a mentor and an entrepreneur, both open their emailed invite links and
register, both complete onboarding (profile + documents), and the
entrepreneur selects the mentor — whose dashboard then shows the mentee.
Staged suites cover each slice independently; one uninterrupted full-journey
test runs the whole loop with no shortcuts.

## Stack

Pest v4 browser testing (`pestphp/pest-plugin-browser`, dev-only) driving
Playwright Chromium. Per the Pest docs, browser tests integrate with
Laravel's test process: `RefreshDatabase`, factories, `actingAs`, the array
mailer, and the in-memory SQLite test database from `phpunit.xml` all work
unchanged — no separate database or served process to manage.

Setup (one-time, part of implementation):

- `composer require pestphp/pest-plugin-browser --dev`
- `npm install -D playwright` and `npx playwright install chromium`
- `tests/Browser/` suite added to `phpunit.xml`; the default `php artisan
  test` run excludes it so the fast suite stays fast. Browser tests run via
  `php artisan test --testsuite=Browser`.
- `tests/Pest.php` extends the browser directory with the same base test
  case + `RefreshDatabase` as Feature tests.

No production dependencies. The journey exercises the app exactly as built —
no test-only routes, no seeded backdoors beyond the standard factories.

## Shared helpers (defined in `tests/Pest.php` or a required helpers file)

- `inviteUrlFor(string $email): string` — finds the captured
  `UserInvitationMail` for that address (array mail transport), renders it,
  and extracts the `/invitations/accept/{token}` URL. Fails loudly if no
  mail was captured.
- Factory shortcuts reused from existing suites (`completeEntrepreneur()`,
  `availableMentor()`) plus stage-precondition builders: an accepted-but-not-
  onboarded user per role, and a pending invitation per role.
- Small real fixture files in `tests/Fixtures/` (a tiny PNG for photos, a
  tiny PDF for identification/certification uploads) used with `attach()`.

## Stage suites (`tests/Browser/Journey/`)

Each stage is a real browser test of its own slice. Preconditions come from
factories so a stage runs and fails independently of the others.

### 1. `AdminInvitesTest`

Browser: log in with the admin factory user, open `/admin/invitations`,
use the Invite people modal twice — once for `mentor@e2e.test` with the
mentor role, once for `founder@e2e.test` with the entrepreneur role.
Assert: both rows appear as Pending in the table, a `UserInvitationMail`
was captured for each address, and no JavaScript errors.

### 2. `InviteAcceptanceTest`

Precondition: a pending invitation per role (factory + real token, or
created via the action so the raw token is known through the mail).
Browser: open the real tokenized accept URL, verify the email field is
locked to the invited address and the role is displayed, fill name and
password, submit. Assert: redirected to the role's onboarding page,
authenticated, user row exists with the invited role, invitation marked
accepted. Also assert an expired token shows the failure state rather
than a form.

### 3. `OnboardingTest`

Precondition: an accepted, not-yet-onboarded user per role (factory).
Browser, mentor: complete the mentor onboarding form (primary expertise,
industry focus, years of experience, availability) and upload the required
documents via `attach()` (passport photo, identification, certification —
whatever `ProfileCompleteness::missingItems` currently requires; the test
asserts the missing list empties rather than hardcoding an item count).
Browser, entrepreneur: complete the business profile (name, description,
sectors, contact fields, years in operation, employees) and its required
documents. Assert for both: the onboarding progress reaches complete and
the dashboard no longer shows the onboarding card.

### 4. `MentorSelectionTest`

Precondition: one onboarded entrepreneur and one available mentor
(factories). Browser: as the entrepreneur, open Mentors, find the mentor
card (capitalized expertise visible), choose them, assert the success
toast and the mentor now under "Your mentors"; open the mentor detail
page. Then sign in as the mentor and assert the entrepreneur appears in
the dashboard's Your mentees section. This is the pairing-convergence
proof at the browser level.

### 5. `FullJourneyTest`

One test, no factory shortcuts anywhere: stage 1's browser flow, then
`inviteUrlFor()` both addresses, stage 2's acceptance for both users,
stage 3's onboarding for both, stage 4's selection, and the final mentor-
dashboard assertion. Sign-outs between actors happen through the real
user menu. Every page visit chains `assertNoJavascriptErrors()`.

## Conventions

- Test data uses the `@e2e.test` domain to be self-describing.
- Selectors prefer visible text and labels (`click('Invite people')`,
  `fill('email', ...)`) over CSS selectors; where the Svelte pages lack
  targetable labels, fixing the page's accessibility (real labels) is the
  preferred change over adding test-only attributes.
- Stage tests may run in any order; nothing persists between tests
  (`RefreshDatabase`).
- The suite runs headless by default; developers can watch with the
  plugin's headed/debug options when diagnosing.

## Error handling / flakiness policy

- No arbitrary sleeps; rely on the plugin's built-in waiting assertions.
  Where an Inertia visit needs settling, assert on resulting content.
- Any discovered UI accessibility gap (unlabeled fields, unreachable
  controls) is fixed in the app as part of this work — the E2E suite is
  also an accessibility audit.

## Testing the tests

- Each stage suite passes independently: `php artisan test
  --testsuite=Browser --filter=<Stage>`.
- The full journey passes headless locally.
- The default `php artisan test` remains browser-free and fast; document
  the Browser suite invocation in the README testing section if one exists
  (or the architecture guide's testing chapter).
