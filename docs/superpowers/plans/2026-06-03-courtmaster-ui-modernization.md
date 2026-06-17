# CourtMaster UI Modernization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Modernize CourtMaster's UI to the approved "Refined (B)" intensity — refine the shared design kit (cascades to ~80 pages) and apply bespoke mobile improvements to 4 hero pages — without redesigning the app or adding any new framework.

**Architecture:** All changes live in `resources/scss/app.scss` + the shared anonymous Blade components (`resources/views/components/*.blade.php`) + 4 hero Blade views. Assets compile with Vite + Sass (`npm run build`). The kit is built first as the foundation; each hero page then layers on top. Desktop layouts are preserved; the work is mobile-first polish + consistency.

**Tech Stack:** Laravel Blade, Bootstrap 5.3 (SCSS), Alpine.js 3, ApexCharts, Vite 7 + Sass. No Tailwind, no new libraries.

---

## Conventions for this plan

This is CSS/Blade visual work. The project's PHPUnit suite is known-broken on sqlite (a MySQL-only migration), so tasks do **not** add unit tests. Instead each task uses a **build → render → eyeball → commit** loop:

- **Build:** `npm run build` — must finish with no Sass/Vite errors.
- **Render:** load the named route in a browser (XAMPP is serving the app) at a mobile width (~390px, DevTools device toolbar) **and** desktop, in both light and dark mode (toggle in the topbar). Confirm the described change and confirm nothing else broke.
- **Commit:** small, per-task commits.

`npm run dev` (Vite HMR) may be used during a task for fast feedback, but every task ends with a clean `npm run build`.

All commits end with:
```
Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>
```

---

## File map

**Kit (Phase 1):**
- Modify: `resources/scss/app.scss` — badge soft-status set, button danger default, field radius/search, empty-state (already close), stat-card trend pill.
- Modify: `resources/views/components/badge.blade.php` — add `status` prop → soft-tint + dot.
- Modify: `resources/views/components/stat-card.blade.php` — corner icon already present; convert trend to a pill.
- Modify: `resources/views/components/button.blade.php` — `danger` variant → outline by default; add `danger-solid`.

**Hero pages (Phases 2–5):**
- Modify: `resources/views/admin/dashboard.blade.php`
- Modify: `resources/views/admin/bookings/index.blade.php`
- Modify: `resources/views/admin/pos/index.blade.php`
- Modify: `resources/views/customer/bookings/create.blade.php` (+ its time-picker partials stay as-is, wrapped)

**QA sweep (Phase 6):**
- Read-only audit across portals; migrate raw `text-bg-*` badge usages to `<x-badge status>` where found on customer-facing/admin list pages.

---

## Phase 1 — Shared Kit

### Task 1: Badge — soft-tint status set + dot

**Files:**
- Modify: `resources/scss/app.scss` (badge section, around lines 545–557 and 657–679)
- Modify: `resources/views/components/badge.blade.php`

- [ ] **Step 1: Add the soft-status CSS.** In `resources/scss/app.scss`, immediately after the existing `.badge-dot` block (ends ~line 557), add:

```scss
// Unified soft-tint status badges (Refined kit). One per booking/membership state.
.badge-status {
    display: inline-flex; align-items: center; gap: .35rem;
    font-weight: 600; border-radius: .4rem; letter-spacing: .015em;
    &::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
}
.badge-st-active    { background: rgba(34,197,94,.13);  color: #16a34a; }
.badge-st-pending   { background: rgba(245,158,11,.14); color: #d97706; }
.badge-st-cancelled { background: rgba(239,68,68,.12);  color: #dc2626; }
.badge-st-expired   { background: rgba(107,114,128,.13);color: #4b5563; }
.badge-st-completed { background: rgba(59,130,246,.12); color: #2563eb; }
.badge-st-info      { background: rgba(59,130,246,.12); color: #2563eb; }
.badge-st-neutral   { background: rgba(107,114,128,.13);color: #4b5563; }

[data-bs-theme="dark"] {
    .badge-st-active    { background: rgba(34,197,94,.18);  color: #4ade80; }
    .badge-st-pending   { background: rgba(245,158,11,.20); color: #fbbf24; }
    .badge-st-cancelled { background: rgba(239,68,68,.18);  color: #f87171; }
    .badge-st-expired,
    .badge-st-neutral   { background: rgba(148,163,184,.16);color: #cbd5e1; }
    .badge-st-completed,
    .badge-st-info      { background: rgba(59,130,246,.20); color: #60a5fa; }
}
```

- [ ] **Step 2: Extend the badge component with a `status` prop.** Replace the entire contents of `resources/views/components/badge.blade.php` with:

```blade
@props([
    'color'  => 'secondary',
    'status' => null,   // active|pending|cancelled|expired|completed|info|neutral → soft-tint + dot
    'dot'    => false,
    'size'   => 'md',
])

@php
$statusMap = [
    'active'    => 'badge-status badge-st-active',
    'confirmed' => 'badge-status badge-st-active',
    'pending'   => 'badge-status badge-st-pending',
    'cancelled' => 'badge-status badge-st-cancelled',
    'denied'    => 'badge-status badge-st-cancelled',
    'no_show'   => 'badge-status badge-st-cancelled',
    'expired'   => 'badge-status badge-st-expired',
    'completed' => 'badge-status badge-st-completed',
    'info'      => 'badge-status badge-st-info',
    'neutral'   => 'badge-status badge-st-neutral',
];

$colors = [
    'gray'      => 'text-bg-secondary',
    'green'     => 'text-bg-success',
    'emerald'   => 'text-bg-success',
    'blue'      => 'text-bg-primary',
    'indigo'    => 'text-bg-primary',
    'purple'    => 'badge-soft-purple',
    'fuchsia'   => 'badge-soft-pink',
    'pink'      => 'badge-soft-pink',
    'red'       => 'text-bg-danger',
    'orange'    => 'badge-soft-orange',
    'amber'     => 'badge-soft-warning',
    'yellow'    => 'text-bg-warning text-dark',
    'secondary' => 'text-bg-secondary',
];

if ($status !== null) {
    $cls = 'badge ' . ($statusMap[$status] ?? 'badge-status badge-st-neutral');
} else {
    $cls = 'badge ' . ($colors[$color] ?? 'text-bg-secondary');
}
@endphp

<span {{ $attributes->merge(['class' => $cls]) }}>
    @if($dot && $status === null)
    <span class="badge-dot me-1"></span>
    @endif
    {{ $slot }}
</span>
```

- [ ] **Step 3: Build.** Run: `npm run build` — Expected: completes, no Sass errors.

- [ ] **Step 4: Render-check.** Create a throwaway check by viewing any page already using `<x-badge>` (e.g. the dashboard). Then in a Blade view temporarily add `<x-badge status="active">Active</x-badge><x-badge status="pending">Pending</x-badge>` to confirm the soft pill + dot render in light and dark. Remove the temporary markup. Expected: soft-tinted pills with a leading dot.

- [ ] **Step 5: Commit.**

```bash
git add resources/scss/app.scss resources/views/components/badge.blade.php
git commit -m "feat(ui): add soft-tint status badge set to kit

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Button — destructive defaults to outline

**Files:**
- Modify: `resources/views/components/button.blade.php:12-25`

- [ ] **Step 1: Re-map the danger variant.** In `resources/views/components/button.blade.php`, change the `$variants` array so `danger` is outline by default and add an explicit solid escape hatch. Replace lines 12–25 (the `$variants` array) with:

```php
$variants = [
    'primary'         => 'btn-primary',
    'secondary'       => 'btn-outline-secondary',
    'ghost'           => 'btn-outline-secondary',
    'danger'          => 'btn-outline-danger',   // default destructive = outline (kit)
    'danger-solid'    => 'btn-danger',           // reserve solid for confirm-modals
    'warning'         => 'btn-warning',
    'success'         => 'btn-success',
    'info'            => 'btn-info',
    'outline'         => 'btn-outline-primary',
    'outline-primary' => 'btn-outline-primary',
    'outline-danger'  => 'btn-outline-danger',
    'soft'            => 'btn-outline-secondary',
    'link'            => 'btn-link',
];
```

- [ ] **Step 2: Audit existing `variant="danger"` usages for confirm-modals.** Run: search the views for destructive confirm buttons that must stay solid.

```bash
grep -rn 'variant="danger"' resources/views
```

For each result that is the **final confirm button inside a delete/cancel modal** (not an inline list action), change it to `variant="danger-solid"`. Inline/list destructive actions stay `variant="danger"` (now outline). Use judgment per the spec: "solid danger reserved for confirm-modals."

- [ ] **Step 3: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 4: Render-check.** Load a page with an inline delete (e.g. `admin/courts/index`) and a delete-confirm modal. Expected: inline destructive buttons are now outline-red; modal confirm stays solid red.

- [ ] **Step 5: Commit.**

```bash
git add resources/views/components/button.blade.php resources/views
git commit -m "feat(ui): destructive buttons default to outline, solid reserved for modals

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Stat-card — trend as a soft pill

**Files:**
- Modify: `resources/views/components/stat-card.blade.php:38-51`
- Modify: `resources/scss/app.scss` (stat-card section ~lines 481–513)

- [ ] **Step 1: Add trend-pill CSS.** In `resources/scss/app.scss`, after the `.stat-value` block (~line 513), add:

```scss
.stat-trend {
    display: inline-flex; align-items: center; gap: .2rem;
    font-size: .7rem; font-weight: 700; padding: .1rem .4rem;
    border-radius: .375rem; letter-spacing: .01em;
}
.stat-trend-up   { background: rgba(34,197,94,.12);  color: #16a34a; }
.stat-trend-down { background: rgba(239,68,68,.12);  color: #dc2626; }
.stat-trend-flat { background: rgba(107,114,128,.12);color: #6b7280; }
[data-bs-theme="dark"] {
    .stat-trend-up   { background: rgba(34,197,94,.2);  color: #4ade80; }
    .stat-trend-down { background: rgba(239,68,68,.2);  color: #f87171; }
    .stat-trend-flat { background: rgba(148,163,184,.16);color: #cbd5e1; }
}
```

- [ ] **Step 2: Render the trend as a pill.** In `resources/views/components/stat-card.blade.php`, replace the trend block (lines 38–51, the `@if($trend) ... @endif`) with:

```blade
@if($trend)
<div class="mt-1">
    @if($trendUp === true)
    <span class="stat-trend stat-trend-up"><i class="bi bi-arrow-up-short"></i>{{ $trend }}</span>
    @elseif($trendUp === false)
    <span class="stat-trend stat-trend-down"><i class="bi bi-arrow-down-short"></i>{{ $trend }}</span>
    @else
    <span class="stat-trend stat-trend-flat">{{ $trend }}</span>
    @endif
</div>
@endif
```

- [ ] **Step 3: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 4: Render-check.** Load `admin/dashboard`. Expected: KPI cards show the trend as a soft up/down/flat pill under the value; corner icon unchanged; numbers unchanged.

- [ ] **Step 5: Commit.**

```bash
git add resources/scss/app.scss resources/views/components/stat-card.blade.php
git commit -m "feat(ui): stat-card trend rendered as soft pill

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Form fields & search polish

**Files:**
- Modify: `resources/scss/app.scss` (forms section ~lines 559–571 and 751–753)

- [ ] **Step 1: Add a reusable search-field style.** In `resources/scss/app.scss`, after the `.form-label` rule (~line 571), add:

```scss
// Search field with a leading emerald icon (kit)
.search-field {
    display: flex; align-items: center; gap: .25rem;
    border: 1px solid var(--bs-border-color); border-radius: $border-radius;
    background: var(--bs-card-bg); padding: 2px 4px;
    transition: border-color .15s, box-shadow .15s;
    &:focus-within { border-color: $primary; box-shadow: 0 0 0 .2rem rgba($primary,.18); }
    > .bi { padding: .4rem .5rem; color: $primary; font-size: .95rem; }
    > input { flex: 1; border: 0; outline: 0; background: transparent; padding: .45rem .25rem; font-size: .875rem; color: var(--bs-body-color); }
    > input::placeholder { color: var(--bs-secondary-color); }
}
```

(Field radius is already unified to `$border-radius` at lines 752; no change needed there.)

- [ ] **Step 2: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 3: Render-check.** Temporarily drop `<div class="search-field"><i class="bi bi-search"></i><input placeholder="Search…"></div>` into a view; confirm the emerald icon + focus ring; remove it. Expected: matches the approved kit search field. (Real usage lands in Task 6.)

- [ ] **Step 4: Commit.**

```bash
git add resources/scss/app.scss
git commit -m "feat(ui): add reusable search-field kit style

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Empty-state — confirm it already matches the kit

**Files:**
- Read: `resources/views/components/empty-state.blade.php`

- [ ] **Step 1: Verify.** The component already renders a gradient icon chip (`.empty-state-icon`, styled in `app.scss` §20/§26), a title, an optional description, and an optional CTA — i.e. it already matches the approved empty-state. Confirm by loading a page with an empty list (e.g. `admin/bookings/index?status=cancelled` with no data). Expected: gradient icon + title + description (+ CTA when `:action` is passed).

- [ ] **Step 2: No code change.** If it matches, do nothing. If a hero page passes plain `<x-empty-state>` without a description/CTA where one is warranted, add `description` / `action` props when that page is touched in its phase. No commit for this task.

---

## Phase 2 — Hero Page: Admin Dashboard

**File:** Modify `resources/views/admin/dashboard.blade.php`

### Task 6: Hero inline actions already present; verify + tighten

- [ ] **Step 1: Verify hero actions.** The hero band (lines 100–107) already includes **New Booking** + **Status Board** buttons. No change needed. Confirm on `admin/dashboard` mobile width that both are tappable inside the hero.

- [ ] **Step 2: No commit** unless tightening spacing; if the buttons wrap awkwardly under 360px, add `flex-wrap` is already handled by `d-grid gap-2 d-md-flex`. Leave as-is.

### Task 7: Collapse heavy lower sections on mobile

**Files:**
- Modify: `resources/views/admin/dashboard.blade.php` (Analytics row ~lines 338–399, Shortcuts row ~lines 402–481)
- Modify: `resources/scss/app.scss` (add a `.dash-collapsible` helper)

- [ ] **Step 1: Add collapsible CSS.** In `resources/scss/app.scss`, at the end of the file, add:

```scss
// Dashboard: collapse heavy sections on phones to cut scroll (desktop always open)
@include media-breakpoint-down(md) {
    .dash-collapsible > .dash-collapsible-body { display: none; }
    .dash-collapsible.open > .dash-collapsible-body { display: block; }
    .dash-collapsible-toggle .bi { transition: transform .2s ease; }
    .dash-collapsible.open .dash-collapsible-toggle .bi { transform: rotate(180deg); }
}
@include media-breakpoint-up(md) {
    .dash-collapsible-toggle { pointer-events: none; }
    .dash-collapsible-toggle .bi { display: none; }
}
```

- [ ] **Step 2: Wrap the Analytics section.** In `resources/views/admin/dashboard.blade.php`, wrap the "Customer analytics" `<div class="dash-section">…` heading + its `<div class="row g-4 mb-4" x-data="dashboardAnalytics()">…</div>` in an Alpine collapsible. Replace the section heading line (`<div class="dash-section">Customer analytics</div>`) and keep the row, restructuring as:

```blade
<div class="dash-collapsible" :class="{ open: open }" x-data="{ open: window.matchMedia('(min-width: 768px)').matches }">
    <button type="button" class="dash-section dash-collapsible-toggle w-100 border-0 bg-transparent text-start" @click="open = !open">
        Customer analytics
        <i class="bi bi-chevron-down ms-auto"></i>
    </button>
    <div class="dash-collapsible-body">
        {{-- existing analytics row markup unchanged (the x-data="dashboardAnalytics()" row) --}}
    </div>
</div>
```

Move the existing `<div class="row g-4 mb-4" x-data="dashboardAnalytics()" x-init="load()">…</div>` (lines 339–399) inside `.dash-collapsible-body` verbatim.

> Note: `.dash-section`'s `::after` hairline rule still applies; the `<button>` keeps the same class so the styling is identical. `ms-auto` pushes the chevron right.

- [ ] **Step 3: Wrap the Shortcuts section the same way.** Apply the identical wrapper to the "Shortcuts" `<div class="dash-section">Shortcuts</div>` + its `<div class="row g-4">…</div>` (lines 402–481):

```blade
<div class="dash-collapsible" :class="{ open: open }" x-data="{ open: window.matchMedia('(min-width: 768px)').matches }">
    <button type="button" class="dash-section dash-collapsible-toggle w-100 border-0 bg-transparent text-start" @click="open = !open">
        Shortcuts
        <i class="bi bi-chevron-down ms-auto"></i>
    </button>
    <div class="dash-collapsible-body">
        {{-- existing shortcuts row markup unchanged --}}
    </div>
</div>
```

- [ ] **Step 4: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 5: Render-check.** Load `admin/dashboard`:
  - Mobile (~390px): Analytics + Shortcuts are collapsed by default; tapping the heading expands them; chevron rotates.
  - Desktop (≥768px): both sections always visible, chevron hidden, heading not clickable.
  Expected: both behaviors correct in light + dark.

- [ ] **Step 6: Commit.**

```bash
git add resources/scss/app.scss resources/views/admin/dashboard.blade.php
git commit -m "feat(ui): collapse dashboard analytics & shortcuts on mobile

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

### Task 8: Dashboard court-status + recent-bookings to kit badges

- [ ] **Step 1: Swap court-status badges.** In `admin/dashboard.blade.php` the Court Status list (lines 208–246) builds `$badge` via `text-bg-*`. Replace the status `<span class="badge {{ $badge }}">` with `<x-badge :status="$court->status === 'available' ? 'active' : ($court->status === 'occupied' ? 'cancelled' : ($court->status === 'reserved' ? 'pending' : 'neutral'))">{{ ucfirst($court->status) }}</x-badge>`. Remove the now-unused `$badge`/`$dot` match if no longer referenced (the colored dot `<span class="rounded-circle {{ $dot }}">` may stay — it complements the pill).

- [ ] **Step 2: Swap recent-bookings status badge.** In the Recent Bookings table (lines 282–322), replace the `<span class="badge {{ $badge }}">` (line 314) with `<x-badge :status="$booking->status">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</x-badge>` and delete the local `$badge` match block (lines 284–292). The component's `$statusMap` already covers confirmed/active/completed/pending/cancelled.

- [ ] **Step 3: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 4: Render-check.** Load `admin/dashboard`. Expected: court status + recent bookings show soft dot badges; mobile recent-bookings still stack via `.table-stack`.

- [ ] **Step 5: Commit.**

```bash
git add resources/views/admin/dashboard.blade.php
git commit -m "feat(ui): dashboard uses soft status badges

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Phase 3 — Hero Page: Bookings list

**File:** Modify `resources/views/admin/bookings/index.blade.php`

### Task 9: Full-width search + status chips

- [ ] **Step 1: Replace the filter card with search + chips + collapsible "More".** Replace the filters `<div class="card mb-4">…</div>` (lines 58–90) with:

```blade
<div x-data="{ moreOpen: false }" class="mb-4">
    <form method="GET">
        {{-- Full-width search --}}
        <div class="search-field mb-2">
            <i class="bi bi-search"></i>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Search booking #, customer…">
        </div>
        {{-- Scrollable status chips --}}
        <div class="d-flex gap-2 overflow-auto pb-1 mb-2 flex-nowrap">
            @php $cur = request('status'); @endphp
            <button type="submit" name="status" value=""
                    class="btn btn-sm rounded-pill flex-shrink-0 {{ $cur ? 'btn-outline-secondary' : 'btn-primary' }}">All</button>
            @foreach(['pending','confirmed','active','completed','cancelled','no_show'] as $s)
            <button type="submit" name="status" value="{{ $s }}"
                    class="btn btn-sm rounded-pill flex-shrink-0 text-capitalize {{ $cur === $s ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ str_replace('_',' ',$s) }}
            </button>
            @endforeach
            <button type="button" @click="moreOpen = !moreOpen"
                    class="btn btn-sm rounded-pill flex-shrink-0 btn-outline-secondary">
                <i class="bi bi-funnel me-1"></i>More
            </button>
        </div>
        {{-- Collapsible extra filters (date) --}}
        <div x-show="moreOpen" x-cloak class="card"><div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-sm-auto">
                    <label class="form-label small mb-1">Date</label>
                    <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Apply</button>
                    @if(request()->anyFilled(['search','date','status']))
                    <a href="{{ route('admin.bookings.index') }}" class="btn btn-link btn-sm text-muted">Clear</a>
                    @endif
                </div>
            </div>
        </div></div>
    </form>
</div>
```

> The chips submit the form on click (each is a `submit` with its own `status` value), preserving the existing GET-filter backend. `search` persists because its input is inside the same form.

- [ ] **Step 2: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 3: Render-check.** Load `admin/bookings/index`. Expected: full-width search, horizontally-scrollable status chips (active chip = emerald), "More" toggles a date filter card. Filtering by a chip reloads with that status. Desktop unaffected (chips just sit on one row).

- [ ] **Step 4: Commit.**

```bash
git add resources/views/admin/bookings/index.blade.php
git commit -m "feat(ui): bookings filters -> search + status chips + more sheet

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

### Task 10: Booking rows — kit badges + leading identity on mobile

- [ ] **Step 1: Swap the status badge.** In the table body (lines 164–182), replace the main status `<span class="badge rounded-pill {{ $badge }}">` with `<x-badge :status="$booking->status">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</x-badge>` and delete the `$badge` match (lines 110–118). Keep the payment-method and cash badges, but convert the payment badge to `<x-badge color="blue">` style or leave as the existing subtle pill — do not delete functional cash/approval badges.

- [ ] **Step 2: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 3: Render-check.** Load `admin/bookings/index` mobile + desktop. Expected: status shows the soft dot badge; mobile rows still stack as cards (the page's own `.bk-table` media query handles stacking); amount + balance-due intact.

- [ ] **Step 4: Commit.**

```bash
git add resources/views/admin/bookings/index.blade.php
git commit -m "feat(ui): bookings status uses soft badge

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Phase 4 — Hero Page: POS mobile cart

**File:** Modify `resources/views/admin/pos/index.blade.php`

### Task 11: Persistent mobile cart bar → expandable order sheet

**Approach:** Keep the entire existing desktop two-column layout and `posSystem()` Alpine state untouched. Add (a) a fixed bottom **cart bar** visible only `< lg`, and (b) make the existing cart column slide up as a sheet on mobile. The simplest robust implementation: add an Alpine flag `cartOpen` to `posSystem()`, render the bottom bar, and toggle a CSS class on the cart column that makes it a bottom sheet on mobile.

- [ ] **Step 1: Add `cartOpen` state.** In the `posSystem()` return object (script, ~line 197), add after `processing: false,`:

```js
        cartOpen: false,
        get cartCount() { return this.cart.reduce((n, i) => n + i.quantity, 0); },
```

- [ ] **Step 2: Add POS mobile-sheet CSS.** In the `@push('styles')` block (after line 24), add:

```css
@media (max-width: 991.98px) {
    /* Cart becomes a bottom sheet on mobile */
    .pos-cart-col {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 1045;
        transform: translateY(100%); transition: transform .28s ease;
        max-height: 88vh; overflow-y: auto;
        padding: 0 .75rem calc(.75rem + env(safe-area-inset-bottom));
    }
    .pos-cart-col.sheet-open { transform: translateY(0); }
    .pos-cart { position: static !important; }
    .pos-cart-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,.45);
        backdrop-filter: blur(2px); z-index: 1044; display: none;
    }
    .pos-cart-backdrop.show { display: block; }
    /* The persistent bar */
    .pos-cart-bar {
        position: fixed; left: .5rem; right: .5rem;
        bottom: calc(.5rem + env(safe-area-inset-bottom)); z-index: 1043;
        background: linear-gradient(180deg, #14c08a, #10b981); color: #fff;
        border-radius: 14px; padding: .7rem .9rem;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 8px 24px -6px rgba(16,185,129,.6);
    }
}
@media (min-width: 992px) { .pos-cart-bar, .pos-cart-backdrop { display: none !important; } }
```

- [ ] **Step 3: Tag the cart column + add the bar and backdrop.** In the markup:
  - Change the cart column wrapper (line 92) from `<div class="col-12 col-lg-4">` to:
    ```blade
    <div class="col-12 col-lg-4 pos-cart-col" :class="{ 'sheet-open': cartOpen }">
    ```
  - Immediately **before** that column's closing `</div>` chain (after line 188's `</div>` for `.pos-cart` card), still inside the row, add the backdrop + bar (they live at row level, shown only on mobile):
    ```blade
    {{-- Mobile bottom sheet backdrop + persistent cart bar --}}
    <div class="pos-cart-backdrop" :class="{ show: cartOpen }" @click="cartOpen = false"></div>
    <div class="pos-cart-bar" x-show="!cartOpen" x-cloak @click="cartOpen = true">
        <div class="d-flex align-items-center gap-2">
            <div class="position-relative fs-5"><i class="bi bi-cart3"></i>
                <span class="badge rounded-pill text-bg-light position-absolute top-0 start-100 translate-middle"
                      x-show="cartCount > 0" x-text="cartCount" style="font-size:.6rem"></span>
            </div>
            <div>
                <div style="font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;opacity:.85">Total</div>
                <div class="fw-bold" x-text="'₱' + total.toFixed(2)"></div>
            </div>
        </div>
        <span class="btn btn-light btn-sm fw-bold px-3">Pay <i class="bi bi-arrow-right ms-1"></i></span>
    </div>
    ```
  - Inside the `.pos-cart .card-header` (line 94), add a mobile-only close handle so users can dismiss the sheet:
    ```blade
    <button type="button" class="btn btn-sm btn-link text-muted d-lg-none ms-auto p-0" @click="cartOpen = false">
        <i class="bi bi-chevron-down"></i>
    </button>
    ```
    (place it before the existing item-count badge, adjusting the header `d-flex` as needed.)

- [ ] **Step 4: Auto-open the sheet when first item is added (mobile nicety).** In `addItem()` (script ~line 219), at the end of the method add:

```js
            if (window.matchMedia('(max-width: 991.98px)').matches) this.cartOpen = true;
```

- [ ] **Step 5: Convert POS product/status badges to kit.** The low-stock badge (lines 78–82) already uses `bg-warning-subtle` — acceptable; optionally swap to `<x-badge color="amber">`. Leave product card structure as-is (already kit-like).

- [ ] **Step 6: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 7: Render-check.** Load `admin/pos/index`:
  - Mobile: the side cart is hidden; a green **cart bar** is pinned at the bottom showing count + total + Pay. Tapping a product adds it and opens the sheet; the sheet shows the full cart/checkout; chevron + backdrop close it; the bar reappears.
  - Desktop (≥992px): unchanged — sticky side cart, no bar.
  Expected: totals match between bar and sheet; both light + dark fine.

- [ ] **Step 8: Commit.**

```bash
git add resources/views/admin/pos/index.blade.php
git commit -m "feat(ui): POS mobile persistent cart bar + bottom sheet

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Phase 5 — Hero Page: Customer Booking wizard

**File:** Modify `resources/views/customer/bookings/create.blade.php`

**Approach:** Convert the single form into a 3-step wizard by adding an Alpine `step` integer to the existing `bookingForm()` and wrapping the three groups of existing fields in `x-show` step containers + a progress bar + a Back/Continue footer. **No field is removed**; the `<form>`, hidden inputs, and `bookingForm()` getters stay. Step gating reuses existing getters (`selectedSlot`, `paymentMethod`, `walletCovers`, `creditFullyCovers`, `submitDisabled`).

### Task 12: Add wizard state + step gating to `bookingForm()`

- [ ] **Step 1: Add `step` + nav helpers.** In the `bookingForm()` return object (script, after `submitting: false,` ~line 210), add:

```js
        step: 1,
        get step1Valid() { return !!this.selectedSlot; },
        get step2Valid() {
            if (!this.paymentMethod) return false;
            if (this.paymentMethod === 'wallet'       && !this.walletCovers)      return false;
            if (this.paymentMethod === 'court_credit' && !this.creditFullyCovers) return false;
            return true;
        },
        next() {
            if (this.step === 1 && !this.step1Valid) return;
            if (this.step === 2 && !this.step2Valid) return;
            if (this.step < 3) this.step++;
        },
        back() { if (this.step > 1) this.step--; },
```

- [ ] **Step 2: Build.** Run: `npm run build` — Expected: no errors (JS is inline; build just re-bundles assets — this view's script is page-level, so also hard-refresh the page to confirm no Alpine console errors).

- [ ] **Step 3: Commit.**

```bash
git add resources/views/customer/bookings/create.blade.php
git commit -m "feat(ui): add wizard step state to booking form

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

### Task 13: Wrap fields in step containers + progress + footer

- [ ] **Step 1: Add the progress header.** Immediately inside `<div class="card-body p-4">` (line 30), before the `<form>`, add:

```blade
<div class="mb-4">
    <div class="d-flex gap-2 mb-2">
        <div class="flex-fill rounded-pill" style="height:5px" :class="step >= 1 ? 'bg-primary' : 'bg-secondary-subtle'"></div>
        <div class="flex-fill rounded-pill" style="height:5px" :class="step >= 2 ? 'bg-primary' : 'bg-secondary-subtle'"></div>
        <div class="flex-fill rounded-pill" style="height:5px" :class="step >= 3 ? 'bg-primary' : 'bg-secondary-subtle'"></div>
    </div>
    <div class="d-flex justify-content-between small fw-semibold">
        <span :class="step >= 1 ? 'text-primary' : 'text-muted'">1 · Court &amp; time</span>
        <span :class="step >= 2 ? 'text-primary' : 'text-muted'">2 · Payment</span>
        <span :class="step >= 3 ? 'text-primary' : 'text-muted'">3 · Confirm</span>
    </div>
</div>
```

- [ ] **Step 2: Wrap Step 1 fields.** Wrap the Court & Date row (lines 47–71) **and** the included time-picker (`@include('partials.booking-time-picker', …)`, line 74) in:

```blade
<div x-show="step === 1">
    {{-- existing court/date row + @include('partials.booking-time-picker', ['staff' => false]) --}}
</div>
```

- [ ] **Step 3: Wrap Step 2 fields.** Wrap the payment-method block (lines 79–138) **and** the promo-code block (lines 141–153) in:

```blade
<div x-show="step === 2" x-cloak>
    {{-- existing payment method cards + promo code --}}
</div>
```

Remove the now-redundant `x-show="selectedSlot"` on the payment wrapper (the step gate handles visibility) — or leave it; it stays true on step 2. Keep the inner per-method `x-show` logic.

- [ ] **Step 4: Wrap Step 3 fields.** Wrap Notes (lines 156–159) + Price summary (lines 162–179) in:

```blade
<div x-show="step === 3" x-cloak>
    {{-- existing notes + price summary --}}
</div>
```

- [ ] **Step 5: Replace the footer buttons.** Replace the final `<div class="d-flex justify-content-end gap-2">…</div>` (lines 181–188) with a step-aware footer:

```blade
<div class="d-flex gap-2 mt-4">
    <button type="button" class="btn btn-outline-secondary" x-show="step > 1" @click="back()">
        <i class="bi bi-arrow-left me-1"></i>Back
    </button>
    <a href="{{ route('customer.bookings.index') }}" class="btn btn-outline-secondary" x-show="step === 1">Cancel</a>
    <button type="button" class="btn btn-primary ms-auto" x-show="step < 3"
            :disabled="(step === 1 && !step1Valid) || (step === 2 && !step2Valid)"
            @click="next()">
        Continue<i class="bi bi-arrow-right ms-1"></i>
    </button>
    <button type="submit" class="btn btn-primary ms-auto" x-show="step === 3" :disabled="submitDisabled">
        <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-1"></span>
        <i class="bi bi-calendar-check me-1" x-show="!submitting"></i>Confirm Booking
    </button>
</div>
```

- [ ] **Step 6: Build.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 7: Render-check (critical — money path).** Load `customer/bookings/create`:
  - Step 1: pick court/date/time; Continue is disabled until a valid slot is selected; the availability verdict still works (the partial's Alpine `runCheck()` etc. unaffected).
  - Step 2: appears after Continue; selecting Wallet with insufficient balance keeps Continue disabled; Cash always allows Continue; promo applies.
  - Step 3: notes + price summary show; Confirm respects `submitDisabled`.
  - Submit a real booking end-to-end and confirm it stores (hidden `start_time`/`end_time` inputs still populate from `selectedSlot`). Server-side `@if($errors->any())` block still shows on validation failure (it sits above the steps — verify it renders on error).
  Expected: full booking completes; no Alpine console errors; desktop also works (steps just render in sequence).

> If server validation errors should return the user to the relevant step, that is out of scope for this task — the error list renders at the top regardless. Note it for future polish.

- [ ] **Step 8: Commit.**

```bash
git add resources/views/customer/bookings/create.blade.php
git commit -m "feat(ui): customer booking flow as 3-step wizard

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Phase 6 — Consistency QA Sweep

### Task 14: Migrate raw status badges to the kit on list/detail pages

**Files:** audit across `resources/views/admin/**`, `resources/views/customer/**`, `resources/views/super/**`

- [ ] **Step 1: Find raw status badges.** Run:

```bash
grep -rn 'text-bg-\(success\|danger\|warning\|primary\|secondary\)\|bg-\(success\|danger\|warning\|primary\)-subtle' resources/views/admin resources/views/customer resources/views/super
```

- [ ] **Step 2: Migrate booking/membership/payment *status* badges** (where the text is a state like Active/Pending/Cancelled/Completed/Expired) to `<x-badge :status="…">`. **Do not** migrate non-status uses (counts, category tags, "free" indicators, payment-method labels) — those keep their current color badges. Work one file per commit to keep diffs reviewable.

- [ ] **Step 3: Build after each file.** Run: `npm run build` — Expected: no errors.

- [ ] **Step 4: Render-check each migrated page** at mobile + desktop, light + dark.

- [ ] **Step 5: Commit per file**, e.g.:

```bash
git add resources/views/admin/memberships/index.blade.php
git commit -m "refactor(ui): memberships list uses soft status badges

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

### Task 15: Final full-app visual pass

- [ ] **Step 1: Walk every portal** (Admin, Customer, Super, Auth) at 390px and desktop, light + dark. Check the kit landed and nothing regressed: buttons (outline-danger inline), badges (soft status), fields/search, empty states, stat cards, the 4 hero pages.

- [ ] **Step 2: Fix any stragglers** found (one commit per fix).

- [ ] **Step 3: Final build.** Run: `npm run build` — Expected: clean.

- [ ] **Step 4: Update the premium-ui-refresh memory** at `C:\Users\Kemp Ompad\.claude\projects\c--xampp-htdocs-courtmaster\memory\` with a one-line note that the 2026-06-03 modernization (soft status badges, outline-danger default, POS mobile cart sheet, booking wizard, collapsible dashboard sections) is applied, so future sessions don't re-derive it.

---

## Self-review notes (coverage check)

- Spec §1 Shared Kit → Tasks 1–5 ✅ (buttons, badges, fields/search, empty-state verify, stat-card pill).
- Spec §2.1 Dashboard → Tasks 6–8 ✅ (hero actions already present; collapsible mobile sections; soft badges).
- Spec §2.2 Bookings → Tasks 9–10 ✅ (search + chips + more; soft badges; mobile cards already via `.bk-table`).
- Spec §2.3 POS → Task 11 ✅ (mobile cart bar + sheet; desktop + Alpine untouched).
- Spec §2.4 Booking wizard → Tasks 12–13 ✅ (3 steps, gating reuses existing getters, all fields preserved).
- Spec §3 remaining pages / QA → Tasks 14–15 ✅ (badge migration + full visual pass).
- Spec accessibility/dark-mode baselines → enforced in every render-check step ✅.
- Risk "badge migration centralized" → resolved via `<x-badge status>` component map (Task 1) + opt-in migration (Task 14), not a global override ✅.
```
