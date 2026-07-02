# Product

## Register

product

## Users

Tolfund is an invitation-only platform with three distinct roles, each in a different operational context:

- **Admins** — program operators who review applications, vet profiles, approve/reject accounts, set planned disbursements, release funds, and manage invitations. They work in review queues and need to move through many items quickly and confidently. Context: focused, repeated daily use; decisions carry financial and accountability weight.
- **Entrepreneurs** — funded (or applying) founders who complete onboarding, browse funding programs, submit applications, manage milestones and progress updates, pay the mentorship subscription, pair with a mentor, and book sessions. Context: goal-oriented, milestone-driven, often less technical; needs clear guidance and status at every step.
- **Mentors** — advisors who review entrepreneur milestones and progress, manage availability, and run mentorship sessions. Context: periodic, review-and-schedule oriented.

The job to be done: move money and mentorship to entrepreneurs through an auditable, rule-bound workflow — application → award → milestones → disbursement, plus a paid mentorship track. Every actor needs to know *what state something is in* and *what they can do next*.

## Product Purpose

Tolfund administers a funding-and-mentorship program end to end: funding programs, applications, awards, milestones, disbursements, mentorship subscriptions, mentor pairing, availability, and sessions. It exists to make a high-stakes, multi-party workflow transparent, enforceable, and auditable — replacing spreadsheets and email with a single system of record where authorization and financial controls are enforced server-side. Success looks like: reviewers clear queues without ambiguity, entrepreneurs always know their next action and status, funds never over-disburse, and every high-value action is traceable.

## Brand Personality

**Trustworthy · grounded · warm.**

Serious about money and accountability, but human and encouraging. Tolfund is a supportive institution, not a cold bank or a faceless government portal. The voice is plain, direct, and reassuring — it tells people exactly where they stand and what to do next, without jargon or false cheer. Emotional goals: confidence (this is handled properly), clarity (I know my next step), and dignity (I'm supported, not processed). The warm cream/green/earth palette carries the human side; precision and strong status visibility carry the trust.

## Anti-references

This should NOT look like:

- **Generic SaaS dashboard** — no purple gradients, no hero-metric template (giant number + tiny label + supporting stats), no endless identical icon+heading+text card grids. Data should be shown in queues, tables, and timelines, not decorative tiles.
- **Cold corporate bank** — no sterile navy-and-gray fintech coldness. The palette is warm on purpose; keep it human.
- **Cluttered government portal** — no dense, dated, hard-to-scan chrome; no unstyled 1998-era data tables; no weak hierarchy. Density must stay *legible*.
- **Consumer/playful app** — no gamification, no emoji-driven UI, no over-rounded (24px+) cartoonish corners that undercut financial seriousness.

## Design Principles

1. **Status is the interface.** Every award, application, milestone, disbursement, and session has a state. The primary job of every screen is to make the current state and the next legal action unmistakable. Design around state machines, not around content.
2. **Calm density.** Operators use this repeatedly under pressure. Prefer tables, queues, and timelines that pack information legibly over spacious marketing layouts. Density earns its keep only when hierarchy and contrast keep it scannable.
3. **Trust through precision.** Money, deadlines, and authorization are exact. Numbers, amounts, dates, and permissions must read as deliberate and correct — alignment, formatting, and consistency signal that the system is careful with people's stakes.
4. **Warmth without whimsy.** The human, encouraging tone lives in copy, color, and generosity of guidance — never in decoration that would undercut financial seriousness.
5. **Role-true screens.** Admin, mentor, and entrepreneur see purpose-built chrome and navigation. Never a one-size shell with hidden controls; each role's most common task is the fastest path on their screen.

## Accessibility & Inclusion

Target **WCAG 2.1 AA**:

- Body text ≥ 4.5:1 contrast; large text ≥ 3:1 (verify against the warm cream background, where muted text easily falls short).
- Full keyboard navigation and visible focus states on every interactive element.
- Respect `prefers-reduced-motion` with a crossfade/instant fallback for every animation.
- Do not encode status by color alone — pair color with label, icon, or shape (color-blind safety; matters heavily for a status-driven UI).
- Clear, plain-language error, empty, and loading states — users span a wide range of technical fluency.
