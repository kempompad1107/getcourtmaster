# Button Consistency Normalization — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Normalize button variants across all four portals so each action *role* renders with one consistent Bootstrap variant, per the convention in the design spec.

**Architecture:** Pure Blade class-token swaps. No JS, routes, controllers, or `app.scss` changes. The button CSS is already centralized and shared; this aligns the *markup* to the convention. Verification is grep-based (the disallowed variants must reduce to a documented exception set) plus an asset rebuild and spot render.

**Tech Stack:** Laravel Blade, Bootstrap 5 button classes, Vite/SCSS (`npm run build`).

**Spec:** `docs/superpowers/specs/2026-06-03-button-consistency-design.md`

---

## Convention recap (target state)

| Role | Variant |
|---|---|
| Primary action (Save/Create/Submit) | `btn-primary` |
| Neutral (Cancel/Back/Filter/modal-dismiss) | `btn-outline-secondary` |
| Active nav-tab (selected page in a `.btn-group`) | `btn-primary` |
| Secondary emphasis (Export/alt) | `btn-outline-primary` |
| Destructive — inline/row/secondary | `btn-outline-danger` |
| Destructive — modal confirm OR single hero CTA | `btn-danger` (kept) |
| Money/confirm (Approve/Mark paid/Checkout) | `btn-success` (kept) |
| Inline text action | `btn-link` (kept) |
| Functional toggle active-state | keep its themed variant (e.g. `btn-warning` for low-stock/walk-in) |

### Documented exceptions (these KEEP solid `btn-danger`/`btn-warning`/`btn-light`)
- `btn-danger` (modal confirm): `admin/bookings/show.blade.php:505,533`, `admin/customers/show.blade.php:270`, `admin/refund-requests/index.blade.php:184`
- `btn-danger` (hero CTA): `admin/staff/my-shift.blade.php:138` (full-width Clock Out)
- `btn-warning` (functional toggle): `admin/bookings/create.blade.php:197,203` (walk-in cap/bump), `admin/inventory/index.blade.php:14` & `admin/inventory/products/index.blade.php:46` (low-stock filter)
- `btn-light` / `btn-danger` / `btn-warning` / `btn-info` inside `resources/views/components/button.blade.php` variant map — these are the component abstraction, handled in Task 6, not page buttons.

---

## Task 1: Neutral `btn-secondary` → `btn-outline-secondary` (filters, submits, modal cancels)

These are all neutral actions (filter-form submits, "Apply", and modal-dismiss buttons).

**Files / exact swaps** — in each, change the `btn-secondary` token to `btn-outline-secondary`, leaving every other class/attribute intact:

- `resources/views/admin/audit/index.blade.php:54` — Filter button
- `resources/views/admin/billing/invoices.blade.php:48` — filter submit
- `resources/views/admin/bookings/index.blade.php:81` — filter submit
- `resources/views/admin/bookings/show.blade.php:449` — modal Cancel
- `resources/views/admin/bookings/show.blade.php:504` — modal "Keep booking"
- `resources/views/admin/bookings/show.blade.php:532` — modal Cancel
- `resources/views/admin/branches/index.blade.php:121` — filter submit
- `resources/views/admin/courts/index.blade.php:101` — filter submit
- `resources/views/admin/customers/index.blade.php:92` — filter submit
- `resources/views/admin/customers/show.blade.php:233` — modal Cancel
- `resources/views/admin/customers/show.blade.php:269` — modal Cancel
- `resources/views/admin/inventory/index.blade.php:45` — filter submit
- `resources/views/admin/inventory/products/edit.blade.php:141` — "Apply"
- `resources/views/admin/inventory/products/index.blade.php:77` — filter submit
- `resources/views/admin/inventory/suppliers/index.blade.php:85` — filter submit
- `resources/views/admin/memberships/index.blade.php:98` — filter submit
- `resources/views/admin/pos/history.blade.php:74` — filter submit
- `resources/views/admin/refund-requests/index.blade.php:159` — modal Cancel
- `resources/views/admin/refund-requests/index.blade.php:183` — modal Cancel
- `resources/views/admin/staff/shifts.blade.php:79` — filter submit
- `resources/views/admin/wallet/show.blade.php:121` — modal Cancel
- `resources/views/admin/wallet/show.blade.php:162` — modal Cancel
- `resources/views/customer/memberships/index.blade.php:330` — modal Cancel
- `resources/views/customer/memberships/index.blade.php:369` — modal Cancel
- `resources/views/customer/memberships/index.blade.php:398` — modal Cancel
- `resources/views/super/billing/invoices.blade.php:154` — modal Cancel
- `resources/views/super/tenants/show.blade.php:256` — modal Cancel
- `resources/views/super/tenants/show.blade.php:275` — modal Cancel
- `resources/views/super/tenants/show.blade.php:294` — modal Cancel

- [ ] **Step 1: Apply the swaps**

For each file above, replace `btn btn-secondary btn-sm` → `btn btn-outline-secondary btn-sm` (and the one `btn btn-secondary btn-sm w-100`-adjacent forms keep their extra classes). Use Edit with enough surrounding context to make each match unique. Example (audit):

```
old: <button class="btn btn-secondary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
new: <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
```

Note: line numbers shift as you edit a file; match on the string, not the line.

- [ ] **Step 2: Verify none of these neutral ones remain**

Run:
```bash
grep -rnE "btn-secondary([^-]|$)" resources/views | grep -v "btn-outline-secondary"
```
Expected: only the 5 **active nav-tab** lines remain (Task 2 handles them) and the `components/button.blade.php` map line — nothing else.

- [ ] **Step 3: Commit**

```bash
git add resources/views
git commit -m "style(ui): neutral btn-secondary -> btn-outline-secondary across portals"
```

---

## Task 2: Active nav-tab `btn-secondary` → `btn-primary` (5 files)

Each is the selected tab in a `.btn-group` whose inactive sibling is already `btn-outline-secondary`. Making the active tab `btn-primary` keeps a clear "you are here" cue and removes `btn-secondary`.

**Exact swaps** (`btn btn-sm btn-secondary` → `btn btn-sm btn-primary`):

- `resources/views/admin/inventory/categories/index.blade.php:12` — "Categories" (active)
- `resources/views/admin/inventory/purchase-orders/index.blade.php:38` — "Purchase Orders" (active)
- `resources/views/admin/inventory/suppliers/index.blade.php:10` — "Suppliers" (active)
- `resources/views/admin/memberships/plans.blade.php:31` — "Plans" (active)
- `resources/views/admin/staff/shifts.blade.php:43` — "Shifts & Attendance" (active)

- [ ] **Step 1: Apply the swaps**

Example (categories):
```
old:                class="btn btn-sm btn-secondary">Categories</a>
new:                class="btn btn-sm btn-primary">Categories</a>
```
Match each on its label text to stay unique.

- [ ] **Step 2: Verify zero `btn-secondary` remain in views**

Run:
```bash
grep -rnE "btn-secondary([^-]|$)" resources/views | grep -v "btn-outline-secondary" | grep -v "components/button.blade.php"
```
Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add resources/views
git commit -m "style(ui): active nav-tab btn-secondary -> btn-primary (5 sub-navs)"
```

---

## Task 3: "Save Note" submit → `btn-primary`

`admin/customers/show.blade.php:198` is a form submit ("Save Note") — a primary action wrongly using secondary. It was excluded from Task 1 because its role is primary, not neutral.

- [ ] **Step 1: Apply the swap**

```
old:                    <button type="submit" class="btn btn-secondary btn-sm w-100">Save Note</button>
new:                    <button type="submit" class="btn btn-primary btn-sm w-100">Save Note</button>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/admin/customers/show.blade.php
git commit -m "style(ui): Save Note submit -> btn-primary (primary action)"
```

---

## Task 4: Stray `btn-danger` → `btn-outline-danger` (inline/secondary destructive)

Convert only the non-modal, non-hero destructive buttons. **Do NOT touch** the exceptions listed at the top (modal confirms `:505,:533,:270,:184`, hero `my-shift:138`).

**Exact swaps:**

- `resources/views/admin/bookings/show.blade.php:87` — "Stop" (session control, btn-sm)
  ```
  old:                class="btn btn-danger btn-sm">Stop</button>
  new:                class="btn btn-outline-danger btn-sm">Stop</button>
  ```
- `resources/views/admin/courts/edit.blade.php:165` — remove-X icon button
  ```
  old:                            class="btn btn-danger btn-sm p-0 lh-1 position-absolute top-0 end-0 m-1"
  new:                            class="btn btn-outline-danger btn-sm p-0 lh-1 position-absolute top-0 end-0 m-1"
  ```
- `resources/views/admin/staff/index.blade.php:79` — "Clock Out" (row action, btn-sm)
  ```
  old:                <button class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Clock Out</button>
  new:                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Clock Out</button>
  ```

- [ ] **Step 1: Apply the three swaps above.**

- [ ] **Step 2: Verify remaining `btn-danger` equals the exception set only**

Run:
```bash
grep -rnE "btn-danger([^-]|$)" resources/views | grep -v "btn-outline-danger"
```
Expected exactly these lines (plus `components/button.blade.php` map):
- `admin/bookings/show.blade.php:505`
- `admin/bookings/show.blade.php:533`
- `admin/customers/show.blade.php:270`
- `admin/refund-requests/index.blade.php:184`
- `admin/staff/my-shift.blade.php:138`

- [ ] **Step 3: Commit**

```bash
git add resources/views
git commit -m "style(ui): inline destructive btn-danger -> btn-outline-danger (keep modal/hero)"
```

---

## Task 5: `btn-warning` and `btn-light` strays

Keep the functional toggles (walk-in cap/bump, low-stock filter). Convert the two genuine strays.

**Swap A — `super/tenants/show.blade.php:276` "Set Trial"** (one-off admin action → neutral):
```
old:                <button class="btn btn-warning btn-sm">Set Trial</button>
new:                <button class="btn btn-outline-secondary btn-sm">Set Trial</button>
```

**Swap B — `admin/bookings/show.blade.php:82` "+30 min"** (paired neutral action next to Stop):
```
old:                class="btn btn-light btn-sm">
new:                class="btn btn-outline-secondary btn-sm">
```

- [ ] **Step 1: Apply swaps A and B.**

- [ ] **Step 2: Verify remaining `btn-warning` / `btn-light` are functional-toggle/component only**

Run:
```bash
grep -rnE "btn-warning|btn-light" resources/views | grep -v "btn-outline-warning"
```
Expected: only `admin/bookings/create.blade.php:197,203`, `admin/inventory/index.blade.php:14`, `admin/inventory/products/index.blade.php:46`, and `components/button.blade.php` lines.

- [ ] **Step 3: Commit**

```bash
git add resources/views
git commit -m "style(ui): map stray btn-warning/btn-light to convention (keep functional toggles)"
```

---

## Task 6: Align `<x-button>` component variant map with the convention

So future `<x-button>` usage can't reintroduce the eliminated variants. Edit `resources/views/components/button.blade.php`.

- [ ] **Step 1: Update the `$variants` map**

```
old:    'secondary'       => 'btn-secondary',
new:    'secondary'       => 'btn-outline-secondary',
```
```
old:    'ghost'           => 'btn-light',
new:    'ghost'           => 'btn-outline-secondary',
```
```
old:    'soft'            => 'btn-light',
new:    'soft'            => 'btn-outline-secondary',
```
Leave `danger`, `warning`, `success`, `info`, `outline*`, `link` as-is (still valid roles).

- [ ] **Step 2: Commit**

```bash
git add resources/views/components/button.blade.php
git commit -m "style(ui): align x-button variant map with button convention"
```

---

## Task 7: Build, clear views, and verify

- [ ] **Step 1: Clear compiled Blade views**

```bash
php artisan view:clear
```
Expected: "Compiled views cleared successfully."

- [ ] **Step 2: Rebuild front-end assets** (no SCSS changed, but rebuild to be safe)

```bash
npm run build
```
Expected: Vite build completes with no errors.

- [ ] **Step 3: Final convention assertion**

```bash
echo "btn-secondary (expect 0):"; grep -rnE "btn-secondary([^-]|$)" resources/views | grep -v "btn-outline-secondary" | grep -v "components/button.blade.php"
echo "btn-danger (expect only 5 modal/hero exceptions):"; grep -rnE "btn-danger([^-]|$)" resources/views | grep -v "btn-outline-danger" | grep -v "components/button.blade.php"
```
Expected: first command empty; second lists exactly the 5 documented exception lines.

- [ ] **Step 4: Spot-render one page per portal**

Load each and confirm no Blade error and buttons look right:
- Auth: `/login`
- Admin/staff: dashboard, a form page (e.g. customers/create), a table page (e.g. customers index), and `admin/bookings/show` (modals: Cancel buttons outline, confirm button still solid danger)
- Super/owner: `super/tenants/index` and a `super/tenants/show` (modal cancels outline)
- Customer: `customer/bookings/index` and `customer/memberships/index` (modal cancels outline)

Confirm: filters/cancels are outline grey, active sub-nav tabs are emerald, destructive row actions are outline-red, modal confirm buttons stay solid red.

- [ ] **Step 5: Final commit (if any view-cache/build artifacts are tracked — usually nothing to add)**

```bash
git status   # confirm clean working tree aside from intended changes already committed
```

---

## Self-review notes
- **Spec coverage:** every normalization rule (1–7) in the spec maps to a task. Rule 7 (preserve other classes) is enforced by string-level swaps. Rule 6 (don't mass-change sizes) — no size changes anywhere in this plan.
- **Exceptions:** modal-confirm danger, hero Clock-Out danger, and functional warning toggles are explicitly preserved and verified.
- **No placeholders:** every swap shows exact old/new strings.
- **End state:** zero `btn-secondary` in views; `btn-danger`/`btn-warning`/`btn-light` reduced to the documented functional/exception set; `<x-button>` map aligned.
