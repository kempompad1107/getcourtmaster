# Customer Portal — Mobile Parity with Admin Portal

**Date:** 2026-06-11
**Status:** Approved

## Goal

Bring the customer portal to visual/behavioral parity with the staff/admin portal.
Desktop is already identical (shared sidebar/topbar/theme via `app.scss`); the work is
mobile-only.

## Scope

### 1. Mobile bottom tab bar

New partial `resources/views/partials/customer-mobile-bottom-nav.blade.php`, mirroring
the admin bar's structure and classes (`.mobile-bottom-nav`, `.mbn-tab`, `.mbn-fab-wrap`,
`.mbn-fab`, `.mbn-label`, `d-lg-none`). Tabs are static (no permission gating):

| Slot | Label | Icon | Route | Active pattern |
|------|-------|------|-------|----------------|
| 1 | Home | `bi-house-door` | `customer.dashboard` | `customer.dashboard` |
| 2 | Bookings | `bi-calendar-check` | `customer.bookings.index` | `customer.bookings.index` |
| 3 (FAB) | Book | `bi-plus-lg` | `customer.bookings.create` | — |
| 4 | Wallet | `bi-wallet2` | `customer.wallet.index` | `customer.wallet.*` |
| 5 | More | `bi-list` | opens drawer via `$store.sidebar.openMobile()` | — |

Layout changes in `resources/views/layouts/customer.blade.php`:

- Add `has-mobile-nav` class to `<body>` (existing SCSS adds bottom padding to
  `.main-content` so content is not covered).
- `@include('partials.customer-mobile-bottom-nav')` after `</main>`, same position as
  the admin layout.

No SCSS changes — all `.mobile-bottom-nav` / `.mbn-*` / `.has-mobile-nav` rules in
`app.scss` are portal-agnostic and already shipped for admin.

### 2. Table consolidation to shared `table-stack`

Three customer pages carry private copies of the card-stacking CSS. Replace each with
the shared `table-stack` pattern (class on `<table>`, `data-label` on `<td>`,
`.cell-plain` to suppress a label, `.cell-actions` for full-width action buttons) and
delete the duplicated `@media` CSS blocks:

| Page | Table class today | Change |
|------|-------------------|--------|
| `customer/bookings/index.blade.php` | `.cb-table` | `table-stack`; action cell (`View` button) becomes `.cell-actions`; `bk-cell-empty` → `cell-plain` |
| `customer/wallet/index.blade.php` | `.wtx` | `table-stack`; `bk-cell-empty` → `cell-plain`; keep page-specific stat-tile CSS |
| `customer/memberships/index.blade.php` | `.cust-tbl` (×2 tables) | `table-stack`; keep pricing-card CSS |

`data-label` attributes already exist on all cells — only classes change.

**Accepted behavior change:** membership tables stack below 768px instead of 576px,
matching admin behavior (that is the point of parity).

## Out of scope

- Desktop changes (already at parity).
- Controllers, routes, SCSS.
- The customer booking wizard (`bookings/create`) — verified it has no sticky bottom
  element conflicting with the fixed bottom bar; it gets the bar like every other page.
- Shared pages (notifications, profile) — they use the customer layout and inherit the
  bar automatically; their content is card-based, no table work needed.

## Risks / verification

- Low risk: bottom-nav SCSS proven in the admin portal; table-stack proven on 14 admin
  pages.
- Verify on a phone-width viewport: bar shows on all customer pages, FAB routes to the
  wizard, drawer opens from More, tables render as cards with labels, the bookings View
  button is full-width, and nothing is hidden behind the bar (incl. paginated wallet
  footer).
- Test suite is broken on sqlite (known, pre-existing); verification is manual/visual.
