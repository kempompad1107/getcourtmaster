@extends('layouts.app')
@section('title', 'POS History')

@push('styles')
<style>
    /* ── POS history — summary tiles + mobile card stacking ── */
    .pos-sum {
        display: flex; align-items: center; gap: .9rem;
        padding: 1rem 1.15rem; border-radius: 1rem;
        background: var(--bs-card-bg); border: 1px solid var(--bs-border-color);
    }
    .pos-sum-ico { width: 46px; height: 46px; border-radius: 13px; flex-shrink: 0; display: grid; place-items: center; font-size: 1.3rem; }
    .pos-sum-value { font-size: 1.5rem; font-weight: 800; line-height: 1; margin: 0; }
    .pos-sum-label { font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color); margin: .3rem 0 0; }

    .pos-hist tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .pos-hist thead { display: none; }
        .pos-hist, .pos-hist tbody, .pos-hist tr, .pos-hist td { display: block; width: 100%; }
        .pos-hist tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .pos-hist td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .pos-hist td + td { border-top: 1px solid var(--bs-border-color); }
        .pos-hist td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .pos-hist td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

@php
$dayTotal  = $orders->where('status', 'completed')->sum('total');
$dayCount  = $orders->where('status', 'completed')->count();
$voidCount = $orders->where('status', 'voided')->count();
@endphp

<x-page-header title="Sales History">
    <x-slot name="actions">
        <a href="{{ route('admin.pos.index') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Order
        </a>
    </x-slot>
</x-page-header>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('admin.pos.history') }}" class="row g-2 align-items-end">
            <div class="col-6 col-sm-auto">
                <label class="form-label small mb-1">Date</label>
                <input type="date" name="date" value="{{ request('date', today()->toDateString()) }}"
                       class="form-control form-control-sm">
            </div>
            <div class="col-6 col-sm-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                    <option value="voided" @selected(request('status') === 'voided')>Voided</option>
                    <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
                @if(request()->anyFilled(['date','status']))
                <a href="{{ route('admin.pos.history') }}" class="btn btn-link btn-sm text-muted">Clear</a>
                @endif
            </div>
        </form>
    </div>
</div>

{{-- Summary tiles --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="pos-sum">
            <div class="pos-sum-ico bg-primary bg-opacity-10 text-primary"><i class="bi bi-receipt"></i></div>
            <div>
                <p class="pos-sum-value">{{ $dayCount }}</p>
                <p class="pos-sum-label">Completed Orders</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-sum">
            <div class="pos-sum-ico bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin"></i></div>
            <div>
                <p class="pos-sum-value text-success">₱{{ number_format($dayTotal, 2) }}</p>
                <p class="pos-sum-label">Total Sales</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-sum">
            <div class="pos-sum-ico bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-octagon"></i></div>
            <div>
                <p class="pos-sum-value text-danger">{{ $voidCount }}</p>
                <p class="pos-sum-label">Voided</p>
            </div>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table pos-hist table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Order #</th>
                    <th>Time</th>
                    <th>Cashier</th>
                    <th>Items</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end">Receipt</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                @php
                $badge = match($order->status) {
                    'completed' => 'bg-success-subtle text-success',
                    'voided'    => 'bg-danger-subtle text-danger',
                    'refunded'  => 'bg-warning-subtle text-warning',
                    default     => 'bg-secondary-subtle text-secondary',
                };
                @endphp
                <tr class="{{ $order->status === 'voided' ? 'opacity-50' : '' }}">
                    <td data-label="Order #" class="small font-monospace fw-semibold">{{ $order->order_number }}</td>
                    <td data-label="Time" class="small">{{ $order->created_at->format('g:i A') }}</td>
                    <td data-label="Cashier" class="small">{{ $order->cashier->name ?? '—' }}</td>
                    <td data-label="Items" class="small">{{ $order->items->sum('quantity') }} item(s)</td>
                    <td data-label="Total" class="small fw-semibold text-end">₱{{ number_format($order->total, 2) }}</td>
                    <td data-label="Status">
                        <span class="badge rounded-pill {{ $badge }}">{{ ucfirst($order->status) }}</span>
                    </td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <a href="{{ route('admin.pos.receipt', $order) }}"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-receipt me-1"></i>Receipt
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="bk-cell-empty">
                        <x-empty-state title="No orders found" icon="bi-receipt"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $orders->withQueryString()->links() }}
    </div>
</div>

@endsection
