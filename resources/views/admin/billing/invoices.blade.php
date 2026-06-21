@extends('layouts.app')
@section('title', 'Subscription Invoices')

@push('styles')
<style>
    /* ── Subscription invoices — polish + mobile card stacking ── */
    .inv-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .inv-table thead { display: none; }
        .inv-table, .inv-table tbody, .inv-table tr, .inv-table td { display: block; width: 100%; }
        .inv-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .inv-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .inv-table td + td { border-top: 1px solid var(--bs-border-color); }
        .inv-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .inv-table td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Subscription Invoices"
                subtitle="Your platform subscription billing history."/>

{{-- Unified filter bar: status only (no search) --}}
<x-filter-bar :searchable="false"
              :active-count="(int) request()->filled('status')"
              :clear="route('admin.subscription-invoices.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach(['pending','paid','failed','refunded','overdue'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </x-slot>
</x-filter-bar>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table inv-table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Invoice #</th>
                    <th>Plan</th>
                    <th>Issued</th>
                    <th>Due</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoices as $invoice)
                @php
                    $badge = match($invoice->status) {
                        'paid'     => 'bg-success-subtle text-success',
                        'pending'  => 'bg-warning-subtle text-warning',
                        'overdue'  => 'bg-danger-subtle text-danger',
                        'failed'   => 'bg-danger-subtle text-danger',
                        'refunded' => 'bg-secondary-subtle text-secondary',
                        default    => 'bg-secondary-subtle text-secondary',
                    };
                @endphp
                <tr>
                    <td data-label="Invoice #" class="font-monospace small fw-semibold">{{ $invoice->invoice_number }}</td>
                    <td data-label="Plan" class="small">
                        {{ $invoice->subscription?->plan?->name ?? '—' }}
                        @if($invoice->payment_gateway)
                            <small class="text-muted d-block">via {{ ucfirst($invoice->payment_gateway) }}</small>
                        @endif
                    </td>
                    <td data-label="Issued" class="small">{{ $invoice->created_at->format('M j, Y') }}</td>
                    <td data-label="Due" class="small">
                        {{ $invoice->due_at?->format('M j, Y') ?? '—' }}
                        @if($invoice->status === 'pending' && $invoice->due_at?->isPast())
                            <small class="text-danger d-block">Overdue</small>
                        @endif
                    </td>
                    <td data-label="Total" class="text-end small fw-semibold">₱{{ number_format($invoice->total, 2) }}</td>
                    <td data-label="Status"><span class="badge rounded-pill {{ $badge }}">{{ ucfirst($invoice->status) }}</span></td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <a href="{{ route('admin.subscription-invoices.pdf', $invoice) }}"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="7" class="bk-cell-empty">
                        <x-empty-state title="No invoices yet"
                                       message="Subscription invoices will appear here once billed."
                                       icon="bi-receipt"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
    <div class="card-footer">
        {{ $invoices->links() }}
    </div>
    @endif
</div>

@endsection
