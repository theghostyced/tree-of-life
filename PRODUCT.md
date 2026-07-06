# Product

## Register

product

## Users

Tolfund is an invitation-only platform that connects entrepreneurs with mentors and supports that relationship through scheduled meetings and a report for every meeting. Three roles, each in a different operational context:

- **Admins** — program operators who invite and vet people, manage accounts (revoke / restore / remove), monitor mentor–entrepreneur pairings, and keep an eye on whether meetings are happening and reports are being captured. Context: focused, repeated daily use; they keep the program running and accountable.
- **Entrepreneurs** — founders who complete a profile, choose a mentor from the approved mentors in the system, schedule meetings, and read the report from each meeting. Context: goal-oriented, often less technical; needs clear guidance and status at every step.
- **Mentors** — advisors who complete a profile, set their availability, meet with paired entrepreneurs on a schedule, and write a short report after each meeting. Context: periodic, schedule-and-report oriented.

The job to be done: connect the right mentor to each entrepreneur, make meetings easy to schedule and keep, and leave a clear record — a report — of every meeting, so the relationship is well supported and the program stays accountable. Every actor needs to know *what state something is in* and *what they can do next*.

**Tolfund does not move money and runs no funding workflow.** It is a mentorship and meeting-support system only — no applications, awards, milestones, or disbursements.

## Product Purpose

Tolfund runs a mentorship program end to end: onboarding, mentor–entrepreneur pairing, availability, scheduling, meetings, and a report for every meeting. It exists to make a multi-party working relationship transparent and accountable — replacing spreadsheets and email with a single system of record where authorization is enforced server-side. Success looks like: admins always know who is paired and whether meetings are happening, entrepreneurs and mentors always know their next meeting and next action, and every meeting leaves a durable, reviewable report.

## Brand Personality

**Trustworthy · grounded · warm.**

Serious about people's time and growth, but human and encouraging. Tolfund is a supportive institution, not a cold portal. The voice is plain, direct, and reassuring — it tells people exactly where they stand and what to do next, without jargon or false cheer. Emotional goals: confidence (this is handled properly), clarity (I know my next step), and dignity (I'm supported, not processed). The warm cream/green/earth palette carries the human side; precision and strong status visibility carry the trust.

## Anti-references

This should NOT look like:

- **Generic SaaS dashboard** — no purple gradients, no hero-metric template (giant number + tiny label + supporting stats), no endless identical icon+heading+text card grids. Data should be shown in queues, tables, and timelines, not decorative tiles.
- **Cold corporate portal** — no sterile navy-and-gray coldness. The palette is warm on purpose; keep it human.
- **Cluttered government portal** — no dense, dated, hard-to-scan chrome; no unstyled 1998-era data tables; no weak hierarchy. Density must stay *legible*.
- **Consumer/playful app** — no gamification, no emoji-driven UI, no over-rounded (24px+) cartoonish corners that undercut the serious, supportive tone.

## Design Principles

1. **Status is the interface.** Every pairing, meeting, and report has a state. The primary job of every screen is to make the current state and the next legal action unmistakable. Design around state machines, not around content.
2. **Calm density.** Operators use this repeatedly. Prefer tables, queues, and timelines that pack information legibly over spacious marketing layouts. Density earns its keep only when hierarchy and contrast keep it scannable.
3. **Trust through precision.** Times, dates, and who-meets-whom are exact. Names, times, and permissions must read as deliberate and correct — alignment, formatting, and consistency signal that the system is careful with people's time.
4. **Warmth without whimsy.** The human, encouraging tone lives in copy, color, and generosity of guidance — never in decoration that would undercut the supportive seriousness.
5. **Role-true screens.** Admin, mentor, and entrepreneur see purpose-built chrome and navigation. Never a one-size shell with hidden controls; each role's most common task is the fastest path on their screen.

## Accessibility & Inclusion

Target **WCAG 2.1 AA**:

- Body text ≥ 4.5:1 contrast; large text ≥ 3:1 (verify against the warm cream background, where muted text easily falls short).
- Full keyboard navigation and visible focus states on every interactive element.
- Respect `prefers-reduced-motion` with a crossfade/instant fallback for every animation.
- Do not encode status by color alone — pair color with label, icon, or shape (color-blind safety; matters heavily for a status-driven UI).
- Clear, plain-language error, empty, and loading states — users span a wide range of technical fluency.
