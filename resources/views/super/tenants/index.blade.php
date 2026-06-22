@extends('layouts.super')
@section('title', 'Tenants')

@section('content')

<x-page-header title="All Tenants" :subtitle="number_format($stats['total']) . ' tenants registered'">
    <x-slot name="actions">
        <a href="{{ route('super.tenants.create') }}" class="btn btn-primary">
            <i class="bi bi-building-add me-1"></i>Add Tenant
        </a>
    </x-slot>
</x-page-header>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
    {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- KPI row --}}
<div class="kpi-grid mb-4">
    <x-stat-card label="Total Tenants" :value="$stats['total']"     icon="bi-buildings"      color="emerald"/>
    <x-stat-card label="Active"        :value="$stats['active']"    icon="bi-check-circle"   color="emerald"/>
    <x-stat-card label="On Trial"      :value="$stats['trial']"     icon="bi-hourglass-split" color="amber"/>
    <x-stat-card label="Suspended"     :value="$stats['suspended']" icon="bi-slash-circle"   color="red"/>
</div>

{{-- Search + filter --}}
<div class="card">
    <div class="card-header d-flex flex-column flex-sm-row align-items-sm-center gap-2 py-3">
        <form method="GET" action="{{ route('super.tenants.index') }}"
              class="d-flex align-items-center gap-2 flex-grow-1 flex-nowrap overflow-auto"
              id="tenant-filter-form">

            <div class="input-group input-group-sm flex-shrink-1" style="min-width:140px;max-width:260px;">
                <span class="input-group-text bg-transparent border-end-0">
                    <i class="bi bi-search text-muted" style="font-size:.8rem;"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control border-start-0 ps-0"
                       placeholder="Search name or email…"
                       autocomplete="off">
            </div>

            <select name="status" class="form-select form-select-sm flex-shrink-0" style="width:140px;"
                    onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active"    @selected(request('status') === 'active')>Active</option>
                <option value="trial"     @selected(request('status') === 'trial')>Trial</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>

            <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Filter</button>

            @if(request('search') || request('status'))
            <a href="{{ route('super.tenants.index') }}" class="btn btn-outline-secondary btn-sm flex-shrink-0">Clear</a>
            @endif
        </form>

        <span class="small text-muted flex-shrink-0">
            {{ number_format($tenants->total()) }} result{{ $tenants->total() !== 1 ? 's' : '' }}
        </span>
    </div>

    @if($tenants->isEmpty())
        <x-empty-state title="No tenants found" icon="bi-buildings"
            description="{{ request('search') || request('status') ? 'No tenants match your current filters.' : 'Add your first tenant to get started.' }}"/>
    @else
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>Club</th>
                    <th class="d-none d-sm-table-cell">Plan</th>
                    <th>Status</th>
                    <th class="d-none d-lg-table-cell">Users</th>
                    <th class="d-none d-lg-table-cell">Courts</th>
                    <th class="d-none d-md-table-cell">Joined</th>
                    <th class="cell-actions"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($tenants as $tenant)
                <tr>
                    <td data-label="Club" class="cell-plain">
                        <div class="fw-semibold small">{{ $tenant->name }}</div>
                        <div class="small text-muted">{{ $tenant->email }}</div>
                        {{-- On mobile: show plan + status inline so context isn't lost --}}
                        <div class="d-sm-none mt-1 d-flex flex-wrap gap-1 align-items-center">
                            <span class="badge bg-primary-subtle text-primary-emphasis">{{ ucfirst($tenant->plan ?? 'N/A') }}</span>
                            @if($tenant->isOnTrial())
                                <x-badge status="pending">Trial</x-badge>
                            @endif
                            <span class="small text-muted">· {{ $tenant->created_at->format('M j, Y') }}</span>
                        </div>
                    </td>
                    <td data-label="Plan" class="d-none d-sm-table-cell">
                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ ucfirst($tenant->plan ?? 'N/A') }}</span>
                        @if($tenant->isOnTrial())
                            <x-badge status="pending">Trial</x-badge>
                        @endif
                    </td>
                    <td data-label="Status">
                        <x-badge :status="match($tenant->status) {
                            'active'    => 'active',
                            'suspended' => 'cancelled',
                            'trial'     => 'pending',
                            'cancelled' => 'cancelled',
                            default     => 'neutral'
                        }">{{ ucfirst($tenant->status) }}</x-badge>
                    </td>
                    <td data-label="Users" class="small text-muted d-none d-lg-table-cell">
                        <i class="bi bi-people me-1 opacity-50"></i>{{ number_format($tenant->users_count) }}
                    </td>
                    <td data-label="Courts" class="small text-muted d-none d-lg-table-cell">
                        <i class="bi bi-grid me-1 opacity-50"></i>{{ number_format($tenant->courts_count) }}
                    </td>
                    <td data-label="Joined" class="small text-muted d-none d-md-table-cell text-nowrap">
                        {{ $tenant->created_at->format('M j, Y') }}
                    </td>
                    <td class="cell-actions">
                        <div class="d-flex align-items-center gap-1 justify-content-end flex-nowrap">
                            <a href="{{ route('super.tenants.show', $tenant) }}"
                               class="btn btn-primary btn-sm">View</a>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        title="More actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('super.tenants.edit', $tenant) }}">
                                            <i class="bi bi-pencil me-2 opacity-60"></i>Edit
                                        </a>
                                    </li>
                                    <li>
                                        <form method="POST" action="{{ route('super.tenants.impersonate', $tenant) }}">
                                            @csrf
                                            <button class="dropdown-item">
                                                <i class="bi bi-person-badge me-2 opacity-60"></i>Impersonate
                                            </button>
                                        </form>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    @if($tenant->status === 'active')
                                    <li>
                                        <form method="POST" action="{{ route('super.tenants.suspend', $tenant) }}">
                                            @csrf
                                            <button class="dropdown-item text-danger">
                                                <i class="bi bi-slash-circle me-2 opacity-60"></i>Suspend
                                            </button>
                                        </form>
                                    </li>
                                    @else
                                    <li>
                                        <form method="POST" action="{{ route('super.tenants.activate', $tenant) }}">
                                            @csrf
                                            <button class="dropdown-item text-success">
                                                <i class="bi bi-check-circle me-2 opacity-60"></i>Activate
                                            </button>
                                        </form>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($tenants->hasPages())
    <div class="px-4 py-3 border-top d-flex justify-content-end">
        {{ $tenants->links() }}
    </div>
    @endif
</div>

@endsection
