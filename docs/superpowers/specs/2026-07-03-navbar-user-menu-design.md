# Navbar User Menu & Role Badge Relocation — Design

**Date:** 2026-07-03
**Status:** Approved

## Goal

Two changes to the admin navbar (`resources/js/components/layout/AppNavbar.svelte`):

1. Clicking the user's profile photo area opens a popup (dropdown menu) with the
   user's identity, placeholder Profile/Settings items, and a working Sign out.
2. The "Viewing as &lt;Role&gt;" `RoleBadge` moves from the Dashboard page header
   into the navbar, so it is visible on every admin page. The role also repeats
   inside the popup's identity header.

## Approach

Build the popup on the existing shadcn-svelte `dropdown-menu` component
(`components/ui/dropdown-menu`, backed by bits-ui). This provides focus
management, Escape/outside-click dismissal, arrow-key navigation, and menu ARIA
for free, and matches how other overlays (e.g. `Select`) are built.

Rejected alternatives: a hand-rolled popover (reimplements accessibility bits-ui
already solves, bloats the navbar file) and the native HTML popover/`<details>`
element (no menu semantics, inconsistent with the codebase).

## Components

### New: `resources/js/components/layout/UserMenu.svelte`

- Reads `page.props.auth` (existing `Auth` type from `@/types/auth`). No props;
  auth is global Inertia shared state.
- **Trigger:** the avatar circle — a real `<button>` (today it is a static
  `aria-hidden` "AD" div). Shows initials derived from `user.name`, falling back
  to the first letter of `user.email`. Keeps the existing `size-8` /
  `bg-accent-soft` styling; adds hover and `focus-visible` ring consistent with
  the other utility buttons. bits-ui supplies `aria-haspopup`/`aria-expanded`.
- **Popup** (right-aligned below the trigger, `bg-surface` + `border-line`):
  1. Identity header (`DropdownMenu.Label`): avatar initials, name, email, and a
     compact `RoleBadge`.
  2. Separator; **Profile** and **Settings** items rendered disabled (routes do
     not exist yet), matching the disabled nav-link pattern.
  3. Separator; **Sign out** item calling Inertia `router.post('/logout')`. The
     server redirect handles navigation.

### Modified: `AppNavbar.svelte`

- Desktop utilities cluster: replace the static avatar div with
  `<RoleBadge role={auth.role} /> <UserMenu />`. The badge is hidden below `lg`
  (the mobile drawer covers small screens).
- Mobile drawer user row: replace hardcoded "Admin / Administrator" with real
  `user.name` / `user.email`, add the `RoleBadge`, and add a Sign out row that
  posts to `/logout`.

### Modified: `pages/admin/Dashboard.svelte`

- Remove the `RoleBadge` import and its usage beside the "Performance" heading.
  The navbar owns the badge now.

## Data flow

No backend changes. `auth.user` and `auth.role` are already shared via
`HandleInertiaRequests`. The menu reads only `name` and `email`. (The middleware
currently shares the raw user model, which CLAUDE.md discourages; tightening
that is out of scope here.)

## Error handling

- Sign out uses an Inertia POST; on failure Inertia surfaces the error page —
  no custom handling needed.
- Missing `user.name` falls back to email-derived initials so the trigger never
  renders empty.

## Testing

- `pnpm types:check` and `pnpm lint:check` pass.
- Manual browser verification: popup opens on click, arrow keys navigate,
  Escape closes and returns focus, Sign out redirects to the login screen, the
  badge shows in the bar on desktop and in the drawer on mobile, and the
  Dashboard header no longer shows the badge.
