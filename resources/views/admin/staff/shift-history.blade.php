@extends('layouts.app')
@section('title', 'Attendance History')

@push('styles')
<style>
    /* Mobile stacked cards — same pattern as my-shift */
    .history-card-grid { display: none; }

    @media (max-width: 767.98px) {
        .history-table-wrap { display: none; }
        .history-card-grid  { display: block; }

        .history-card {
            border: 1px solid var(--bs-border-color);
            border-left: 3px solid var(--bs-border-color);
            background: var(--bs-card-bg);
            margin-bottom: .6rem;
        }
        .history-card[data-status="completed"] { border-left-color: #22c55e; }
        .history-card[data-status="active"]    { border-left-color: #3b82f6; }
        .history-card[data-status="late"]      { border-left-color: #f59e0b; }
        .history-card[data-status="absent"]    { border-left-color: #ef4444; }
        .history-card[data-status="scheduled"] { border-left-color: #94a3b8; }

        .history-card-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: .7rem 1rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .history-card-header .date { font-weight: 700; font-size: .9rem; }
        .history-card-header .dow  { font-size: .7rem; color: var(--bs-secondary-color); margin-top: 1px; }

        .history-card-body { display: grid; grid-template-columns: 1fr 1fr; }
        .history-cell {
            padding: .5rem 1rem;
            font-size: .82rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .history-cell.full  { grid-column: 1 / 3; }
        .history-cell.left  { border-right: 1px solid var(--bs-border-color); }
        .history-cell.footer { grid-column: 1 / 3; background: var(--bs-body-bg-alt, rgba(148,163,184,.04)); border-bottom: 0; }
        .history-cell-label {
            font-size: .6rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--bs-secondary-color);
            display: block; margin-bottom: .15rem;
        }
        .history-cell-val { font-weight: 600; }
    }

    /* Filter bar */
    .history-filters { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
</style>
@endpush

@section('content')

<x-page-header title="Attendance History" subtitle="Your full shift record">
    <x-slot name="actions">
        <a href="{{ route('admin.staff.my-shift') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to My Shift
        </a>
    </x-slot>
</x-page-header>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="history-filters">
            <select name="status" class="form-select form-select-sm" style="width:auto">
                <option value="">All statuses</option>
                @foreach(['completed','active','late','absent','scheduled','cancelled'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="month" class="form-select form-select-sm" style="width:auto">
                <option value="">All months</option>
                @foreach(range(1,12) as $m)
                <option value="{{ $m }}" @selected(request('month') == $m)>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                @endforeach
            </select>
            <select name="year" class="form-select form-select-sm" style="width:auto">
                <option value="">All years</option>
                @foreach(range(now()->year, now()->year - 2) as $y)
                <option value="{{ $y }}" @selected(request('year') == $y)>{{ $y }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            @if(request()->hasAny(['status','month','year']))
            <a href="{{ route('admin.staff.my-shift.history') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            @endif
            <span class="text-muted small ms-auto">{{ $history->total() }} records &middot; {{ number_format($totalHours, 1) }}h total</span>
        </form>
    </div>
</div>

{{-- Desktop table --}}
<div class="card history-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Scheduled</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th class="text-end">Hours</th>
                    <th class="text-end">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $shift)
                <tr>
                    <td class="fw-semibold small text-nowrap">{{ $shift->shift_date->format('M j, Y') }}</td>
                    <td class="small text-muted">{{ $shift->shift_date->format('l') }}</td>
                    <td class="small font-monospace text-muted text-nowrap">
                        {{ \Carbon\Carbon::parse($shift->scheduled_start)->format('H:i') }}
                        – {{ \Carbon\Carbon::parse($shift->scheduled_end)->format('H:i') }}
                    </td>
                    <td class="small font-monospace">{{ $shift->clocked_in_at?->format('H:i') ?? '—' }}</td>
                    <td class="small font-monospace">{{ $shift->clocked_out_at?->format('H:i') ?? '—' }}</td>
                    <td class="small fw-semibold text-end">
                        @if($shift->clocked_in_at && $shift->clocked_out_at)
                            {{ number_format($shift->duration_minutes / 60, 1) }}h
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <x-badge :status="match($shift->status) { 'scheduled'=>'pending','active'=>'active','completed'=>'completed','absent'=>'cancelled','late'=>'pending','cancelled'=>'cancelled',default=>'neutral' }">{{ ucfirst($shift->status) }}</x-badge>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state title="No records found" icon="bi-clock-history"
                            description="Try adjusting your filters."/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($history->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center gap-3 flex-wrap">
        <small class="text-muted">Showing {{ $history->firstItem() }}–{{ $history->lastItem() }} of {{ $history->total() }}</small>
        {{ $history->links() }}
    </div>
    @endif
</div>

{{-- Mobile cards --}}
<div class="history-card-grid">
    @forelse($history as $shift)
    <div class="history-card" data-status="{{ $shift->status }}">
        <div class="history-card-header">
            <div>
                <div class="date">{{ $shift->shift_date->format('M j, Y') }}</div>
                <div class="dow">{{ $shift->shift_date->format('l') }}</div>
            </div>
            <x-badge :status="match($shift->status) { 'scheduled'=>'pending','active'=>'active','completed'=>'completed','absent'=>'cancelled','late'=>'pending','cancelled'=>'cancelled',default=>'neutral' }">{{ ucfirst($shift->status) }}</x-badge>
        </div>
        <div class="history-card-body">
            <div class="history-cell full">
                <span class="history-cell-label">Scheduled</span>
                <span class="history-cell-val font-monospace">
                    {{ \Carbon\Carbon::parse($shift->scheduled_start)->format('g:i A') }}
                    – {{ \Carbon\Carbon::parse($shift->scheduled_end)->format('g:i A') }}
                </span>
            </div>
            <div class="history-cell left">
                <span class="history-cell-label">Clock In</span>
                <span class="history-cell-val font-monospace">{{ $shift->clocked_in_at?->format('H:i') ?? '—' }}</span>
            </div>
            <div class="history-cell">
                <span class="history-cell-label">Clock Out</span>
                <span class="history-cell-val font-monospace">{{ $shift->clocked_out_at?->format('H:i') ?? '—' }}</span>
            </div>
            <div class="history-cell footer">
                <span class="history-cell-label">Hours</span>
                <span class="history-cell-val">
                    @if($shift->clocked_in_at && $shift->clocked_out_at)
                        {{ number_format($shift->duration_minutes / 60, 1) }}h
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </span>
            </div>
        </div>
    </div>
    @empty
    <x-empty-state title="No records found" icon="bi-clock-history" description="Try adjusting your filters."/>
    @endforelse

    @if($history->hasPages())
    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <small class="text-muted">{{ $history->firstItem() }}–{{ $history->lastItem() }} of {{ $history->total() }}</small>
        {{ $history->links() }}
    </div>
    @endif
</div>

@endsection
