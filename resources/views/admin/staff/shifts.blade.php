@extends('layouts.app')
@section('title', 'Shifts & Attendance')

@push('styles')
<style>
    .sh-avatar {
        width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .8rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
    }
    /* TailAdmin table header */
    .sh-table thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .sh-table tbody td { padding-top: .75rem; padding-bottom: .75rem; }
    .sh-table tbody tr { transition: background-color .15s; }
</style>
@endpush

@section('content')

@php $activeFilters = (int) request()->filled('status') + (int) request()->filled('date') + (int) request()->filled('staff_id'); @endphp

<form method="GET" action="{{ route('admin.staff.shifts') }}" x-data="{ open: false }">
<x-page-header title="Shifts & Attendance">
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

        {{-- Filter icon --}}
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
                 style="min-width:260px">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All statuses</option>
                            <option value="scheduled"  @selected(request('status') === 'scheduled')>Scheduled</option>
                            <option value="active"     @selected(request('status') === 'active')>Active</option>
                            <option value="completed"  @selected(request('status') === 'completed')>Completed</option>
                            <option value="absent"     @selected(request('status') === 'absent')>Absent</option>
                            <option value="late"       @selected(request('status') === 'late')>Late</option>
                            <option value="cancelled"  @selected(request('status') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Date</label>
                        <input type="date" name="date" value="{{ request('date') }}" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Staff</label>
                        <select name="staff_id" class="form-select">
                            <option value="">All staff</option>
                            @foreach($staffList as $s)
                            <option value="{{ $s->id }}" @selected(request('staff_id') == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                        @if($activeFilters)
                        <a href="{{ route('admin.staff.shifts') }}" class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn btn-primary"
                data-bs-toggle="modal" data-bs-target="#newShiftModal">
            <i class="bi bi-plus-lg"></i>New Shift
        </button>
    </x-slot>
</x-page-header>
</form>

<div class="card">
    <div class="card-body pb-2 d-flex align-items-center justify-content-between gap-3">
        <span style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
            Shifts
        </span>
        <span class="badge rounded-pill bg-secondary-subtle text-secondary">{{ $shifts->total() }}</span>
    </div>

    @if($shifts->isEmpty())
        <x-empty-state title="No shifts found" icon="bi-clock-history"
            description="Schedule a shift or adjust your filters."/>
    @else
    <div class="table-responsive">
        <table class="table sh-table table-stack table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Staff</th>
                    <th>Date</th>
                    <th class="d-none d-sm-table-cell">Scheduled</th>
                    <th class="d-none d-md-table-cell">Actual</th>
                    <th class="text-end d-none d-md-table-cell">Hours</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($shifts as $shift)
                <tr>
                    <td class="cell-plain">
                        <div class="d-flex align-items-center gap-2">
                            <div class="sh-avatar">{{ strtoupper(substr($shift->staff->name ?? '?', 0, 1)) }}</div>
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold text-truncate">{{ $shift->staff->name ?? 'Unknown' }}</p>
                                <small class="text-muted">{{ $shift->branch->name ?? '—' }}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Date" class="small text-nowrap">
                        {{ $shift->shift_date->format('M j, Y') }}
                    </td>
                    <td data-label="Scheduled" class="small font-monospace text-muted d-none d-sm-table-cell text-nowrap">
                        {{ \Carbon\Carbon::parse($shift->scheduled_start)->format('H:i') }}
                        – {{ \Carbon\Carbon::parse($shift->scheduled_end)->format('H:i') }}
                    </td>
                    <td data-label="Actual" class="small font-monospace d-none d-md-table-cell text-nowrap">
                        @if($shift->clocked_in_at || $shift->clocked_out_at)
                            {{ $shift->clocked_in_at?->format('H:i') ?? '—' }}
                            – {{ $shift->clocked_out_at?->format('H:i') ?? '—' }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td data-label="Hours" class="small fw-semibold text-end d-none d-md-table-cell">
                        @if($shift->clocked_in_at && $shift->clocked_out_at)
                            {{ number_format($shift->duration_minutes / 60, 1) }}h
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td data-label="Status">
                        <x-badge :status="match($shift->status) {
                            'scheduled' => 'pending',
                            'active'    => 'active',
                            'completed' => 'completed',
                            'absent'    => 'cancelled',
                            'late'      => 'pending',
                            'cancelled' => 'cancelled',
                            default     => 'neutral'
                        }">{{ ucfirst($shift->status) }}</x-badge>
                    </td>
                    <td class="cell-actions text-end">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                data-bs-toggle="modal" data-bs-target="#editShiftModal{{ $shift->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($shifts->hasPages())
    <div class="card-footer">{{ $shifts->withQueryString()->links() }}</div>
    @endif
    @endif
</div>

@endsection

@push('modals')

{{-- Edit shift modals --}}
@foreach($shifts as $shift)
@php $isClockedIn = (bool) $shift->clocked_in_at; @endphp

<x-modal name="editShiftModal{{ $shift->id }}"
         title="Edit Shift"
         subtitle="{{ $shift->staff->name ?? '' }} · {{ $shift->shift_date->format('M j, Y') }}">
    <form method="POST" action="{{ route('admin.shifts.update', $shift) }}"
          id="edit-shift-form-{{ $shift->id }}">
        @csrf @method('PUT')

        @if($isClockedIn)
        <div class="alert alert-info border-0 small d-flex gap-2 align-items-start mb-3">
            <i class="bi bi-lock-fill mt-1 flex-shrink-0"></i>
            <div>Clocked in at <strong>{{ $shift->clocked_in_at->format('M j, g:i A') }}</strong>.
            Date and times are locked — only status and notes can be changed.</div>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Status</label>
                <select name="status" required class="form-select">
                    @foreach(['active' => 'Active', 'completed' => 'Completed', 'late' => 'Late'] as $val => $lbl)
                    <option value="{{ $val }}" @selected($shift->status === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" maxlength="500" class="form-control">{{ $shift->notes }}</textarea>
            </div>
        </div>
        @else
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Staff</label>
                <select name="staff_id" required class="form-select">
                    @foreach($staffList as $s)
                    <option value="{{ $s->id }}" @selected($shift->staff_id === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Date</label>
                <input type="date" name="shift_date"
                       value="{{ $shift->shift_date->format('Y-m-d') }}"
                       required class="form-control">
            </div>
            <div class="col-6">
                <label class="form-label">Start</label>
                <input type="time" name="scheduled_start"
                       value="{{ \Carbon\Carbon::parse($shift->scheduled_start)->format('H:i') }}"
                       required class="form-control">
            </div>
            <div class="col-6">
                <label class="form-label">End</label>
                <input type="time" name="scheduled_end"
                       value="{{ \Carbon\Carbon::parse($shift->scheduled_end)->format('H:i') }}"
                       required class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Status</label>
                <select name="status" required class="form-select">
                    @foreach(['scheduled' => 'Scheduled', 'absent' => 'Absent (no-show)', 'cancelled' => 'Cancelled'] as $val => $lbl)
                    <option value="{{ $val }}" @selected($shift->status === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" maxlength="500" class="form-control">{{ $shift->notes }}</textarea>
            </div>
        </div>
        @endif
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="edit-shift-form-{{ $shift->id }}" class="btn btn-primary">
            <i class="bi bi-floppy"></i>Save
        </button>
    </x-slot>
</x-modal>

@endforeach

{{-- New Shift modal --}}
<x-modal name="newShiftModal" title="Schedule New Shift">
    <form method="POST" action="{{ route('admin.shifts.store') }}" id="new-shift-form">
        @csrf
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label">Staff <span class="text-danger">*</span></label>
                <select name="staff_id" required
                        class="form-select @error('staff_id') is-invalid @enderror">
                    <option value="">— Select staff —</option>
                    @foreach($staffList as $s)
                    <option value="{{ $s->id }}" @selected(old('staff_id') == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                @error('staff_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <div class="alert alert-light border small d-flex align-items-center gap-2 mb-0 py-2">
                    <i class="bi bi-shop text-success flex-shrink-0"></i>
                    <span>Scheduling in <strong>{{ $activeBranch?->name ?? 'All branches' }}</strong>.
                    Switch from the topbar to schedule elsewhere.</span>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" name="shift_date"
                       value="{{ old('shift_date', now()->toDateString()) }}"
                       required class="form-control @error('shift_date') is-invalid @enderror">
                @error('shift_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6">
                <label class="form-label">Start <span class="text-danger">*</span></label>
                <input type="time" name="scheduled_start"
                       value="{{ old('scheduled_start', '08:00') }}"
                       required class="form-control @error('scheduled_start') is-invalid @enderror">
                @error('scheduled_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-6">
                <label class="form-label">End <span class="text-danger">*</span></label>
                <input type="time" name="scheduled_end"
                       value="{{ old('scheduled_end', '17:00') }}"
                       required class="form-control @error('scheduled_end') is-invalid @enderror">
                @error('scheduled_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="3" maxlength="500"
                          class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="new-shift-form" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>Schedule Shift
        </button>
    </x-slot>
</x-modal>

@if($errors->any() && (request()->old('shift_date') || request()->old('staff_id')))
<script>
    document.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('newShiftModal')).show();
    });
</script>
@endif

@endpush
