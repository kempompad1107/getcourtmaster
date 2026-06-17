# Customer Portal Mobile Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the customer portal the same mobile experience as the staff/admin portal — a bottom tab bar and shared `table-stack` card-stacking — per the approved spec `docs/superpowers/specs/2026-06-11-customer-portal-mobile-parity-design.md`.

**Architecture:** Blade-only changes. A new static (non-permission-gated) bottom-nav partial reuses the existing `.mobile-bottom-nav` / `.mbn-*` SCSS already shipped for admin; three customer pages drop their private copies of card-stacking CSS in favor of the shared `table-stack` utility (SCSS section 27 in `resources/scss/app.scss`). No controller, route, or SCSS changes; no asset rebuild needed.

**Tech Stack:** Laravel Blade, Bootstrap 5.3, Alpine.js (`$store.sidebar`), existing SCSS utilities.

**Verification note:** The PHPUnit suite is broken on sqlite (known, pre-existing — do NOT try to fix it). There are no view tests. Each task is verified by compiling all Blade templates (`php artisan view:cache` fails loudly on Blade syntax errors) and finally by visual checks at a phone-width viewport.

---

### Task 1: Customer mobile bottom nav

**Files:**
- Create: `resources/views/partials/customer-mobile-bottom-nav.blade.php`
- Modify: `resources/views/layouts/customer.blade.php` (body tag ~line 21, after `</main>` ~line 104)

- [ ] **Step 1: Create the partial**

Create `resources/views/partials/customer-mobile-bottom-nav.blade.php` with exactly:

```blade
{{--
    Mobile bottom tab bar (customer). Shown below `lg` only; desktop uses the sidebar.
    Static tabs (no permission gating): Home · Bookings · ＋Book · Wallet · More.
--}}
<nav class="mobile-bottom-nav d-lg-none" aria-label="Primary mobile navigation">

    <a href="{{ route('customer.dashboard') }}"
       class="mbn-tab {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door mbn-ic"></i>
        <span class="mbn-label">Home</span>
    </a>

    <a href="{{ route('customer.bookings.index') }}"
       class="mbn-tab {{ request()->routeIs('customer.bookings.index') || request()->routeIs('customer.bookings.show') ? 'active' : '' }}">
        <i class="bi bi-calendar-check mbn-ic"></i>
        <span class="mbn-label">Bookings</span>
    </a>

    {{-- Center FAB → Book a Court --}}
    <a href="{{ route('customer.bookings.create') }}" class="mbn-fab-wrap" aria-label="Book a court">
        <span class="mbn-fab"><i class="bi bi-plus-lg"></i></span>
        <span class="mbn-label">Book</span>
    </a>

    <a href="{{ route('customer.wallet.index') }}"
       class="mbn-tab {{ request()->routeIs('customer.wallet.*') ? 'active' : '' }}">
        <i class="bi bi-wallet2 mbn-ic"></i>
        <span class="mbn-label">Wallet</span>
    </a>

    {{-- More → opens the existing drawer --}}
    <button type="button" class="mbn-tab" @click="$store.sidebar.openMobile()" aria-label="More menu">
        <i class="bi bi-list mbn-ic"></i>
        <span class="mbn-label">More</span>
    </button>

</nav>
```

- [ ] **Step 2: Wire it into the customer layout**

In `resources/views/layouts/customer.blade.php`:

Edit 1 — body class (line 21):

```html
<!-- old -->
<body>
<!-- new -->
<body class="has-mobile-nav">
```

Edit 2 — include the partial after `</main>` (line 104), matching the admin layout's placement:

```blade
{{-- old --}}
</main>

@stack('modals')
{{-- new --}}
</main>

{{-- ── Mobile bottom navigation (below lg only) ─────────────────────────── --}}
@include('partials.customer-mobile-bottom-nav')

@stack('modals')
```

- [ ] **Step 3: Verify all Blade templates compile**

Run: `php artisan view:clear; php artisan view:cache; php artisan view:clear`
Expected: "Compiled views cleared successfully." / "Blade templates cached successfully." — no exceptions. (The final `view:clear` restores the normal dev state.)

- [ ] **Step 4: Commit**

```bash
git add resources/views/partials/customer-mobile-bottom-nav.blade.php resources/views/layouts/customer.blade.php
git commit -m "feat(ui): customer portal mobile bottom tab bar (parity with admin)"
```

---

### Task 2: My Bookings table → shared table-stack

**Files:**
- Modify: `resources/views/customer/bookings/index.blade.php` (styles block lines 5–30, table line 45, cells lines 85, 90)

- [ ] **Step 1: Delete the private stacking CSS**

Replace the whole `@push('styles')` block (lines 5–30) with:

```blade
@push('styles')
<style>
    /* ── My bookings — polish (mobile stacking via shared .table-stack) ── */
    .cb-court-ico { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: grid; place-items: center; background: rgba(16,185,129,.1); color: #10b981; }
    .cb-table tbody tr { transition: background-color .15s; }
</style>
@endpush
```

- [ ] **Step 2: Switch the table and cells to the shared classes**

Edit 1 — table tag (line 45):

```html
<!-- old -->
<table class="table cb-table mb-0 align-middle">
<!-- new -->
<table class="table cb-table mb-0 align-middle table-stack">
```

Edit 2 — actions cell (line 85). `cell-actions` suppresses the label and makes the button full-width on mobile:

```blade
{{-- old --}}
<td data-label="" class="bk-cell-empty text-end">
{{-- new --}}
<td class="cell-actions text-end">
```

Edit 3 — empty-state cell (line 90):

```blade
{{-- old --}}
<tr><td colspan="8" class="bk-cell-empty text-center text-muted py-5">
{{-- new --}}
<tr><td colspan="8" class="cell-plain text-center text-muted py-5">
```

(The `data-label` attributes on the other cells stay exactly as they are.)

- [ ] **Step 3: Verify all Blade templates compile**

Run: `php artisan view:clear; php artisan view:cache; php artisan view:clear`
Expected: success messages, no exceptions.

- [ ] **Step 4: Commit**

```bash
git add resources/views/customer/bookings/index.blade.php
git commit -m "refactor(ui): customer bookings list uses shared table-stack"
```

---

### Task 3: Wallet transactions table → shared table-stack

**Files:**
- Modify: `resources/views/customer/wallet/index.blade.php` (styles block lines 5–24, table line 209, empty cell line 247)

- [ ] **Step 1: Delete the private stacking CSS**

Replace the whole `@push('styles')` block (lines 5–24) with:

```blade
@push('styles')
<style>
    /* ── Customer wallet — stat tiles (mobile table via shared .table-stack) ── */
    .cw-stat { border: 1px solid var(--bs-border-color); transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease; }
    .cw-stat:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.3); box-shadow: 0 14px 28px -22px rgba(0,0,0,.5); }
    .cw-stat-ico { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; display: grid; place-items: center; font-size: 1.25rem; }
    .cw-stat-label { font-size: .68rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 0; }
    .cw-stat-value { font-size: 1.25rem; font-weight: 800; line-height: 1; margin: .25rem 0 0; }
</style>
@endpush
```

- [ ] **Step 2: Switch the table and empty cell to the shared classes**

Edit 1 — table tag (line 209; `wtx` is dropped because its only purpose was the removed CSS):

```html
<!-- old -->
<table class="table wtx mb-0 align-middle">
<!-- new -->
<table class="table mb-0 align-middle table-stack">
```

Edit 2 — empty-state cell (line 247):

```blade
{{-- old --}}
<td colspan="7" class="bk-cell-empty text-center py-5">
{{-- new --}}
<td colspan="7" class="cell-plain text-center py-5">
```

- [ ] **Step 3: Verify all Blade templates compile**

Run: `php artisan view:clear; php artisan view:cache; php artisan view:clear`
Expected: success messages, no exceptions.

- [ ] **Step 4: Commit**

```bash
git add resources/views/customer/wallet/index.blade.php
git commit -m "refactor(ui): customer wallet transactions use shared table-stack"
```

---

### Task 4: Membership tables → shared table-stack

**Files:**
- Modify: `resources/views/customer/memberships/index.blade.php` (styles block lines 5–27, tables lines 149 and 265)

- [ ] **Step 1: Delete the private stacking CSS**

In the `@push('styles')` block, delete only the `@media (max-width: 575.98px) { .cust-tbl … }` rules (lines 18–25), keeping the `.cust-plan` pricing-card rules. The block becomes:

```blade
@push('styles')
<style>
    /* ── Customer memberships — pricing cards (mobile tables via shared .table-stack) ── */
    .cust-plan {
        height: 100%; overflow: hidden; --accent: #10b981; --accent-rgb: 16,185,129;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .cust-plan.is-vip { --accent: #f59e0b; --accent-rgb: 245,158,11; border-color: rgba(245,158,11,.4); }
    .cust-plan:hover { transform: translateY(-4px); border-color: rgba(var(--accent-rgb),.45); box-shadow: 0 18px 36px -24px rgba(0,0,0,.6); }
    .cust-plan .plan-accent { height: 5px; background: linear-gradient(90deg, var(--accent), rgba(var(--accent-rgb),.25)); }
    .cust-plan-price { font-size: 2rem; font-weight: 800; letter-spacing: -.02em; line-height: 1; }
</style>
@endpush
```

- [ ] **Step 2: Switch both tables to the shared class**

Edit 1 — Recent Activity table (line 149):

```html
<!-- old -->
<table class="table cust-tbl mb-0 align-middle">
<!-- new -->
<table class="table mb-0 align-middle table-stack">
```

Edit 2 — Past Memberships table (line 265): identical change — `cust-tbl` out, `table-stack` in. (Both tables stack below 768px now instead of 576px; that matches admin and is the accepted behavior change in the spec.)

- [ ] **Step 3: Verify all Blade templates compile**

Run: `php artisan view:clear; php artisan view:cache; php artisan view:clear`
Expected: success messages, no exceptions.

- [ ] **Step 4: Commit**

```bash
git add resources/views/customer/memberships/index.blade.php
git commit -m "refactor(ui): customer membership tables use shared table-stack"
```

---

### Task 5: Visual verification

**Files:** none (verification only)

- [ ] **Step 1: Serve the app**

XAMPP/Apache already serves `http://localhost/courtmaster/public` (or use `php artisan serve`). Log in as a customer-role user from the demo seed.

- [ ] **Step 2: Phone-width checks (≈390px viewport)**

On each of Dashboard, My Bookings, Wallet, Membership, Book a Court:

- Bottom bar visible with Home · Bookings · ➕Book · Wallet · More; active tab highlights on the current page; FAB opens the booking wizard; More opens the sidebar drawer.
- Page content (including the wallet pagination footer) is not hidden behind the bar.
- Tables render as stacked chip-cards with uppercase labels; the bookings "View" button is full-width.

- [ ] **Step 3: Desktop check (≥1200px viewport)**

- No bottom bar; sidebar/topbar unchanged; the three tables render as normal tables identical to before.
