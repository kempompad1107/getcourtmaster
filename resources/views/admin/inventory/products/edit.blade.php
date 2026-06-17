@extends('layouts.app')
@section('title', 'Edit ' . $product->name)

@section('content')

<x-page-header :title="'Edit: ' . $product->name" :back="route('admin.products.index')"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">

        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Product name</label>
                            <input type="text" name="name" value="{{ old('name', $product->name) }}" required
                                   class="form-control">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" required class="form-select">
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">SKU</label>
                            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}"
                                   class="form-control font-monospace">
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Cost price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}"
                                       min="0" step="0.01" required class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Selling price</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}"
                                       min="0" step="0.01" required class="form-control">
                            </div>
                        </div>

                        <div class="col-12 col-sm-6">
                            <label class="form-label">Low stock threshold</label>
                            <input type="number" name="low_stock_threshold" value="{{ old('low_stock_threshold', $product->low_stock_threshold) }}"
                                   min="0" required class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="2" class="form-control">{{ old('description', $product->description) }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Product image</label>
                            @if($product->image)
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <img src="{{ file_url($product->image) }}" alt="{{ $product->name }}"
                                     class="rounded border object-fit-cover" style="width:64px;height:64px">
                                <div class="form-check">
                                    <input type="checkbox" name="remove_image" value="1" id="remove_image"
                                           class="form-check-input">
                                    <label class="form-check-label small" for="remove_image">Remove current image</label>
                                </div>
                            </div>
                            @endif
                            <input type="file" name="image" accept="image/*"
                                   class="form-control @error('image') is-invalid @enderror">
                            <div class="form-text">Upload a new file to replace the current image.</div>
                            @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input type="hidden" name="track_inventory" value="0">
                                    <input type="checkbox" name="track_inventory" value="1" id="track_inv"
                                           class="form-check-input" @checked(old('track_inventory', $product->track_inventory))>
                                    <label class="form-check-label" for="track_inv">Track inventory</label>
                                </div>
                                <div class="form-check">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" id="is_active_edit"
                                           class="form-check-input" @checked(old('is_active', $product->is_active))>
                                    <label class="form-check-label" for="is_active_edit">Active</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Stock adjustment --}}
        @if($product->track_inventory)
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Adjust Stock</h6>
                <span class="badge text-bg-secondary">Current: {{ $product->stock_quantity }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.products.adjust', $product) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-sm-auto">
                        <select name="type" class="form-select form-select-sm">
                            <option value="restock">Restock (+)</option>
                            <option value="adjustment">Adjustment</option>
                            <option value="damage">Damage (−)</option>
                            <option value="return">Return (+)</option>
                        </select>
                    </div>
                    <div class="col-6 col-sm-auto" style="width:100px">
                        <input type="number" name="quantity" min="1" value="1" required
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-sm flex-grow-1">
                        <input type="text" name="note" placeholder="Reason / note"
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Apply</button>
                    </div>
                </form>
                <div class="mt-3">
                    <a href="{{ route('admin.products.movements', $product) }}"
                       class="btn btn-link btn-sm p-0 small">
                        View movement history <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection
