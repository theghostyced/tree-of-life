# Team Organizations and Shared Mentorship — Implementation Plan

Executes `docs/superpowers/specs/2026-07-10-team-organizations-shared-mentorship-design.md`. Phased; each phase ends green and shippable.

## Global constraints
- No hyphens in user facing copy.
- Reuse existing systems (invitations, chat, meetings, notifications); do not rebuild.
- Every phase keeps the full suite green; backfill existing data so nothing breaks.
- shadcn components first; page content capped at max-w-7xl (Team page is a normal page).

## Phase 1 — Company and Team (this phase)

### 1a. Company on acceptance + backfill
- `AcceptUserInvitation`: when the accepted role is Entrepreneur, create a Company owned by the new user (name from invitation name or a placeholder).
- Sync the Company `name` from `business_name` whenever the entrepreneur profile is saved (in the entrepreneur `ProfileController@update`, beside the onboarding check).
- Migration/backfill: create one Company for every existing entrepreneur who lacks an `ownedCompany`, name from their `business_name` when present, and set the owner's `company_id` to it.
- Tests: acceptance creates the company; backfill is one per entrepreneur and idempotent; name syncs on profile save.

### 1b. Team page (owner)
- `EmployeeInvitationController`: add `index` (roster) rendering `entrepreneur/Team`, plus `destroy` for a pending invitation (revoke) and `removeMember` for a joined member. Keep the existing `store` (invite).
- Roster data: pending invitations for the owner's company (email, name, invited at, status) and joined members (name, email, joined at) excluding the owner.
- Routes (owner only, `role:entrepreneur`, `account.active`): `GET /entrepreneur/team`, `POST /entrepreneur/employees` (exists), `DELETE /entrepreneur/team/invitations/{invitation}`, `DELETE /entrepreneur/team/members/{member}`.
- Authorization: the invitation/member must belong to the owner's company.
- Frontend `entrepreneur/Team.svelte`: invite form (name + email, shadcn inputs + button), roster table (pending + joined, shadcn table + role/status chips), revoke and remove actions with sonner toasts. Empty state.
- Nav: add Team to the entrepreneur layout links.
- Optional: notify the owner in app when a member joins (reuse notification base).
- Tests: owner sees roster; invite adds a pending row; accept moves it to joined; revoke and remove work; a member (employee) is forbidden from the Team page and its actions.

## Phase 2 — Org scoped pairings
Add `pairings.company_id`, backfill, move mentor selection and all pairing scoping to the company. (Planned in detail when Phase 1 lands.)

## Phase 3 — Shared meetings
`booked_by_user_id`; members see, book, and join org meetings; meetings pages and reminder scheduler org scoped.

## Phase 4 — Shared chat
Conversations include all members plus the mentor; group thread UI; observer provisions participants on join and on new mentor.

## Phase 5 — Notifications and access polish
Fan out notifications to members; open workspace routes and onboarding gates to employees; owner notified on member join.
