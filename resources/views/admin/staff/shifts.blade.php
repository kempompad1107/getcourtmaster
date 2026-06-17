@extends('layouts.app')
@section('title', 'Shifts & Attendance')

@push('styles')
<style>
    /* ── Shifts table — polish + mobile card stacking ── */
    .sh-avatar {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .78rem;
        color: #fff; background: linear-gradient(135deg, #0ea5e9, #0369a1);
    }
    .sh-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .sh-table thead { display: none; }
        .sh-table, .sh-table tbody, .sh-table tr, .sh-table td { display: block; width: 100%; }
        .sh-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .sh-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .sh-table td + td { border-top: 1px solid var(--bs-border-color); }
        .sh-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .sh-table td.bk-cell-empty::before { content: none; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Shifts & Attendance">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.staff.index') }}"
               class="btn btn-sm btn-outline-secondary">Staff Members</a>
            <a href="{{ route('admin.staff.shifts') }}"
               class="btn btn-sm btn-primary">Shifts & Attendance</a>
        </div>
        <button type="button" class="btn btn-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#newShiftModal">
            <i class="bi bi-plus-lg me-1"></i>New Shift
        </button>
    </x-slot>
</x-page-header>

{{-- Unified filter bar --}}
<x-filter-bar :searchable="false"
              :active-count="(int) request()->filled('status') + (int) request()->filled('date') + (int) request()->filled('staff_id')"
              :clear="route('admin.staff.shifts')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
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
            <label class="form-label small fw-semibold mb-1">Date</label>
            <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm">
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Staff</label>
            <select name="staff_id" class="form-select form-select-sm">
                <option value="">All staff</option>
                @foreach($staffList as $s)
                <option value="{{ $s->id }}" @selected(request('staff_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
    </x-slot>
</x-filter-bar>

{{-- Shifts table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1 text-muted"></i>Shifts</h6>
        <span class="badge rounded-pill bg-secondary-subtle text-secondary">{{ $shifts->total() }}</span>
    </div>
    <div class="table-responsive">
        <table class="table sh-table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Staff</th>
                    <th>Date</th>
                    <th>Scheduled</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th class="text-end">Hours</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($shifts as $shift)
                <tr x-data="{ editing: false }">
                    <td data-label="Staff">
                        <div class="d-flex align-items-center gap-2">
                            <div class="sh-avatar">{{ strtoupper(substr($shift->staff->name ?? '?', 0, 1)) }}</div>
                            <div>
                                <span class="small fw-semibold d-block">{{ $shift->staff->name ?? 'Unknown' }}</span>
                                <small class="text-muted">{{ $shift->branch->name ?? '—' }}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Date" class="small">{{ $shift->shift_date->format('M j, Y') }}</td>
                    <td data-label="Scheduled" class="small font-monospace text-muted">
                        {{ \Carbon\Carbon::parse($shift->scheduled_start)->format('H:i') }}
                        – {{ \Carbon\Carbon::parse($shift->scheduled_end)->format('H:i') }}
                    </td>
                    <td data-label="Clock In" class="small font-monospace">{{ $shift->clocked_in_at?->format('H:i') ?? '—' }}</td>
                    <td data-label="Clock Out" class="small font-monospace">{{ $shift->clocked_out_at?->format('H:i') ?? '—' }}</td>
                    <td data-label="Hours" class="small fw-semibold text-end">
                        @if($shift->clocked_in_at && $shift->clocked_out_at)
                            {{ number_format($shift->duration_minutes / 60, 1) }}h
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td data-label="Status">
                        <x-badge :status="match($shift->status) { 'scheduled' => 'pending', 'active' => 'active', 'completed' => 'completed', 'absent' => 'cancelled', 'late' => 'pending', 'cancelled' => 'cancelled', default => 'neutral' }">{{ ucfirst($shift->status) }}</x-badge>
                    </td>
                    <td data-label="" class="bk-cell-empty text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm"
                                data-bs-toggle="modal" data-bs-target="#editShiftModal{{ $shift->id }}">
                            Edit
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="bk-cell-empty">
                        <x-empty-state title="No shifts yet" icon="bi-clock-history"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($shifts->hasPages())
    <div class="card-footer">
        {{ $shifts->withQueryString()->links() }}
    </div>
    @endif
</div>

@push('modals')
{{-- Edit modals --}}
@foreach($shifts as $shift)
@php $isClockedIn = (bool) $shift->clocked_in_at; @endphp
<div class="modal fade" id="editShiftModal{{ $shift->id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.shifts.update', $shift) }}">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title">Edit Shift — {{ $shift->staff->name ?? '' }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($isClockedIn)
                    <div class="alert alert-info py-2 small mb-3">
                        <i class="bi bi-lock-fill me-1"></i>
                        Clocked in at <strong>{{ $shift->clocked_in_at->format('M j, g:i A') }}</strong>.
                        Date and times are locked — only status and notes can be changed.
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Status</label>
                            <select name="status" required class="form-select form-select-sm">
                                @foreach(['active', 'completed', 'late'] as $st)
                                <option value="{{ $st }}" @selected($shift->status === $st)>{{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notes</label>
                            <textarea name="notes" rows="2" maxlength="500"
                                      class="form-control form-control-sm">{{ $shift->notes }}</textarea>
                        </div>
                    </div>
                    @else
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Staff</label>
                            <select name="staff_id" required class="form-select form-select-sm">
                                @foreach($staffList as $s)
                                <option value="{{ $s->id }}" @selected($shift->staff_id === $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Date</label>
                            <input type="date" name="shift_date" value="{{ $shift->shift_date->format('Y-m-d') }}"
                                   required class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Start</label>
                            <input type="time" name="scheduled_start"
                                   value="{{ \Carbon\Carbon::parse($shift->scheduled_start)->format('H:i') }}"
                                   required class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">End</label>
                            <input type="time" name="scheduled_end"
                                   value="{{ \Carbon\Carbon::parse($shift->scheduled_end)->format('H:i') }}"
                                   required class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Status</label>
                            <select name="status" required class="form-select form-select-sm">
                                @foreach(['scheduled' => 'Scheduled', 'absent' => 'Absent (no-show)', 'cancelled' => 'Cancelled'] as $val => $label)
                                <option value="{{ $val }}" @selected($shift->status === $val)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notes</label>
                            <textarea name="notes" rows="2" maxlength="500"
                                      class="form-control form-control-sm">{{ $shift->notes }}</textarea>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

{{-- New Shift modal --}}
<div class="modal fade" id="newShiftModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.shifts.store') }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Schedule New Shift</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small">Staff <span class="text-danger">*</span></label>
                            <select name="staff_id" required class="form-select form-select-sm @error('staff_id') is-invalid @enderror">
                                <option value="">— Select staff —</option>
                                @foreach($staffList as $s)
                                <option value="{{ $s->id }}" @selected(old('staff_id') == $s->id)>{{ $s->name }}</option>
                                @endforeach
                            </select>
                            @error('staff_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="alert alert-light border py-2 px-2 mb-0 small d-flex align-items-center gap-2">
                                <i class="bi bi-shop text-success"></i>
                                <span>
                                    Scheduling in
                                    <strong>{{ $activeBranch?->name ?? 'All branches' }}</strong>.
                                    Switch from the topbar to schedule elsewhere.
                                </span>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Date <span class="text-danger">*</span></label>
                            <input type="date" name="shift_date" value="{{ old('shift_date', now()->toDateString()) }}"
                                   required class="form-control form-control-sm @error('shift_date') is-invalid @enderror">
                            @error('shift_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Start <span class="text-danger">*</span></label>
                            <input type="time" name="scheduled_start" value="{{ old('scheduled_start', '08:00') }}"
                                   required class="form-control form-control-sm @error('scheduled_start') is-invalid @enderror">
                            @error('scheduled_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label small">End <span class="text-danger">*</span></label>
                            <input type="time" name="scheduled_end" value="{{ old('scheduled_end', '17:00') }}"
                                   required class="form-control form-control-sm @error('scheduled_end') is-invalid @enderror">
                            @error('scheduled_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Notes</label>
                            <textarea name="notes" rows="2" maxlength="500"
                                      class="form-control form-control-sm @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Schedule Shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->any() && (request()->old('shift_date') || request()->old('staff_id')))
<script>
    document.addEventListener('DOMContentLoaded', () => {
        new bootstrap.Modal(document.getElementById('newShiftModal')).show();
    });
</script>
@endif
@endpush

@endsection
