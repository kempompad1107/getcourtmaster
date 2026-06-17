# Mobile UI Refactor (Foundation + Dashboard) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a shared mobile foundation (permission-aware bottom tab bar + reusable responsive table→card utility + touch/spacing tokens) and fully refactor the admin dashboard for 320–768px, preserving all features, logic, branding, and desktop layout.

**Architecture:** Pure presentation changes — new Blade partial + SCSS in the existing Bootstrap 5 / Inter / emerald theme. The bottom nav reuses the existing Alpine `sidebar` store for its "More" tab. The `.table-stack` utility generalizes the existing `bk-table` `data-label` pattern into one class. No PHP/JS logic, routes, or controllers change.

**Tech Stack:** Laravel Blade, Bootstrap 5 (SCSS), Alpine.js, Vite, Bootstrap Icons.

---

## Testing note (read first)

These changes are CSS/Blade presentation only. There is no meaningful unit test for visual layout, and per project memory the PHPUnit suite is broken on sqlite (a MySQL-only migration) — do **not** rely on `php artisan test`. Verification for every task is: **rebuild assets, then visually check the running app at mobile widths.**

- **Rebuild after each change:** `npm run build` (one-shot) — or run `npm run dev` once in a separate terminal and leave it watching.
- **App URL:** `http://localhost/courtmaster/public/admin/dashboard`
- **How to test widths:** open the URL, open browser DevTools → device toolbar (Ctrl+Shift+M in Chrome), and check at **320, 375, 414, 768px**. Also check ≥ 992px (`lg`) to confirm desktop is unchanged.
- You must be logged in as a business owner to see all tabs; the permission-fallback behavior is verified in Task 2.

---

## File Structure

- **Create:** `resources/views/partials/mobile-bottom-nav.blade.php` — the bottom tab bar (markup + permission logic). One responsibility: render the mobile nav.
- **Modify:** `resources/scss/app.scss` — append four new, clearly-commented sections (table-stack, touch/spacing tokens, bottom-nav, content offset). Existing rules untouched.
- **Modify:** `resources/views/layouts/app.blade.php` — add `has-mobile-nav` body class + include the partial.
- **Modify:** `resources/views/admin/dashboard.blade.php` — hero buttons, Recent Bookings → `.table-stack`, remove redundant mobile `<style>`, tap-size tweaks.

---

## Task 1: Shared SCSS utilities — `.table-stack`, touch targets, spacing, page-header

**Files:**
- Modify: `resources/scss/app.scss` (append at end of file)

- [ ] **Step 1: Append the `.table-stack` utility**

Add to the end of `resources/scss/app.scss`:

```scss
// ── 27. Responsive table → card stacking (shared mobile utility) ─────────────
// Usage: add class `table-stack` to a <table>; add data-label="…" to each <td>.
// Mark an actions cell with `.cell-actions` (full-width buttons) and any
// label-less cell with `.cell-plain` (suppresses the label). Below 768px each
// row renders as a card; at/above 768px it is a normal table.
@include media-breakpoint-down(md) {
    .table-stack {
        thead { display: none; }
        &, tbody, tr, td { display: block; width: 100%; }

        tr {
            border: 1px solid var(--bs-border-color);
            border-radius: .85rem;
            padding: .35rem .9rem;
            margin: .75rem 0;
            background: var(--bs-card-bg);
            box-shadow: $box-shadow-sm;
        }
        // Remove outer card chrome's double border when stacked inside a .card
        .card > .table-responsive & tr,
        .card > & tr { margin-left: 0; margin-right: 0; }

        td {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; border: 0; padding: .5rem 0; text-align: right;
            min-height: 40px;
        }
        td + td { border-top: 1px solid var(--bs-border-color); }
        td::before {
            content: attr(data-label);
            text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        td.cell-plain::before { content: none; }
        td.cell-actions {
            border-top: 1px solid var(--bs-border-color);
            padding-top: .65rem;
            &::before { content: none; }
            .btn { flex: 1; }
            > a, > .btn, > div { width: 100%; }
        }
    }
}
```

- [ ] **Step 2: Append touch-target + spacing + page-header rules**

Add immediately after the block from Step 1:

```scss
// ── 28. Mobile touch targets & spacing ───────────────────────────────────────
@include media-breakpoint-down(md) {
    // Reclaim horizontal space on phones
    .main-content .content-wrapper { padding: 1rem; }

    // Comfortable tap sizes
    .btn { min-height: 40px; }
    .btn-sm { min-height: 38px; }
    .form-control, .form-select { min-height: 44px; font-size: 16px; } // 16px avoids iOS zoom-on-focus
    .form-control-sm, .form-select-sm { min-height: 40px; }
    .topbar-btn { width: 42px; height: 42px; }

    // Page header: stack title above full-width actions
    .page-header {
        flex-direction: column; align-items: stretch; gap: .75rem;
        .page-actions { width: 100%; flex-wrap: wrap;
            > .btn, > .btn-group { flex: 1 1 auto; }
            .btn-group > .btn { flex: 1; }
        }
    }
}
```

- [ ] **Step 3: Rebuild assets**

Run: `npm run build`
Expected: build completes with no SCSS errors; `public/build/` updates.

- [ ] **Step 4: Visually verify no regression**

Open `http://localhost/courtmaster/public/admin/bookings` at 375px and at ≥992px.
Expected:
- Desktop (≥992px): bookings table and page header look exactly as before (the new `.table-stack` class is not yet applied anywhere, so only the touch/spacing/page-header rules can show — confirm desktop is unchanged since those are `down(md)` only).
- 375px: page-header actions are full-width and stacked; inputs are taller; no horizontal scroll. (The bookings table still uses its own `bk-table` stacking — unchanged.)

- [ ] **Step 5: Commit**

```bash
git add resources/scss/app.scss
git commit -m "feat(ui): add shared mobile table-stack utility + touch/spacing tokens"
```

---

## Task 2: Mobile bottom tab bar (partial + SCSS + layout wiring)

**Files:**
- Create: `resources/views/partials/mobile-bottom-nav.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (body tag line 22; include after topbar ~line 191)
- Modify: `resources/scss/app.scss` (append)

- [ ] **Step 1: Create the bottom-nav partial**

Create `resources/views/partials/mobile-bottom-nav.blade.php`:

```blade
{{--
    Mobile bottom tab bar (admin). Shown below `lg` only; desktop uses the sidebar.
    Operations-first: Home · [middle] · ＋New Booking · [middle] · More.
    Permission-aware — middle slots fill from an ordered candidate list of
    screens the user can access; "More" always opens the full drawer.
--}}
@php
    $u = auth()->user();
    $isOwner = $u && ($u->hasRole('business_owner') || $u->isBusinessOwner());

    // Center FAB → New Booking (gated like the sidebar's Bookings item)
    $showFab = $isOwner || $u->can('bookings.view');

    // Ordered candidates for the two middle slots: [label, icon, route, activePattern]
    $candidates = [];
    if ($isOwner || $u->can('bookings.view'))  $candidates[] = ['Bookings', 'bi-calendar-check', 'admin.bookings.index',       'admin.bookings.index'];
    if ($isOwner || $u->can('courts.view'))    $candidates[] = ['Courts',   'bi-grid',           'admin.courts.index',         'admin.courts.index'];
    if ($isOwner || $u->can('courts.view'))    $candidates[] = ['Status',   'bi-grid-3x3-gap',   'admin.courts.status-board',  'admin.courts.status-board'];
    if ($isOwner || $u->can('pos.access'))     $candidates[] = ['POS',      'bi-receipt',        'admin.pos.index',            'admin.pos.*'];
    if ($isOwner || $u->can('customers.view')) $candidates[] = ['Customers','bi-people',         'admin.customers.index',      'admin.customers.*'];

    $middle = array_slice($candidates, 0, 2);
    $leftItem  = $middle[0] ?? null;
    $rightItem = $middle[1] ?? null;
@endphp

<nav class="mobile-bottom-nav d-lg-none" aria-label="Primary mobile navigation">

    {{-- Home (always) --}}
    <a href="{{ route('admin.dashboard') }}"
       class="mbn-tab {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door mbn-ic"></i>
        <span class="mbn-label">Home</span>
    </a>

    {{-- Left middle slot --}}
    @if($leftItem)
    <a href="{{ route($leftItem[2]) }}"
       class="mbn-tab {{ request()->routeIs($leftItem[3]) ? 'active' : '' }}">
        <i class="bi {{ $leftItem[1] }} mbn-ic"></i>
        <span class="mbn-label">{{ $leftItem[0] }}</span>
    </a>
    @endif

    {{-- Center FAB → New Booking --}}
    @if($showFab)
    <a href="{{ route('admin.bookings.create') }}" class="mbn-fab-wrap" aria-label="New booking">
        <span class="mbn-fab"><i class="bi bi-plus-lg"></i></span>
        <span class="mbn-label">New</span>
    </a>
    @endif

    {{-- Right middle slot --}}
    @if($rightItem)
    <a href="{{ route($rightItem[2]) }}"
       class="mbn-tab {{ request()->routeIs($rightItem[3]) ? 'active' : '' }}">
        <i class="bi {{ $rightItem[1] }} mbn-ic"></i>
        <span class="mbn-label">{{ $rightItem[0] }}</span>
    </a>
    @endif

    {{-- More → opens the existing drawer --}}
    <button type="button" class="mbn-tab" @click="$store.sidebar.openMobile()" aria-label="More menu">
        <i class="bi bi-list mbn-ic"></i>
        <span class="mbn-label">More</span>
    </button>

</nav>
```

- [ ] **Step 2: Wire the partial + body class into the layout**

In `resources/views/layouts/app.blade.php`, change the body open tag (line 22) from:

```blade
<body>
```
to:
```blade
<body class="has-mobile-nav">
```

Then, immediately after the closing `</main>` of the main content block (currently line 202) and before the `{{-- Modals stack --}}` comment, add:

```blade
{{-- ── Mobile bottom navigation (below lg only) ─────────────────────────── --}}
@include('partials.mobile-bottom-nav')
```

- [ ] **Step 3: Append the bottom-nav SCSS**

Add to the end of `resources/scss/app.scss`:

```scss
// ── 29. Mobile bottom navigation bar ─────────────────────────────────────────
$bottomnav-height: 58px;

.mobile-bottom-nav {
    position: fixed;
    left: 0; right: 0; bottom: 0;
    height: calc(#{$bottomnav-height} + env(safe-area-inset-bottom));
    padding: 0 .25rem env(safe-area-inset-bottom);
    background: rgba(255, 255, 255, .92);
    backdrop-filter: saturate(180%) blur(14px);
    -webkit-backdrop-filter: saturate(180%) blur(14px);
    border-top: 1px solid var(--bs-border-color);
    box-shadow: 0 -2px 16px -8px rgba(15, 23, 42, .25);
    display: flex; align-items: center; justify-content: space-around;
    z-index: 1035; // above topbar(1030)/content, below overlay(1039) + sidebar(1040)

    [data-bs-theme="dark"] & { background: rgba(15, 23, 42, .9); }

    .mbn-tab {
        flex: 1; min-width: 0;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 2px; height: 100%;
        border: 0; background: transparent;
        color: var(--bs-secondary-color, #6b7280);
        text-decoration: none;
        font-size: .65rem; font-weight: 600;
        transition: color .15s ease;
        -webkit-tap-highlight-color: transparent;

        .mbn-ic { font-size: 1.2rem; line-height: 1; }
        .mbn-label { line-height: 1; }

        &.active { color: $primary; }
        &:active { color: $primary-dark; }
    }

    .mbn-fab-wrap {
        flex: 0 0 auto;
        display: flex; flex-direction: column; align-items: center;
        gap: 2px; text-decoration: none;
        color: $primary; font-size: .65rem; font-weight: 600;
        -webkit-tap-highlight-color: transparent;
    }
    .mbn-fab {
        width: 46px; height: 46px; margin-top: -22px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; color: #fff;
        background-image: linear-gradient(180deg, lighten($primary, 4%) 0%, $primary 100%);
        box-shadow: 0 4px 14px -2px rgba(16, 185, 129, .6), 0 0 0 4px var(--cm-content-bg);
        [data-bs-theme="dark"] & { box-shadow: 0 4px 14px -2px rgba(16,185,129,.6), 0 0 0 4px var(--bs-body-bg); }
    }
}

// ── 30. Content offset so nothing hides behind the bottom bar ────────────────
@include media-breakpoint-down(lg) {
    .has-mobile-nav .main-content {
        padding-bottom: calc(#{$bottomnav-height} + env(safe-area-inset-bottom) + .5rem);
    }
}
@include media-breakpoint-up(lg) {
    .mobile-bottom-nav { display: none !important; } // belt-and-suspenders w/ d-lg-none
}
```

- [ ] **Step 4: Rebuild assets**

Run: `npm run build`
Expected: build succeeds, no SCSS errors.

- [ ] **Step 5: Visually verify the bottom bar**

Open `http://localhost/courtmaster/public/admin/dashboard` (logged in as business owner).
Expected at 375px:
- Bottom bar pinned to the bottom: Home · Bookings · ＋ · Courts · More.
- "Home" tab is emerald (active) on the dashboard.
- Tapping ＋ goes to New Booking; Bookings/Courts navigate; tapping **More** slides in the existing sidebar drawer, and the drawer + dark overlay fully cover the bottom bar (no bar bleeding through).
- Scroll to the very bottom: the last card is fully visible, not hidden behind the bar.
Expected at ≥992px:
- The bottom bar is **not** displayed; the sidebar is the only nav; no extra bottom padding on content.

- [ ] **Step 6: Verify permission fallback (spot check)**

If a non-owner staff account is available, log in as one with limited permissions (e.g. no POS) and confirm the middle slots fill with screens they *can* access and "More" is always present. If no such account exists, confirm the candidate logic by reading the partial and skip live check.

- [ ] **Step 7: Commit**

```bash
git add resources/views/partials/mobile-bottom-nav.blade.php resources/views/layouts/app.blade.php resources/scss/app.scss
git commit -m "feat(ui): add permission-aware mobile bottom tab bar"
```

---

## Task 3: Dashboard mobile refactor

**Files:**
- Modify: `resources/views/admin/dashboard.blade.php`

- [ ] **Step 1: Make hero action buttons full-width on phones**

In `resources/views/admin/dashboard.blade.php`, replace the hero actions block (currently lines 108–115):

```blade
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Booking
            </a>
            <a href="{{ route('admin.courts.status-board') }}" class="btn btn-hero btn-sm">
                <i class="bi bi-grid-3x3-gap me-1"></i>Status Board
            </a>
        </div>
```

with:

```blade
        <div class="d-grid d-sm-flex align-items-center gap-2 flex-shrink-0 w-100 w-md-auto">
            <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Booking
            </a>
            <a href="{{ route('admin.courts.status-board') }}" class="btn btn-hero btn-sm">
                <i class="bi bi-grid-3x3-gap me-1"></i>Status Board
            </a>
        </div>
```

- [ ] **Step 2: Convert Recent Bookings to the shared `.table-stack`**

In the same file, in the Recent Bookings `<style>`/markup, do two edits.

First, **remove** the now-redundant `.recent-table` mobile block from the `@push('styles')` `<style>` (currently lines 70–78):

```css
    /* Recent bookings — collapse to stacked cards on phones */
    @media (max-width: 575.98px) {
        .recent-table thead { display: none; }
        .recent-table, .recent-table tbody, .recent-table tr, .recent-table td { display: block; width: 100%; }
        .recent-table tr { padding: .85rem 1rem; border-bottom: 1px solid var(--bs-border-color); }
        .recent-table td { padding: .15rem 0 !important; border: 0 !important; }
        .recent-table td.recent-amt { font-size: 1.05rem; font-weight: 800; margin-top: .15rem; }
        .recent-table td.recent-action { display: none !important; }
    }
```
Delete that entire block (leave the rest of the `<style>` intact).

Second, replace the table opening tag (currently line 277) from:

```blade
        <table class="table table-hover mb-0 recent-table">
```
to:
```blade
        <table class="table table-hover mb-0 table-stack">
```

- [ ] **Step 3: Add `data-label`s to the Recent Bookings cells**

Replace the entire `<tbody>` row markup of the Recent Bookings table (the `@forelse` row, currently lines 301–330) with this version (adds `data-label` to every `<td>` and marks the action cell `.cell-actions`; the empty-state cell gets `.cell-plain`):

```blade
                <tr>
                    <td data-label="Booking">
                        <a href="{{ route('admin.bookings.show', $booking) }}"
                           class="font-monospace small fw-semibold text-primary text-decoration-none">
                            {{ $booking->booking_number }}
                        </a>
                    </td>
                    <td data-label="Customer">
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $booking->customer->avatar_url }}" alt=""
                                 class="rounded-circle flex-shrink-0" style="width:28px;height:28px;object-fit:cover">
                            <span class="small fw-medium">{{ $booking->customer->name }}</span>
                        </div>
                    </td>
                    <td data-label="Court" class="d-none d-md-table-cell small text-muted">{{ $booking->court->name }}</td>
                    <td data-label="Date & Time" class="d-none d-lg-table-cell">
                        <p class="mb-0 small">{{ $booking->booking_date->format('M j, Y') }}</p>
                        <small class="text-muted">{{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}</small>
                    </td>
                    <td data-label="Amount" class="fw-semibold small recent-amt">₱{{ number_format($booking->total_amount, 2) }}</td>
                    <td data-label="Status">
                        <span class="badge {{ $badge }}">
                            {{ ucfirst(str_replace('_', ' ', $booking->status)) }}
                        </span>
                    </td>
                    <td data-label="" class="cell-actions text-end recent-action">
                        <a href="{{ route('admin.bookings.show', $booking) }}"
                           class="btn btn-outline-primary btn-sm">View</a>
                    </td>
                </tr>
```

Note: the `d-none d-md-table-cell` / `d-none d-lg-table-cell` cells stay hidden on mobile, so the stacked card shows Booking, Customer, Amount, Status, and a full-width "View" button — Court/Date reappear as columns on larger screens. The action cell is now visible (full-width) on mobile instead of hidden.

Also update the empty-state cell (currently lines 332–338) to add `.cell-plain` so it doesn't render a stray label when stacked:

```blade
                <tr>
                    <td colspan="7" class="cell-plain">
                        <x-empty-state title="No bookings yet"
                            description="Bookings will appear here as customers make reservations."
                            icon="bi-calendar"/>
                    </td>
                </tr>
```

- [ ] **Step 4: Bump quick-action tile & court-status tap sizes**

In the Court Status list, increase row tap height — replace the row container class (currently line 231) from:

```blade
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom court-status-item">
```
to:
```blade
                <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom court-status-item">
```

(The quick-action tiles already use 44px icon chips with full-tile links — no change needed; leave as-is.)

- [ ] **Step 5: Rebuild assets**

Run: `npm run build`
Expected: build succeeds, no errors.

- [ ] **Step 6: Visually verify the dashboard at all widths**

Open `http://localhost/courtmaster/public/admin/dashboard`.
Expected at 320 / 375 / 414px:
- No horizontal scroll anywhere.
- Hero: "New Booking" + "Status Board" buttons are full-width and stacked; hero stat grid is 2×2.
- KPI cards 2×2; revenue chart fits with no overflow.
- Recent Bookings renders as **cards** (one per booking) showing Booking #, Customer, Amount, Status, and a full-width "View" button — no inner scrollbar.
- Court status rows are comfortably tall; bottom bar still clears the last card.
Expected at 768px:
- Recent Bookings shows the Court column (md+) as a normal table again.
Expected at ≥992px:
- Dashboard is visually identical to before this task (table with all columns, inline hero buttons, no bottom bar).

- [ ] **Step 7: Verify dark mode + data integrity**

Toggle dark mode (moon/sun in topbar) at 375px: cards, bottom bar, and stacked rows all use dark surfaces correctly. Confirm charts and the lazy-loaded analytics section still populate (logic untouched).

- [ ] **Step 8: Commit**

```bash
git add resources/views/admin/dashboard.blade.php
git commit -m "feat(ui): mobile-refactor admin dashboard (hero, recent bookings cards, tap sizes)"
```

---

## Final verification (whole feature)

- [ ] At 320/375/414/768px on `/admin/dashboard`: zero horizontal scroll; bottom bar correct, permission-aware, "More" opens drawer; FAB → New Booking; content never hidden behind bar; dark mode intact.
- [ ] At ≥992px: desktop dashboard + nav unchanged from before the branch.
- [ ] Spot-check one other admin page (e.g. `/admin/bookings`) at 375px: page-header now stacks actions full-width; existing `bk-table` still works; no regressions.

---

## Self-review (author)

- **Spec §3.1 bottom nav** → Task 2 (partial, gating, z-index 1035, safe-area, FAB, More→drawer, active state). ✓
- **Spec §3.2 `.table-stack`** → Task 1 Step 1 (markup contract: `table-stack` + `data-label` + `.cell-actions`/`.cell-plain`, 768px breakpoint). ✓
- **Spec §3.3 touch/spacing/page-header** → Task 1 Step 2. ✓
- **Spec §3.4 dashboard** (hero buttons, recent→table-stack, removed redundant style, tap sizes, charts no overflow) → Task 3. ✓
- **Spec §3.5 states** → existing primitives reused; `.cell-plain` keeps empty-state clean (Task 3 Step 3). ✓
- **Spec §4 files touched** → exactly the 4 files in File Structure. ✓
- **Spec §5 verification** → per-task visual checks + Final verification at the listed widths. ✓
- **Spec §6 risks** → z-index ordering (Task 2 Step 3), content offset (Task 2 Step 3 §30), permission fallback (Task 2 Steps 1 & 6), desktop guarded by `media-breakpoint-down` (all SCSS). ✓
- **Type/name consistency:** class names `table-stack`, `cell-actions`, `cell-plain`, `mobile-bottom-nav`, `mbn-tab`, `mbn-ic`, `mbn-label`, `mbn-fab`, `mbn-fab-wrap`, `has-mobile-nav` used identically across SCSS, partial, and dashboard edits. ✓
- **No placeholders:** every step shows exact code/commands. ✓
