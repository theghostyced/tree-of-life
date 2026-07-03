# Navbar User Menu & Role Badge Relocation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a profile popup (identity + disabled Profile/Settings + working Sign out) to the admin navbar avatar, and move the "Viewing as Role" badge from the Dashboard header into the navbar.

**Architecture:** A new `UserMenu.svelte` layout component wraps the existing shadcn-svelte `dropdown-menu` primitive (bits-ui). `AppNavbar.svelte` renders `RoleBadge` + `UserMenu` in its desktop utilities cluster and real user data in its mobile drawer. `Dashboard.svelte` drops its `RoleBadge`. No backend changes — `auth.user` / `auth.role` are already shared Inertia props.

**Tech Stack:** Svelte 5 (runes), Inertia.js Svelte adapter, bits-ui via `components/ui/dropdown-menu`, Tailwind with project color tokens, Lucide icons.

## Global Constraints

- Never hardcode colors — only Tailwind tokens (`bg-surface`, `text-ink`, `border-line`, `bg-accent-soft`, `text-accent`, `text-muted`, `text-faint`, `bg-elevated`, `ring-accent/60`) per CLAUDE.md.
- Components never live under `resources/js/pages/` — reusable pieces go in `resources/js/components/<domain>/` (CLAUDE.md "Inertia And Svelte").
- Follow existing sibling idioms: Svelte 5 runes (`$derived`, `$props`), `page` from `@inertiajs/svelte` accessed directly (e.g. `page.props`), `cn()` from `@/lib/utils`, Lucide icons with `strokeWidth={1.75}`.
- No JS unit-test framework is configured (no vitest/jest); verification is `pnpm types:check`, `pnpm lint:check`, and driving the app in the browser. Do not add a test framework.
- Commit messages: imperative mood, no co-author trailers.
- All paths below are relative to the repo root `/Users/admin/Documents/Projects/UNDP/TreeOfLife/tol-fund`.

---

### Task 1: Create the UserMenu component

**Files:**
- Create: `resources/js/components/layout/UserMenu.svelte`

**Interfaces:**
- Consumes: `page.props.auth` (`Auth` from `@/types/auth`), `* as DropdownMenu` from `@/components/ui/dropdown-menu`, `RoleBadge` from `@/components/ui/role-badge`, `router` from `@inertiajs/svelte`.
- Produces: `<UserMenu />` — no props; self-contained avatar button + dropdown. Task 2 imports it as `import UserMenu from './UserMenu.svelte';`.

- [ ] **Step 1: Write the component**

Create `resources/js/components/layout/UserMenu.svelte`:

```svelte
<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { LogOut, Settings, UserRound } from '@lucide/svelte';
    import * as DropdownMenu from '@/components/ui/dropdown-menu';
    import { RoleBadge } from '@/components/ui/role-badge';
    import type { Auth } from '@/types/auth';

    /**
     * Navbar avatar button + identity dropdown, fed by the shared Inertia
     * `auth` prop. Profile and Settings stay disabled until their routes
     * exist (same convention as the disabled nav links).
     */
    const auth = $derived(page.props.auth as Auth);

    const initials = $derived.by(() => {
        const name = auth.user.name?.trim();

        if (name) {
            const parts = name.split(/\s+/);
            const first = parts[0][0] ?? '';
            const last = parts.length > 1 ? (parts[parts.length - 1][0] ?? '') : '';

            return (first + last).toUpperCase();
        }

        return auth.user.email.charAt(0).toUpperCase();
    });

    function signOut() {
        router.post('/logout');
    }
</script>

<DropdownMenu.Root>
    <DropdownMenu.Trigger
        aria-label="Open user menu"
        class="ml-2 flex size-8 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent outline-none transition-colors hover:border-accent/50 focus-visible:ring-2 focus-visible:ring-accent/60"
    >
        {initials}
    </DropdownMenu.Trigger>
    <DropdownMenu.Content
        align="end"
        sideOffset={8}
        class="w-64 border border-line bg-surface p-1.5 text-ink"
    >
        <DropdownMenu.Label class="flex items-center gap-3 px-2.5 py-2">
            <div
                class="flex size-9 shrink-0 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
                aria-hidden="true"
            >
                {initials}
            </div>
            <div class="min-w-0 leading-tight">
                <p class="truncate text-sm font-medium text-ink">
                    {auth.user.name}
                </p>
                <p class="truncate text-xs font-normal text-faint">
                    {auth.user.email}
                </p>
                <RoleBadge role={auth.role} class="mt-1.5" />
            </div>
        </DropdownMenu.Label>
        <DropdownMenu.Separator class="bg-line" />
        <DropdownMenu.Item disabled class="px-2.5 py-2 text-muted">
            <UserRound class="size-4" strokeWidth={1.75} />
            Profile
        </DropdownMenu.Item>
        <DropdownMenu.Item disabled class="px-2.5 py-2 text-muted">
            <Settings class="size-4" strokeWidth={1.75} />
            Settings
        </DropdownMenu.Item>
        <DropdownMenu.Separator class="bg-line" />
        <DropdownMenu.Item onSelect={signOut} class="px-2.5 py-2 text-muted">
            <LogOut class="size-4" strokeWidth={1.75} />
            Sign out
        </DropdownMenu.Item>
    </DropdownMenu.Content>
</DropdownMenu.Root>
```

- [ ] **Step 2: Type-check**

Run: `pnpm types:check`
Expected: `0 ERRORS` (4 pre-existing warnings in auth components are fine).

- [ ] **Step 3: Lint**

Run: `pnpm lint:check`
Expected: exits 0 with no new errors for `UserMenu.svelte`. If prettier ordering complains, run `pnpm format` and re-check.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/layout/UserMenu.svelte
git commit -m "Add UserMenu dropdown component for the navbar avatar"
```

---

### Task 2: Wire UserMenu and RoleBadge into AppNavbar (desktop + mobile)

**Files:**
- Modify: `resources/js/components/layout/AppNavbar.svelte`

**Interfaces:**
- Consumes: `<UserMenu />` from Task 1; `RoleBadge` from `@/components/ui/role-badge`; existing `page` import; `router` from `@inertiajs/svelte`.
- Produces: navbar renders the badge + live avatar menu; mobile drawer shows real identity and a Sign out row. Nothing downstream consumes new APIs.

- [ ] **Step 1: Add imports and auth state**

In `resources/js/components/layout/AppNavbar.svelte`, change the script header. The import line `import { Link, page } from '@inertiajs/svelte';` becomes:

```ts
import { Link, page, router } from '@inertiajs/svelte';
```

Add `LogOut` to the Lucide import list:

```ts
import {
    LayoutGrid,
    ClipboardList,
    Award,
    Flag,
    FolderKanban,
    Users,
    Send,
    Search,
    Bell,
    Settings,
    Menu,
    X,
    LogOut,
} from '@lucide/svelte';
```

Below `import { cn } from '@/lib/utils';` add:

```ts
import { RoleBadge } from '@/components/ui/role-badge';
import UserMenu from './UserMenu.svelte';
import type { Auth } from '@/types/auth';
```

Below `const currentUrl = $derived(page.url);` add:

```ts
const auth = $derived(page.props.auth as Auth);
```

- [ ] **Step 2: Replace the static desktop avatar**

Replace this block inside the desktop utilities `<div>`:

```svelte
<div
    class="ml-2 flex size-8 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
    aria-hidden="true"
>
    AD
</div>
```

with:

```svelte
<RoleBadge role={auth.role} class="ml-2" />
<UserMenu />
```

- [ ] **Step 3: Replace the hardcoded mobile user row and add Sign out**

Replace this block at the bottom of the mobile drawer:

```svelte
<div class="mt-1 flex items-center gap-3 px-3 py-3">
    <div
        class="flex size-9 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
        aria-hidden="true"
    >
        AD
    </div>
    <div class="leading-tight">
        <p class="text-sm font-medium text-ink">Admin</p>
        <p class="text-xs text-faint">Administrator</p>
    </div>
</div>
```

with:

```svelte
<div class="mt-1 flex items-center gap-3 px-3 py-3">
    <div
        class="flex size-9 items-center justify-center rounded-full border border-line bg-accent-soft text-[12px] font-semibold text-accent"
        aria-hidden="true"
    >
        {auth.user.name
            .trim()
            .split(/\s+/)
            .map((part) => part[0])
            .slice(0, 2)
            .join('')
            .toUpperCase() || auth.user.email.charAt(0).toUpperCase()}
    </div>
    <div class="min-w-0 leading-tight">
        <p class="truncate text-sm font-medium text-ink">
            {auth.user.name}
        </p>
        <p class="truncate text-xs text-faint">{auth.user.email}</p>
    </div>
    <RoleBadge role={auth.role} class="ml-auto shrink-0" />
</div>

<button
    type="button"
    onclick={() => router.post('/logout')}
    class={mobileRowClass(false)}
>
    <LogOut class="size-5" strokeWidth={1.75} />
    Sign out
</button>
```

- [ ] **Step 4: Type-check and lint**

Run: `pnpm types:check && pnpm lint:check`
Expected: `0 ERRORS` from svelte-check; eslint exits 0.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/layout/AppNavbar.svelte
git commit -m "Show role badge and live user menu in the navbar"
```

---

### Task 3: Remove the RoleBadge from the Dashboard header

**Files:**
- Modify: `resources/js/pages/admin/Dashboard.svelte`

**Interfaces:**
- Consumes: nothing new.
- Produces: Dashboard header shows only the `h1`; the navbar owns the badge.

- [ ] **Step 1: Remove the badge usage**

In `resources/js/pages/admin/Dashboard.svelte`, delete the line:

```svelte
<RoleBadge {role} />
```

and unwrap the `h1` from its now-unneeded flex row: replace

```svelte
<div class="flex items-center gap-3">
    <h1 class="text-3xl font-semibold tracking-tight text-ink">
        Performance
    </h1>
    <RoleBadge {role} />
</div>
```

with

```svelte
<h1 class="text-3xl font-semibold tracking-tight text-ink">
    Performance
</h1>
```

- [ ] **Step 2: Remove dead imports/state**

Delete `import { RoleBadge } from '@/components/ui/role-badge';` and the `const role = $derived((page.props.auth as Auth).role);` line plus its comment. If `Auth` and `page` are no longer referenced anywhere else in the file (check with `grep -n "page\.\|Auth" resources/js/pages/admin/Dashboard.svelte`), delete their imports too; otherwise leave them.

- [ ] **Step 3: Type-check and lint**

Run: `pnpm types:check && pnpm lint:check`
Expected: `0 ERRORS`; eslint clean (it will flag any unused import you missed in Step 2).

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/admin/Dashboard.svelte
git commit -m "Move the viewer role badge out of the dashboard header"
```

---

### Task 4: Browser verification end-to-end

**Files:**
- None (verification only).

**Interfaces:**
- Consumes: the running app; admin seeder credentials `admin@tolfund.com` / `password`.
- Produces: confirmation the spec's manual checklist passes.

- [ ] **Step 1: Start the app**

Run: `composer run dev` (starts Laravel + Vite together; if that script does not exist, run `php artisan serve` and `pnpm dev` in two background shells).
Expected: app responds at the printed local URL.

- [ ] **Step 2: Verify in the browser**

Sign in as `admin@tolfund.com` / `password`, then confirm on `/admin/dashboard`:

1. Navbar right side shows "Viewing as Admin" badge, then the avatar with real initials ("TA" for Tolfund Admin).
2. Clicking the avatar opens the popup: name, email, role badge; disabled Profile and Settings (dimmed, not clickable); Sign out.
3. Arrow keys move between enabled items; Escape closes and returns focus to the avatar.
4. The Dashboard "Performance" heading no longer shows the badge.
5. Narrow the window below `lg`: the drawer shows real name/email + badge + Sign out row.
6. Sign out redirects to the login screen.

- [ ] **Step 3: Report results**

No commit — report any deviation back for a fix before declaring done.
