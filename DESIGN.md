---
name: Tolfund
description: A warm, status-driven mentorship and meeting-support platform — trustworthy, grounded, human.
colors:
  primary: "#024017"
  primary-deep: "#013012"
  accent-orange: "#dc541e"
  accent-yellow: "#e0bf00"
  secondary-blue: "#6cb6da"
  background: "#ffecc2"
  surface: "#ffffff"
  text-primary: "#401602"
  text-secondary: "#7c5b4b"
  text-on-dark: "#ffecc2"
  border-light: "#f0d8a8"
  border-dark: "#5a2811"
  success: "#2f7f33"
  warning: "#e0bf00"
  error: "#c72929"
typography:
  display:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 600
    lineHeight: 1.25
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "0.04em"
rounded:
  sm: "4px"
  md: "8px"
  lg: "12px"
  pill: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  2xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.text-on-dark}"
    rounded: "{rounded.md}"
    padding: "10px 20px"
  button-primary-hover:
    backgroundColor: "{colors.primary-deep}"
    textColor: "{colors.text-on-dark}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "{rounded.md}"
    padding: "10px 20px"
  button-ghost:
    backgroundColor: "{colors.background}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  chip-status:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  input-field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text-primary}"
    rounded: "{rounded.lg}"
    padding: "24px"
---

# Design System: Tolfund

## 1. Overview

**Creative North Star: "The Greenhouse"**

Tolfund is a greenhouse for entrepreneurs: a warm, structured, well-lit place where mentor–entrepreneur relationships are cultivated, tracked, and brought to maturity. The green primary (`#024017`, a deep cultivated leaf) is the growth; the cream background (`#ffecc2`, warm morning light through glass) is the shelter; the earth-brown ink (`#401602`) is the soil everything is rooted in. This is not a bank and not a government portal — it is a supportive institution that is careful with people's time and growth, and the interface must feel like it is *handling this properly* while still treating people with warmth and dignity.

The system is built for operators under repeated daily pressure. Its first job on every screen is to make the current **state** and the next **legal action** unmistakable — status is the interface, not decoration on top of it. Density is a feature: reviewers clear queues in tables and timelines, not in spacious marketing layouts. But density only earns its keep when warmth, hierarchy, and contrast keep it legible. The warm palette carries the human side; exact numbers, aligned columns, and strong status visibility carry the trust.

This system explicitly rejects the **generic SaaS dashboard** (no purple gradients, no hero-metric template, no endless identical icon-card grids), the **cold corporate bank** (no sterile navy-and-gray fintech chill), the **cluttered government portal** (no dated, weak-hierarchy chrome, no 1998-era unstyled tables), and the **consumer/playful app** (no gamification, no emoji-driven UI, no cartoonish 24px+ corners that undercut the serious, supportive tone).

**Key Characteristics:**

- **Warm, not cold.** Cream + green + earth. Warmth lives in color, copy, and generosity of guidance — never in decoration.
- **Status-first.** Every pairing, meeting, and report shows its state and next action before anything else.
- **Calm density.** Tables, queues, and timelines over spacious cards. Legible under pressure.
- **Precise.** Times, dates, and permissions read as deliberate and correct — alignment and formatting signal care.
- **Role-true.** Admin, mentor, and entrepreneur get purpose-built chrome, never a one-size shell with hidden controls.

## 2. Colors

A warm earth palette: deep cultivated green as the voice of action and trust, a cream field as the ground, brown as ink, and a tightly scoped set of accents and semantic status colors. Authored in HSL in `resources/css/app.css` (`@theme`); hex equivalents appear in the frontmatter for tooling.

### Primary
- **Cultivated Green** (`#024017` · `hsl(140, 94%, 13%)`): The brand voice. Primary buttons, active nav, key totals, brand chrome, and any surface that must read as "Tolfund, handled." Deliberately deep and low-lightness so cream text sits on it at ~10:1. Note: this hue was rotated from the original brown `#401602` at *identical* saturation and lightness — green and ink are the same color wearing different clothes.
- **Cultivated Green Deep** (`#013012` · synthesized): The hover/active step under Cultivated Green. Used only as the pressed/hover state of primary surfaces.

### Secondary
- **Sky Blue** (`#6cb6da` · `hsl(200, 60%, 64%)`): A cool counterweight to the warm field. Informational accents, secondary data series, links inside dense data, `info` status. Used sparingly; it is the one cool note in a warm room.

### Tertiary
- **Ember Orange** (`#dc541e` · `hsl(17, 76%, 49%)`): Warm emphasis and attention — pending/awaiting-action highlights, non-error callouts that need heat. Never for primary actions (that is green's job).
- **Harvest Yellow** (`#e0bf00` · `hsl(51, 100%, 44%)`): High-visibility marker; doubles as the `warning` semantic. Reserve for genuine attention; it is loud by design.

### Neutral
- **Warm Field** (`#ffecc2` · `hsl(41, 100%, 88%)`): The page background. The greenhouse light. Every screen sits on this warm cream, never on pure white.
- **Surface White** (`#ffffff` · synthesized): The content plane — cards, tables, inputs, and panels lift off the cream field as clean white.
- **Earth Ink** (`#401602` · `hsl(19, 94%, 13%)`): Primary text. The original brand brown; near-black warmth for maximum legibility on cream (>13:1).
- **Muted Earth** (`#7c5b4b` · `hsl(20, 25%, 39%)`): Secondary text, captions, meta. Passes 4.5:1 on both cream and white — do not push it lighter "for elegance."
- **Cream Ink** (`#ffecc2` · = Warm Field): Text and icons *on* green/dark surfaces. The inverse of Earth Ink.
- **Light Border** (`#f0d8a8` · `hsl(40, 70%, 80%)`): Hairline dividers, table rules, input strokes on the cream field.
- **Dark Border** (`#5a2811` · `hsl(19, 68%, 21%)`): Strong borders, focus outlines on light surfaces, emphasis rules.

### Semantic Status
- **Success Green** (`#2f7f33` · `hsl(123, 46%, 34%)`): Approved, completed, active-in-good-standing. Note it is *brighter and cooler* than the brand primary, so "success" never blurs with "brand."
- **Warning Yellow** (`#e0bf00` · = Harvest Yellow): Needs attention, expiring, changes requested.
- **Error Red** (`#c72929` · `hsl(0, 66%, 47%)`): Rejected, failed, overdue, blocked, destructive confirmation.

### Named Rules
**The Cream Field Rule.** Every screen sits on Warm Field cream (`#ffecc2`). Content lifts off it on Surface White. Pure white is never the page background, and cream is never a content plane — the two-layer warmth is the identity.

**The Green-Is-Action Rule.** Cultivated Green means "the primary thing you can do" and "Tolfund itself." It is never decoration and never a status. Status is carried only by the semantic quartet (Success / Warning / Error / Info-blue). If green and Success Green ever appear adjacent, the Success color must be the brighter cool one so the two do not read as the same state.

**The Accent Rarity Rule.** Ember Orange and Harvest Yellow together occupy ≤10% of any screen. Their heat is the signal; spend it only on genuine attention and pending action.

## 3. Typography

**Display / Body / Label Font:** Instrument Sans (with `ui-sans-serif, system-ui, sans-serif` fallback).

**Character:** One warm humanist sans carries everything — headings, data, labels, body. Instrument Sans is friendly enough to hold the warmth but neutral and legible enough to pack a dense review queue without noise. This is a product UI: one well-tuned family beats a display/body pairing.

### Hierarchy
- **Display** (600, 1.75rem / 28px, line-height 1.2): Page titles — "Meetings," "Report #1042." One per screen. Fixed rem, never fluid clamp; a page title should not shrink inside a sidebar.
- **Headline** (600, 1.375rem / 22px, line-height 1.25): Section headers within a page, panel titles.
- **Title** (600, 1.125rem / 18px, line-height 1.3): Card titles, table group headers, dialog titles.
- **Body** (400, 0.9375rem / 15px, line-height 1.5): Default reading text and form values. Cap prose at 65–75ch; data and table cells may run denser.
- **Label** (600, 0.75rem / 12px, letter-spacing 0.04em, UPPERCASE): Status chips, table column headers, field labels, metadata keys. The quiet workhorse of a status-driven UI.

### Named Rules
**The One-Family Rule.** Instrument Sans only. Never a display serif, never a second sans, never a script. The Inter stylesheet still loaded on the starter `Welcome.svelte` is a leftover to remove, not a second font.

**The Fixed-Scale Rule.** Type sizes are fixed rem steps (~1.2 ratio), not `clamp()`. Users work at consistent DPI; fluid headings that shrink in panels look worse, not more responsive.

## 4. Elevation

Tolfund is **flat with a warm two-layer ground**, not a shadow-driven system. Depth comes from the cream-field-vs-Surface-White contrast and from hairline borders (Light Border on the field, Dark Border for emphasis), not from stacked drop shadows. The only ambient lift permitted is a single soft, low shadow to float a content surface off the cream field, and structural shadows for genuinely floating layers — dropdowns, popovers, modals, toasts. Resting cards do **not** cast shadows; they are defined by their border and their white plane.

### Shadow Vocabulary
- **Field Lift** (`box-shadow: 0 1px 2px rgba(64, 22, 2, 0.06)`): Optional, subtle. Floats a primary content card off the cream field. Tinted with Earth Ink, never neutral gray-black.
- **Floating Layer** (`box-shadow: 0 8px 24px rgba(64, 22, 2, 0.14)`): Dropdowns, popovers, modals, toasts — things that genuinely sit above the page. The one place a defined shadow is correct.

### Named Rules
**The Flat-Field Rule.** Surfaces are flat at rest. Elevation is a response to *floating above the page* (menus, modals), not a default decoration on every card. If a resting card has both a 1px border and a wide soft shadow, delete the shadow.

**The Warm-Shadow Rule.** Shadows are tinted with Earth Ink (`rgba(64,22,2,...)`), never `rgba(0,0,0,...)`. A neutral-black shadow on a warm cream field reads cold and wrong.

## 5. Components

For each component: character first, then shape, color, states, and behavior. Every interactive component ships with **default, hover, focus-visible, active, disabled**, and where relevant **loading** and **error** — never half of these.

### Buttons
- **Shape:** Gently squared (8px radius / `{rounded.md}`). Never pill-shaped for actions; never 24px+.
- **Primary:** Cultivated Green fill (`#024017`) with Cream Ink text, 10px × 20px padding. The one high-emphasis action per context. Hover → Cultivated Green Deep (`#013012`). Focus-visible → 2px Dark Border outline with offset. Active → Deep + slight inset. Disabled → 50% opacity, no hover.
- **Secondary:** Surface White fill, Cultivated Green text, 1px Cultivated Green border. For the second-most-likely action. Hover → 4% green tint fill.
- **Ghost:** Transparent on the cream field, Earth Ink text, no border. For low-emphasis / tertiary actions (Cancel, table row actions). Hover → subtle Light Border tint.
- **Destructive:** Error Red fill or Error Red text-on-outline, reserved for irreversible actions; always paired with a confirmation.

### Chips (Status)
- **Style:** Pill (`{rounded.pill}`), 12px uppercase Label type, 2px × 10px padding. This is the signature component — status is the interface.
- **Never color-only.** Every status chip pairs its semantic color with a **text label** (and where useful an icon or shape): "Approved," "Pending," "Changes Requested," "Rejected." Color-blind users and glance-scanning operators both depend on the word, not just the hue.
- **Mapping:** Success Green = approved / completed / active. Warning Yellow = needs attention / expiring / changes requested. Error Red = rejected / failed / overdue. Sky Blue = info / draft-in-review. Muted Earth = neutral/inactive (draft, deactivated). Filled chip for active states, tinted-background + colored-text chip for quieter states.

### Cards / Containers
- **Corner Style:** 12px (`{rounded.lg}`). Cards are for *repeated items* (a pairing tile, a list row expanded) or genuinely framed tools — not as decoration around every block. Never nest a card inside a card.
- **Background:** Surface White on the cream field.
- **Shadow Strategy:** Flat by default (border-defined). Field Lift shadow only when a card must clearly float above the field. See Elevation.
- **Border:** 1px Light Border. Dark Border only for an emphasized/selected card.
- **Internal Padding:** 24px (`{spacing.2xl}`); 16px in dense contexts.

### Inputs / Fields
- **Style:** Surface White fill, 1px Light Border, 8px radius (`{rounded.md}`), 8px × 12px padding, Earth Ink value text, Label-style field label above.
- **Focus:** Border shifts to Cultivated Green + a 2px green focus ring. No glow, no color-shift animation beyond 150ms.
- **Error:** Border and helper text switch to Error Red; the message states what to fix in plain language.
- **Disabled:** Cream-tinted fill, Muted Earth text, no focus ring.
- **Placeholder:** Muted Earth at full 4.5:1 contrast — never a pale gray that fails against white.

### Navigation
- **Style:** Role-specific chrome (admin / mentor / entrepreneur), typically a top bar plus side nav. Cultivated Green or cream-on-green for the active surface.
- **Type:** Label-style (12px, tracked) for nav groups; Body weight for items.
- **States:** Default Earth Ink; hover Light Border tint; **active** carries a Cultivated Green marker (left indicator bar *inside* the nav is acceptable as an active indicator — this is the one legitimate accent stripe, and only for current-item, never as card decoration).
- **Mobile:** Side nav collapses to a drawer; the bar stays. Structural responsive behavior, not fluid type.

### Data Table (Signature)
The primary surface for admins and mentors — review queues, pairings, meetings, reports.
- Surface White plane on the cream field; Light Border row rules; Label-style uppercase column headers.
- Right-align all numeric columns; tabular figures; consistent date and time formatting. Precision is the trust signal.
- Row hover → faint cream tint. Selected row → Light Border-dark left edge + tint.
- Status column always uses a Status Chip, never bare colored text.
- **Skeleton rows** on load (not a centered spinner). **Empty state** teaches the next action ("No meetings awaiting a report — you're clear"), never a bare "Nothing here."

## 6. Do's and Don'ts

### Do:
- **Do** sit every screen on Warm Field cream (`#ffecc2`) and lift content onto Surface White. The two-layer warmth is the brand.
- **Do** reserve Cultivated Green (`#024017`) for primary actions and Tolfund's own chrome — action and identity, never status.
- **Do** carry status with the semantic quartet (Success `#2f7f33` / Warning `#e0bf00` / Error `#c72929` / Info `#6cb6da`) **and always a text label**. Never encode status by color alone.
- **Do** keep Muted Earth (`#7c5b4b`) as the floor for secondary text — it passes 4.5:1 on cream and white. Verify contrast on the warm background, where muted text easily falls short.
- **Do** use one family, Instrument Sans, at fixed rem sizes across headings, data, and labels.
- **Do** lead with tables, queues, and timelines. Density is legible when hierarchy and warm contrast hold.
- **Do** ship every interactive component with default/hover/focus-visible/active/disabled, plus skeleton loading and teaching empty states.
- **Do** tint shadows with Earth Ink (`rgba(64,22,2,...)`) and keep surfaces flat at rest.

### Don't:
- **Don't** build a **generic SaaS dashboard** — no purple gradients, no hero-metric template (giant number + tiny label + supporting stats), no endless identical icon+heading+text card grids.
- **Don't** drift toward a **cold corporate bank** — no sterile navy-and-gray fintech coldness. The palette is warm on purpose; keep it human.
- **Don't** ship a **cluttered government portal** — no dense, dated, hard-to-scan chrome; no unstyled 1998-era data tables; no weak hierarchy.
- **Don't** make it a **consumer/playful app** — no gamification, no emoji-driven UI, no over-rounded (24px+) cartoonish corners that undercut the serious, supportive tone.
- **Don't** use a `border-left`/`border-right` colored stripe greater than 1px as decoration on cards, list items, or callouts. (The single exception: a nav active-item indicator.)
- **Don't** pair a 1px border with a wide soft drop shadow on the same resting card — pick one.
- **Don't** use pure white as a page background or cream as a content plane. Don't use `rgba(0,0,0,...)` shadows on the warm field.
- **Don't** introduce a second font. Remove the leftover Inter stylesheet from `Welcome.svelte`; Instrument Sans is the only family.
- **Don't** let Cultivated Green stand in for "success," or use full-saturation accents (Ember/Harvest) on inactive or resting states.
- **Don't** reach for a modal as the first thought — exhaust inline and progressive alternatives first.
