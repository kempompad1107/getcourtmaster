@extends('layouts.app')
@section('title', 'New Purchase Order')

@section('content')

<x-page-header title="New Purchase Order">
    <x-slot name="actions">
        <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </x-slot>
</x-page-header>

<form method="POST" action="{{ route('admin.purchase-orders.store') }}"
      x-data="poForm({{ $products->toJson() }})">
    @csrf

    <div class="row g-4">

        {{-- Header --}}
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="bi bi-clipboard-check text-primary"></i>
                    <h6 class="mb-0 fw-semibold">Order Details</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small">Supplier <span class="text-danger">*</span></label>
                        <select name="supplier_id" required
                                class="form-select form-select-sm @error('supplier_id') is-invalid @enderror">
                            <option value="">— Select supplier —</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                            @endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Expected Date</label>
                        <input type="date" name="expected_at" value="{{ old('expected_at') }}"
                               class="form-control form-control-sm @error('expected_at') is-invalid @enderror">
                        @error('expected_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label small">Notes</label>
                        <textarea name="notes" rows="3"
                                  class="form-control form-control-sm @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Line items --}}
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-semibold"><i class="bi bi-list-ul me-1 text-muted"></i>Line Items</h6>
                    <button type="button" @click="addRow()" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add Item
                    </button>
                </div>
                @error('items')<div class="alert alert-danger m-3 mb-0 py-2 small">{{ $message }}</div>@enderror
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th style="width:120px">Qty</th>
                                <th style="width:140px">Unit Cost</th>
                                <th class="text-end" style="width:120px">Line Total</th>
                                <th style="width:40px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in rows" :key="idx">
                                <tr>
                                    <td>
                                        <select :name="`items[${idx}][product_id]`" required
                                                x-model="row.product_id"
                                                @change="onProductChange(idx)"
                                                class="form-select form-select-sm">
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
                                               class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <input type="number" min="0" step="0.01" required
                                               :name="`items[${idx}][unit_cost]`"
                                               x-model.number="row.unit_cost"
                                               class="form-control form-control-sm">
                                    </td>
                                    <td class="text-end small fw-medium">
                                        ₱<span x-text="lineTotal(row).toFixed(2)"></span>
                                    </td>
                                    <td>
                                        <button type="button" @click="removeRow(idx)"
                                                class="btn btn-link btn-sm p-0 text-danger"
                                                :disabled="rows.length === 1">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end small fw-semibold">Subtotal</td>
                                <td class="text-end small fw-semibold">₱<span x-text="subtotal().toFixed(2)"></span></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Create PO
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
