@extends('layouts.app')
@section('title', 'Customers')

@push('styles')
<style>
    .cu-avatar {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; object-fit: cover;
        display: grid; place-items: center; font-weight: 700; font-size: .82rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
    }
    .cu-table thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .cu-table tbody td { padding-top: .85rem; padding-bottom: .85rem; }
    .cu-table tbody tr { transition: background-color .15s; }
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
    $showBranchFilter = isset($availableBranches) && ($availableBranches->count() > 1 || ($canSeeAllBranches ?? false));
@endphp

<x-page-header title="Customers" :subtitle="$subtitle">
    <x-slot name="actions">
        @php $customerLimit = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, 'customers'); @endphp
        @if($customerLimit['allowed'])
            <a href="{{ route('admin.customers.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus"></i>New Customer
            </a>
        @else
            <button class="btn btn-primary" disabled title="Plan limit reached ({{ $customerLimit['used'] }}/{{ $customerLimit['max'] }} on {{ $customerLimit['plan'] }})">
                <i class="bi bi-lock-fill"></i>New Customer
            </button>
        @endif
    </x-slot>
</x-page-header>

@include('admin._partials.plan-limit-banner', ['resource' => 'customers'])

<x-filter-bar placeholder="Search by name, email, or phone..."
              :active-count="$showBranchFilter ? (int) request()->filled('branch_id') : 0"
              :clear="route('admin.customers.index')">
    @if($showBranchFilter)
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
    @endif
</x-filter-bar>

{{-- Table --}}
<div class="card">
    @if($customers->isEmpty())
        <x-empty-state title="No customers found" icon="bi-people"
            description="Add your first customer to get started."/>
    @else
    <div class="table-responsive">
        <table class="table cu-table table-stack table-hover align-middle mb-0">
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
                @foreach($customers as $customer)
                <tr>
                    <td class="cell-plain">
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
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td class="cell-actions text-end">
                        <a href="{{ route('admin.customers.show', $customer) }}"
                           class="btn btn-primary btn-sm">View</a>
                        <a href="{{ route('admin.customers.edit', $customer) }}"
                           class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($customers->hasPages())
    <div class="card-footer">
        {{ $customers->withQueryString()->links() }}
    </div>
    @endif
    @endif
</div>

@endsection
