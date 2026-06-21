@extends('layouts.app')

@section('title', 'Audit Log')

@section('content')

@php
    $filterLog   = request('log');
    $filterEvent = request('event');
    $filterCauser= request('causer');
    $filterFrom  = request('from');
    $filterTo    = request('to');
    $activeFilters = collect([$filterLog, $filterEvent, $filterCauser, $filterFrom, $filterTo])->filter()->count();
@endphp

<form method="GET" action="{{ route('admin.audit.index') }}" x-data="{ open: false }">
<x-page-header title="Audit Log" subtitle="Every change across the platform — who did what, and when">
    <x-slot name="actions">
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
                 style="min-width:240px">
                <div class="d-flex flex-column gap-3">

                    {{-- Log type --}}
                    @if($logNames->isNotEmpty())
                    <div>
                        <label class="form-label">Log type</label>
                        <select name="log" class="form-select">
                            <option value="">All</option>
                            @foreach($logNames as $name)
                                <option value="{{ $name }}" @selected($filterLog === $name)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Event --}}
                    <div>
                        <label class="form-label">Event</label>
                        <select name="event" class="form-select">
                            <option value="">All events</option>
                            <option value="created"  @selected($filterEvent === 'created')>Created</option>
                            <option value="updated"  @selected($filterEvent === 'updated')>Updated</option>
                            <option value="deleted"  @selected($filterEvent === 'deleted')>Deleted</option>
                        </select>
                    </div>

                    {{-- Causer --}}
                    @if($causerUsers->isNotEmpty())
                    <div>
                        <label class="form-label">Staff member</label>
                        <select name="causer" class="form-select">
                            <option value="">All staff</option>
                            @foreach($causerUsers as $u)
                                <option value="{{ $u->id }}" @selected((int)$filterCauser === $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Date range --}}
                    <div>
                        <label class="form-label">Date range</label>
                        <div class="d-flex align-items-center gap-1">
                            <input type="date" name="from" value="{{ $filterFrom }}" class="form-control" aria-label="From">
                            <span class="text-muted flex-shrink-0">–</span>
                            <input type="date" name="to" value="{{ $filterTo }}" class="form-control" aria-label="To">
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                        @if($activeFilters)
                        <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
</x-page-header>
</form>

@if($logs->isEmpty())
    <x-empty-state title="No activity found" icon="bi-clipboard-check"
        description="{{ $activeFilters ? 'No audit entries match your filters.' : 'Nothing has been logged yet.' }}"/>
@else
<div class="card">
    <div class="table-responsive">
        <table class="table table-stack align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">When</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Log</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Event</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Subject</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Staff</th>
                    <th style="font-size:.68rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:var(--bs-secondary-color)">Changes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                @php
                    $eventBadge = match($log->event) {
                        'created' => 'bg-success-subtle text-success',
                        'updated' => 'bg-primary-subtle text-primary',
                        'deleted' => 'bg-danger-subtle text-danger',
                        default   => 'bg-secondary-subtle text-secondary',
                    };
                    $causerInitial = optional($log->causer)->name ? strtoupper(substr($log->causer->name, 0, 1)) : null;
                @endphp
                <tr>
                    <td data-label="When">
                        <div class="small text-nowrap">{{ $log->created_at?->format('M d, Y') }}</div>
                        <div class="small text-muted">{{ $log->created_at?->format('H:i:s') }}</div>
                    </td>
                    <td data-label="Log">
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $log->log_name }}</span>
                    </td>
                    <td data-label="Event">
                        <span class="badge rounded-pill {{ $eventBadge }} text-capitalize">{{ $log->event }}</span>
                    </td>
                    <td data-label="Subject" class="small font-monospace text-muted">
                        {{ class_basename($log->subject_type) }}&nbsp;#{{ $log->subject_id }}
                    </td>
                    <td data-label="Staff">
                        @if($causerInitial)
                            <div class="d-flex align-items-center gap-2">
                                <span style="width:28px;height:28px;border-radius:50%;flex-shrink:0;display:grid;place-items:center;font-weight:700;font-size:.72rem;color:#fff;background:linear-gradient(135deg,#64748b,#475569)">{{ $causerInitial }}</span>
                                <span class="small">{{ $log->causer->name }}</span>
                            </div>
                        @else
                            <span class="small text-muted"><i class="bi bi-robot me-1"></i>System</span>
                        @endif
                    </td>
                    <td data-label="Changes">
                        @if($log->properties->isNotEmpty())
                            <details>
                                <summary class="small" style="cursor:pointer;color:var(--bs-secondary-color)">view diff</summary>
                                <pre class="small mb-0 mt-2 p-2" style="white-space:pre-wrap;word-break:break-word;background:var(--bs-body-bg-alt,rgba(148,163,184,.08));border:1px solid var(--bs-border-color);border-radius:.5rem;max-height:220px;overflow:auto;font-size:.72rem">{{ json_encode($log->properties, JSON_PRETTY_PRINT) }}</pre>
                            </details>
                        @else
                            <span class="small text-muted">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="px-4 py-3 border-top">{{ $logs->links() }}</div>
    @endif
</div>
@endif

@endsection
