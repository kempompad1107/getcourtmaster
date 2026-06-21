@extends('layouts.app')
@section('title', 'Point of Sale')

@push('styles')
<style>
    /* ── POS — refined product grid + cart over the admin theme ── */
    .pos-product {
        cursor: pointer; border: 1px solid var(--bs-border-color); overflow: hidden;
        transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
    }
    .pos-product:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.4); box-shadow: 0 14px 28px -20px rgba(0,0,0,.7); }
    .pos-product:active { transform: translateY(-1px) scale(.99); }
    .pos-product.is-oos { opacity: .5; pointer-events: none; filter: grayscale(.3); }
    .pos-product-media {
        position: relative;
        height: 110px; display: flex; align-items: center; justify-content: center; overflow: hidden;
        background: rgba(148,163,184,.1);
        border-radius: var(--bs-card-border-radius) var(--bs-card-border-radius) 0 0;
    }
    .pos-product-media img { width: 100%; height: 100%; object-fit: cover; }

    /* Stock chip overlaid on the image (TailAdmin product-tile style) — keeps the
       card body to just name + price so every tile is the same height. */
    .pos-stock-badge {
        position: absolute; top: .4rem; left: .4rem; z-index: 1;
        font-size: .62rem; font-weight: 600; line-height: 1.3;
        padding: .12rem .45rem; border-radius: 999px;
        backdrop-filter: blur(2px);
    }

    .pos-cart { position: sticky; top: 80px; }
    .pos-cart .card-header { background: rgba(16,185,129,.07); border-bottom-color: rgba(16,185,129,.18); }
    .pos-qty-btn { width: 26px; height: 26px; display: grid; place-items: center; padding: 0; line-height: 1; }
    .pos-cat-tabs::-webkit-scrollbar { height: 4px; }
    .pos-cat-tabs::-webkit-scrollbar-thumb { background: var(--bs-border-color); border-radius: 4px; }

    /* Barcode scanner bar — standard size, light emerald accent to match the POS theme */
    .pos-scan-bar .input-group-text {
        background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.35); color: #10b981;
    }
    .pos-scan-bar .form-control { border-color: rgba(16,185,129,.35); }
    .pos-scan-bar .form-control:focus {
        border-color: rgba(16,185,129,.6); box-shadow: 0 0 0 .2rem rgba(16,185,129,.15);
    }

@media (max-width: 991.98px) {
    /* Cart column becomes a bottom sheet on mobile */
    /* These three are direct children of the Bootstrap .row, which forces
       `width:100%` + a `margin-top` gutter on every child. That overrode the
       fixed left/right (bar overshot the right edge) and pushed the backdrop
       down (topbar left undimmed). Neutralise both here. */
    .pos-cart-col, .pos-cart-bar, .pos-cart-backdrop { width: auto !important; margin-top: 0 !important; }
    .pos-cart-col {
        position: fixed; left: 0; right: 0; bottom: 0; z-index: 1045;
        transform: translateY(100%); transition: transform .28s ease, visibility .28s;
        visibility: hidden;   /* fully hide (not just off-screen) so it can't be
                                 captured/focused while closed */
        max-height: 88vh; overflow-y: auto;
        padding: 0 .75rem calc(.75rem + env(safe-area-inset-bottom));
    }
    .pos-cart-col.sheet-open { transform: translateY(0); visibility: visible; }
    .pos-cart { position: static !important; }
    .pos-cart-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,.45);
        backdrop-filter: blur(2px); z-index: 1044; display: none;
    }
    .pos-cart-backdrop.show { display: block; }
    .col-12.col-lg-8 { padding-bottom: 6.5rem; }
    .pos-cart-bar {
        position: fixed; left: .75rem; right: .75rem;
        bottom: calc(84px + env(safe-area-inset-bottom) + .5rem); z-index: 1043; /* 58px nav + ~26px FAB hump */
        background: linear-gradient(180deg, #14c08a, #10b981); color: #fff;
        border-radius: 14px; padding: .7rem .9rem; gap: .75rem;
        display: flex; align-items: center; justify-content: space-between;
        box-shadow: 0 8px 24px -6px rgba(16,185,129,.6);
    }
    /* keep Pay pinned right & never let a long total push it off-screen */
    .pos-cart-bar > .d-flex { min-width: 0; }
    .pos-cart-bar > .d-flex .fw-bold { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pos-cart-bar .btn-light { flex-shrink: 0; }
}
@media (min-width: 992px) { .pos-cart-bar, .pos-cart-backdrop { display: none !important; } }
</style>
@endpush

@section('content')

<div x-data="posSystem()">

<x-page-header title="Point of Sale" subtitle="Ring up walk-in sales · cash, card, GCash, wallet">
    <x-slot name="actions">
        <button type="button" @click="toggleScan()"
                :class="scanning ? 'btn-primary' : 'btn-outline-secondary'"
                class="btn btn-sm">
            <i class="bi bi-upc-scan me-1"></i><span x-text="scanning ? 'Done' : 'Scan'"></span>
        </button>
        <a href="{{ route('admin.pos.history') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-receipt me-1"></i>Sales History
        </a>
    </x-slot>
</x-page-header>

<div class="row g-4">

    {{-- Product Grid --}}
    <div class="col-12 col-lg-8">

        <div x-show="scanning" x-cloak class="input-group pos-scan-bar mb-3">
            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
            <input x-ref="scanInput" x-model="scanCode" type="text"
                   placeholder="Scan a barcode / SKU — adds automatically"
                   @input="onScanInput()"
                   @keydown.enter.prevent="submitScan()"
                   class="form-control">
            <button type="button" @click="submitScan()" class="btn btn-success">
                <i class="bi bi-plus-lg me-1"></i>Add
            </button>
        </div>
        <p x-show="scanMessage" x-text="scanMessage" class="small text-danger mb-2"></p>

        {{-- Category Tabs --}}
        <div class="pos-cat-tabs d-flex gap-2 overflow-auto pb-2 mb-3 flex-nowrap no-scrollbar">
            <button @click="activeCategory = null"
                    :class="activeCategory === null ? 'btn-primary' : 'btn-outline-secondary'"
                    class="btn btn-sm flex-shrink-0 rounded-pill px-3">All</button>
            @foreach($categories as $category)
            <button @click="activeCategory = {{ $category->id }}"
                    :class="activeCategory === {{ $category->id }} ? 'btn-primary' : 'btn-outline-secondary'"
                    class="btn btn-sm flex-shrink-0 rounded-pill px-3">{{ $category->name }}</button>
            @endforeach
        </div>

        {{-- Products --}}
        <div class="row g-3">
            @foreach($categories as $category)
            @foreach($category->products as $product)
            <div class="col-6 col-sm-4 col-xl-3"
                 x-show="activeCategory === null || activeCategory === {{ $category->id }}">
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
                    <div class="pos-product-media">
                        @if($product->track_inventory)
                            @if($product->isOutOfStock())
                            <span class="pos-stock-badge bg-danger-subtle text-danger">
                                <i class="bi bi-x-circle me-1"></i>Out of stock
                            </span>
                            @elseif($product->isLowStock())
                            <span class="pos-stock-badge bg-warning-subtle text-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i>Low: {{ $product->stock_quantity }}
                            </span>
                            @else
                            <span class="pos-stock-badge bg-body-secondary text-secondary">{{ $product->stock_quantity }} in stock</span>
                            @endif
                        @endif
                        @if($product->image)
                        <img src="{{ file_url($product->image) }}" alt="{{ $product->name }}">
                        @else
                        <i class="bi bi-box text-secondary fs-3 opacity-50"></i>
                        @endif
                    </div>
                    <div class="card-body p-2 d-flex flex-column">
                        <p class="mb-1 small fw-semibold text-truncate">{{ $product->name }}</p>
                        <p class="mb-0 fw-bold text-success mt-auto">₱{{ number_format($product->selling_price, 2) }}</p>
                    </div>
                </div>
            </div>
            @endforeach
            @endforeach
        </div>
    </div>

    {{-- Cart --}}
    <div class="col-12 col-lg-4 pos-cart-col" :class="{ 'sheet-open': cartOpen }">
        <div class="card pos-cart">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-cart3 me-1"></i>Order</h6>
                <button type="button" class="btn btn-sm btn-link text-muted d-lg-none p-0 ms-auto me-2" @click="cartOpen = false" aria-label="Close cart">
                    <i class="bi bi-chevron-down"></i>
                </button>
                <span class="badge rounded-pill bg-success-subtle text-success"
                      x-show="cart.length > 0"
                      x-text="cart.reduce((n, i) => n + i.quantity, 0) + ' item(s)'"></span>
            </div>

            <div class="list-group list-group-flush" style="max-height:300px;overflow-y:auto">
                <template x-for="(item, index) in cart" :key="index">
                    <div class="list-group-item py-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="flex-grow-1 min-w-0 me-2">
                                <p class="mb-0 small fw-semibold text-truncate" x-text="item.name"></p>
                                <small class="text-muted" x-text="'₱' + item.unit_price.toFixed(2)"></small>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <button @click="decrementItem(index)"
                                        class="btn btn-outline-secondary btn-sm pos-qty-btn">−</button>
                                <span class="small fw-bold" style="min-width:24px;text-align:center" x-text="item.quantity"></span>
                                <button @click="incrementItem(index)"
                                        class="btn btn-outline-secondary btn-sm pos-qty-btn">+</button>
                                <button @click="removeItem(index)"
                                        class="btn btn-link btn-sm p-0 text-danger ms-1">
                                    <i class="bi bi-x-lg" style="font-size:.7rem"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="cart.length === 0" class="list-group-item text-center text-muted py-5">
                    <i class="bi bi-cart-x fs-3 d-block mb-2 opacity-50"></i>Cart is empty
                </div>
            </div>

            <div class="card-body border-top">
                {{-- Totals --}}
                <p x-show="stockMessage" x-cloak x-text="stockMessage" class="small text-danger mb-2"></p>
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>Subtotal</span><span x-text="'₱' + subtotal.toFixed(2)"></span>
                </div>
                <div class="d-flex justify-content-between small text-muted mb-1" x-show="tax > 0" x-cloak>
                    <span>Tax</span><span x-text="'₱' + tax.toFixed(2)"></span>
                </div>
                <div class="d-flex justify-content-between small text-success mb-2" x-show="discount > 0" x-cloak>
                    <span>Discount</span><span x-text="'-₱' + discount.toFixed(2)"></span>
                </div>
                <div class="d-flex justify-content-between fw-bold fs-5 mb-3 border-top pt-2">
                    <span>Total</span><span class="text-success" x-text="'₱' + total.toFixed(2)"></span>
                </div>

                {{-- Promo code --}}
                <div class="input-group mb-1">
                    <input x-model="promoCode" type="text" placeholder="Promo Code"
                           @keydown.enter.prevent="applyPromo()"
                           class="form-control">
                    <button type="button" @click="applyPromo()"
                            :disabled="promoChecking || !promoCode || cart.length === 0"
                            class="btn btn-outline-primary">
                        <span x-show="!promoChecking">Apply</span>
                        <span x-show="promoChecking"><span class="spinner-border spinner-border-sm"></span></span>
                    </button>
                </div>
                <p x-show="promoMessage" x-cloak class="small mb-3"
                   :class="promoValid ? 'text-success' : 'text-danger'" x-text="promoMessage"></p>

                {{-- Payment method --}}
                <p class="form-label small fw-semibold mb-1">Payment Method</p>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <template x-for="method in ['cash','gcash','maya','card','qr']" :key="method">
                        <button @click="paymentMethod = method"
                                :class="paymentMethod === method ? 'btn-primary' : 'btn-outline-secondary'"
                                class="btn btn-sm text-capitalize flex-fill" x-text="method"></button>
                    </template>
                </div>

                {{-- Amount tendered --}}
                <div class="input-group mb-2">
                    <span class="input-group-text">₱</span>
                    <input x-model.number="amountTendered" type="number" step="0.01"
                           :placeholder="'Amount (' + total.toFixed(2) + ')'"
                           class="form-control">
                </div>
                <div x-show="amountTendered > 0" x-cloak class="d-flex justify-content-between small text-success fw-semibold mb-3">
                    <span>Change</span>
                    <span x-text="'₱' + Math.max(0, amountTendered - total).toFixed(2)"></span>
                </div>

                <button @click="processOrder()"
                        :disabled="cart.length === 0 || processing"
                        class="btn btn-success btn-lg w-100">
                    <span x-show="!processing"><i class="bi bi-check-circle me-1"></i>Process Payment</span>
                    <span x-show="processing"><span class="spinner-border spinner-border-sm me-1"></span>Processing...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile bottom-sheet backdrop + persistent cart bar (hidden ≥992px via CSS) --}}
    <div class="pos-cart-backdrop" :class="{ show: cartOpen }" @click="cartOpen = false"></div>
    <div class="pos-cart-bar" role="button" tabindex="0" aria-label="Open cart"
             x-show="!cartOpen && cart.length > 0" x-cloak
             @click="cartOpen = true" @keydown.enter="cartOpen = true" @keydown.space.prevent="cartOpen = true">
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
</div>
</div>

@endsection

@php $posBranchId = $activeBranchId ?? null; @endphp
@push('scripts')
<script>
function posSystem() {
    return {
        activeCategory: null,
        cart: [],
        promoCode: '',
        discountAmount: 0,
        promoValid: false,
        promoMessage: '',
        promoChecking: false,
        paymentMethod: 'cash',
        amountTendered: 0,
        processing: false,
        stockMessage: '',
        scanning: false,
        scanCode: '',
        scanMessage: '',
        scanTimer: null,
        scanBusy: false,
        cartOpen: false,
        get cartCount() { return this.cart.reduce((n, i) => n + i.quantity, 0); },
        get subtotal() { return this.cart.reduce((s, i) => s + i.unit_price * i.quantity, 0); },
        // Tax is per-line: unit_price * quantity * tax_rate%, summed across the cart.
        // Mirrors PosService::createOrder so server + client totals match exactly.
        get tax() {
            return this.cart.reduce(
                (s, i) => s + (i.unit_price * i.quantity * ((i.tax_rate || 0) / 100)),
                0
            );
        },
        get discount() { return this.discountAmount; },
        get total() { return Math.max(0, this.subtotal + this.tax - this.discount); },
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
            // On mobile, adding a product just builds the cart (the bottom bar shows
            // the running total). The order/payment sheet only opens when the user
            // taps the bar's "Pay".
        },
        toggleScan() {
            this.scanning = !this.scanning;
            this.scanMessage = '';
            clearTimeout(this.scanTimer);
            if (this.scanning) { this.$nextTick(() => this.$refs.scanInput?.focus()); }
        },
        onScanInput() {
            // Hardware scanners type the whole code in a burst with <20ms between
            // keystrokes, then stop. A short 50ms settle window feels instant to a
            // person but still lets the burst finish so we look up the complete code
            // once — not each partial. Works even when the scanner sends no Enter;
            // Enter and the Add button still submit immediately.
            clearTimeout(this.scanTimer);
            if (!this.scanCode.trim()) return;
            this.scanTimer = setTimeout(() => this.submitScan(), 50);
        },
        async submitScan() {
            clearTimeout(this.scanTimer);
            const code = this.scanCode.trim();
            // scanBusy guards against the debounce timer and an Enter/Add click
            // racing into two lookups for the same scan.
            if (!code || this.scanBusy) return;
            this.scanBusy = true;
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
                this.scanBusy = false;
                this.$refs.scanInput?.focus();
            }
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
        decrementItem(index) {
            if (this.cart[index].quantity > 1) this.cart[index].quantity--;
            else this.removeItem(index);
            this.clearPromo();
        },
        removeItem(index) { this.cart.splice(index, 1); this.clearPromo(); },
        clearPromo() {
            // The discount is derived from the cart subtotal, so any change to
            // the cart invalidates a previously applied code — force a re-apply.
            if (!this.promoValid && this.discountAmount === 0) return;
            this.discountAmount = 0;
            this.promoValid = false;
            this.promoMessage = this.promoCode ? 'Cart changed — re-apply the promo code.' : '';
        },
        async applyPromo() {
            const code = this.promoCode.trim();
            if (!code) return;
            if (!this.cart.length) {
                this.promoValid = false;
                this.promoMessage = 'Add items before applying a promo.';
                return;
            }
            this.promoChecking = true;
            try {
                const res = await fetch(`${window.APP_BASE}/admin/promotions/validate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':     '{{ csrf_token() }}',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ code, amount: this.subtotal, applies_to: 'pos' }),
                });
                const data = await res.json();
                if (res.ok && data.valid) {
                    this.discountAmount = Number(data.discount) || 0;
                    this.promoValid = true;
                    this.promoMessage = data.message || 'Promo applied!';
                } else {
                    this.discountAmount = 0;
                    this.promoValid = false;
                    this.promoMessage = data.message || 'Invalid promo code.';
                }
            } catch (err) {
                this.discountAmount = 0;
                this.promoValid = false;
                this.promoMessage = 'Could not validate promo code. Please try again.';
            } finally {
                this.promoChecking = false;
            }
        },
        async processOrder() {
            if (!this.cart.length) return;
            this.processing = true;

            try {
                const res = await fetch(`${window.APP_BASE}/admin/pos/orders`, {
                    method: 'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':     '{{ csrf_token() }}',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        items: this.cart.map(i => ({
                            product_id: i.product_id,
                            name:       i.name,
                            quantity:   i.quantity,
                            unit_price: i.unit_price,
                            tax_rate:   i.tax_rate || 0,
                        })),
                        branch_id:       @json($posBranchId),
                        payments:        [{ method: this.paymentMethod, amount: this.total }],
                        amount_tendered: this.amountTendered || this.total,
                        promo_code:      this.promoCode,
                    }),
                });

                // Try to parse JSON; fall back to text for HTML error pages.
                let data = null, raw = '';
                const ct = res.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    data = await res.json();
                } else {
                    raw = await res.text();
                }

                if (!res.ok) {
                    // 422 = validation, 419 = csrf, 403 = auth, 500 = server
                    let msg = `Request failed (${res.status})`;
                    if (data?.errors) {
                        msg = Object.values(data.errors).flat().join('\n');
                    } else if (data?.message) {
                        msg = data.message;
                    } else if (raw) {
                        msg += `\n${raw.slice(0, 300)}`;
                    }
                    console.error('POS order failed:', res.status, data ?? raw);
                    alert(msg);
                    return;
                }

                if (data?.order) {
                    // change_amount is cast to decimal:2 in the model, so
                    // Laravel sends it as a STRING ("0.00"). Coerce to number
                    // before calling toFixed.
                    const change = Number(data.change ?? 0);
                    alert(`Order complete! Change: ₱${change.toFixed(2)}`);
                    window.open(data.receipt_url, '_blank');
                    this.cart = [];
                    this.amountTendered = 0;
                    this.promoCode = '';
                    this.discountAmount = 0;
                    this.promoValid = false;
                    this.promoMessage = '';
                    this.stockMessage = '';
                }
            } catch (err) {
                // Surface the *actual* error in both the console and the alert
                // so we don't have to dig — common causes: invalid JSON body,
                // network failure, CSRF rotation.
                const detail = (err && (err.message || err.toString())) || 'Unknown error';
                console.error('POS order error:', err, '\nname:', err?.name, '\nmessage:', err?.message, '\nstack:', err?.stack);
                alert('POS order error: ' + detail);
            } finally {
                // Always release the spinner so the UI never gets wedged.
                this.processing = false;
            }
        },
    };
}
</script>
@endpush
