@extends('layouts.app')
@section('title', 'Suppliers')

@section('content')

<x-page-header title="Suppliers">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.suppliers.index') }}"
               class="btn btn-sm btn-primary">Suppliers</a>
            <a href="{{ route('admin.purchase-orders.index') }}"
               class="btn btn-sm btn-outline-secondary">Purchase Orders</a>
        </div>
    </x-slot>
</x-page-header>

<div class="row g-4">

    {{-- Create form --}}
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-truck text-primary"></i>
                <h6 class="mb-0 fw-semibold">New Supplier</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.suppliers.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="160"
                               class="form-control form-control-sm @error('name') is-invalid @enderror">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Contact Name</label>
                        <input type="text" name="contact_name" value="{{ old('contact_name') }}" maxlength="160"
                               class="form-control form-control-sm @error('contact_name') is-invalid @enderror">
                        @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               class="form-control form-control-sm @error('email') is-invalid @enderror">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Phone</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" maxlength="30"
                               class="form-control form-control-sm @error('phone') is-invalid @enderror">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Address</label>
                        <input type="text" name="address" value="{{ old('address') }}" maxlength="255"
                               class="form-control form-control-sm @error('address') is-invalid @enderror">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Notes</label>
                        <textarea name="notes" rows="2" maxlength="500"
                                  class="form-control form-control-sm @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus-lg me-1"></i>Add Supplier
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Supplier list --}}
    <div class="col-12 col-lg-8">

        {{-- Filters: search only --}}
        <div class="mb-3">
            <form method="GET">
                <div class="search-field">
                    <i class="bi bi-search"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           aria-label="Search suppliers" placeholder="Search suppliers...">
                </div>
                @if(request('search'))
                <div class="mt-1">
                    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-link btn-sm text-muted px-0">Clear</a>
                </div>
                @endif
            </form>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-truck me-1 text-muted"></i>Suppliers</h6>
                <span class="badge rounded-pill bg-secondary-subtle text-secondary">{{ $suppliers->total() }}</span>
            </div>
            <div class="list-group list-group-flush">
                @forelse($suppliers as $supplier)
                <div x-data="{ editing: false }" class="list-group-item py-3">

                    {{-- View mode --}}
                    <div x-show="!editing">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="d-flex align-items-start gap-2 min-w-0">
                                <span class="d-grid flex-shrink-0" style="width:38px;height:38px;place-items:center;border-radius:11px;background:rgba(16,185,129,.1);color:#10b981">
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
                                    <small class="text-muted d-block fst-italic">{{ $supplier->notes }}</small>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex gap-1 flex-shrink-0">
                                <button @click="editing = true" class="btn btn-outline-secondary btn-sm" title="Edit"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}"
                                      onsubmit="return confirm('Delete this supplier?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>

                    {{-- Edit mode --}}
                    <div x-show="editing" x-cloak>
                        <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
                            @csrf @method('PUT')
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <label class="form-label small">Name</label>
                                    <input type="text" name="name" value="{{ $supplier->name }}" required maxlength="160"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small">Contact</label>
                                    <input type="text" name="contact_name" value="{{ $supplier->contact_name }}" maxlength="160"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small">Email</label>
                                    <input type="email" name="email" value="{{ $supplier->email }}"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label small">Phone</label>
                                    <input type="text" name="phone" value="{{ $supplier->phone }}" maxlength="30"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Address</label>
                                    <input type="text" name="address" value="{{ $supplier->address }}" maxlength="255"
                                           class="form-control form-control-sm">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small">Notes</label>
                                    <textarea name="notes" rows="2" maxlength="500"
                                              class="form-control form-control-sm">{{ $supplier->notes }}</textarea>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" id="active-{{ $supplier->id }}"
                                               class="form-check-input" @checked($supplier->is_active)>
                                        <label class="form-check-label small" for="active-{{ $supplier->id }}">Active</label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" class="btn btn-success btn-sm">Save</button>
                                <button type="button" @click="editing = false" class="btn btn-outline-secondary btn-sm">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
                @empty
                <div class="list-group-item">
                    <x-empty-state title="No suppliers yet" icon="bi-truck"/>
                </div>
                @endforelse
            </div>
            @if($suppliers->hasPages())
            <div class="card-footer">
                {{ $suppliers->withQueryString()->links() }}
            </div>
            @endif
        </div>

    </div>
</div>

@endsection
