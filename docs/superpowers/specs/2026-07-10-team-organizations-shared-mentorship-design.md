# Team Organizations and Shared Mentorship — Design Spec

**Date:** 2026-07-10
**Status:** For review

## Goal

Let an entrepreneur invite employees into their organization, and turn the mentorship model from **per user** into **per organization**: the whole team shares its mentors, co-attends the same meetings, and talks to each mentor in one shared thread. Reuse the existing invitation, chat, meetings, and notification systems rather than rebuild them.

This re-architects the app's core (pairings, meetings, conversations) from per user to per organization, so it ships in phases, each keeping the app working and tested, with a backfill for existing data.

## Copy rules

No hyphens in user facing copy. Warm, professional, concrete (house style, already in use).

---

## 1. The organization model

- A **Company** is the organization. `owner_id` is the founding entrepreneur. Its **members** are the users whose `company_id` points at it (the owner is a member too).
- Every entrepreneur owns exactly one Company. Employees belong to exactly one Company (no multi org membership in v1).
- **Roles inside an org:** `entrepreneur` = owner (invites and manages the team); `employee` = member (full participant in the shared mentorship, cannot manage the team or the org profile). Mentors and admins are unchanged.

### Company creation and sync
- Created when an **entrepreneur accepts their own invitation** (`AcceptUserInvitation`), owner set to them, name seeded from the invitation or a placeholder.
- Its `name` is kept in sync with the entrepreneur's `business_name` as they fill onboarding (the org is their business).
- **Backfill:** a migration creates a Company for every existing entrepreneur who lacks one, and points their existing pairings and profile at it.

---

## 2. Mentorship becomes per organization

Today: `Pairing(entrepreneur_user_id, mentor_user_id)`, and meetings, reports, and conversations hang off the pairing.

New: a pairing is between a **Company and a mentor**.

- Add `company_id` to `pairings`; a pairing is unique per `(company_id, mentor_user_id)`. Keep `entrepreneur_user_id` as `initiated_by` history, but all scoping moves to `company_id`.
- **Selecting a mentor is an org action:** any member browsing the mentor directory can add a mentor, which creates the org's pairing with that mentor (or reuses it). The whole org is then paired with that mentor; every member sees that mentor, their meetings, and their thread.
- Access rule everywhere: a user may see a pairing (and its meetings, reports, chat) if they are a **member of the pairing's company** or the **pairing's mentor**.

### Backfill
Set `pairings.company_id` from the entrepreneur member's company for all existing pairings.

---

## 3. Shared meetings (members co-attend)

- A meeting still belongs to a pairing, now a company ↔ mentor pairing. Record `booked_by_user_id` (which member booked it).
- **Any member can book** a call on any of the org's mentor pairings, using the existing availability calendar and `BookMeeting` (booking becomes org scoped, not entrepreneur scoped).
- **Every member sees and can join** all of the org's meetings on their Meetings page, via the same meeting link. No per member RSVP in v1 — a member joins a call by opening its link.
- Reports are unchanged (the mentor files one report per meeting); all members see it.

---

## 4. Shared mentor chat (one thread, many members)

- A conversation still maps one to one to a pairing, but its **participants are every org member plus the mentor** (the chat already uses a `conversation_participants` many to many, so this is an extension, not a rebuild).
- When an employee joins the org, they are **added as a participant to all the org's existing conversations**; when the org adds a mentor, a conversation is provisioned with all current members plus that mentor (extends the existing `PairingObserver`).
- Group thread behavior: messages from any member or the mentor are visible to all participants. The thread shows **sender names** (it is no longer strictly two people). Unread counts and read receipts become per participant; presence stays as is. System messages (for example a booked call) post once to the shared thread.

---

## 5. Team management (owner)

A new **Team** page for the owner at `/entrepreneur/team`:
- Invite an employee (name and email) using the existing `EmployeeInvitationController` and invitation email.
- See the **roster**: pending invitations and joined members, each with status.
- **Revoke** a pending invitation; **remove** a member (detaches them from the org and its conversations; their own account remains).
- Members (employees) do not see this page.

---

## 6. Employee experience and access

- Employees use the **same shared workspace** as the owner: the mentor directory, meetings, and messages, all org scoped. They do not onboard (no business profile of their own) and do not manage the team.
- **Route and role:** the shared workspace routes open to both `entrepreneur` and `employee`. The Team page and the org profile stay owner only.
- **Onboarding gates:** the `account.active` and profile completeness gates key off the **organization's** onboarding (the owner's business profile), not the individual employee, so members are not bounced to an onboarding they do not have. Employees land in the workspace on login.

---

## 7. Notifications fan out

Recipients shift from "the entrepreneur" to "the org members" where it makes sense:
- Meeting booked, reminders (a day and an hour before), report available, reschedule reviewed: to **all members** of the org (plus the mentor where relevant), not only the owner. The mentor side is unchanged.
- Report submitted: admins, unchanged.
- **Employee invited:** the existing invitation email. **Employee joined:** notify the owner (in app), and optionally announce in the shared threads.
- The in app bell, realtime, and email templates are reused as is.

---

## 8. Data model summary

Changed or new:
- `companies` already exists; ensure `owner_id`, `name`. Every entrepreneur owns one.
- `pairings`: add `company_id` (indexed), unique `(company_id, mentor_user_id)`.
- `meetings`: add `booked_by_user_id` (nullable).
- `conversation_participants`: already many to many; membership now spans all org members plus the mentor.
- `users.company_id`: already exists; set for owners and members.
- Backfills: companies for existing entrepreneurs, `pairings.company_id`, conversation participants.

---

## 9. Authorization

A single notion: **org membership**. Replace entrepreneur specific checks (own pairing, own meeting, own conversation) with "the user is a member of the resource's company, or is the mentor." Update the relevant policies, gates, and controller scoping. Admins retain their access.

---

## 10. Non goals (v1)

- Per member meeting RSVP or attendance tracking (any member may join any org call).
- Org permission tiers beyond owner vs member.
- A user belonging to more than one org; leaving or transferring orgs.
- Reassigning or deleting a member's historical messages when removed (their past messages stay in the thread; they lose future access).
- Mentors seeing org structure beyond the shared thread and meetings.

---

## 11. Phasing (each phase ships working and tested)

1. **Company and Team.** Company created at acceptance and backfilled for existing entrepreneurs; owner Team page (invite, roster, revoke, remove); employees accept and join. No changes to pairings, meetings, or chat yet. Fully shippable.
2. **Org scoped pairings.** Add `pairings.company_id`, backfill, move mentor selection and scoping to the org. Mentors page and pairing logic become org scoped.
3. **Shared meetings.** Members see and book and join the org's meetings; `booked_by_user_id`; meetings pages org scoped. Reminder scheduler fans out.
4. **Shared chat.** Conversations include all members plus the mentor; group thread UI (sender names, per participant unread); observer provisions participants on join and on new mentor.
5. **Notifications and access polish.** Fan out notifications to members; open workspace routes and onboarding gates to employees; owner notified when a member joins.

---

## 12. Testing

- Company: created on entrepreneur acceptance; backfill creates exactly one per existing entrepreneur; name syncs from business profile.
- Invitations: owner invites an employee; acceptance sets `company_id` and joins the org; roster shows pending and joined; revoke and remove work; a non owner cannot access Team.
- Pairings: any member can add a mentor; one pairing per org and mentor; members share it; access is by membership.
- Meetings: any member books; all members see and can join; authorization by membership; reminders reach all members.
- Chat: all members plus mentor are participants; a new member is added to existing threads; group messages visible to all; unread per participant.
- Notifications: fan out to members on the emailed and in app events; owner notified on member join.
- Regression: the existing chat, meetings, calendar, reminders, and notification suites stay green after each phase.
