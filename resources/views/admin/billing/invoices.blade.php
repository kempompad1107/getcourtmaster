@extends('layouts.app')
@section('title', 'Subscription Invoices')

@section('content')

@php
    $filterStatus  = request('status');
    $activeFilters = (int) filled($filterStatus);
@endphp

<form method="GET" action="{{ route('admin.subscription-invoices.index') }}" x-data="{ open: false }">
<x-page-header title="Subscription Invoices" subtitle="Your platform billing history">
    <x-slot name="actions">
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
                 style="min-width:210px">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All statuses</option>
                            @foreach(['pending','paid','failed','refunded','overdue'] as $s)
                                <option value="{{ $s }}" @selected($filterStatus === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                        @if($activeFilters)
                            <a href="{{ route('admin.subscription-invoices.index') }}" class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
</x-page-header>
</form>

@if($invoices->isEmpty())
    <x-empty-state
        title="{{ $activeFilters ? 'No invoices match this filter' : 'No invoices yet' }}"
        description="{{ $activeFilters ? 'Try clearing the filter.' : 'Subscription invoices will appear here once billed.' }}"
        icon="bi-receipt"/>
@else
<div class="card">
    <div class="table-responsive">
        <table class="table table-stack table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Invoice #</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Plan</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Issued</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Due</th>
                    <th class="text-end" style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Total</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Status</th>
                    <th class="cell-actions" style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $invoice)
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
                    <td data-label="Invoice #" class="font-monospace small fw-semibold">
                        {{ $invoice->invoice_number }}
                    </td>
                    <td data-label="Plan" class="small">
                        {{ $invoice->subscription?->plan?->name ?? '—' }}
                        @if($invoice->payment_gateway)
                            <div class="text-muted" style="font-size:.75rem">via {{ ucfirst($invoice->payment_gateway) }}</div>
                        @endif
                    </td>
                    <td data-label="Issued" class="small text-nowrap">
                        {{ $invoice->created_at->format('M j, Y') }}
                    </td>
                    <td data-label="Due" class="small text-nowrap">
                        {{ $invoice->due_at?->format('M j, Y') ?? '—' }}
                        @if($invoice->status === 'pending' && $invoice->due_at?->isPast())
                            <div class="text-danger" style="font-size:.75rem">Overdue</div>
                        @endif
                    </td>
                    <td data-label="Total" class="text-end small fw-semibold text-nowrap">
                        ₱{{ number_format($invoice->total, 2) }}
                    </td>
                    <td data-label="Status">
                        <span class="badge rounded-pill {{ $badge }}">{{ ucfirst($invoice->status) }}</span>
                    </td>
                    <td class="cell-actions">
                        <a href="{{ route('admin.subscription-invoices.pdf', $invoice) }}"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
        <div class="px-4 py-3 border-top">{{ $invoices->links() }}</div>
    @endif
</div>
@endif

@endsection
