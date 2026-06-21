@extends('layouts.app')
@section('title', 'New Purchase Order')

@push('styles')
<style>
    /* TailAdmin line-items table header */
    .po-items-table thead th {
        text-transform: uppercase; font-size: .68rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .75rem; padding-bottom: .75rem;
    }
</style>
@endpush

@section('content')

<x-page-header title="New Purchase Order"
    :back="route('admin.purchase-orders.index')" backLabel="Purchase Orders"/>

<form method="POST" action="{{ route('admin.purchase-orders.store') }}"
      x-data="poForm({{ $products->toJson() }})">
    @csrf

    <div class="row g-4">

        {{-- Order details sidebar --}}
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body">
                    <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
                        Order Details
                    </p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" required
                                    class="form-select @error('supplier_id') is-invalid @enderror">
                                <option value="">— Select supplier —</option>
                                @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Expected Date</label>
                            <input type="date" name="expected_at" value="{{ old('expected_at') }}"
                                   class="form-control @error('expected_at') is-invalid @enderror">
                            @error('expected_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Line items --}}
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-body pb-2 d-flex align-items-center justify-content-between gap-3">
                    <span style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
                        Line Items
                    </span>
                    <button type="button" @click="addRow()" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-plus-lg"></i>Add Item
                    </button>
                </div>

                @error('items')
                <div class="alert alert-danger mx-3 mb-0 py-2 small border-0">{{ $message }}</div>
                @enderror

                {{-- Desktop table (md+) --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table po-items-table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th style="width:120px">Qty</th>
                                <th style="width:150px">Unit Cost (₱)</th>
                                <th class="text-end" style="width:120px">Total</th>
                                <th style="width:44px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in rows" :key="idx">
                                <tr>
                                    <td>
                                        <select :name="`items[${idx}][product_id]`" required
                                                x-model="row.product_id"
                                                @change="onProductChange(idx)"
                                                class="form-select">
                                            <option value="">— Select product —</option>
                                            <template x-for="p in products" :key="p.id">
                                                <option :value="p.id" x-text="p.name"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" min="1" step="1" required
                                               :name="`items[${idx}][quantity]`"
                                               x-model.number="row.quantity"
                                               class="form-control">
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.01" required
                                               :name="`items[${idx}][unit_cost]`"
                                               x-model.number="row.unit_cost"
                                               class="form-control">
                                    </td>
                                    <td class="text-end fw-semibold text-nowrap">
                                        ₱<span x-text="lineTotal(row).toFixed(2)"></span>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" @click="removeRow(idx)"
                                                class="btn btn-link p-0 text-danger"
                                                :disabled="rows.length === 1">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="table-light">
                                <td colspan="3" class="text-end small fw-semibold">Subtotal</td>
                                <td class="text-end fw-bold text-nowrap">
                                    ₱<span x-text="subtotal().toFixed(2)"></span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Mobile cards (below md) --}}
                <div class="d-md-none px-3 pb-3">
                    <template x-for="(row, idx) in rows" :key="idx">
                        <div class="border rounded-3 p-3 mb-3 position-relative">
                            <button type="button" @click="removeRow(idx)"
                                    class="btn btn-link p-0 text-danger position-absolute top-0 end-0 mt-2 me-2"
                                    :disabled="rows.length === 1">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <div class="mb-3">
                                <label class="form-label">Product</label>
                                <select :name="`items[${idx}][product_id]`" required
                                        x-model="row.product_id"
                                        @change="onProductChange(idx)"
                                        class="form-select">
                                    <option value="">— Select product —</option>
                                    <template x-for="p in products" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="row g-3 align-items-end">
                                <div class="col-5">
                                    <label class="form-label">Qty</label>
                                    <input type="number" min="1" step="1" required
                                           :name="`items[${idx}][quantity]`"
                                           x-model.number="row.quantity"
                                           class="form-control">
                                </div>
                                <div class="col-7">
                                    <label class="form-label">Unit Cost (₱)</label>
                                    <input type="number" min="0" step="0.01" required
                                           :name="`items[${idx}][unit_cost]`"
                                           x-model.number="row.unit_cost"
                                           class="form-control">
                                </div>
                                <div class="col-12 text-end">
                                    <span class="text-muted small">Total: </span>
                                    <span class="fw-bold">₱<span x-text="lineTotal(row).toFixed(2)"></span></span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div class="d-flex justify-content-between align-items-center border-top pt-3 mt-1">
                        <span class="small fw-semibold text-muted">Subtotal</span>
                        <span class="fw-bold">₱<span x-text="subtotal().toFixed(2)"></span></span>
                    </div>
                </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 border-top pt-4">
                <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i>Create PO
                </button>
            </div>
        </div>

    </div>
</form>

<script>
function poForm(products) {
    return {
        products: products,
        rows: [{ product_id: '', quantity: 1, unit_cost: 0 }],
        addRow() {
            this.rows.push({ product_id: '', quantity: 1, unit_cost: 0 });
        },
        removeRow(idx) {
            if (this.rows.length > 1) this.rows.splice(idx, 1);
        },
        onProductChange(idx) {
            const row = this.rows[idx];
            const product = this.products.find(p => p.id == row.product_id);
            if (product && (!row.unit_cost || row.unit_cost == 0)) {
                row.unit_cost = parseFloat(product.cost_price ?? 0);
            }
        },
        lineTotal(row) {
            return (parseFloat(row.quantity) || 0) * (parseFloat(row.unit_cost) || 0);
        },
        subtotal() {
            return this.rows.reduce((sum, r) => sum + this.lineTotal(r), 0);
        },
    };
}
</script>

@endsection
