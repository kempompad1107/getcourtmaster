@extends('layouts.app')
@section('title', 'Purchase Orders')

@push('styles')
<style>
    /* ── Purchase orders — polish + mobile card stacking ── */
    .po-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .po-table thead { display: none; }
        .po-table, .po-table tbody, .po-table tr, .po-table td { display: block; width: 100%; }
        .po-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .po-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .po-table td + td { border-top: 1px solid var(--bs-border-color); }
        .po-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .po-table td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Purchase Orders">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.suppliers.index') }}"
               class="btn btn-sm btn-outline-secondary">Suppliers</a>
            <a href="{{ route('admin.purchase-orders.index') }}"
               class="btn btn-sm btn-primary">Purchase Orders</a>
        </div>
        <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New PO
        </a>
    </x-slot>
</x-page-header>

{{-- Unified filter bar --}}
<x-filter-bar :searchable="false"
              :active-count="(int) request()->filled('status')"
              :clear="route('admin.purchase-orders.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <option value="draft"     @selected(request('status') === 'draft')>Draft</option>
                <option value="sent"      @selected(request('status') === 'sent')>Sent</option>
                <option value="received"  @selected(request('status') === 'received')>Received</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
        </div>
    </x-slot>
</x-filter-bar>

<div class="card">
    <div class="table-responsive">
        <table class="table po-table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Status</th>
                    <th>Expected</th>
                    <th>Received</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $po)
                <tr>
                    <td data-label="PO #" class="small font-monospace fw-semibold">{{ $po->po_number }}</td>
                    <td data-label="Supplier" class="small">{{ $po->supplier?->name ?? '—' }}</td>
                    <td data-label="Status">
                        @php
                            $badge = match($po->status) {
                                'draft'     => 'bg-secondary-subtle text-secondary',
                                'sent'      => 'bg-info-subtle text-info',
                                'received'  => 'bg-success-subtle text-success',
                                'cancelled' => 'bg-danger-subtle text-danger',
                                default     => 'bg-light text-muted',
                            };
                        @endphp
                        <span class="badge rounded-pill {{ $badge }}">{{ ucfirst($po->status) }}</span>
                    </td>
                    <td data-label="Expected" class="small text-muted">{{ $po->expected_at?->format('M d, Y') ?? '—' }}</td>
                    <td data-label="Received" class="small text-muted">{{ $po->received_at?->format('M d, Y') ?? '—' }}</td>
                    <td data-label="Total" class="text-end small fw-semibold">₱{{ number_format($po->total, 2) }}</td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <a href="{{ route('admin.purchase-orders.show', $po) }}"
                           class="btn btn-outline-primary btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="7" class="bk-cell-empty">
                        <x-empty-state title="No purchase orders" icon="bi-receipt"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
    <div class="card-footer">
        {{ $orders->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
