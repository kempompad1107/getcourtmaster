@extends('layouts.app')
@section('title', 'Inventory')

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

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-sm-auto flex-grow-1">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search products..." class="form-control form-control-sm">
            </div>
            <div class="col-6 col-sm-auto">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>SKU</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                <tr>
                    <td>
                        <p class="mb-0 small fw-medium">{{ $product->name }}</p>
                        @if($product->description)
                        <small class="text-muted text-truncate d-block" style="max-width:200px">{{ $product->description }}</small>
                        @endif
                    </td>
                    <td class="small">{{ $product->category->name }}</td>
                    <td class="small font-monospace text-muted">{{ $product->sku ?? '—' }}</td>
                    <td>
                        <p class="mb-0 small fw-medium">₱{{ number_format($product->selling_price, 2) }}</p>
                        <small class="text-muted">Cost: ₱{{ number_format($product->cost_price, 2) }}</small>
                    </td>
                    <td>
                        @if(!$product->track_inventory)
                        <span class="small text-muted">Not tracked</span>
                        @elseif($product->isOutOfStock())
                        <span class="badge text-bg-danger"><i class="bi bi-circle-fill me-1" style="font-size:.4rem"></i>Out of stock</span>
                        @elseif($product->isLowStock())
                        <span class="badge text-bg-warning text-dark">Low: {{ $product->stock_quantity }}</span>
                        @else
                        <span class="small">{{ $product->stock_quantity }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.products.edit', $product) }}"
                           class="btn btn-link btn-sm p-0 text-primary me-2">Edit</a>
                        @if($product->track_inventory)
                        <a href="{{ route('admin.products.movements', $product) }}"
                           class="btn btn-link btn-sm p-0 text-muted">History</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state title="No products found" icon="bi-box-seam"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
    <div class="card-footer">
        {{ $products->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
