@extends('layouts.app')
@section('title', 'Inventory')

@push('styles')
<style>
    .pr-thumb {
        width: 42px; height: 42px; border-radius: 10px; flex-shrink: 0; object-fit: cover;
        display: grid; place-items: center; font-size: 1.1rem;
        background: rgba(148,163,184,.12); color: var(--bs-secondary-color);
        border: 1px solid var(--bs-border-color);
    }
    /* TailAdmin table header */
    .pr-table thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .pr-table tbody td { padding-top: .8rem; padding-bottom: .8rem; }
    .pr-table tbody tr { transition: background-color .15s; }
</style>
@endpush

@section('content')

@php
    $isLowStock = (bool) request('low_stock');
    $activeFilters = (int) request()->filled('category');
@endphp

<form method="GET" action="{{ route('admin.products.index') }}" x-data="{ open: false }">
    @if($isLowStock)<input type="hidden" name="low_stock" value="1">@endif
<x-page-header title="Inventory">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.products.index') }}"
               class="btn {{ !$isLowStock && request()->routeIs('admin.products.index') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Products
            </a>
            <a href="{{ route('admin.products.index', ['low_stock' => 1]) }}"
               class="btn {{ $isLowStock ? 'btn-warning' : 'btn-outline-secondary' }}">
                Low Stock
            </a>
            <a href="{{ route('admin.categories.index') }}"
               class="btn {{ request()->routeIs('admin.categories.*') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Categories
            </a>
        </div>

        {{-- Filter icon --}}
        <div class="position-relative" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="btn {{ $activeFilters ? 'btn-primary' : 'btn-outline-secondary' }} position-relative">
                <i class="bi bi-sliders2"></i>
                @if($activeFilters)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                      style="font-size:.55rem">{{ $activeFilters }}</span>
                @endif
            </button>
            <div x-show="open" x-cloak
                 class="position-absolute end-0 mt-1 p-3 rounded-3 shadow-lg border bg-body z-3"
                 style="min-width:240px">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All categories</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                        @if($activeFilters)
                        <a href="{{ route('admin.products.index', $isLowStock ? ['low_stock'=>1] : []) }}"
                           class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>Add Product
        </a>
    </x-slot>
</x-page-header>
</form>

<div class="card">
    @if($products->isEmpty())
        <x-empty-state
            title="{{ $isLowStock ? 'No low-stock products' : 'No products found' }}"
            icon="{{ $isLowStock ? 'bi-check-circle' : 'bi-box-seam' }}"
            description="{{ $isLowStock ? 'All products are sufficiently stocked.' : 'Add your first product to get started.' }}"/>
    @else
    <div class="table-responsive">
        <table class="table pr-table table-stack table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th class="d-none d-sm-table-cell">Category</th>
                    <th class="d-none d-lg-table-cell">SKU</th>
                    <th class="text-end">Price</th>
                    <th>Stock</th>
                    <th class="d-none d-sm-table-cell">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td class="cell-plain">
                        <div class="d-flex align-items-center gap-3">
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
                    <td data-label="Category" class="small d-none d-sm-table-cell">
                        {{ $product->category->name }}
                    </td>
                    <td data-label="SKU" class="small font-monospace text-muted d-none d-lg-table-cell">
                        {{ $product->sku ?? '—' }}
                    </td>
                    <td data-label="Price" class="text-end">
                        <p class="mb-0 small fw-semibold">₱{{ number_format($product->selling_price, 2) }}</p>
                        <small class="text-muted">Cost: ₱{{ number_format($product->cost_price, 2) }}</small>
                    </td>
                    <td data-label="Stock">
                        @if(!$product->track_inventory)
                            <span class="small text-muted">Not tracked</span>
                        @elseif($product->isOutOfStock())
                            <span class="badge rounded-pill bg-danger-subtle text-danger">Out of stock</span>
                        @elseif($product->isLowStock())
                            <span class="badge rounded-pill bg-warning-subtle text-warning">Low: {{ $product->stock_quantity }}</span>
                        @else
                            <span class="badge rounded-pill bg-success-subtle text-success">{{ $product->stock_quantity }} in stock</span>
                        @endif
                    </td>
                    <td data-label="Status" class="d-none d-sm-table-cell">
                        <span class="badge rounded-pill {{ $product->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="cell-actions text-end">
                        <a href="{{ route('admin.products.edit', $product) }}"
                           class="btn btn-primary btn-sm">Edit</a>
                        @if($product->track_inventory)
                        <a href="{{ route('admin.products.movements', $product) }}"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-clock-history"></i>
                        </a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="card-footer">{{ $products->withQueryString()->links() }}</div>
    @endif
    @endif
</div>

@endsection
