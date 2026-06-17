@extends('layouts.app')
@section('title', 'Product Categories')

@section('content')

<x-page-header title="Product Categories">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.products.index') }}"
               class="btn btn-sm btn-outline-secondary">Products</a>
            <a href="{{ route('admin.categories.index') }}"
               class="btn btn-sm btn-primary">Categories</a>
        </div>
    </x-slot>
</x-page-header>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">

        {{-- Create form --}}
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0 fw-semibold">New Category</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.categories.store') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Category name" required
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <button type="submit" class="btn btn-primary flex-shrink-0">
                        <i class="bi bi-plus-lg me-1"></i>Add
                    </button>
                </form>
            </div>
        </div>

        {{-- Category list --}}
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-tags me-1 text-muted"></i>Categories</h6>
                <span class="badge rounded-pill bg-secondary-subtle text-secondary">{{ $categories->count() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse($categories as $category)
                <div x-data="{ editing: false }" class="list-group-item py-3">
                    <div x-show="!editing" class="d-flex align-items-center justify-content-between gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="d-grid flex-shrink-0" style="width:36px;height:36px;place-items:center;border-radius:10px;background:rgba(16,185,129,.1);color:#10b981">
                                <i class="bi bi-tag-fill"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold text-truncate">{{ $category->name }}</p>
                                <small class="text-muted">{{ $category->products_count }} product(s)</small>
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <button @click="editing = true" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                  onsubmit="return confirm('Delete this category? Products will be uncategorized.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        {{ $category->products_count > 0 ? 'disabled' : '' }}
                                        title="{{ $category->products_count > 0 ? 'Has products — cannot delete' : 'Delete category' }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div x-show="editing" x-cloak>
                        <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="d-flex gap-2">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $category->name }}" required
                                   class="form-control form-control-sm">
                            <button type="submit" class="btn btn-success btn-sm flex-shrink-0">Save</button>
                            <button type="button" @click="editing = false" class="btn btn-outline-secondary btn-sm flex-shrink-0">Cancel</button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="list-group-item">
                    <x-empty-state title="No categories yet" icon="bi-tag"/>
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@endsection
