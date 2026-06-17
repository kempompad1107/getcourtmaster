@extends('layouts.app')
@section('title', 'Customers')

@push('styles')
<style>
    /* ── Customers list — polish + mobile card stacking ── */
    .cu-avatar {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; object-fit: cover;
        display: grid; place-items: center; font-weight: 700; font-size: .82rem;
        color: #fff; background: linear-gradient(135deg, #6366f1, #4f46e5);
    }
    .cu-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .cu-table thead { display: none; }
        .cu-table, .cu-table tbody, .cu-table tr, .cu-table td { display: block; width: 100%; }
        .cu-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .cu-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .cu-table td + td { border-top: 1px solid var(--bs-border-color); }
        .cu-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .cu-table td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

@php
    $filterBranch = $filterBranchId && isset($availableBranches)
        ? $availableBranches->firstWhere('id', $filterBranchId)
        : null;
    $subtitle = $filterBranchId
        ? $customers->total() . ' active at ' . ($filterBranch->name ?? 'this branch') . ' (last ' . $activityWindow . ' days)'
        : $customers->total() . ' total customers';
@endphp

<x-page-header title="Customers" :subtitle="$subtitle">
    <x-slot name="actions">
        @php $customerLimit = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, 'customers'); @endphp
        @if($customerLimit['allowed'])
            <a href="{{ route('admin.customers.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-person-plus me-1"></i>New Customer
            </a>
        @else
            <button class="btn btn-primary btn-sm" disabled title="Plan limit reached ({{ $customerLimit['used'] }}/{{ $customerLimit['max'] }} on {{ $customerLimit['plan'] }})">
                <i class="bi bi-lock-fill me-1"></i>New Customer
            </button>
        @endif
    </x-slot>
</x-page-header>

@include('admin._partials.plan-limit-banner', ['resource' => 'customers'])

{{-- Unified filter bar --}}
@isset($availableBranches)
    @if($availableBranches->count() > 1 || ($canSeeAllBranches ?? false))
    <x-filter-bar placeholder="Search by name, email, or phone..."
                  :active-count="(int) request()->filled('branch_id')"
                  :clear="route('admin.customers.index')">
        <x-slot name="filters">
            <div>
                <label class="form-label small fw-semibold mb-1">Branch</label>
                <select name="branch_id" class="form-select form-select-sm">
                    @if($canSeeAllBranches ?? false)
                        <option value="all" @selected($filterBranchId === null)>All branches</option>
                    @endif
                    @foreach($availableBranches as $b)
                        <option value="{{ $b->id }}" @selected($filterBranchId === $b->id)>
                            Active at {{ $b->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </x-slot>
    </x-filter-bar>
    @else
    <x-filter-bar placeholder="Search by name, email, or phone..."
                  :active-count="0"
                  :clear="route('admin.customers.index')">
    </x-filter-bar>
    @endif
@else
<x-filter-bar placeholder="Search by name, email, or phone..."
              :active-count="0"
              :clear="route('admin.customers.index')">
</x-filter-bar>
@endisset

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table cu-table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Customer</th>
                    <th>Joined</th>
                    <th class="text-end">Bookings</th>
                    <th class="text-end">Lifetime Value</th>
                    <th class="text-end">Wallet</th>
                    <th>Membership</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                <tr>
                    <td data-label="Customer">
                        <div class="d-flex align-items-center gap-2">
                            @if($customer->avatar)
                            <img src="{{ $customer->avatar_url }}" alt="{{ $customer->name }}" class="cu-avatar">
                            @else
                            <div class="cu-avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</div>
                            @endif
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold text-truncate">{{ $customer->name }}</p>
                                <small class="text-muted d-block text-truncate">{{ $customer->email }}</small>
                                @if($customer->phone)
                                <small class="text-muted">{{ $customer->phone }}</small>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td data-label="Joined" class="small">{{ $customer->created_at->format('M j, Y') }}</td>
                    <td data-label="Bookings" class="small fw-semibold text-end">{{ $customer->bookings_count }}</td>
                    <td data-label="Lifetime Value" class="small fw-semibold text-end">₱{{ number_format($customer->bookings_sum_total_amount ?? 0, 2) }}</td>
                    <td data-label="Wallet" class="small fw-semibold text-end">₱{{ number_format($customer->wallet_balance, 2) }}</td>
                    <td data-label="Membership">
                        @if($customer->activeMembership)
                        <span class="badge rounded-pill bg-primary-subtle text-primary">{{ $customer->activeMembership->plan->name }}</span>
                        @else
                        <span class="text-muted small">None</span>
                        @endif
                    </td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <div class="d-inline-flex gap-1">
                            <a href="{{ route('admin.customers.show', $customer) }}"
                               class="btn btn-outline-primary btn-sm">View</a>
                            <a href="{{ route('admin.customers.edit', $customer) }}"
                               class="btn btn-outline-secondary btn-sm">Edit</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="bk-cell-empty">
                        <x-empty-state title="No customers found" icon="bi-people"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $customers->withQueryString()->links() }}
    </div>
</div>

@endsection
