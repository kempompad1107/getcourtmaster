@extends('layouts.super')
@section('title', $plan->name . ' — Tenants')

@section('content')

<x-page-header :title="$plan->name . ' — Tenants'"
               :back="route('super.plans.index')"
               :subtitle="$tenants->count() . ' tenant' . ($tenants->count() === 1 ? '' : 's') . ' currently on this plan'">
    <x-slot name="actions">
        <a href="{{ route('super.plans.edit', $plan) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit Plan
        </a>
    </x-slot>
</x-page-header>

{{-- Plan summary card --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h5 class="fw-semibold mb-1">{{ $plan->name }}</h5>
                <p class="text-muted small mb-0">
                    ₱{{ number_format($plan->price_monthly) }}/mo
                    @if($plan->price_yearly) · ₱{{ number_format($plan->price_yearly) }}/yr @endif
                </p>
            </div>
            <div class="d-flex flex-wrap gap-3 small">
                <div><span class="text-muted">Max courts</span> <strong>{{ $plan->max_courts ?? '∞' }}</strong></div>
                <div><span class="text-muted">Max branches</span> <strong>{{ $plan->max_branches ?? '∞' }}</strong></div>
                <div><span class="text-muted">Max staff</span> <strong>{{ $plan->max_staff ?? '∞' }}</strong></div>
                <div><span class="text-muted">Max customers</span> <strong>{{ $plan->max_customers ?? '∞' }}</strong></div>
            </div>
        </div>
    </div>
</div>

{{-- Tenants table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Status</th>
                    <th>Cycle</th>
                    <th class="text-end">Branches</th>
                    <th class="text-end">Courts</th>
                    <th class="text-end">Users</th>
                    <th class="text-end">Bookings</th>
                    <th>Renews</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $t)
                    @php
                        $statusClass = ['active'=>'success','suspended'=>'danger','trial'=>'warning','cancelled'=>'secondary'][$t->status] ?? 'secondary';
                        $overCourts    = $plan->max_courts    && $t->courts_count    > $plan->max_courts;
                        $overBranches  = $plan->max_branches  && $t->branches_count  > $plan->max_branches;
                        $overCustomers = $plan->max_customers && $t->users_count     > $plan->max_customers;
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('super.tenants.show', $t) }}" class="fw-medium text-decoration-none">{{ $t->name }}</a>
                            <div class="small text-muted">{{ $t->slug }}</div>
                        </td>
                        <td><span class="badge bg-{{ $statusClass }}-subtle text-{{ $statusClass }} text-capitalize">{{ $t->status }}</span></td>
                        <td class="text-capitalize small">{{ $t->activeSubscription?->billing_cycle ?? '—' }}</td>
                        <td class="text-end {{ $overBranches ? 'text-danger fw-semibold' : '' }}">{{ $t->branches_count }}@if($plan->max_branches) <small class="text-muted">/ {{ $plan->max_branches }}</small>@endif</td>
                        <td class="text-end {{ $overCourts ? 'text-danger fw-semibold' : '' }}">{{ $t->courts_count }}@if($plan->max_courts) <small class="text-muted">/ {{ $plan->max_courts }}</small>@endif</td>
                        <td class="text-end {{ $overCustomers ? 'text-danger fw-semibold' : '' }}">{{ $t->users_count }}</td>
                        <td class="text-end">{{ $t->bookings_count }}</td>
                        <td class="small text-muted">{{ $t->activeSubscription?->renews_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('super.tenants.users', $t) }}" class="btn btn-link btn-sm p-0 me-2">Users</a>
                            <a href="{{ route('super.tenants.show', $t) }}" class="btn btn-link btn-sm p-0">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted small py-4">No tenants currently on this plan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
