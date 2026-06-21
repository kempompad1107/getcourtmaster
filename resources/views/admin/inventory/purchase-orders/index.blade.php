@extends('layouts.app')
@section('title', 'Purchase Orders')

@push('styles')
<style>
    /* TailAdmin table header */
    .po-table thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .po-table tbody td { padding-top: .8rem; padding-bottom: .8rem; }
    .po-table tbody tr { transition: background-color .15s; }
</style>
@endpush

@section('content')

@php $activeFilters = (int) request()->filled('status'); @endphp

<form method="GET" action="{{ route('admin.purchase-orders.index') }}" x-data="{ open: false }">
<x-page-header title="Purchase Orders">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.suppliers.index') }}"
               class="btn {{ request()->routeIs('admin.suppliers.*') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Suppliers
            </a>
            <a href="{{ route('admin.purchase-orders.index') }}"
               class="btn {{ request()->routeIs('admin.purchase-orders.index') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Purchase Orders
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
                 style="min-width:220px">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All statuses</option>
                            <option value="draft"     @selected(request('status') === 'draft')>Draft</option>
                            <option value="sent"      @selected(request('status') === 'sent')>Sent</option>
                            <option value="received"  @selected(request('status') === 'received')>Received</option>
                            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                        @if($activeFilters)
                        <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>New PO
        </a>
    </x-slot>
</x-page-header>
</form>

<div class="card">
    @if($orders->isEmpty())
        <x-empty-state title="No purchase orders" icon="bi-receipt"
            description="Create your first purchase order to track incoming stock."/>
    @else
    <div class="table-responsive">
        <table class="table po-table table-stack table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>PO #</th>
                    <th class="d-none d-sm-table-cell">Supplier</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Expected</th>
                    <th class="d-none d-md-table-cell">Received</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $po)
                @php
                    $badge = match($po->status) {
                        'draft'     => 'bg-secondary-subtle text-secondary',
                        'sent'      => 'bg-info-subtle text-info',
                        'received'  => 'bg-success-subtle text-success',
                        'cancelled' => 'bg-danger-subtle text-danger',
                        default     => 'bg-light text-muted',
                    };
                @endphp
                <tr>
                    <td class="cell-plain">
                        <p class="mb-0 small fw-semibold font-monospace">{{ $po->po_number }}</p>
                        <small class="text-muted d-sm-none">{{ $po->supplier?->name ?? '—' }}</small>
                    </td>
                    <td data-label="Supplier" class="small d-none d-sm-table-cell">
                        {{ $po->supplier?->name ?? '—' }}
                    </td>
                    <td data-label="Status">
                        <span class="badge rounded-pill {{ $badge }}">{{ ucfirst($po->status) }}</span>
                    </td>
                    <td data-label="Expected" class="small text-muted d-none d-md-table-cell">
                        {{ $po->expected_at?->format('M d, Y') ?? '—' }}
                    </td>
                    <td data-label="Received" class="small text-muted d-none d-md-table-cell">
                        {{ $po->received_at?->format('M d, Y') ?? '—' }}
                    </td>
                    <td data-label="Total" class="text-end small fw-semibold">
                        ₱{{ number_format($po->total, 2) }}
                    </td>
                    <td class="cell-actions text-end">
                        <a href="{{ route('admin.purchase-orders.show', $po) }}"
                           class="btn btn-primary btn-sm">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer">{{ $orders->withQueryString()->links() }}</div>
    @endif
    @endif
</div>

@endsection
