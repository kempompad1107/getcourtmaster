@extends('layouts.app')
@section('title', 'POS History')

@push('styles')
<style>
    /* ── POS history — summary tiles + mobile card stacking ── */
    .pos-sum {
        height: 100%;
        display: flex; align-items: center; gap: .9rem;
        padding: 1rem 1.15rem; border-radius: 1rem;
        background: var(--bs-card-bg); border: 1px solid var(--bs-border-color);
        box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 1px 3px rgba(15,23,42,.06);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .pos-sum:hover { transform: translateY(-2px); box-shadow: 0 6px 18px -8px rgba(15,23,42,.25); }
    .pos-sum-ico { width: 42px; height: 42px; border-radius: 11px; flex-shrink: 0; display: grid; place-items: center; font-size: 1.2rem; }
    .pos-sum-value { font-size: 1.2rem; font-weight: 700; line-height: 1; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pos-sum-label { font-size: .65rem; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--bs-secondary-color); margin: .25rem 0 0; }

    /* TailAdmin-style table header + airier rows (mobile stacking handled by the
       shared .table-stack pattern). */
    .pos-hist thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .pos-hist tbody td { padding-top: .85rem; padding-bottom: .85rem; }
    .pos-hist tbody tr { transition: background-color .15s; }
</style>
@endpush

@section('content')

@php
$dayTotal  = $orders->where('status', 'completed')->sum('total');
$dayCount  = $orders->where('status', 'completed')->count();
$voidCount = $orders->where('status', 'voided')->count();
@endphp

<form method="GET" action="{{ route('admin.pos.history') }}" x-data="{ open: false }">
<x-page-header title="Sales History">
    <x-slot name="actions">
        <a href="{{ route('admin.pos.index') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>New Order
        </a>

        {{-- Filter button --}}
        @php $activeFilters = (int) request()->filled('status') + (int) request()->filled('date'); @endphp
        <div class="position-relative" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="btn {{ $activeFilters > 0 ? 'btn-primary' : 'btn-outline-secondary' }} d-flex align-items-center gap-2"
                    :aria-expanded="open.toString()">
                <i class="bi bi-sliders2"></i>
                @if($activeFilters > 0)
                <span class="badge rounded-pill text-bg-light text-dark">{{ $activeFilters }}</span>
                @endif
            </button>
            <div x-show="open" x-cloak x-transition.origin.top.right
                 class="card shadow position-absolute end-0 mt-2"
                 style="width:clamp(240px,80vw,300px);max-width:calc(100vw - 1.5rem);z-index:1050">
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <label class="form-label small fw-semibold mb-1">Date</label>
                            <input type="date" name="date" value="{{ request('date', today()->toDateString()) }}"
                                   class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="form-label small fw-semibold mb-1">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                                <option value="voided" @selected(request('status') === 'voided')>Voided</option>
                                <option value="refunded" @selected(request('status') === 'refunded')>Refunded</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                        <a href="{{ route('admin.pos.history') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                    </div>
                </div>
            </div>
        </div>

    </x-slot>
</x-page-header>
<button type="submit" class="visually-hidden">Search</button>
</form>

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
    <div class="col-12 col-sm-4">
        <div class="pos-sum">
            <div class="pos-sum-ico bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin"></i></div>
            <div>
                <p class="pos-sum-value text-success">₱{{ number_format($dayTotal, 2) }}</p>
                <p class="pos-sum-label">Total Sales</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-4">
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
        <table class="table pos-hist table-stack table-hover align-middle mb-0">
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
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.pos.receipt', $order) }}"
                           class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-receipt me-1"></i>Receipt
                        </a>
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="7" class="cell-plain">
                        <x-empty-state title="No orders found" icon="bi-receipt"/>
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
