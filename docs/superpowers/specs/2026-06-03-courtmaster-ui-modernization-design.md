# CourtMaster UI Modernization — Design Spec

**Date:** 2026-06-03
**Branch:** `feature/mobile-ui-refactor`
**Status:** Approved in brainstorm; pending spec review

## Goal

Modernize and polish the existing CourtMaster UI **without redesigning it**. Keep the
current identity, navigation, colors, and structure. The problem to solve is
*inconsistency, spacing, hierarchy, and mobile usability* — not the design language.
Outcome target: "CourtMaster 2.0" — instantly recognizable, just cleaner and more premium.

## Non-Goals

- No new CSS framework. **No Tailwind.** Stays Bootstrap 5 + the existing `app.scss`.
- No new layout structure, no navigation overhaul, no new charting library (ApexCharts stays).
- No per-page bespoke redesign of the ~75 non-hero pages — they inherit the shared kit.

## Calibration: intensity "B · Refined"

Every change is tuned to the **Refined (B)** intensity (chosen from a 3-way A/B/C
comparison): a clear, premium upgrade that stays unmistakably CourtMaster. Not the
barely-noticeable "light polish" (A), not the redesign-risking "bold" treatment (C).

Concretely, B means: larger primary numbers, corner-aligned icons, soft-tint pills
for trends/status, gentle layered shadows, consistent 8/12/16/20px radii, and reduced
color noise — all inside the existing emerald + slate palette.

## Architecture / where changes live

The whole app shares **one `resources/scss/app.scss`** + shared Blade components
(`x-card`, `x-stat-card`, `x-button`, `x-page-header`, `x-badge`, `x-empty-state`,
`x-input`) + 3 near-identical layouts (app / super / customer). Editing the design
system therefore cascades to ~100 pages. The work splits in two:

1. **Shared kit** — token + component edits in `app.scss` and the `x-*` components.
   Cascades to all ~80 pages automatically. ~70% of the consistency win.
2. **Hero pages** — 4 pages with bespoke layouts that get individual treatment on top
   of the kit.

## 1. Shared Kit (approved)

Applies everywhere via `app.scss` + shared components. Desktop and mobile.

- **Buttons** — one height, one radius. Primary keeps the emerald gradient + glow so it
  clearly leads. Destructive defaults to **outline** (`btn-outline-danger`) so "Delete"
  stops shouting in dense UIs; solid danger reserved for confirm-modals. Secondary/ghost
  recede (muted slate, transparent).
- **Status badges** — replace solid Bootstrap fills with a unified **soft-tint + status-dot**
  set: Active (emerald), Pending (amber), Cancelled (red), Expired (slate), Completed
  (blue), all at ~12–14% bg opacity with a leading dot. Far less color noise in tables.
- **Form fields & search** — consistent 8px radius, roomier padding, 44px min tap height
  on mobile (16px font to avoid iOS zoom), on-brand emerald focus ring. Search promoted to
  a full-width field with a leading emerald icon.
- **Empty states** — gradient icon chip + a friendly title + one-line description + a clear
  primary CTA (instead of plain gray text). Stays compact, not oversized.
- **Mobile table rows** — the shared `.table-stack` utility renders each row as a readable
  card: leading identity (avatar + primary id + status) at top, key money bold, secondary
  fields as label:value or on a tinted strip. No horizontal scrolling.

## 2. Hero Pages (approved)

### 2.1 Admin Dashboard (`admin/dashboard.blade.php`)
Keep all sections, hero concept, and data. Changes:
- KPI cards adopt the B stat-card (big number, corner icon, trend pill, util mini-bar).
- Hero band gains the **New Booking / Status Board** actions inline (reachable without scroll).
- Court Status uses soft dot badges + live remaining-time; Recent Bookings render as cards
  (not a sideways-scrolling table) on mobile.
- On mobile, the heavy lower sections (**Customer Analytics**, **Shortcuts**) become
  **collapsible** to cut the long scroll. **Desktop layout unchanged** (4-up KPIs, 8/4 chart split).

### 2.2 Bookings list (`admin/bookings/index.blade.php`)
- Search promoted to full-width; the cramped date+status+filter row becomes
  **scrollable status chips** (All / Today / Pending / Active / …) + a "More" filter sheet.
- Mobile booking card leads with **avatar + booking# + status**; amount bold; court + time on
  one tinted strip; soft dot badges; "balance due" in danger tint; inline "View →".
- **Desktop stays a real table** (refined header + dot badges). List/Calendar toggle,
  pagination, and every existing filter retained.

### 2.3 POS (`admin/pos/index.blade.php`)
- **Mobile only:** the cart stops stacking below the product grid. It becomes a
  **persistent bottom cart bar** (item count + running total + "Pay") that expands into a
  **full-height order sheet** (line items with ±, promo, payment method, amount tendered,
  change, Process Payment).
- Product cards get the kit treatment; low-stock as a soft amber chip.
- **Desktop untouched** (8/4 grid + sticky side cart). Same `posSystem()` Alpine state and
  totals math — purely a change of *where the cart renders* on small screens.

### 2.4 Customer Booking flow (`customer/bookings/create.blade.php`) — **3-step wizard**
Convert the single long form into a **guided 3-step wizard** on mobile:
1. **Court & time** — court, date, start + duration, availability verdict.
2. **Payment** — Wallet / Court Credit / Cash selection cards + promo code.
3. **Confirm** — notes + price summary + Confirm.
- Progress bar across the top; sticky **Back / Continue** bar at the bottom.
- Validation gates between steps (e.g. can't choose payment before a valid slot;
  Continue disabled until the step is satisfiable — reuses the existing `submitDisabled`
  / `selectedSlot` / `walletCovers` / `creditFullyCovers` logic).
- **All existing fields and the `bookingForm()` Alpine logic are preserved** — this is a
  presentation layer (step container + progress + nav) wrapping the same inputs.
- Desktop may keep the single-page form or adopt the same stepped layout (decide during
  implementation; wizard is the mobile-priority deliverable).

> Note: this is the one hero page where the user chose the bolder of two options (wizard
> over "polished single page"). It is the largest single-page change in scope.

## 3. The remaining ~75 pages

No bespoke design. They inherit the shared kit automatically because they use the shared
components + `app.scss`. Required follow-up: a **QA sweep** per portal (Admin, Customer,
Super, Auth) to catch pages that hand-rolled markup instead of using `x-*` components, and
migrate those to the components / kit classes. This is verification + cleanup, not redesign.

## Accessibility & mobile baselines (carried throughout)

- Tap targets ≥ 40–44px on mobile (already partly in `app.scss` §28).
- 16px input font on mobile to prevent iOS zoom-on-focus.
- Emerald focus-visible ring app-wide.
- Color is never the only status signal — the badge dot + text label carry meaning.

## Risks / watch-items

- **Badge migration:** many pages use `text-bg-*` / `bg-*-subtle` inline. Moving to the dot
  set means either a shared `x-badge` status map or a global CSS normalization. Prefer the
  component map so it's centralized.
- **Booking wizard** touches real money/availability logic — must keep `bookingForm()`
  state intact and re-verify `submitDisabled` gating per step.
- **Auth pages** are pinned light via `.auth-page` high-specificity resets — kit changes to
  `.form-control` etc. must be checked there (see `premium-ui-refresh` memory).
- **Dark mode**: every kit change must be verified in both light and `data-bs-theme="dark"`.

## Out of scope for this spec (possible future work)

- Desktop booking wizard parity.
- Bottom-nav visual refresh beyond kit-level polish.
- Per-page analytics/chart restyling beyond color/legend consistency.
