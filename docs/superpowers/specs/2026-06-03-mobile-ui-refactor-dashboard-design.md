# Mobile UI/UX Refactor — Foundation + Dashboard

**Date:** 2026-06-03
**Status:** Approved design, pre-implementation
**Scope of this pass:** A shared mobile foundation (applied app-wide) + a full mobile refactor of the **admin dashboard** (`/admin/dashboard`). Subsequent admin pages, and the customer/super-admin portals, are explicitly **out of scope** for this pass and will reuse the foundation in later passes.

---

## 1. Background & problem

The admin portal (`resources/views/layouts/app.blade.php` + `resources/scss/app.scss`) is already a polished, premium Bootstrap 5 theme (dark-slate + emerald) with real responsive behavior:

- Sidebar collapses to an off-canvas drawer below `lg` with an overlay (`app.js` `Alpine.store('sidebar')`).
- Topbar adapts; dashboard KPI grid and hero stats already reflow on small screens.

The gap is **consistency and reach**, not a missing foundation:

1. **Navigation cost.** On phones every action is two taps: open the hamburger drawer, then pick. There is no fast path to high-frequency screens.
2. **Ad-hoc table responsiveness.** The table→card "stacking" pattern is reimplemented per page with divergent breakpoints — dashboard `recent-table` stacks at **575.98px**, bookings `bk-table` stacks at **767.98px** — and most of the other ~55 admin views simply horizontal-scroll their tables inside `.table-responsive`.
3. **Touch targets & spacing.** `.btn-sm`, icon buttons, and form controls can fall below comfortable tap sizes; `.content-wrapper` keeps 1.5rem padding even at 320px, costing usable width; `.page-header` actions can crowd the title.

This pass fixes the foundation once, then proves it on the dashboard.

## 2. Goals / non-goals

**Goals**
- Add a permission-aware mobile **bottom tab bar** (operations-first) for one-tap reach to key screens; keep the drawer for the full menu.
- Establish a **single** responsive table→card utility (`.table-stack`) and adopt it on the dashboard.
- Raise mobile touch targets to ~44px and tighten small-screen spacing.
- Fully refactor the dashboard for 320–768px with **zero horizontal scroll** and no loss of features/data.
- Preserve the existing design language, branding, dark mode, and all business logic.

**Non-goals**
- No changes to controllers, routes, models, services, or any business logic.
- No desktop (`≥ lg`) visual changes beyond incidental shared-token tweaks.
- No refactor of other admin pages or the customer/super-admin portals in this pass.
- No new JS framework or dependency; reuse Alpine + existing Bootstrap bundle.

## 3. Design

### 3.1 Mobile bottom tab bar (global)

**Component:** new partial `resources/views/partials/mobile-bottom-nav.blade.php`, included in `layouts/app.blade.php` after the topbar (and only meaningful on the admin layout for this pass).

**Visibility:** shown below `lg` only (`d-lg-none`), matching the drawer breakpoint. Hidden on desktop, where the sidebar is the nav.

**Tabs (operations-first, left→right):**
1. **Home** → `admin.dashboard` (icon `bi-house-door`)
2. **Bookings** → `admin.bookings.index` (icon `bi-calendar-check`)
3. **➕ New Booking** (center FAB) → `admin.bookings.create` (icon `bi-plus-lg`)
4. **Courts** → `admin.courts.index` (icon `bi-grid`)
5. **More** → opens the existing drawer via `@click="$store.sidebar.openMobile()"` (icon `bi-list`)

**Permission awareness.** Each non-"More" tab is gated with the same checks used in the sidebar (`$isOwner || $u->can('…')`). To avoid empty slots when a user lacks a permission, the bar is built from an **ordered candidate list** and renders the first N the user can access:

- Fixed first slot: Home (always).
- Center FAB: New Booking if `bookings.create`/owner, else first creatable among {POS sale `pos.access`}, else hidden FAB (bar falls back to even 4-slot layout).
- Remaining slots filled in priority order from: Bookings (`bookings.view`) → Courts (`courts.view`) → Status Board (`courts.view`) → POS (`pos.access`) → Customers (`customers.view`).
- Final slot is always **More** (opens full drawer), so nothing is ever unreachable.

Implementation: a small `@php` array of `[label, icon, route, can]` candidates filtered with the same gating expressions already used in the layout; take the first available to fill slots 2 and 4; FAB resolved separately; More appended last.

**Active state.** Reuse `request()->routeIs(...)` (same approach as `x-nav-item`) to mark the active tab.

**Styling (`app.scss`, new `// ── Mobile bottom nav` block):**
- Fixed bottom, full width, height 58px, `z-index: 1035` (above content `topbar`=1030, below drawer `sidebar`=1040 and overlay=1039 so the open drawer covers it).
- Surface matches topbar: `rgba(255,255,255,.92)` + `backdrop-filter: blur(10px)`; dark-mode variant `rgba(15,23,42,.9)`.
- Top hairline using the existing border color; subtle top shadow.
- **iOS safe area:** `padding-bottom: env(safe-area-inset-bottom)` and add the inset to the height so the bar clears the home indicator.
- Tab: column layout, 18px icon + 8px label, `color: var(--bs-secondary-color)`; active tab emerald (`$primary`). Min tap target 44px tall per tab.
- Center FAB: 44px circle, emerald gradient (reuse `.btn-primary` gradient/shadow tokens), raised `-20px` to overlap the bar.
- Show only `< lg` via the same `media-breakpoint-down(lg)` guard used for the sidebar drawer.

**Content offset.** Below `lg`, add `padding-bottom` to `.main-content` (≈ `calc(58px + env(safe-area-inset-bottom) + .5rem)`) so page content and the last card are never hidden behind the bar. The bar must also not cover the drawer or its overlay (z-index ordering above).

### 3.2 Responsive table→card utility `.table-stack` (global)

Promote the existing `bk-table` pattern into one reusable class in `app.scss`, replacing the per-page `<style>` variants.

- **Markup contract:** add class `table-stack` to a `<table>`; add `data-label="…"` to each `<td>`; mark an actions cell with `.cell-actions` (renders as a full-width button row) and label-less cells with `.cell-plain` (suppresses the `::before` label).
- **Below 768px** (`max-width: 767.98px`): `thead` hidden; `table/tbody/tr/td` become block; each `tr` becomes a card (1px border, `.85rem` radius, `var(--bs-card-bg)`, margin between rows); each `td` is a flex row with the `data-label` (uppercase, muted) on the left and the value on the right; `.cell-actions` buttons stretch full width.
- **At/above 768px:** behaves as a normal table — no change.
- Works with Laravel pagination and `.table-responsive` wrappers (the wrapper simply never needs to scroll on mobile once stacked).

Dashboard adopts `.table-stack`; `bk-table` on the bookings page can migrate later (out of scope now, but the utility is built to absorb it).

### 3.3 Touch targets & spacing (global)

In `app.scss`, scoped to `media-breakpoint-down(md)` / `(lg)` as appropriate so desktop is unchanged:
- Buttons: ensure `min-height` ~40px (`.btn-sm` ~38px) and adequate horizontal padding on touch widths; icon-only buttons (`.topbar-btn`, table action links) ~44px hit area.
- Form controls (`.form-control`, `.form-select`): `min-height: 44px` on mobile; `font-size: 16px` on inputs at `< md` to prevent iOS zoom-on-focus.
- `.content-wrapper`: reduce padding to `1rem` below 576px (currently 1.5rem) to reclaim width.
- `.page-header`: on mobile, stack `.page-title` above `.page-actions`, with `.page-actions` going full-width (buttons `flex:1` or `w-100`).

### 3.4 Dashboard refactor (`resources/views/admin/dashboard.blade.php`)

Keep all data bindings, Alpine analytics fetch, ApexCharts, and routes. Changes are layout/markup/CSS only:
- **Hero:** action buttons (`New Booking`, `Status Board`) become full-width stacked (`d-grid gap-2`) below `sm`; tighten hero padding on xs. (KPI grid + hero-stats 2×2 already correct — keep.)
- **Charts row:** confirm `col-12` stacking on mobile; cap chart container heights and ensure ApexCharts `width:100%` so no horizontal overflow at 320px.
- **Court Status list:** keep; ensure each row ≥44px tap height.
- **Recent Bookings:** replace the bespoke 575px `recent-table` stacking with `.table-stack` (768px). Each row card surfaces: customer (avatar + name), booking number, amount, court · time, status badge; the row links to `admin.bookings.show`. Add `data-label`s; drop the now-dead `recent-table` `<style>`.
- **Quick actions:** keep 2-col tiles; bump tile tap area / icon size on mobile.
- **Revenue by method:** keep (already stacks).
- Remove the page's now-redundant mobile `<style>` rules that the shared utilities replace; keep dashboard-only decorative styles (hero gradient, etc.).

### 3.5 Loading / empty / validation states

Reuse existing primitives (`.skeleton`, `<x-empty-state>`, flash alerts). Only standardize where the dashboard already shows them (e.g. analytics "Loading…" / empty rows remain). Add a shared mobile-friendly invalid-feedback spacing rule if needed; no new components required for this pass.

## 4. Files touched

- `resources/views/partials/mobile-bottom-nav.blade.php` — **new** bottom tab bar partial.
- `resources/views/layouts/app.blade.php` — include the bottom nav; ensure main content bottom offset class hook.
- `resources/scss/app.scss` — new sections: mobile bottom nav, `.table-stack`, touch-target/spacing tokens, content offset.
- `resources/views/admin/dashboard.blade.php` — hero buttons, recent-bookings → `.table-stack`, remove redundant mobile `<style>`, minor tap-size tweaks.

No PHP/JS logic files change. (`app.js` sidebar store is reused as-is by the "More" tab.)

## 5. Verification

Build assets (`npm run build` or dev) and check `/admin/dashboard` at **320 / 375 / 414 / 768px**:
- No horizontal scroll at any width.
- Bottom bar visible < lg, hidden ≥ lg; tabs reflect permissions; active tab correct; FAB → New Booking; "More" opens the drawer and the drawer/overlay correctly cover the bar.
- Last card / page bottom is not hidden behind the bar (content offset works); safe-area padding present.
- Recent Bookings renders as cards (no scroll); each card links to the booking.
- Buttons/inputs meet ~44px; inputs don't trigger iOS zoom.
- Dark mode intact across new surfaces; desktop layout unchanged.
- All dashboard data/charts/analytics still load (logic untouched).

## 6. Risks & mitigations

- **z-index conflicts** between bottom bar, drawer, overlay, modals → explicit ordering (bar 1035 < overlay 1039 < drawer 1040; Bootstrap modals 1050+ remain on top).
- **Content hidden behind bar** → main-content bottom offset + safe-area inset, verified at test widths.
- **Permission edge cases** (staff with few permissions) → ordered candidate fill guarantees 4 slots + always-present "More".
- **Regression on desktop** → all new rules guarded by `media-breakpoint-down`; desktop selectors untouched.
