@extends('layouts.app')
@section('title', 'Suppliers')

@section('content')

<x-page-header title="Suppliers">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.suppliers.index') }}"
               class="btn {{ request()->routeIs('admin.suppliers.*') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Suppliers
            </a>
            <a href="{{ route('admin.purchase-orders.index') }}"
               class="btn {{ request()->routeIs('admin.purchase-orders.*') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Purchase Orders
            </a>
        </div>
    </x-slot>
</x-page-header>

<div class="row g-4">

    {{-- Create form --}}
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
                    New Supplier
                </p>
                <form method="POST" action="{{ route('admin.suppliers.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="160"
                                   class="form-control @error('name') is-invalid @enderror">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contact Name</label>
                            <input type="text" name="contact_name" value="{{ old('contact_name') }}" maxlength="160"
                                   class="form-control @error('contact_name') is-invalid @enderror">
                            @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" maxlength="30"
                                   class="form-control @error('phone') is-invalid @enderror">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" value="{{ old('address') }}" maxlength="255"
                                   class="form-control @error('address') is-invalid @enderror">
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" rows="2" maxlength="500"
                                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg"></i>Add Supplier
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Supplier list --}}
    <div class="col-12 col-lg-8">

        <form method="GET" class="mb-3">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control" placeholder="Search suppliers…">
                @if(request('search'))
                <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
                @endif
            </div>
        </form>

        <div class="card">
            <div class="card-body pb-2 d-flex align-items-center justify-content-between gap-3">
                <span style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
                    Suppliers
                </span>
                <span class="badge rounded-pill bg-secondary-subtle text-secondary">{{ $suppliers->total() }}</span>
            </div>

            @if($suppliers->isEmpty())
                <x-empty-state title="No suppliers found" icon="bi-truck"
                    description="{{ request('search') ? 'Try a different search term.' : 'Add your first supplier using the form.' }}"/>
            @else
            <div class="list-group list-group-flush">
                @foreach($suppliers as $supplier)
                <div x-data="{ editing: false }" class="list-group-item py-3">

                    {{-- View mode --}}
                    <div x-show="!editing">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="d-flex align-items-start gap-3 min-w-0">
                                <span class="flex-shrink-0 d-grid"
                                      style="width:40px;height:40px;place-items:center;border-radius:11px;background:rgba(16,185,129,.1);color:#10b981">
                                    <i class="bi bi-building"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="mb-0 small fw-semibold">
                                        {{ $supplier->name }}
                                        @if(!$supplier->is_active)
                                        <span class="badge rounded-pill bg-secondary-subtle text-secondary ms-1">Inactive</span>
                                        @endif
                                    </p>
                                    @if($supplier->contact_name)
                                    <small class="text-muted d-block">{{ $supplier->contact_name }}</small>
                                    @endif
                                    <small class="text-muted d-block">
                                        @if($supplier->email)<i class="bi bi-envelope me-1"></i>{{ $supplier->email }}@endif
                                        @if($supplier->email && $supplier->phone) &middot; @endif
                                        @if($supplier->phone)<i class="bi bi-telephone me-1"></i>{{ $supplier->phone }}@endif
                                    </small>
                                    @if($supplier->address)
                                    <small class="text-muted d-block"><i class="bi bi-geo-alt me-1"></i>{{ $supplier->address }}</small>
                                    @endif
                                    @if($supplier->notes)
                                    <small class="text-muted d-block fst-italic">"{{ $supplier->notes }}"</small>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <button @click="editing = true" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}"
                                      onsubmit="return confirm('Delete this supplier?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Edit mode --}}
                    <div x-show="editing" x-cloak>
                        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
                            @csrf @method('PUT')
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" value="{{ $supplier->name }}" required
                                           maxlength="160" class="form-control">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label">Contact</label>
                                    <input type="text" name="contact_name" value="{{ $supplier->contact_name }}"
                                           maxlength="160" class="form-control">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" value="{{ $supplier->email }}" class="form-control">
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" value="{{ $supplier->phone }}"
                                           maxlength="30" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" value="{{ $supplier->address }}"
                                           maxlength="255" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" rows="2" maxlength="500"
                                              class="form-control">{{ $supplier->notes }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1"
                                               id="active-{{ $supplier->id }}"
                                               class="form-check-input" @checked($supplier->is_active)>
                                        <label class="form-check-label" for="active-{{ $supplier->id }}">Active</label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-success">Save</button>
                                <button type="button" @click="editing = false"
                                        class="btn btn-outline-secondary">Cancel</button>
                            </div>
                        </form>
                    </div>

                </div>
                @endforeach
            </div>
            @if($suppliers->hasPages())
            <div class="card-footer">{{ $suppliers->withQueryString()->links() }}</div>
            @endif
            @endif
        </div>

    </div>
</div>

@endsection
