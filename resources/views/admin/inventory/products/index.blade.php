@extends('layouts.app')
@section('title', 'Inventory')

@push('styles')
<style>
    /* ── Inventory list — polish + mobile card stacking ── */
    .pr-thumb {
        width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0; object-fit: cover;
        display: grid; place-items: center; font-size: 1.1rem;
        background: rgba(148,163,184,.12); color: var(--bs-secondary-color);
        border: 1px solid var(--bs-border-color);
    }
    .pr-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .pr-table thead { display: none; }
        .pr-table, .pr-table tbody, .pr-table tr, .pr-table td { display: block; width: 100%; }
        .pr-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .pr-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .pr-table td + td { border-top: 1px solid var(--bs-border-color); }
        .pr-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .pr-table td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Inventory">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.products.index') }}"
               class="btn btn-sm {{ request()->routeIs('admin.products.index') && !request('low_stock') ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Products
            </a>
            <a href="{{ route('admin.products.index', ['low_stock' => 1]) }}"
               class="btn btn-sm {{ request('low_stock') ? 'btn-warning' : 'btn-outline-secondary' }}">
                Low Stock
            </a>
            <a href="{{ route('admin.categories.index') }}"
               class="btn btn-sm {{ request()->routeIs('admin.categories.*') ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Categories
            </a>
        </div>
        <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Product
        </a>
    </x-slot>
</x-page-header>

{{-- Unified filter bar --}}
<x-filter-bar placeholder="Search products..."
              :active-count="(int) request()->filled('category')"
              :clear="route('admin.products.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Category</label>
            <select name="category" class="form-select form-select-sm">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
    </x-slot>
</x-filter-bar>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table pr-table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>SKU</th>
                    <th class="text-end">Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <tr>
                    <td data-label="Product">
                        <div class="d-flex align-items-center gap-2">
                            @if($product->image)
                            <img src="{{ file_url($product->image) }}" alt="{{ $product->name }}" class="pr-thumb">
                            @else
                            <span class="pr-thumb"><i class="bi bi-box"></i></span>
                            @endif
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold text-truncate">{{ $product->name }}</p>
                                @if($product->description)
                                <small class="text-muted text-truncate d-block" style="max-width:220px">{{ $product->description }}</small>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td data-label="Category" class="small">{{ $product->category->name }}</td>
                    <td data-label="SKU" class="small font-monospace text-muted">{{ $product->sku ?? '—' }}</td>
                    <td data-label="Price" class="text-end">
                        <div>
                            <p class="mb-0 small fw-semibold">₱{{ number_format($product->selling_price, 2) }}</p>
                            <small class="text-muted">Cost: ₱{{ number_format($product->cost_price, 2) }}</small>
                        </div>
                    </td>
                    <td data-label="Stock">
                        @if(!$product->track_inventory)
                        <span class="small text-muted">Not tracked</span>
                        @elseif($product->isOutOfStock())
                        <span class="badge rounded-pill bg-danger-subtle text-danger"><i class="bi bi-circle-fill me-1" style="font-size:.4rem"></i>Out of stock</span>
                        @elseif($product->isLowStock())
                        <span class="badge rounded-pill bg-warning-subtle text-warning">Low: {{ $product->stock_quantity }}</span>
                        @else
                        <span class="badge rounded-pill bg-success-subtle text-success">{{ $product->stock_quantity }} in stock</span>
                        @endif
                    </td>
                    <td data-label="Status">
                        <span class="badge rounded-pill {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="{{ route('admin.products.edit', $product) }}"
                               class="btn btn-outline-primary btn-sm">Edit</a>
                            @if($product->track_inventory)
                            <a href="{{ route('admin.products.movements', $product) }}"
                               class="btn btn-outline-secondary btn-sm">History</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="bk-cell-empty">
                        <x-empty-state title="No products found" icon="bi-box-seam"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $products->withQueryString()->links() }}
    </div>
</div>

@endsection
