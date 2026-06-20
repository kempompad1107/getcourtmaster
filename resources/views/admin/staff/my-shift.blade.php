@extends('layouts.app')
@section('title', 'My Shift')

@push('styles')
<style>
    /* ── My Shift — scoped polish over the admin theme ── */
    .shift-hero {
        position: relative; overflow: hidden;
        border: 1px solid var(--bs-border-color);
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(148,163,184,.10) 0%, transparent 55%),
            var(--bs-card-bg);
    }
    .shift-hero.is-active {
        border-color: rgba(16,185,129,.3);
        background:
            radial-gradient(120% 140% at 100% 0%, rgba(16,185,129,.18) 0%, transparent 55%),
            linear-gradient(135deg, rgba(16,185,129,.10) 0%, rgba(5,150,105,.02) 45%),
            var(--bs-card-bg);
    }
    .shift-eyebrow { font-size: .72rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 0; }
    .shift-clock {
        font-family: ui-monospace, "JetBrains Mono", monospace; font-weight: 800;
        font-variant-numeric: tabular-nums; letter-spacing: -.02em;
        font-size: clamp(2.4rem, 5vw, 3.4rem); line-height: 1; margin: .3rem 0 0;
    }
    .shift-status-pill {
        display: inline-flex; align-items: center; gap: .5rem;
        padding: .4rem .9rem; border-radius: 999px;
        font-size: .78rem; font-weight: 600; letter-spacing: .04em;
    }
    .shift-status-pill.on  { background: rgba(16,185,129,.14); color: #34d399; border: 1px solid rgba(16,185,129,.3); }
    .shift-status-pill.off { background: rgba(148,163,184,.12); color: var(--bs-secondary-color); border: 1px solid var(--bs-border-color); }
    .shift-live-dot { width: 8px; height: 8px; border-radius: 50%; background: #34d399; box-shadow: 0 0 0 0 rgba(52,211,153,.7); animation: shiftPulse 2s infinite; }
    @keyframes shiftPulse { 0% { box-shadow: 0 0 0 0 rgba(52,211,153,.55); } 70% { box-shadow: 0 0 0 8px rgba(52,211,153,0); } 100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); } }

    .shift-context {
        padding: .85rem 1.1rem; border-radius: .9rem;
        background: var(--bs-body-bg-alt, rgba(148,163,184,.06));
        border: 1px solid var(--bs-border-color);
    }
    .shift-context-label { font-size: .68rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 0; }
    .shift-elapsed { font-family: ui-monospace, "JetBrains Mono", monospace; font-variant-numeric: tabular-nums; font-weight: 700; font-size: 1.35rem; line-height: 1; margin: .25rem 0 0; }

    /* Clock in/out action — refined to match the soft tiles instead of a flat Bootstrap block. */
    .shift-action-btn {
        border-radius: .9rem; border: 0;
        padding-top: .85rem; padding-bottom: .85rem;
        font-weight: 600; letter-spacing: -.01em;
        transition: transform .12s ease, box-shadow .15s ease, filter .15s ease;
    }
    .shift-action-btn:active { transform: translateY(1px); }
    .shift-action-btn.btn-success {
        --bs-btn-bg: #10b981; --bs-btn-border-color: #10b981;
        --bs-btn-hover-bg: #059669; --bs-btn-hover-border-color: #059669;
        --bs-btn-active-bg: #059669;
        background-image: linear-gradient(180deg, #14c98e 0%, #10b981 100%);
        box-shadow: 0 1px 2px rgba(5,150,105,.25), 0 6px 16px -6px rgba(16,185,129,.5);
    }
    .shift-action-btn.btn-danger {
        --bs-btn-bg: #e11d48; --bs-btn-border-color: #e11d48;
        --bs-btn-hover-bg: #be123c; --bs-btn-hover-border-color: #be123c;
        --bs-btn-active-bg: #be123c;
        background-image: linear-gradient(180deg, #f43f5e 0%, #e11d48 100%);
        box-shadow: 0 1px 2px rgba(190,18,60,.22), 0 6px 16px -6px rgba(244,63,94,.45);
    }
    .shift-action-btn:hover { box-shadow: 0 2px 5px rgba(0,0,0,.12), 0 10px 22px -6px rgba(0,0,0,.25); filter: brightness(1.02); }

    .shift-hours-tile { text-align: center; padding: .85rem 1.25rem; border-radius: .9rem; background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.2); }
    .shift-hours-value { font-weight: 800; font-size: 1.5rem; line-height: 1; margin: 0; }
    .shift-hours-label { font-size: .66rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color); margin: .25rem 0 0; }

    /* KPI summary row */
    .shift-kpi-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
    @media (min-width: 768px) { .shift-kpi-grid { grid-template-columns: repeat(4, 1fr); } }
    .shift-kpi {
        display: flex; align-items: center; gap: 1rem;
        padding: 1rem 1.25rem;
        border-radius: .9rem;
        background: var(--bs-card-bg);
        border: 1px solid var(--bs-border-color);
        transition: border-color .15s, box-shadow .15s;
    }
    .shift-kpi:hover { border-color: rgba(16,185,129,.3); box-shadow: 0 4px 12px -4px rgba(0,0,0,.08); }
    .shift-kpi-icon {
        width: 42px; height: 42px; border-radius: .75rem; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .shift-kpi-val { font-size: 1.4rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
    .shift-kpi-lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--bs-secondary-color); margin-top: .2rem; }

    .shift-datechip {
        width: 50px; flex-shrink: 0; text-align: center; border-radius: .7rem; overflow: hidden;
        border: 1px solid var(--bs-border-color); line-height: 1;
    }
    .shift-datechip .m { display: block; font-size: .6rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; padding: .25rem 0; background: rgba(16,185,129,.12); color: #34d399; }
    .shift-datechip .d { display: block; font-size: 1.2rem; font-weight: 800; padding: .3rem 0; }

    .shift-upcoming-item { transition: background-color .15s; }
    .shift-upcoming-item:hover { background: rgba(148,163,184,.05); }
    .shift-attendance tbody tr { transition: background-color .15s; }

    /* Stack the attendance table into cards on phones */
    @media (max-width: 575.98px) {
        .shift-attendance thead { display: none; }
        .shift-attendance, .shift-attendance tbody { display: block; width: 100%; }

        /* Each row becomes a flat card with a colored left accent */
        .shift-attendance tr:not(.shift-attendance-empty) {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid var(--bs-border-color);
            border-left: 3px solid var(--bs-border-color);
            border-radius: 0;
            overflow: hidden;
            margin-bottom: .6rem;
            background: var(--bs-card-bg);
        }
        /* Status-based left accent color */
        .shift-attendance tr[data-status="completed"] { border-left-color: #22c55e; }
        .shift-attendance tr[data-status="active"]    { border-left-color: #3b82f6; }
        .shift-attendance tr[data-status="late"]      { border-left-color: #f59e0b; }
        .shift-attendance tr[data-status="absent"]    { border-left-color: #ef4444; }
        .shift-attendance tr[data-status="scheduled"] { border-left-color: #94a3b8; }

        /* Date — full width header */
        .shift-attendance td[data-label="Date"] {
            grid-column: 1 / 2;
            display: flex; flex-direction: column;
            padding: .75rem 1rem .6rem;
            border-bottom: 1px solid var(--bs-border-color);
            font-weight: 700;
        }
        .shift-attendance td[data-label="Date"]::before {
            content: attr(data-label);
            font-size: .6rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--bs-secondary-color);
            margin-bottom: .2rem;
        }

        /* Status — top-right aligned with date */
        .shift-attendance td[data-label="Status"] {
            grid-column: 2 / 3;
            grid-row: 1;
            display: flex; align-items: center; justify-content: flex-end;
            padding: .75rem 1rem .6rem;
            border-bottom: 1px solid var(--bs-border-color);
        }
        .shift-attendance td[data-label="Status"]::before { display: none; }

        /* Scheduled — full width below header */
        .shift-attendance td[data-label="Scheduled"] {
            grid-column: 1 / 3;
            display: flex; align-items: center; justify-content: space-between;
            padding: .55rem 1rem;
            border-bottom: 1px solid var(--bs-border-color);
            font-size: .82rem;
        }
        .shift-attendance td[data-label="Scheduled"]::before {
            content: attr(data-label);
            font-size: .6rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }

        /* Clock In + Clock Out side by side */
        .shift-attendance td[data-label="Clock In"],
        .shift-attendance td[data-label="Clock Out"] {
            display: flex; flex-direction: column;
            padding: .55rem 1rem;
            font-size: .82rem;
        }
        .shift-attendance td[data-label="Clock In"] { border-right: 1px solid var(--bs-border-color); border-bottom: 1px solid var(--bs-border-color); }
        .shift-attendance td[data-label="Clock Out"] { border-bottom: 1px solid var(--bs-border-color); }
        .shift-attendance td[data-label="Clock In"]::before,
        .shift-attendance td[data-label="Clock Out"]::before {
            content: attr(data-label);
            font-size: .6rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--bs-secondary-color);
            margin-bottom: .2rem;
        }

        /* Hours — full width footer */
        .shift-attendance td[data-label="Hours"] {
            grid-column: 1 / 3;
            display: flex; align-items: center; justify-content: space-between;
            padding: .55rem 1rem;
            font-size: .82rem;
            background: var(--bs-body-bg-alt, rgba(148,163,184,.04));
        }
        .shift-attendance td[data-label="Hours"]::before {
            content: attr(data-label);
            font-size: .6rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--bs-secondary-color);
        }

        /* Empty state row reset */
        .shift-attendance-empty,
        .shift-attendance-empty td {
            display: block !important;
            border: 0 !important; padding: 0 !important;
            margin: 0 !important; background: none !important;
            border-radius: 0 !important;
        }
        .shift-attendance-empty td::before { display: none !important; }
    }
</style>
@endpush

@section('content')

<x-page-header title="My Shift" subtitle="Clock in, track your hours and review attendance"/>

{{-- Status hero --}}
<div class="card shift-hero {{ $activeShift ? 'is-active' : '' }} mb-4">
    <div class="card-body p-4">
        <div class="row align-items-center g-4">
            {{-- Live clock + status --}}
            <div class="col-12 col-lg-4">
                <p class="shift-eyebrow">{{ now()->format('l, F j, Y') }}</p>
                <p class="shift-clock" id="clock-time">{{ now()->format('H:i:s') }}</p>
                <div class="mt-3">
                    @if($activeShift)
                    <span class="shift-status-pill on"><span class="shift-live-dot"></span>On the clock</span>
                    @else
                    <span class="shift-status-pill off"><i class="bi bi-pause-circle"></i>Off the clock</span>
                    @endif
                </div>
            </div>

            {{-- Action (left) + paired info cards --}}
            <div class="col-12 col-lg-8">
                <div class="d-flex flex-column flex-sm-row align-items-stretch gap-3">
                    {{-- Compact action button, on the left --}}
                    @if($activeShift)
                    <form method="POST" action="{{ route('admin.staff.clock-out') }}" class="d-flex">
                        @csrf
                        <button class="btn btn-danger shift-action-btn d-flex align-items-center justify-content-center px-4 w-100 h-100">
                            <i class="bi bi-box-arrow-right me-1"></i>Clock Out
                        </button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('admin.staff.clock-in') }}" class="d-flex">
                        @csrf
                        <button class="btn btn-success shift-action-btn d-flex align-items-center justify-content-center px-4 w-100 h-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Clock In
                        </button>
                    </form>
                    @endif

                    {{-- Elapsed / scheduled context — pairs with the hours tile at equal height --}}
                    <div class="shift-context flex-grow-1">
                        @if($activeShift)
                        <p class="shift-context-label">Elapsed this session</p>
                        <p class="shift-elapsed text-success" id="shift-elapsed"
                           data-start="{{ $activeShift->clocked_in_at->getTimestamp() * 1000 }}">00:00:00</p>
                        <p class="small text-muted mb-0 mt-2">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Clocked in at {{ $activeShift->clocked_in_at->format('g:i A') }}
                        </p>
                        @elseif($todayShift)
                        <p class="shift-context-label">Today's scheduled shift</p>
                        <p class="fw-semibold mb-0 mt-1 font-monospace">
                            {{ \Carbon\Carbon::parse($todayShift->scheduled_start)->format('g:i A') }}
                            – {{ \Carbon\Carbon::parse($todayShift->scheduled_end)->format('g:i A') }}
                        </p>
                        <p class="small text-muted mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Clock in when you arrive.</p>
                        @else
                        <p class="shift-context-label">No shift scheduled</p>
                        <p class="small text-muted mb-0 mt-2">Clocking in now will create an ad-hoc record for today.</p>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- KPI summary row --}}
<div class="shift-kpi-grid mb-4">
    <div class="shift-kpi">
        <div class="shift-kpi-icon bg-success bg-opacity-10">
            <i class="bi bi-calendar-week text-success"></i>
        </div>
        <div>
            <div class="shift-kpi-val">{{ number_format($hoursThisWeek, 1) }}<span class="fs-6 fw-normal text-muted ms-1">h</span></div>
            <div class="shift-kpi-lbl">This week</div>
        </div>
    </div>
    <div class="shift-kpi">
        <div class="shift-kpi-icon bg-primary bg-opacity-10">
            <i class="bi bi-calendar-check text-primary"></i>
        </div>
        <div>
            <div class="shift-kpi-val">{{ number_format($hoursThisMonth, 1) }}<span class="fs-6 fw-normal text-muted ms-1">h</span></div>
            <div class="shift-kpi-lbl">This month</div>
        </div>
    </div>
    <div class="shift-kpi">
        <div class="shift-kpi-icon bg-info bg-opacity-10">
            <i class="bi bi-person-check text-info"></i>
        </div>
        <div>
            <div class="shift-kpi-val">{{ $daysWorked }}</div>
            <div class="shift-kpi-lbl">Days worked</div>
        </div>
    </div>
    <div class="shift-kpi">
        <div class="shift-kpi-icon {{ $onTimeRate === null ? 'bg-secondary bg-opacity-10' : ($onTimeRate >= 80 ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-10') }}">
            <i class="bi bi-clock-history {{ $onTimeRate === null ? 'text-secondary' : ($onTimeRate >= 80 ? 'text-success' : 'text-warning') }}"></i>
        </div>
        <div>
            <div class="shift-kpi-val">{{ $onTimeRate !== null ? $onTimeRate . '%' : '—' }}</div>
            <div class="shift-kpi-lbl">On-time rate</div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Upcoming shifts --}}
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-week me-1 text-muted"></i>Upcoming Shifts</h6></div>
            <div class="list-group list-group-flush">
                @forelse($upcoming as $shift)
                <div class="list-group-item shift-upcoming-item d-flex align-items-center gap-3 px-4 py-3">
                    <div class="shift-datechip">
                        <span class="m">{{ $shift->shift_date->format('M') }}</span>
                        <span class="d">{{ $shift->shift_date->format('j') }}</span>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <p class="mb-0 fw-semibold small">{{ $shift->shift_date->format('l') }}</p>
                        <div class="d-flex align-items-center gap-1 mt-1">
                            <i class="bi bi-clock text-muted" style="font-size:.7rem"></i>
                            <small class="text-muted font-monospace">
                                {{ \Carbon\Carbon::parse($shift->scheduled_start)->format('g:i A') }}
                                – {{ \Carbon\Carbon::parse($shift->scheduled_end)->format('g:i A') }}
                            </small>
                        </div>
                    </div>
                    <x-badge :status="match($shift->status) { 'scheduled' => 'pending', 'active' => 'active', 'completed' => 'completed', 'absent' => 'cancelled', 'late' => 'pending', 'cancelled' => 'cancelled', default => 'neutral' }">{{ ucfirst($shift->status) }}</x-badge>
                </div>
                @empty
                <div class="list-group-item text-center text-muted py-5">
                    <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>No upcoming shifts scheduled.
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent attendance --}}
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1 text-muted"></i>Recent Attendance</h6>
                <a href="{{ route('admin.staff.my-shift.history') }}" class="small text-primary text-decoration-none">
                    View all <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table shift-attendance table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Scheduled</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th class="text-end">Hours</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent as $shift)
                        <tr data-status="{{ $shift->status }}">
                            <td data-label="Date" class="small fw-medium text-nowrap">{{ $shift->shift_date->format('M j, Y') }}</td>
                            <td data-label="Scheduled" class="small font-monospace text-muted text-nowrap">
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
                            <td data-label="Status" class="text-end">
                                <x-badge :status="match($shift->status) { 'scheduled' => 'pending', 'active' => 'active', 'completed' => 'completed', 'absent' => 'cancelled', 'late' => 'pending', 'cancelled' => 'cancelled', default => 'neutral' }">{{ ucfirst($shift->status) }}</x-badge>
                            </td>
                        </tr>
                        @empty
                        <tr class="shift-attendance-empty">
                            <td colspan="6">
                                <x-empty-state title="No attendance records yet" icon="bi-clock-history"/>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Live wall clock (seconds), drift-free off the device clock.
    function tickClock() {
        const el = document.getElementById('clock-time');
        if (!el) return;
        const n = new Date();
        el.textContent =
            String(n.getHours()).padStart(2,'0') + ':' +
            String(n.getMinutes()).padStart(2,'0') + ':' +
            String(n.getSeconds()).padStart(2,'0');
    }
    setInterval(tickClock, 1000);
    tickClock();

    // Live elapsed since clock-in — derived from the absolute start timestamp
    // each tick so it never drifts (no local accumulation).
    (function () {
        const el = document.getElementById('shift-elapsed');
        if (!el) return;
        const startMs = parseInt(el.dataset.start) || Date.now();
        function tickElapsed() {
            let s = Math.max(0, Math.floor((Date.now() - startMs) / 1000));
            const h = Math.floor(s / 3600);
            const m = Math.floor((s % 3600) / 60);
            const sec = s % 60;
            el.textContent =
                String(h).padStart(2,'0') + ':' +
                String(m).padStart(2,'0') + ':' +
                String(sec).padStart(2,'0');
        }
        setInterval(tickElapsed, 1000);
        tickElapsed();
    })();
</script>

@endsection
