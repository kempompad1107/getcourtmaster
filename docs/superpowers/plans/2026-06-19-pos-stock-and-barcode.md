# POS Stock Display + Barcode Scanning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show per-product stock on the POS grid, block overselling of inventory-tracked products, and add a toggle-activated barcode/SKU scanner that adds products to the cart.

**Architecture:** Mostly frontend wiring in one Blade file (`admin/pos/index.blade.php`) using the existing Alpine `posSystem()` component. The barcode backend already exists (`admin.pos.barcode` route → `PosController::barcode` → `PosService::lookupByBarcode`); we wire the UI to it. A row-locked server-side stock guard is added to `PosService::createOrder` as defense in depth.

**Tech Stack:** Laravel 11, Blade, Alpine.js, Bootstrap 5, MySQL (prod) / sqlite (local tests — known-broken, see Testing Notes).

---

## Testing Notes (read first)

- The local sqlite test suite is known-broken by a MySQL-only `MODIFY ENUM`
  migration (~61 failures unrelated to this work). Do **not** treat a red
  `php artisan test` as this feature failing. Verify the server guard via the
  targeted test below **only if** the suite is runnable (CI/MySQL); otherwise
  verify via `php artisan tinker` and manual browser checks.
- Frontend (Blade/Alpine) changes are verified manually in the browser at
  `/admin/pos`. There is no JS test harness in this repo.

## File Structure

| File | Responsibility | Change |
|------|----------------|--------|
| `app/Http/Controllers/Admin/PosController.php` | Barcode JSON shape | Add `track_inventory` to `barcode()` response |
| `app/Services/PosService.php` | Sale + inventory integrity | Row-locked stock guard in `createOrder` before deduction |
| `resources/views/admin/pos/index.blade.php` | POS UI | Stock badges, out-of-stock dimming, click-payload fields, scanner toggle/input, Alpine state + handlers, overselling block |
| `tests/Feature/PosTest.php` | Server guard regression | Add overselling-block test (CI/MySQL only) |

---

## Task 1: Add `track_inventory` to the barcode endpoint response

**Files:**
- Modify: `app/Http/Controllers/Admin/PosController.php` (the `barcode()` method, ~lines 28-42)

- [ ] **Step 1: Add the field to the JSON payload**

In `barcode()`, the product array currently ends with `'stock'`. Add
`track_inventory` so scanned items carry the same data a clicked card does:

```php
'product' => $product ? [
    'id'              => $product->id,
    'name'            => $product->name,
    'sku'             => $product->sku,
    'price'           => (float) $product->selling_price,
    'tax'             => (float) $product->tax_rate,
    'stock'           => (int) $product->stock_quantity,
    'track_inventory' => (bool) $product->track_inventory,
] : null,
```

- [ ] **Step 2: Sanity-check the route responds**

Run (replace `<CODE>` with a real product barcode/sku for a logged-in tenant, or
just confirm the method shape compiles):

```bash
php -l app/Http/Controllers/Admin/PosController.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Admin/PosController.php
git commit -m "feat(pos): include track_inventory in barcode lookup response"
```

---

## Task 2: Server-side overselling guard in `createOrder`

**Files:**
- Modify: `app/Services/PosService.php` (the `createOrder()` method, ~lines 17-86)
- Test: `tests/Feature/PosTest.php`

The current loop in `createOrder` calls `Product::find($item['product_id'])`
twice (once for pricing, once for the stock deduction) and never guards against
overselling. We add a `lockForUpdate` load and a guard. Untracked products are
never blocked.

- [ ] **Step 1: Write the failing test (CI/MySQL only)**

Add to `tests/Feature/PosTest.php`. Match the existing test setup/style in that
file (factories, auth, tenant). The assertion: an order requesting more than the
tracked stock is rejected with a 422 and stock is unchanged.

```php
public function test_pos_blocks_overselling_a_tracked_product(): void
{
    // Arrange: a cashier + a tracked product with only 2 in stock.
    // (Use the same auth/tenant/branch setup the other PosTest cases use.)
    $cashier = $this->actingCashier();           // existing helper / inline setup
    $product = Product::factory()->create([
        'tenant_id'       => $cashier->tenant_id,
        'branch_id'       => $cashier->branch_id,
        'track_inventory' => true,
        'stock_quantity'  => 2,
        'selling_price'   => 100,
        'tax_rate'        => 0,
        'is_active'       => true,
    ]);

    // Act: try to sell 3.
    $response = $this->postJson(route('admin.pos.store'), [
        'items' => [[
            'product_id' => $product->id,
            'name'       => $product->name,
            'quantity'   => 3,
            'unit_price' => 100,
            'tax_rate'   => 0,
        ]],
        'branch_id'       => $cashier->branch_id,
        'payments'        => [['method' => 'cash', 'amount' => 300]],
        'amount_tendered' => 300,
    ]);

    // Assert: rejected, stock untouched.
    $response->assertStatus(422);
    $this->assertSame(2, $product->fresh()->stock_quantity);
}
```

> If `Product::factory()` lacks these fields or an `actingCashier` helper does
> not exist, mirror the arrange block of the nearest existing test in
> `tests/Feature/PosTest.php` instead — do not invent new helpers.

- [ ] **Step 2: Run the test to verify it fails**

Run:

```bash
php artisan test --filter test_pos_blocks_overselling_a_tracked_product
```

Expected (if suite runnable): FAIL — order succeeds with status 200 / stock goes
negative. If the suite is broken by the sqlite ENUM migration, skip to Step 3
and verify manually per Testing Notes.

- [ ] **Step 3: Implement the guard**

In `createOrder`, replace the pricing loop's `Product::find(...)` with a
row-locked load, and add the guard. Then reuse that loaded model for the
deduction instead of re-finding it. The full updated loop body:

```php
foreach ($items as &$item) {
    $product = ! empty($item['product_id'])
        ? Product::where('id', $item['product_id'])->lockForUpdate()->first()
        : null;

    // Block overselling of inventory-tracked products (defense in depth —
    // the POS UI also guards this, but two cashiers racing the last unit, or
    // any client bypass, must still fail cleanly here).
    if ($product && $product->track_inventory && $item['quantity'] > $product->stock_quantity) {
        throw ValidationException::withMessages([
            'items' => "Only {$product->stock_quantity} of {$product->name} in stock.",
        ]);
    }

    $unitPrice = $item['unit_price'] ?? ($product?->selling_price ?? 0);
    $quantity  = $item['quantity'];
    $taxRate   = $product?->tax_rate ?? 0;
    $itemTax   = $unitPrice * $quantity * ($taxRate / 100);
    $item['unit_price'] = $unitPrice;
    $item['tax']        = $itemTax;
    $item['subtotal']   = ($unitPrice * $quantity) + $itemTax;
    $item['_product']   = $product;   // reuse for deduction; stripped before persist
    $subtotal += $unitPrice * $quantity;
    $taxTotal += $itemTax;
}
unset($item);
```

Then in the persistence loop, use the already-loaded, already-locked product and
do **not** persist the temporary `_product` key:

```php
foreach ($items as $item) {
    PosOrderItem::create([
        'order_id'   => $order->id,
        'product_id' => $item['product_id'] ?? null,
        'name'       => $item['name'],
        'sku'        => $item['sku'] ?? null,
        'quantity'   => $item['quantity'],
        'unit_price' => $item['unit_price'],
        'discount'   => 0,
        'tax'        => $item['tax'],
        'subtotal'   => $item['subtotal'],
    ]);

    $product = $item['_product'] ?? null;
    if ($product && $product->track_inventory) {
        $product->adjustStock(
            -$item['quantity'],
            'sale',
            null,
            $order->order_number,
            $cashier->id
        );
    }
}
```

`ValidationException` is already imported at the top of `PosService.php`. The
`store()` controller already catches `ValidationException` and returns a clean
422, so no controller change is needed.

- [ ] **Step 4: Run the test to verify it passes**

Run:

```bash
php artisan test --filter test_pos_blocks_overselling_a_tracked_product
```

Expected (if runnable): PASS. Otherwise verify in tinker: create a tracked
product with `stock_quantity = 1`, call
`app(\App\Services\PosService::class)->createOrder([...qty 2...], $cashier)` and
confirm it throws `ValidationException` and stock stays at 1.

- [ ] **Step 5: Confirm no regression for untracked + normal sales**

`php -l app/Services/PosService.php` → `No syntax errors detected`. Manually (or
in tinker) ring a normal in-stock sale and an untracked-product sale; both must
still succeed and (for tracked) decrement stock by the sold quantity.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PosService.php tests/Feature/PosTest.php
git commit -m "feat(pos): block overselling tracked products with row-locked guard"
```

---

## Task 3: Stock badges + out-of-stock dimming on product cards

**Files:**
- Modify: `resources/views/admin/pos/index.blade.php` (product card block ~lines 96-123; styles block ~lines 5-63)

- [ ] **Step 1: Add the out-of-stock CSS**

Inside the existing `@push('styles')` `<style>` block, add:

```css
.pos-product.is-oos { opacity: .5; pointer-events: none; filter: grayscale(.3); }
```

- [ ] **Step 2: Add stock to the click payload and guard the click**

Replace the card wrapper + `@click` so the payload carries stock fields and an
out-of-stock card is both visually dimmed and unclickable:

```blade
<div class="card pos-product h-100 @if($product->isOutOfStock()) is-oos @endif"
     @if(! $product->isOutOfStock())
     @click="addItem({{ json_encode([
         'product_id'      => $product->id,
         'name'            => $product->name,
         'unit_price'      => (float) $product->selling_price,
         'tax_rate'        => (float) $product->tax_rate,
         'stock'           => (int) $product->stock_quantity,
         'track_inventory' => (bool) $product->track_inventory,
     ]) }})"
     @endif>
```

- [ ] **Step 3: Replace the low-stock-only badge with full stock badges**

Replace the existing `@if($product->isLowStock()) … @endif` badge block in the
card body with the three-state badge logic (only for tracked products):

```blade
@if($product->track_inventory)
    @if($product->isOutOfStock())
    <span class="badge rounded-pill bg-danger-subtle text-danger mt-2 align-self-start">
        <i class="bi bi-x-circle me-1"></i>Out of stock
    </span>
    @elseif($product->isLowStock())
    <span class="badge rounded-pill bg-warning-subtle text-warning mt-2 align-self-start">
        <i class="bi bi-exclamation-triangle me-1"></i>Low: {{ $product->stock_quantity }}
    </span>
    @else
    <span class="badge rounded-pill bg-secondary-subtle text-secondary mt-2 align-self-start">
        <i class="bi bi-box-seam me-1"></i>Stock: {{ $product->stock_quantity }}
    </span>
    @endif
@endif
```

- [ ] **Step 4: Verify in the browser**

Run the app and open `/admin/pos`. Confirm: in-stock cards show grey `Stock: N`,
low cards show amber `Low: N`, out-of-stock cards show red `Out of stock`, are
dimmed, and cannot be clicked. Untracked products show no badge and click
normally.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/pos/index.blade.php
git commit -m "feat(pos): show per-product stock badges and dim out-of-stock cards"
```

---

## Task 4: Client-side overselling block in the cart

**Files:**
- Modify: `resources/views/admin/pos/index.blade.php` (Alpine `posSystem()` — `addItem`/`incrementItem` ~lines 280-294; add a `stockMessage` state + display)

- [ ] **Step 1: Add `stockMessage` state**

In the `posSystem()` return object, alongside the other state props (e.g. after
`processing: false,`), add:

```js
stockMessage: '',
```

- [ ] **Step 2: Add a place to show the message**

In the cart card body, just above the totals block (before the
`<div class="d-flex justify-content-between small text-muted mb-1"><span>Subtotal</span>` line),
add:

```blade
<p x-show="stockMessage" x-text="stockMessage" class="small text-danger mb-2"></p>
```

- [ ] **Step 3: Enforce the ceiling in `addItem` and `incrementItem`**

Replace `addItem` and `incrementItem` with versions that respect stock for
tracked products and surface a message. A small shared helper keeps it DRY:

```js
atStockCeiling(line, nextQty) {
    return line.track_inventory && typeof line.stock === 'number' && nextQty > line.stock;
},
addItem(product) {
    const existing = this.cart.find(i => i.product_id === product.product_id);
    const nextQty = existing ? existing.quantity + 1 : 1;
    if (this.atStockCeiling(product, nextQty)) {
        this.stockMessage = `Only ${product.stock} of ${product.name} in stock.`;
        return;
    }
    this.stockMessage = '';
    if (existing) { existing.quantity++; } else { this.cart.push({ ...product, quantity: 1 }); }
    this.clearPromo();
},
incrementItem(index) {
    const line = this.cart[index];
    if (this.atStockCeiling(line, line.quantity + 1)) {
        this.stockMessage = `Only ${line.stock} of ${line.name} in stock.`;
        return;
    }
    this.stockMessage = '';
    line.quantity++;
    this.clearPromo();
},
```

- [ ] **Step 4: Verify in the browser**

On `/admin/pos`, click a tracked product up to its stock count, then once more —
the extra click is refused and "Only N of … in stock." appears. Untracked
products increment without limit.

- [ ] **Step 5: Commit**

```bash
git add resources/views/admin/pos/index.blade.php
git commit -m "feat(pos): block adding tracked products beyond available stock"
```

---

## Task 5: Toggle-activated barcode scanner

**Files:**
- Modify: `resources/views/admin/pos/index.blade.php` (page header actions ~lines 68-74; scanner bar above category tabs ~line 81; Alpine state + `toggleScan`/`submitScan` handlers)

- [ ] **Step 1: Add the Scan toggle button to the header**

In the `<x-slot name="actions">` of the page header, before the Sales History
link, add (this must be inside the `x-data="posSystem()"` scope — see Step 4 if
the header is outside it):

```blade
<button type="button" @click="toggleScan()"
        :class="scanning ? 'btn-primary' : 'btn-outline-secondary'"
        class="btn btn-sm me-2">
    <i class="bi bi-upc-scan me-1"></i><span x-text="scanning ? 'Done' : 'Scan'"></span>
</button>
```

- [ ] **Step 2: Add the scanner input bar above the category tabs**

Immediately inside `<div class="col-12 col-lg-8">`, before the
`pos-cat-tabs` div, add:

```blade
<div x-show="scanning" x-cloak class="input-group input-group-sm mb-3">
    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
    <input x-ref="scanInput" x-model="scanCode" type="text"
           placeholder="Scan or type barcode / SKU, then Enter"
           @keydown.enter.prevent="submitScan()"
           class="form-control">
    <button type="button" @click="submitScan()" class="btn btn-outline-primary">Add</button>
</div>
<p x-show="scanMessage" x-text="scanMessage" class="small text-danger mb-2"></p>
```

- [ ] **Step 3: Add scanner state + handlers to `posSystem()`**

Add state props (near `stockMessage`):

```js
scanning: false,
scanCode: '',
scanMessage: '',
```

Add methods (alongside the other methods):

```js
toggleScan() {
    this.scanning = !this.scanning;
    this.scanMessage = '';
    if (this.scanning) { this.$nextTick(() => this.$refs.scanInput?.focus()); }
},
async submitScan() {
    const code = this.scanCode.trim();
    if (!code) return;
    try {
        const res = await fetch(`${window.APP_BASE}/admin/pos/barcode`, {
            method: 'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':     '{{ csrf_token() }}',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ code }),
        });
        const data = await res.json();
        const p = data?.product;
        if (!p) {
            this.scanMessage = `No product found for "${code}".`;
        } else if (p.track_inventory && p.stock <= 0) {
            this.scanMessage = `${p.name} is out of stock.`;
        } else {
            // Map the barcode response into the shape addItem expects.
            this.addItem({
                product_id:      p.id,
                name:            p.name,
                unit_price:      p.price,
                tax_rate:        p.tax,
                stock:           p.stock,
                track_inventory: p.track_inventory,
            });
            // addItem sets stockMessage if the ceiling was hit; clear any
            // stale scan message either way (the add was attempted).
            this.scanMessage = '';
        }
    } catch (err) {
        this.scanMessage = 'Scan lookup failed. Please try again.';
    } finally {
        this.scanCode = '';
        this.$refs.scanInput?.focus();
    }
},
```

- [ ] **Step 4: Confirm the header is inside the Alpine scope**

The `@click="toggleScan()"` button (Step 1) must be inside the element carrying
`x-data="posSystem()"`. In the current file `x-data` is on the `<div class="row g-4">`
**below** the `<x-page-header>`, so the header button is OUT of scope. Fix by
moving `x-data="posSystem()"` up onto a wrapper that includes both the header and
the row — wrap the page header and the `.row` in a single
`<div x-data="posSystem()">` and remove `x-data` from the inner `.row` (keep its
classes: `<div class="row g-4">`). Verify the page still renders and the cart
still works after the move.

- [ ] **Step 5: Verify in the browser**

On `/admin/pos`: click **Scan** → input appears and is focused, button reads
**Done**. Type a valid barcode/SKU + Enter (or use a USB-HID scanner) → product
is added to the cart and the field clears, ready for the next scan. An unknown
code shows "No product found". An out-of-stock code shows the out-of-stock
message. Scanning a tracked item past its stock is blocked (via `addItem`).
Click **Done** → input hides.

- [ ] **Step 6: Commit**

```bash
git add resources/views/admin/pos/index.blade.php
git commit -m "feat(pos): wire toggle-activated barcode/SKU scanner into cart"
```

---

## Final verification

- [ ] `/admin/pos` loads cleanly; no console errors.
- [ ] Stock badges correct for in-stock / low / out-of-stock / untracked.
- [ ] Out-of-stock card not clickable; clicking/scanning past stock blocked with message.
- [ ] Scanner toggles, adds by barcode and by SKU, clears + refocuses, handles no-match and out-of-stock.
- [ ] A normal sale and an untracked-product sale still complete and (for tracked) decrement stock.
- [ ] Server guard rejects an oversell attempt with a 422 (tinker/manual if suite unrunnable).
