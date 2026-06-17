@extends('layouts.app')
@section('title', 'Add Product')

@section('content')

<x-page-header title="Add Product" :back="route('admin.products.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
    @csrf

    {{-- Product details --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Product Details</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-medium">Product name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Category <span class="text-danger">*</span></label>
                    <select name="category_id" required class="form-select @error('category_id') is-invalid @enderror">
                        <option value="">Select category</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">SKU</label>
                    <input type="text" name="sku" value="{{ old('sku') }}" class="form-control font-monospace">
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Barcode</label>
                    <input type="text" name="barcode" value="{{ old('barcode') }}" class="form-control font-monospace">
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Cost price <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="cost_price" value="{{ old('cost_price') }}"
                               min="0" step="0.01" required class="form-control">
                    </div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Selling price <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="selling_price" value="{{ old('selling_price') }}"
                               min="0" step="0.01" required class="form-control">
                    </div>
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Initial stock quantity <span class="text-danger">*</span></label>
                    <input type="number" name="stock_quantity" value="{{ old('stock_quantity', 0) }}"
                           min="0" required class="form-control">
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Low stock alert threshold <span class="text-danger">*</span></label>
                    <input type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', 5) }}"
                           min="0" required class="form-control">
                </div>

                <div class="col-12">
                    <label class="form-label fw-medium">Description</label>
                    <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label fw-medium">Product image <span class="text-muted small fw-normal">— optional</span></label>
                    <input type="file" name="image" accept="image/*"
                           class="form-control @error('image') is-invalid @enderror">
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Footer actions --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex flex-wrap gap-4">
            <div class="form-check">
                <input type="hidden" name="track_inventory" value="0">
                <input type="checkbox" name="track_inventory" value="1" id="track_inventory"
                       class="form-check-input" @checked(old('track_inventory', true))>
                <label class="form-check-label fw-medium" for="track_inventory">Track inventory</label>
            </div>
            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active_prod"
                       class="form-check-input" checked>
                <label class="form-check-label fw-medium" for="is_active_prod">Active (show in POS)</label>
            </div>
        </div>
        <div class="d-flex gap-2 ms-auto">
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Create Product
            </button>
        </div>
    </div>

</form>

</div>
</div>

@endsection
