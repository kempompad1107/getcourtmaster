@extends('layouts.app')
@section('title', 'Product Categories')

@section('content')

<x-page-header title="Product Categories">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.products.index') }}"
               class="btn {{ request()->routeIs('admin.products.index') && !request('low_stock') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Products
            </a>
            <a href="{{ route('admin.products.index', ['low_stock' => 1]) }}"
               class="btn btn-outline-secondary">
                Low Stock
            </a>
            <a href="{{ route('admin.categories.index') }}"
               class="btn {{ request()->routeIs('admin.categories.*') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Categories
            </a>
        </div>
    </x-slot>
</x-page-header>

<div class="row justify-content-center">
<div class="col-12 col-md-8 col-lg-6">

    {{-- Create form --}}
    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
                New Category
            </p>
            <form method="POST" action="{{ route('admin.categories.store') }}" class="d-flex gap-2">
                @csrf
                <input type="text" name="name" value="{{ old('name') }}"
                       placeholder="Category name" required
                       class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <button type="submit" class="btn btn-primary flex-shrink-0">
                    <i class="bi bi-plus-lg"></i>Add
                </button>
            </form>
        </div>
    </div>

    {{-- Category list --}}
    <div class="card">
        <div class="card-body pb-2 d-flex align-items-center justify-content-between gap-3">
            <span style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
                Categories
            </span>
            <span class="badge rounded-pill bg-secondary-subtle text-secondary">{{ $categories->count() }}</span>
        </div>

        @if($categories->isEmpty())
            <x-empty-state title="No categories yet" icon="bi-tag"
                description="Add a category above to organise your products."/>
        @else
        <div class="list-group list-group-flush">
            @foreach($categories as $category)
            <div x-data="{ editing: false }" class="list-group-item py-3">

                {{-- View row --}}
                <div x-show="!editing" class="d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <span class="flex-shrink-0 d-grid"
                              style="width:36px;height:36px;place-items:center;border-radius:10px;background:rgba(16,185,129,.1);color:#10b981">
                            <i class="bi bi-tag-fill"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="mb-0 small fw-semibold text-truncate">{{ $category->name }}</p>
                            <small class="text-muted">{{ $category->products_count }} product{{ $category->products_count === 1 ? '' : 's' }}</small>
                        </div>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button @click="editing = true" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil"></i>
                        </button>
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

                {{-- Edit row --}}
                <div x-show="editing" x-cloak>
                    <form method="POST" action="{{ route('admin.categories.update', $category) }}"
                          class="d-flex gap-2">
                        @csrf @method('PUT')
                        <input type="text" name="name" value="{{ $category->name }}"
                               required class="form-control">
                        <button type="submit" class="btn btn-success flex-shrink-0">Save</button>
                        <button type="button" @click="editing = false"
                                class="btn btn-outline-secondary flex-shrink-0">Cancel</button>
                    </form>
                </div>

            </div>
            @endforeach
        </div>
        @endif
    </div>

</div>
</div>

@endsection
