# Browser E2E Journey Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Real-browser proof of the platform's core loop — admin invites a mentor and an entrepreneur, both accept their emailed links and complete onboarding, the entrepreneur selects the mentor, and the mentor's dashboard shows the mentee — as independent stage suites plus one uninterrupted full-journey test.

**Architecture:** Pest v4 browser plugin (Playwright Chromium) on top of the existing testing env — `RefreshDatabase`, in-memory SQLite, array mailer, sync queue all work unchanged per the Pest docs. Stage suites build their preconditions with the existing factories/helpers so each runs alone; the full journey uses no shortcuts. Invite links are extracted from the array mail transport. Browser tests are grouped and excluded from the default run.

**Tech Stack:** `pestphp/pest-plugin-browser` (dev), Playwright Chromium (npm dev), existing Pest 4.7 + pest-plugin-laravel.

## Global Constraints

- New dependencies are DEV-ONLY: `pestphp/pest-plugin-browser` (composer) and `playwright` (npm). Nothing lands in production deps.
- The default `php artisan test` run must stay browser-free and fast; browser tests run explicitly (`php artisan test --group=browser`).
- Selectors prefer visible text and labels; where the UI lacks a targetable label (known case: the hidden onboarding file inputs), FIX THE APP's accessibility (add `aria-label`) rather than adding test-only attributes.
- Test identities use the `@e2e.test` domain.
- No arbitrary sleeps; rely on the plugin's waiting assertions. Every page in the full journey chains `->assertNoJavascriptErrors()`.
- **API adaptation note (applies to every task):** the fluent call names below follow the published Pest browser docs (`visit`, `fill`, `click`, `press`, `select`, `check`, `attach`, `assertSee`, `assertUrlIs`, `assertUrlContains`, `assertNoJavascriptErrors`). Before writing the first test, skim `vendor/pestphp/pest-plugin-browser` (README + src/Api) and adapt call names/signatures to the installed version — behavior contracts in each step are the requirement, exact method spelling is not. Note the adaptation in your report.
- Existing global helpers in `tests/Pest.php` (`availableMentor()`, `completeEntrepreneur()`) are reused, not duplicated.
- The repo owner may have uncommitted WIP. NEVER `git add -A` / `.` / `-a`; stage only named files. Imperative commit messages, no co-author trailers.
- UI strings the tests assert against are FACTS from the current code (documented per task); if a click/assert fails because the app text differs, re-check the component before changing the test.
- All paths relative to repo root `/Users/admin/Documents/Projects/UNDP/TreeOfLife/tol-fund`.

---

### Task 1: Toolchain, Browser suite wiring, smoke test

**Files:**
- Modify: `composer.json`/`composer.lock` (via composer), `package.json`/`package-lock or pnpm-lock` (via pnpm), `phpunit.xml`, `tests/Pest.php`
- Create: `tests/Browser/SmokeTest.php`

**Interfaces:**
- Produces: a working `--group=browser` suite wired to `Tests\TestCase` + `RefreshDatabase`; default `php artisan test` excludes the group. Later tasks drop files into `tests/Browser/Journey/`.

- [ ] **Step 1: Install the toolchain**

```bash
composer require pestphp/pest-plugin-browser --dev
pnpm add -D playwright
npx playwright install chromium
```

Expected: composer resolves against Pest 4.7 without conflicts; Chromium downloads.

- [ ] **Step 2: Wire the suite**

In `phpunit.xml`, add inside `<testsuites>`:

```xml
        <testsuite name="Browser">
            <directory>tests/Browser</directory>
        </testsuite>
```

and add as a sibling of `<testsuites>` (directly after it):

```xml
    <groups>
        <exclude>
            <group>browser</group>
        </exclude>
    </groups>
```

In `tests/Pest.php`, next to the existing Feature binding add:

```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->group('browser')
    ->in('Browser');
```

(Match the file's existing import style — it may already import `TestCase`/`RefreshDatabase` unqualified.)

- [ ] **Step 3: Write the smoke test**

Create `tests/Browser/SmokeTest.php`:

```php
<?php

it('renders the sign in page in a real browser', function () {
    $page = visit('/login');

    $page->assertSee('Welcome back')
        ->assertSee('Email address')
        ->assertNoJavascriptErrors();
});
```

- [ ] **Step 4: Verify both run modes**

Run: `php artisan test --group=browser`
Expected: 1 passed (a Chromium window headlessly loads the Svelte login page; first run may be slow while Vite assets build — if the plugin needs a built frontend, run `pnpm build` once and note it).

Run: `php artisan test --compact`
Expected: the full existing suite passes with the browser test EXCLUDED (count unchanged from before this task).

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock package.json pnpm-lock.yaml phpunit.xml tests/Pest.php tests/Browser/SmokeTest.php
git commit -m "Wire up Pest browser testing with an excluded browser group"
```

(If pnpm wrote a different lockfile name, stage that one; verify `git diff --cached --stat` lists only these files.)

---

### Task 2: Invite-mail helper + AdminInvitesTest

**Files:**
- Modify: `tests/Pest.php` (add `inviteUrlFor()`)
- Create: `tests/Browser/Journey/AdminInvitesTest.php`

**Interfaces:**
- Consumes: array mail transport (`MAIL_MAILER=array`), sync queue; admin factory `User::factory()->admin()->approved()`.
- Produces: `inviteUrlFor(string $email): string` — used verbatim by Tasks 3 and 6.

**UI facts this task's clicks rely on:** invitations page button `Invite people`; modal fields `#inv-email` (label `Email address *`), role via custom Select trigger `#inv-role` (options `Entrepreneur` / `Mentor` / `Admin`); submit `Send invitation`; success toast `Invitation sent to {email}`.

- [ ] **Step 1: Add the helper**

Append to `tests/Pest.php`:

```php
/**
 * The accept URL from the captured invitation mail for this address.
 * MAIL_MAILER=array + sync queue means queued invitation mail is rendered
 * and stored in-process the moment it is sent.
 */
function inviteUrlFor(string $email): string
{
    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();

    foreach ($transport->messages() as $sent) {
        $original = $sent->getOriginalMessage();

        $to = collect($original->getTo())->map(fn ($a) => $a->getAddress());

        if (! $to->contains($email)) {
            continue;
        }

        if (preg_match('#https?://[^"\s]*/invitations/accept/[A-Za-z0-9]+#', $original->getHtmlBody() ?? $original->toString(), $m)) {
            return html_entity_decode($m[0]);
        }
    }

    throw new RuntimeException("No invitation mail captured for {$email}.");
}
```

(If the transport API differs on this Symfony Mailer version — e.g. `messages()` returns `SentMessage` without `getOriginalMessage()` — adapt to reach the rendered HTML body; the contract is: return the full accept URL for that recipient or throw.)

- [ ] **Step 2: Write AdminInvitesTest**

Create `tests/Browser/Journey/AdminInvitesTest.php`:

```php
<?php

use App\Models\User;
use App\Models\UserInvitation;

it('lets an admin invite a mentor and an entrepreneur from the invitations page', function () {
    $admin = User::factory()->admin()->approved()->create();
    $this->actingAs($admin);

    $page = visit('/admin/invitations');

    // Invite the mentor.
    $page->click('Invite people')
        ->assertSee('They\'ll get a single-use link to set up their account.')
        ->fill('inv-email', 'mentor@e2e.test')
        ->click('#inv-role')
        ->click('Mentor')
        ->click('Send invitation')
        ->assertSee('Invitation sent to mentor@e2e.test');

    // Invite the entrepreneur (role select defaults to Entrepreneur).
    $page->click('Invite people')
        ->fill('inv-email', 'founder@e2e.test')
        ->click('Send invitation')
        ->assertSee('Invitation sent to founder@e2e.test')
        ->assertSee('mentor@e2e.test')
        ->assertSee('founder@e2e.test')
        ->assertNoJavascriptErrors();

    expect(UserInvitation::where('email', 'mentor@e2e.test')->first()->role->value)->toBe('mentor')
        ->and(UserInvitation::where('email', 'founder@e2e.test')->first()->role->value)->toBe('entrepreneur')
        ->and(inviteUrlFor('mentor@e2e.test'))->toContain('/invitations/accept/')
        ->and(inviteUrlFor('founder@e2e.test'))->toContain('/invitations/accept/');
});
```

(`fill('inv-email', ...)` targets by id; if the installed plugin resolves fields by label instead, use `fill('Email address *', ...)`. The custom Select needs trigger-then-option clicks; if `click('#inv-role')` selector syntax is unsupported, click the visible trigger text `Entrepreneur` then `Mentor`.)

- [ ] **Step 3: Run to green**

Run: `php artisan test --group=browser --filter=AdminInvitesTest`
Expected: PASS. Iterate on selector/API details per the adaptation note; document what differed.

- [ ] **Step 4: Commit**

```bash
git add tests/Pest.php tests/Browser/Journey/AdminInvitesTest.php
git commit -m "Cover admin invitations in the browser and extract invite links from mail"
```

---

### Task 3: InviteAcceptanceTest

**Files:**
- Create: `tests/Browser/Journey/InviteAcceptanceTest.php`

**Interfaces:**
- Consumes: `inviteUrlFor()` (Task 2); `App\Actions\CreateUserInvitation` + `App\Mail\UserInvitationMail` to mint a real tokenized link without the admin UI.

**UI facts:** accept page heading `Create your account`; shows the invited email as static text; fields `Full name` (`#name`), `Password` (`#password`), `Confirm password` (`#password_confirmation`); submit `Create account`; redirects to `/mentor/onboarding` / `/entrepreneur/onboarding` (admin invites go to `/admin/dashboard`).

- [ ] **Step 1: Write the test**

Create `tests/Browser/Journey/InviteAcceptanceTest.php`:

```php
<?php

use App\Actions\CreateUserInvitation;
use App\Enums\UserRole;
use App\Mail\UserInvitationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function browserInvite(string $email, UserRole $role): string
{
    $admin = User::factory()->admin()->approved()->create();
    [$invitation, $token] = app(CreateUserInvitation::class)->handle(
        email: $email,
        role: $role,
        invitedBy: $admin,
    );

    Mail::to($email)->send(new UserInvitationMail($invitation, $token));

    return inviteUrlFor($email);
}

it('registers a mentor through their invite link', function () {
    $url = browserInvite('mentor@e2e.test', UserRole::Mentor);

    visit($url)
        ->assertSee('Create your account')
        ->assertSee('mentor@e2e.test')
        ->fill('name', 'Mia Mentor')
        ->fill('password', 'sturdy-password-1')
        ->fill('password_confirmation', 'sturdy-password-1')
        ->click('Create account')
        ->assertUrlContains('/mentor/onboarding')
        ->assertNoJavascriptErrors();

    expect(User::where('email', 'mentor@e2e.test')->first()->isMentor())->toBeTrue();
});

it('registers an entrepreneur through their invite link', function () {
    $url = browserInvite('founder@e2e.test', UserRole::Entrepreneur);

    visit($url)
        ->assertSee('founder@e2e.test')
        ->fill('name', 'Fatou Founder')
        ->fill('password', 'sturdy-password-1')
        ->fill('password_confirmation', 'sturdy-password-1')
        ->click('Create account')
        ->assertUrlContains('/entrepreneur/onboarding')
        ->assertNoJavascriptErrors();

    expect(User::where('email', 'founder@e2e.test')->first()->isEntrepreneur())->toBeTrue();
});

it('shows the failure state for an expired invitation', function () {
    $url = browserInvite('late@e2e.test', UserRole::Mentor);

    App\Models\UserInvitation::where('email', 'late@e2e.test')
        ->update(['expires_at' => now()->subDay()]);

    visit($url)
        ->assertDontSee('Create your account')
        ->assertNoJavascriptErrors();
});
```

(For the expired case, assert whatever the real failure copy is once seen — check `InvitationAcceptanceController@show`'s invalid-token rendering and pin its actual heading; `assertDontSee` of the form heading is the minimum contract.)

- [ ] **Step 2: Run to green**

Run: `php artisan test --group=browser --filter=InviteAcceptanceTest`
Expected: PASS (3 tests). Pin the expired-state copy while here.

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/Journey/InviteAcceptanceTest.php
git commit -m "Cover invitation acceptance in the browser"
```

---

### Task 4: Upload-input accessibility + fixtures + OnboardingTest

**Files:**
- Modify: `resources/js/pages/mentor/Onboarding.svelte`, `resources/js/pages/entrepreneur/Onboarding.svelte` (upload inputs get accessible names)
- Create: `tests/Fixtures/tiny.png`, `tests/Fixtures/tiny.pdf`
- Create: `tests/Browser/Journey/OnboardingTest.php`

**Interfaces:**
- Consumes: role factories; the onboarding wizards.
- Produces: `tests/Fixtures/` files reused by Task 6; upload inputs targetable as `{Document Label} file` (e.g. `Passport Photo file`).

**UI facts:** both wizards are 4-step steppers with buttons `Save & continue` (steps 0-1), `Continue` (step 2), `Go to dashboard` (step 3), `Back`. Mentor fields: native selects `#pe` (Primary expertise, e.g. `Trade finance`), `#ye` (Years of experience, e.g. `6–10 years`), `#av` (Availability, e.g. `Weekday evenings`), textarea `#af` (AfCFTA knowledge), `Industry focus` checklist (e.g. `Agriculture`). Mentor docs: `Passport Photo`, `Identification Card`, `Certification`. Entrepreneur fields: `#bn` Business name, `#bd` description, `Sectors` checklist (e.g. `Manufacturing`), `#be` Business email, `#bp` Business phone, `#yo` Years in operation, `#ec` Number of employees. Entrepreneur docs: `Business Certificate`, `Business Registration Documents`, `Business Plan`, `Operational Plan`, `Technical Support Requirements`. Upload success toast: `Document uploaded.`; save toast `Progress saved.`; review step success copy `You're all set — your profile is complete.`

- [ ] **Step 1: Make the hidden upload inputs accessible**

In BOTH onboarding pages, find the document-row file input (hidden `<input type="file" accept=".pdf,.png,.jpg,.jpeg,.docx">` inside the upload `<label>`) and add an accessible name derived from the row's document label:

```svelte
<input
    type="file"
    accept=".pdf,.png,.jpg,.jpeg,.docx"
    aria-label={`${doc.label} file`}
    ...existing attributes/handlers unchanged
/>
```

(Read each page first; the loop variable may not be named `doc` — use the actual name. This is an accessibility improvement, not a test hook: screen readers currently announce these inputs with no name.)

- [ ] **Step 2: Create fixtures**

```bash
mkdir -p tests/Fixtures
printf '\x89PNG\r\n\x1a\n' > /dev/null # marker only; use the base64 below
base64 -d > tests/Fixtures/tiny.png <<'EOF'
iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==
EOF
printf '%%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 10 10]>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n0\n%%%%EOF\n' > tests/Fixtures/tiny.pdf
file tests/Fixtures/tiny.png tests/Fixtures/tiny.pdf
```

Expected: `PNG image data, 1 x 1` and `PDF document`.

- [ ] **Step 3: Write OnboardingTest**

First append to `tests/Pest.php` (Task 6 reuses this too):

```php
function fixture(string $name): string
{
    return base_path("tests/Fixtures/{$name}");
}
```

Then create `tests/Browser/Journey/OnboardingTest.php`:

```php
<?php

use App\Data\OnboardingProgress;
use App\Models\User;

it('walks a mentor through onboarding to completion', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    $this->actingAs($mentor);

    $page = visit('/mentor/onboarding');

    // Step: Expertise.
    $page->assertSee('Your expertise')
        ->select('pe', 'Trade finance')
        ->check('Agriculture')
        ->click('Save & continue')
        ->assertSee('Progress saved.');

    // Step: Experience.
    $page->assertSee('Your experience')
        ->select('ye', '6–10 years')
        ->select('av', 'Weekday evenings')
        ->fill('af', 'A decade of cross-border trade finance across AfCFTA markets.')
        ->click('Save & continue')
        ->assertSee('Progress saved.');

    // Step: Documents.
    $page->assertSee('Required documents')
        ->attach('Passport Photo file', fixture('tiny.png'))
        ->assertSee('Document uploaded.')
        ->attach('Identification Card file', fixture('tiny.pdf'))
        ->attach('Certification file', fixture('tiny.pdf'))
        ->click('Continue');

    // Step: Review.
    $page->assertSee("You're all set")
        ->click('Go to dashboard')
        ->assertUrlContains('/mentor/dashboard')
        ->assertNoJavascriptErrors();

    expect(OnboardingProgress::forUser($mentor->refresh())->isComplete)->toBeTrue();
});

it('walks an entrepreneur through onboarding to completion', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $this->actingAs($entrepreneur);

    $page = visit('/entrepreneur/onboarding');

    $page->assertSee('About your business')
        ->fill('bn', 'E2E Textiles')
        ->fill('bd', 'We weave test coverage into cloth.')
        ->check('Manufacturing')
        ->click('Save & continue')
        ->assertSee('Progress saved.');

    $page->assertSee('Contact & scale')
        ->fill('be', 'hello@e2e-textiles.test')
        ->fill('bp', '+254700999888')
        ->select('yo', '3–5 years')
        ->select('ec', '6–20')
        ->click('Save & continue')
        ->assertSee('Progress saved.');

    $page->assertSee('Required documents')
        ->attach('Business Certificate file', fixture('tiny.pdf'))
        ->assertSee('Document uploaded.')
        ->attach('Business Registration Documents file', fixture('tiny.pdf'))
        ->attach('Business Plan file', fixture('tiny.pdf'))
        ->attach('Operational Plan file', fixture('tiny.pdf'))
        ->attach('Technical Support Requirements file', fixture('tiny.pdf'))
        ->click('Continue');

    $page->assertSee("You're all set")
        ->click('Go to dashboard')
        ->assertUrlContains('/entrepreneur/dashboard')
        ->assertNoJavascriptErrors();

    expect(OnboardingProgress::forUser($entrepreneur->refresh())->isComplete)->toBeTrue();
});
```

(Select values: the wizard's native selects may use numeric option values with the quoted labels as text — `select()` by visible option text per the plugin docs; adapt if it needs values. The en-dash in `6–10 years` is the app's real option text.)

- [ ] **Step 4: Verify frontend + run to green**

```bash
pnpm types:check
npx eslint resources/js/pages/mentor/Onboarding.svelte resources/js/pages/entrepreneur/Onboarding.svelte
php artisan test --group=browser --filter=OnboardingTest
php artisan test --compact
```

Expected: types 0 ERRORS; eslint clean; 2 browser tests pass; full default suite untouched and green.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/mentor/Onboarding.svelte resources/js/pages/entrepreneur/Onboarding.svelte tests/Fixtures/tiny.png tests/Fixtures/tiny.pdf tests/Pest.php tests/Browser/Journey/OnboardingTest.php
git commit -m "Cover both onboarding wizards in the browser with accessible upload inputs"
```

---

### Task 5: MentorSelectionTest

**Files:**
- Create: `tests/Browser/Journey/MentorSelectionTest.php`

**Interfaces:**
- Consumes: existing `availableMentor()` / `completeEntrepreneur()` helpers from `tests/Pest.php`.

**UI facts:** mentors page h1 `Mentors`; directory section `Find a mentor`; card button `Choose {firstName}` (e.g. `Choose Grace`); sonner toast `You're now working with Grace Mentor.`; chosen section h2 `Your mentors`; mentor dashboard section `Your mentees`.

- [ ] **Step 1: Write the test**

Create `tests/Browser/Journey/MentorSelectionTest.php`:

```php
<?php

it('lets an entrepreneur choose a mentor who then sees the mentee', function () {
    $mentor = availableMentor('Grace Mentor');
    $entrepreneur = completeEntrepreneur();

    $this->actingAs($entrepreneur);

    visit('/entrepreneur/mentors')
        ->assertSee('Find a mentor')
        ->assertSee('Grace Mentor')
        ->assertSee('Trade finance') // capitalized expertise on the card
        ->click('Choose Grace')
        ->assertSee("You're now working with Grace Mentor.")
        ->assertSee('Your mentors')
        ->assertNoJavascriptErrors();

    $this->actingAs($mentor);

    visit('/mentor/dashboard')
        ->assertSee('Your mentees')
        ->assertSee($entrepreneur->name)
        ->assertNoJavascriptErrors();
});
```

(`availableMentor()` seeds expertise `Trade finance` lowercase in the DB? It stores `'Trade finance'` capitalized already — the card renders it CSS-capitalized either way; assert the string as stored.)

- [ ] **Step 2: Run to green**

Run: `php artisan test --group=browser --filter=MentorSelectionTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/Journey/MentorSelectionTest.php
git commit -m "Cover mentor selection and mentee visibility in the browser"
```

---

### Task 6: FullJourneyTest + docs note

**Files:**
- Create: `tests/Browser/Journey/FullJourneyTest.php`
- Modify: `docs/Tolfund_Laravel_Svelte_Technical_Architecture_and_Developer_Guide.md` (testing chapter: one short subsection on running the Browser suite)

**Interfaces:**
- Consumes: everything above — `inviteUrlFor()`, `fixture()`, all UI facts. NO factory shortcuts inside the test besides the admin account itself.

- [ ] **Step 1: Write the full journey**

Create `tests/Browser/Journey/FullJourneyTest.php` — one test, four actors' flows chained; sign-outs go through the real user menu (`aria-label="Open user menu"` → `Sign out`):

```php
<?php

use App\Models\Pairing;
use App\Models\User;

it('runs the complete invite, onboard, and mentor selection journey', function () {
    User::factory()->admin()->approved()->create([
        'email' => 'admin@e2e.test',
    ]);

    // ── Admin invites both people ────────────────────────────────────
    $page = visit('/login')
        ->fill('email', 'admin@e2e.test')
        ->fill('password', 'password')
        ->click('Continue')
        ->assertUrlContains('/admin/dashboard')
        ->assertNoJavascriptErrors();

    $page->navigate('/admin/invitations')
        ->click('Invite people')
        ->fill('inv-email', 'mentor@e2e.test')
        ->click('#inv-role')
        ->click('Mentor')
        ->click('Send invitation')
        ->assertSee('Invitation sent to mentor@e2e.test')
        ->click('Invite people')
        ->fill('inv-email', 'founder@e2e.test')
        ->click('Send invitation')
        ->assertSee('Invitation sent to founder@e2e.test');

    $page->click('[aria-label="Open user menu"]')
        ->click('Sign out')
        ->assertUrlContains('/login');

    // ── Mentor accepts and onboards ──────────────────────────────────
    $page = visit(inviteUrlFor('mentor@e2e.test'))
        ->fill('name', 'Mia Mentor')
        ->fill('password', 'sturdy-password-1')
        ->fill('password_confirmation', 'sturdy-password-1')
        ->click('Create account')
        ->assertUrlContains('/mentor/onboarding');

    $page->select('pe', 'Trade finance')
        ->check('Agriculture')
        ->click('Save & continue')
        ->assertSee('Progress saved.')
        ->select('ye', '6–10 years')
        ->select('av', 'Weekday evenings')
        ->fill('af', 'Cross-border trade finance experience across AfCFTA markets.')
        ->click('Save & continue')
        ->assertSee('Progress saved.')
        ->attach('Passport Photo file', fixture('tiny.png'))
        ->assertSee('Document uploaded.')
        ->attach('Identification Card file', fixture('tiny.pdf'))
        ->attach('Certification file', fixture('tiny.pdf'))
        ->click('Continue')
        ->assertSee("You're all set")
        ->click('Go to dashboard')
        ->assertUrlContains('/mentor/dashboard')
        ->assertNoJavascriptErrors();

    $page->click('[aria-label="Open user menu"]')
        ->click('Sign out');

    // ── Entrepreneur accepts and onboards ────────────────────────────
    $page = visit(inviteUrlFor('founder@e2e.test'))
        ->fill('name', 'Fatou Founder')
        ->fill('password', 'sturdy-password-1')
        ->fill('password_confirmation', 'sturdy-password-1')
        ->click('Create account')
        ->assertUrlContains('/entrepreneur/onboarding');

    $page->fill('bn', 'E2E Textiles')
        ->fill('bd', 'We weave test coverage into cloth.')
        ->check('Manufacturing')
        ->click('Save & continue')
        ->assertSee('Progress saved.')
        ->fill('be', 'hello@e2e-textiles.test')
        ->fill('bp', '+254700999888')
        ->select('yo', '3–5 years')
        ->select('ec', '6–20')
        ->click('Save & continue')
        ->assertSee('Progress saved.')
        ->attach('Business Certificate file', fixture('tiny.pdf'))
        ->assertSee('Document uploaded.')
        ->attach('Business Registration Documents file', fixture('tiny.pdf'))
        ->attach('Business Plan file', fixture('tiny.pdf'))
        ->attach('Operational Plan file', fixture('tiny.pdf'))
        ->attach('Technical Support Requirements file', fixture('tiny.pdf'))
        ->click('Continue')
        ->assertSee("You're all set")
        ->click('Go to dashboard')
        ->assertUrlContains('/entrepreneur/dashboard')
        ->assertNoJavascriptErrors();

    // ── Entrepreneur selects the mentor ──────────────────────────────
    $page->navigate('/entrepreneur/mentors')
        ->assertSee('Mia Mentor')
        ->click('Choose Mia')
        ->assertSee("You're now working with Mia Mentor.")
        ->assertSee('Your mentors')
        ->assertNoJavascriptErrors();

    $page->click('[aria-label="Open user menu"]')
        ->click('Sign out');

    // ── The mentor sees their mentee ─────────────────────────────────
    visit('/login')
        ->fill('email', 'mentor@e2e.test')
        ->fill('password', 'sturdy-password-1')
        ->click('Continue')
        ->assertUrlContains('/mentor/dashboard')
        ->assertSee('Your mentees')
        ->assertSee('Fatou Founder')
        ->assertNoJavascriptErrors();

    // The database agrees with everything the browser saw.
    $pairing = Pairing::sole();
    expect($pairing->mentor->email)->toBe('mentor@e2e.test')
        ->and($pairing->entrepreneur->email)->toBe('founder@e2e.test')
        ->and($pairing->status->value)->toBe('active');
});
```

(The admin factory's default password is `password` — verify in `database/factories/UserFactory.php`; if it differs, set one explicitly in the create call. `navigate()` vs a fresh `visit()` — use whichever the plugin provides for same-session navigation.)

- [ ] **Step 2: Run to green, then run everything**

```bash
php artisan test --group=browser --filter=FullJourneyTest
php artisan test --group=browser
php artisan test --compact
```

Expected: the journey passes; all browser suites pass together; the default suite is unchanged and green.

- [ ] **Step 3: Document the suite**

In the architecture guide's testing chapter, add a short subsection:

```markdown
### Browser test suite

Real-browser journey tests (Pest v4 + Playwright Chromium) live in
`tests/Browser`. They are excluded from the default run; execute them with:

    php artisan test --group=browser

One-time setup on a fresh machine: `npx playwright install chromium`.
```

(Anchor it wherever the guide discusses running tests; read the chapter first.)

- [ ] **Step 4: Commit**

```bash
git add tests/Browser/Journey/FullJourneyTest.php docs/Tolfund_Laravel_Svelte_Technical_Architecture_and_Developer_Guide.md
git commit -m "Prove the full invite to mentorship journey in a real browser"
```
