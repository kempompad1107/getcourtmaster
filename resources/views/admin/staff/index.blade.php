@extends('layouts.app')
@section('title', 'Staff')

@push('styles')
<style>
    /* ── Staff list — polish + mobile card stacking ── */
    .st-avatar {
        width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .85rem;
        color: #fff; background: linear-gradient(135deg, #0ea5e9, #0369a1);
    }
    .st-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .st-table thead { display: none; }
        .st-table, .st-table tbody, .st-table tr, .st-table td { display: block; width: 100%; }
        .st-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .st-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .st-table td + td { border-top: 1px solid var(--bs-border-color); }
        .st-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .st-table td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Staff Management">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.staff.index') }}"
               class="btn btn-sm {{ request()->routeIs('admin.staff.index') ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Staff Members
            </a>
            <a href="{{ route('admin.staff.shifts') }}"
               class="btn btn-sm {{ request()->routeIs('admin.staff.shifts') ? 'btn-secondary' : 'btn-outline-secondary' }}">
                Shifts & Attendance
            </a>
        </div>
        @php $staffLimit = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, 'staff'); @endphp
        @if($staffLimit['allowed'])
            <a href="{{ route('admin.staff.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Add Staff
            </a>
        @else
            <button class="btn btn-primary btn-sm" disabled title="Plan limit reached ({{ $staffLimit['used'] }}/{{ $staffLimit['max'] }} on {{ $staffLimit['plan'] }})">
                <i class="bi bi-lock-fill me-1"></i>Add Staff
            </button>
        @endif
    </x-slot>
</x-page-header>

@include('admin._partials.plan-limit-banner', ['resource' => 'staff'])

{{-- Clock in/out --}}
@if(auth()->user()->hasRole(['front_desk','inventory_manager','manager']))
<div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h6 class="mb-0 fw-semibold">My Attendance</h6>
            <small class="text-muted">{{ now()->format('l, F j, Y') }}</small>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.staff.clock-in') }}">
                @csrf
                <button class="btn btn-success btn-sm"><i class="bi bi-box-arrow-in-right me-1"></i>Clock In</button>
            </form>
            <form method="POST" action="{{ route('admin.staff.clock-out') }}">
                @csrf
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Clock Out</button>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table st-table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Staff Member</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($staff as $member)
                <tr>
                    <td data-label="Staff">
                        <div class="d-flex align-items-center gap-2">
                            <div class="st-avatar">{{ strtoupper(substr($member->name, 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold text-truncate">{{ $member->name }}</p>
                                <small class="text-muted d-block text-truncate">{{ $member->email }}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Role">
                        <div class="d-flex flex-wrap gap-1 justify-content-end justify-content-md-start">
                            @foreach($member->roles as $role)
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary fw-medium">
                                {{ str_replace('_', ' ', ucfirst($role->name)) }}
                            </span>
                            @endforeach
                        </div>
                    </td>
                    <td data-label="Status">
                        <x-badge :status="$member->is_active ? 'active' : 'expired'">{{ $member->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </td>
                    <td data-label="Last Login" class="small text-muted">
                        {{ $member->last_login_at ? $member->last_login_at->diffForHumans() : 'Never' }}
                    </td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <a href="{{ route('admin.staff.edit', $member) }}"
                           class="btn btn-outline-primary btn-sm">Edit</a>
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="5" class="bk-cell-empty">
                        <x-empty-state title="No staff members found" icon="bi-person-badge"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($staff->hasPages())
    <div class="card-footer">
        {{ $staff->links() }}
    </div>
    @endif
</div>

@endsection
