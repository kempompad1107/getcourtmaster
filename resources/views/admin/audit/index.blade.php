@extends('layouts.app')

@section('title', 'Audit Log')

@push('styles')
<style>
    /* ── Audit log — polish + mobile card stacking ── */
    .au-causer {
        width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .72rem;
        color: #fff; background: linear-gradient(135deg, #64748b, #475569);
    }
    .au-changes summary { cursor: pointer; color: var(--bs-secondary-color); }
    .au-changes pre {
        white-space: pre-wrap; word-break: break-word; margin-top: .5rem; padding: .65rem .8rem;
        background: var(--bs-body-bg-alt, rgba(148,163,184,.08)); border: 1px solid var(--bs-border-color);
        border-radius: .6rem; max-height: 260px; overflow: auto;
    }
    .au-table tbody tr { transition: background-color .15s; }
    @media (max-width: 767.98px) {
        .au-table thead { display: none; }
        .au-table, .au-table tbody, .au-table tr, .au-table td { display: block; width: 100%; }
        .au-table tr {
            border: 1px solid var(--bs-border-color); border-radius: .85rem;
            padding: .35rem .9rem; margin: .75rem 0; background: var(--bs-card-bg);
        }
        .au-table td {
            display: flex; align-items: center; justify-content: space-between; gap: 1rem;
            border: 0; padding: .5rem 0; text-align: right;
        }
        .au-table td + td { border-top: 1px solid var(--bs-border-color); }
        .au-table td::before {
            content: attr(data-label); text-align: left; flex-shrink: 0;
            font-size: .68rem; font-weight: 600; letter-spacing: .05em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }
        .au-table td.bk-cell-empty::before { content: none; }
        .au-table .au-changes { width: 100%; }
    }
</style>
@endpush

@section('content')

<x-page-header title="Audit Log" subtitle="Every change across the platform — who did what, and when">
    <x-slot name="actions">
        <form method="GET" class="d-flex gap-2">
            <select name="log" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:160px">
                <option value="">All log types</option>
                @foreach ($logNames as $name)
                    <option value="{{ $name }}" {{ request('log') === $name ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
        </form>
    </x-slot>
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table au-table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>When</th>
                    <th>Log</th>
                    <th>Event</th>
                    <th>Subject</th>
                    <th>Causer</th>
                    <th>Changes</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $eventBadge = match($log->event) {
                            'created' => 'bg-success-subtle text-success',
                            'updated' => 'bg-primary-subtle text-primary',
                            'deleted' => 'bg-danger-subtle text-danger',
                            default   => 'bg-secondary-subtle text-secondary',
                        };
                    @endphp
                    <tr>
                        <td data-label="When" class="small text-muted text-nowrap">{{ $log->created_at?->format('M d, H:i:s') }}</td>
                        <td data-label="Log"><span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">{{ $log->log_name }}</span></td>
                        <td data-label="Event"><span class="badge rounded-pill {{ $eventBadge }} text-capitalize">{{ $log->event }}</span></td>
                        <td data-label="Subject" class="small font-monospace">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                        <td data-label="Causer">
                            <div class="d-flex align-items-center gap-2 justify-content-end justify-content-md-start">
                                @if(optional($log->causer)->name)
                                    <span class="au-causer">{{ strtoupper(substr($log->causer->name, 0, 1)) }}</span>
                                    <span class="small">{{ $log->causer->name }}</span>
                                @else
                                    <span class="small text-muted"><i class="bi bi-robot me-1"></i>System</span>
                                @endif
                            </div>
                        </td>
                        <td data-label="Changes" class="small au-changes">
                            @if ($log->properties->isNotEmpty())
                                <details>
                                    <summary>view</summary>
                                    <pre class="small mb-0">{{ json_encode($log->properties, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="bk-cell-empty text-center text-muted py-5">
                        <i class="bi bi-clipboard-check fs-3 d-block mb-2 opacity-50"></i>No activity logged.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
    <div class="card-footer">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
