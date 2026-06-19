# POS Stock Display + Barcode Scanning — Design

**Date:** 2026-06-19
**Status:** Approved (pending spec review)
**Page:** `/admin/pos` ([resources/views/admin/pos/index.blade.php](../../../resources/views/admin/pos/index.blade.php))

## Background

The POS already has most of the plumbing for both requested features, but the
UI does not use it:

- **Barcode:** A working endpoint `POST /admin/pos/barcode`
  ([PosController::barcode()](../../../app/Http/Controllers/Admin/PosController.php))
  → [PosService::lookupByBarcode()](../../../app/Services/PosService.php)
  matches on `barcode` **or** `sku`, but the POS screen has no input that calls
  it — so it is dead weight for the cashier today.
- **Stock:** `Product` has `stock_quantity`, `track_inventory`,
  `low_stock_threshold`, `isLowStock()`, `isOutOfStock()`. The POS grid only
  renders a "Low: N" badge for low-stock items; it never shows the count for
  normal items, and nothing prevents a cashier from overselling.

This work is mostly **frontend wiring** of one Blade file, plus a small
server-side guard.

## Decisions (from brainstorming)

1. **Stock behavior:** show availability on every card **and block
   overselling** — but only for products with `track_inventory` enabled.
   Untracked products continue to sell freely.
2. **Scanner UX:** **toggle/button to activate** a focused input (not an
   always-focused box), so the field never steals focus from promo/amount
   inputs.

## Feature 1 — Stock display on every product card

Each card shows availability scaled to its state (only when `track_inventory`):

| State | Badge | Card behavior |
|-------|-------|---------------|
| In stock | muted `Stock: N` | clickable (normal) |
| Low stock (`isLowStock()`) | amber `Low: N` (existing) | clickable |
| Out of stock (`isOutOfStock()`) | red `Out of stock` | dimmed + not clickable |
| Untracked (`track_inventory` off) | no badge | clickable, no limit |

The card-click payload ([index.blade.php around line 100](../../../resources/views/admin/pos/index.blade.php))
gains two fields so the cart can enforce limits:

- `stock` — `(int) $product->stock_quantity`
- `track_inventory` — `(bool) $product->track_inventory`

Out-of-stock dimming uses CSS (`opacity` + `pointer-events: none`) and the
`@click` is guarded so it cannot add the item.

## Feature 2 — Overselling block (tracked products only)

**Client (Alpine):**

- `addItem(product)` — if `product.track_inventory` and the resulting quantity
  would exceed `product.stock`, refuse and show an inline message
  ("Only N in stock."). Store `stock` and `track_inventory` on the cart line.
- `incrementItem(index)` — same ceiling check before incrementing.

**Server (defense in depth):** in
[PosService::createOrder()](../../../app/Services/PosService.php), inside the
existing `DB::transaction`, lock each tracked product row
(`lockForUpdate`) when loading it and throw a `ValidationException` (→ clean
422, already handled by `PosController::store`'s catch block) if
`track_inventory && requested_quantity > stock_quantity`. This protects against:

- two cashiers selling the last unit at the same time, and
- any client that bypasses the JS guard.

Untracked products are never blocked. The existing `adjustStock(-qty, 'sale', …)`
deduction stays unchanged and now only runs after the guard passes.

## Feature 3 — Toggle-activated barcode scanner

**Trigger:** a **"Scan"** button in the page header, next to "Sales History".
Clicking toggles a `scanning` Alpine flag and reveals a barcode/SKU input bar
above the category tabs; the button label flips to "Done". When activated the
input is focused (`x-ref` + `$nextTick(() => $refs.scanInput.focus())`).

**Input behavior:** the field handles `@keydown.enter.prevent` (USB-HID
scanners type the digits then send Enter):

1. POST the code to `route('admin.pos.barcode')` with CSRF + JSON headers (same
   fetch pattern as `applyPromo`).
2. On response:
   - **Match** → call `addItem(product)` (so the stock block applies equally to
     scanned items), clear the field, keep focus for the next scan.
   - **Out of stock** → message, do not add, keep focus.
   - **No match** (`product === null`) → "No product found for `<code>`", keep
     focus.

**Endpoint change:** add `track_inventory` to the `barcode()` JSON response in
[PosController](../../../app/Http/Controllers/Admin/PosController.php) so scanned
products carry the same fields a clicked card does (`id`, `name`, `price` →
`unit_price`, `tax` → `tax_rate`, `stock`, `track_inventory`). The frontend maps
the response into the shape `addItem` expects.

## Scope / files

| File | Change |
|------|--------|
| `resources/views/admin/pos/index.blade.php` | Card badges + out-of-stock dimming; `stock`/`track_inventory` in click payload; scanner toggle button, input bar, Alpine state (`scanning`, `scanCode`, `scanMessage`) + handlers; stock ceiling in `addItem`/`incrementItem` | 
| `app/Services/PosService.php` | Row-locked stock guard in `createOrder` before deduction |
| `app/Http/Controllers/Admin/PosController.php` | Add `track_inventory` to `barcode()` JSON |

No DB migrations — every field already exists.

## Out of scope (YAGNI)

- Editing/adjusting stock from the POS screen (managed under Inventory).
- Per-cashier scanner device config / dedicated hardware integration beyond the
  standard USB-HID "type + Enter" behavior.
- Backorder / negative-stock workflows.

## Testing

- **Manual:** verify on `/admin/pos` — stock badges render per state;
  out-of-stock card is not clickable; clicking/scanning a tracked item past its
  stock is blocked with a message; scanning a valid code adds it; scanning an
  unknown code shows "no product found".
- **Note:** the local sqlite test suite is known-broken via a MySQL-only
  migration (see memory `test-suite-sqlite-broken`); for any added assertions,
  verify behavior via `tinker`/manual rather than relying on a green
  `php artisan test`. The server-side guard is the main candidate for a feature
  test if the suite is runnable in CI (MySQL).
