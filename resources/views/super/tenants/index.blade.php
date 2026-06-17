@extends('layouts.super')
@section('title', 'Tenants')

@push('styles')
@include('super._partials.premium-ui')
@endpush

@section('content')

<x-page-header title="All Tenants" :subtitle="$tenants->total() . ' tenants'">
    <x-slot name="actions">
        <a href="{{ route('super.tenants.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-building-add me-1"></i>Add Tenant
        </a>
    </x-slot>
</x-page-header>

{{-- KPI row --}}
<div class="kpi-grid mb-4">
    <x-stat-card label="Total Tenants" :value="$tenants->total()" icon="bi-buildings" color="emerald"/>
    <x-stat-card label="Active" :value="$tenants->where('status','active')->count()" icon="bi-check-circle" color="emerald"/>
    <x-stat-card label="Suspended" :value="$tenants->where('status','suspended')->count()" icon="bi-slash-circle" color="red"/>
    <x-stat-card label="Trial" :value="$tenants->whereNotNull('trial_ends_at')->where('trial_ends_at','>=',now())->count()" icon="bi-hourglass-split" color="amber"/>
</div>

{{-- Tenants table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 pro-table">
            <thead>
                <tr>
                    <th>Club</th>
                    <th>Plan</th>
                    <th>Users</th>
                    <th>Courts</th>
                    <th>Bookings</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $tenant)
                <tr>
                    <td>
                        <p class="mb-0 small fw-medium">{{ $tenant->name }}</p>
                        <small class="text-muted">{{ $tenant->email }}</small>
                    </td>
                    <td data-label="Plan">
                        <span class="badge text-bg-primary">{{ ucfirst($tenant->plan) }}</span>
                        @if($tenant->isOnTrial())
                            <x-badge status="pending">Trial</x-badge>
                        @endif
                    </td>
                    <td class="small tcell-hide" data-label="Users">{{ $tenant->users_count }}</td>
                    <td class="small tcell-hide" data-label="Courts">{{ $tenant->courts_count }}</td>
                    <td class="small tcell-hide" data-label="Bookings">{{ $tenant->bookings_count }}</td>
                    <td class="small text-muted tcell-hide" data-label="Joined">{{ $tenant->created_at->format('M j, Y') }}</td>
                    <td data-label="Status">
                        <x-badge :status="match($tenant->status) { 'active' => 'active', 'suspended' => 'cancelled', 'trial' => 'pending', 'cancelled' => 'cancelled', default => 'neutral' }">{{ ucfirst($tenant->status) }}</x-badge>
                    </td>
                    <td class="text-end">
                        <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                            <a href="{{ route('super.tenants.show', $tenant) }}"
                               class="btn btn-link btn-sm p-0 text-success">View</a>

                            <a href="{{ route('super.tenants.edit', $tenant) }}"
                               class="btn btn-link btn-sm p-0 text-secondary">Edit</a>

                            @if($tenant->status === 'active')
                                <form method="POST" action="{{ route('super.tenants.suspend', $tenant) }}">
                                    @csrf
                                    <button class="btn btn-link btn-sm p-0 text-danger">Suspend</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('super.tenants.activate', $tenant) }}">
                                    @csrf
                                    <button class="btn btn-link btn-sm p-0 text-success">Activate</button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('super.tenants.impersonate', $tenant) }}">
                                @csrf
                                <button class="btn btn-link btn-sm p-0 text-primary">Impersonate</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5 small">No tenants found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tenants->hasPages())
    <div class="card-footer d-flex justify-content-end">
        {{ $tenants->links() }}
    </div>
    @endif
</div>

@endsection
