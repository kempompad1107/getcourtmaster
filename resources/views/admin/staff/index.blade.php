@extends('layouts.app')
@section('title', 'Staff')

@push('styles')
<style>
    .st-avatar {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .88rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
    }
    /* TailAdmin table header */
    .st-table thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .st-table tbody td { padding-top: .8rem; padding-bottom: .8rem; }
    .st-table tbody tr { transition: background-color .15s; }
</style>
@endpush

@section('content')

<x-page-header title="Staff Management">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.staff.index') }}"
               class="btn {{ request()->routeIs('admin.staff.index') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Staff Members
            </a>
            <a href="{{ route('admin.staff.shifts') }}"
               class="btn {{ request()->routeIs('admin.staff.shifts') ? 'btn-primary' : 'btn-outline-secondary' }}">
                Shifts & Attendance
            </a>
        </div>
        @php $staffLimit = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, 'staff'); @endphp
        @if($staffLimit['allowed'])
            <a href="{{ route('admin.staff.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i>Add Staff
            </a>
        @else
            <button class="btn btn-primary" disabled
                    title="Plan limit reached ({{ $staffLimit['used'] }}/{{ $staffLimit['max'] }} on {{ $staffLimit['plan'] }})">
                <i class="bi bi-lock-fill"></i>Add Staff
            </button>
        @endif
    </x-slot>
</x-page-header>

@include('admin._partials.plan-limit-banner', ['resource' => 'staff'])

{{-- Clock in/out — only visible to non-owner roles --}}
@if(auth()->user()->hasRole(['front_desk','inventory_manager','manager']))
<div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <p class="mb-0 fw-semibold small">My Attendance</p>
            <small class="text-muted">{{ now()->format('l, F j, Y') }}</small>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.staff.clock-in') }}">
                @csrf
                <button class="btn btn-success">
                    <i class="bi bi-box-arrow-in-right"></i>Clock In
                </button>
            </form>
            <form method="POST" action="{{ route('admin.staff.clock-out') }}">
                @csrf
                <button class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i>Clock Out
                </button>
            </form>
        </div>
    </div>
</div>
@endif

<div class="card">
    @if($staff->isEmpty())
        <x-empty-state title="No staff members yet" icon="bi-person-badge"
            description="Add your first staff member to get started."/>
    @else
    <div class="table-responsive">
        <table class="table st-table table-stack table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Staff Member</th>
                    <th class="d-none d-sm-table-cell">Role</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staff as $member)
                <tr>
                    <td class="cell-plain">
                        <div class="d-flex align-items-center gap-3">
                            <div class="st-avatar">{{ strtoupper(substr($member->name, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold text-truncate">{{ $member->name }}</p>
                                <small class="text-muted d-block text-truncate">{{ $member->email }}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Role" class="d-none d-sm-table-cell">
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($member->roles as $role)
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary fw-medium">
                                {{ str_replace('_', ' ', ucfirst($role->name)) }}
                            </span>
                            @endforeach
                        </div>
                    </td>
                    <td data-label="Status">
                        <x-badge :status="$member->is_active ? 'active' : 'expired'">
                            {{ $member->is_active ? 'Active' : 'Inactive' }}
                        </x-badge>
                    </td>
                    <td data-label="Last Login" class="small text-muted d-none d-md-table-cell">
                        {{ $member->last_login_at ? $member->last_login_at->diffForHumans() : 'Never' }}
                    </td>
                    <td class="cell-actions text-end">
                        <a href="{{ route('admin.staff.show', $member) }}" class="btn btn-primary btn-sm">
                            View
                        </a>
                        <a href="{{ route('admin.staff.edit', $member) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-pencil"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($staff->hasPages())
    <div class="px-4 py-3 border-top">{{ $staff->links() }}</div>
    @endif
    @endif
</div>

@endsection
