@extends('layouts.app')
@section('title', 'Booking Calendar')

@section('content')

<x-page-header title="Booking Calendar" subtitle="Visual schedule across your courts">
    <x-slot name="actions">
        <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i><span class="d-none d-sm-inline">List View</span>
        </a>
        <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Booking
        </a>
    </x-slot>
</x-page-header>

{{-- Toolbar: court filters (left) + status legend (right) --}}
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">

            {{-- Court filter chips --}}
            <div class="d-flex align-items-center gap-2 flex-wrap min-w-0">
                <span class="cal-filter-label">
                    <i class="bi bi-funnel-fill"></i> Courts
                </span>
                @php
                    // When arriving from a court's "Availability" button, pre-select only
                    // that court; otherwise default to showing every court.
                    $focusCourtId = request('court');
                @endphp
                <div class="d-flex flex-wrap gap-2">
                    @foreach($courts as $court)
                    <input type="checkbox" class="btn-check court-filter" autocomplete="off"
                           id="court_{{ $court->id }}" value="{{ $court->id }}"
                           @checked(! $focusCourtId || (int) $focusCourtId === $court->id)>
                    <label class="court-chip" for="court_{{ $court->id }}">{{ $court->name }}</label>
                    @endforeach
                </div>
                @if($courts->count() > 1)
                <button type="button" id="toggleCourts" class="btn btn-link btn-sm p-0 ms-1 cal-toggle-all">
                    Toggle all
                </button>
                @endif
            </div>

            {{-- Status legend --}}
            <div class="cal-legend d-flex flex-wrap align-items-center gap-3">
                <span class="cal-legend-item"><span class="cal-dot" style="--dot:#10b981"></span>Confirmed</span>
                <span class="cal-legend-item"><span class="cal-dot" style="--dot:#3b82f6"></span>Active</span>
                <span class="cal-legend-item"><span class="cal-dot" style="--dot:#f59e0b"></span>Pending</span>
                <span class="cal-legend-item"><span class="cal-dot" style="--dot:#ef4444"></span>Cancelled</span>
            </div>
        </div>
    </div>
</div>

<div class="card calendar-card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css">
<style>
/* ── Filter / legend bar ─────────────────────────────────────────── */
.cal-filter-label {
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: var(--bs-secondary-color, #6b7280);
    white-space: nowrap;
}
.cal-filter-label .bi { color: var(--bs-primary); }

.court-chip {
    display: inline-flex; align-items: center;
    padding: .3rem .7rem;
    font-size: .78rem; font-weight: 600; line-height: 1;
    border: 1px solid var(--bs-border-color);
    border-radius: 999px;
    color: var(--bs-secondary-color, #6b7280);
    background: var(--bs-body-bg-alt, #f8fafc);
    cursor: pointer; user-select: none;
    transition: all .15s ease;
}
.court-chip::before {
    content: ''; width: 7px; height: 7px; border-radius: 50%;
    background: currentColor; margin-right: .45rem; opacity: .4;
    transition: opacity .15s ease;
}
.court-chip:hover { border-color: rgba(16,185,129,.4); color: var(--bs-body-color); }
.btn-check:checked + .court-chip {
    color: #fff;
    background: linear-gradient(180deg, #34d399 0%, #10b981 100%);
    border-color: #10b981;
    box-shadow: 0 2px 8px -2px rgba(16,185,129,.5);
}
.btn-check:checked + .court-chip::before { opacity: 1; background: #fff; }
.btn-check:focus-visible + .court-chip { outline: 2px solid rgba(16,185,129,.6); outline-offset: 2px; }

.cal-toggle-all { font-size: .72rem; font-weight: 600; text-decoration: none; }
.cal-toggle-all:hover { text-decoration: underline; }

.cal-legend-item {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .75rem; font-weight: 500; white-space: nowrap;
    color: var(--bs-secondary-color, #6b7280);
}
.cal-dot {
    width: 9px; height: 9px; border-radius: 50%;
    background: var(--dot); box-shadow: 0 0 0 2px color-mix(in srgb, var(--dot) 18%, transparent);
}

/* ── FullCalendar theming → emerald / slate ──────────────────────── */
.fc {
    --fc-border-color: var(--bs-border-color);
    --fc-page-bg-color: transparent;
    --fc-today-bg-color: rgba(16,185,129,.06);
    --fc-now-indicator-color: #ef4444;
    --fc-event-text-color: #fff;
    --fc-neutral-bg-color: var(--bs-body-bg-alt, #f8fafc);
    font-size: .8125rem;
}
[data-bs-theme="dark"] .fc { --fc-neutral-bg-color: #182235; }

/* Toolbar title */
.fc .fc-toolbar-title { font-size: 1.1rem; font-weight: 700; letter-spacing: -.02em; }
.fc .fc-toolbar.fc-header-toolbar { margin-bottom: 1rem; }

/* Column / day headers — match our table header style */
.fc .fc-col-header-cell-cushion {
    padding: .55rem .25rem; font-size: .68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .04em;
    color: var(--bs-secondary-color, #6b7280); text-decoration: none;
}
.fc .fc-col-header-cell { background: var(--bs-body-bg-alt, #f8fafc); }
[data-bs-theme="dark"] .fc .fc-col-header-cell { background: #182235; }

/* Day-grid (month) numbers */
.fc .fc-daygrid-day-number {
    font-size: .78rem; font-weight: 600; padding: .35rem .5rem;
    color: var(--bs-body-color);
}
.fc .fc-day-today .fc-daygrid-day-number { color: var(--bs-primary); font-weight: 800; }
.fc .fc-daygrid-day.fc-day-today { background: var(--fc-today-bg-color); }

/* Time-grid slot labels */
.fc .fc-timegrid-slot-label-cushion,
.fc .fc-timegrid-axis-cushion {
    font-size: .68rem; font-weight: 600; color: var(--bs-secondary-color, #6b7280);
}

/* Events — rounded, soft, legible */
.fc-event {
    border: none !important; border-radius: 7px !important;
    padding: 1px 2px; font-weight: 600;
    box-shadow: 0 1px 2px rgba(15,23,42,.18);
    cursor: pointer; transition: transform .1s ease, box-shadow .1s ease;
}
.fc-event:hover { transform: translateY(-1px); box-shadow: 0 4px 10px -2px rgba(15,23,42,.3); }
.fc .fc-daygrid-event { padding: 1px 5px; border-radius: 6px !important; }
.fc .fc-event-time { font-weight: 700; opacity: .9; }
.fc .fc-event-title { font-weight: 600; }
.fc .fc-daygrid-event-dot { display: none; }

/* "More" link in month cells */
.fc .fc-daygrid-more-link {
    font-size: .7rem; font-weight: 700; color: var(--bs-primary);
    background: rgba(16,185,129,.08); border-radius: 5px; padding: 1px 6px;
}

/* Now indicator dot */
.fc .fc-timegrid-now-indicator-arrow { border-color: #ef4444; }

/* List (agenda) view — used on mobile */
.fc .fc-list { border-radius: var(--bs-border-radius-lg, .75rem); overflow: hidden; }
.fc .fc-list-day-cushion { background: var(--bs-body-bg-alt, #f8fafc); }
[data-bs-theme="dark"] .fc .fc-list-day-cushion { background: #182235; }
.fc .fc-list-event { cursor: pointer; }
.fc .fc-list-event:hover td { background: rgba(16,185,129,.06); }
.fc .fc-list-event-title { font-weight: 600; }
.fc .fc-list-empty { background: transparent; font-size: .85rem; color: var(--bs-secondary-color); }

/* Toolbar buttons → TailAdmin segmented control: neutral by default, the
   active view (and pressed states) filled emerald. themeSystem renders these
   as Bootstrap .btn-primary, so we recolour via the button CSS vars. */
.fc .fc-toolbar .btn-primary {
    --bs-btn-bg:                    var(--bs-body-bg-alt, #f8fafc);
    --bs-btn-border-color:          var(--bs-border-color);
    --bs-btn-color:                 var(--bs-body-color);
    --bs-btn-hover-bg:              var(--bs-border-color);
    --bs-btn-hover-border-color:    var(--bs-border-color);
    --bs-btn-hover-color:           var(--bs-body-color);
    --bs-btn-active-bg:             var(--bs-primary);
    --bs-btn-active-border-color:   var(--bs-primary);
    --bs-btn-active-color:          #fff;
    --bs-btn-disabled-bg:           var(--bs-body-bg-alt, #f8fafc);
    --bs-btn-disabled-border-color: var(--bs-border-color);
    --bs-btn-disabled-color:        var(--bs-secondary-color, #9aa4b2);
    box-shadow: none;
    text-transform: capitalize;
    font-size: .8rem;
    font-weight: 600;
}
.fc .fc-button-group > .fc-button { text-transform: capitalize; }
/* Active view stays emerald however FC marks it (.active or .fc-button-active) */
.fc .fc-toolbar .btn-primary.fc-button-active,
.fc .fc-toolbar .btn-primary.active {
    background: var(--bs-primary); border-color: var(--bs-primary); color: #fff;
}

/* ── Mobile polish ───────────────────────────────────────────────── */
@media (max-width: 575.98px) {
    .calendar-card .card-body { padding: .5rem; }
    .fc .fc-toolbar.fc-header-toolbar {
        flex-direction: column; align-items: stretch; gap: .6rem; margin-bottom: .85rem;
    }
    .fc .fc-toolbar-chunk { display: flex; justify-content: center; }
    .fc .fc-toolbar-title { font-size: 1rem; text-align: center; }
    .fc .fc-button { padding: .35rem .6rem; }
    .cal-legend { width: 100%; justify-content: flex-start; }
    /* Slightly larger touch targets for list rows */
    .fc .fc-list-event td { padding: .7rem .65rem; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Phones can't fit a 7-column week grid comfortably — default to the agenda
    // list view there, and offer the grid views as opt-in. Desktop keeps the week grid.
    const mobile = window.matchMedia('(max-width: 767.98px)');
    const mobileToolbar = {
        left:   'prev,next today',
        center: 'title',
        right:  'listWeek,dayGridMonth',
    };
    const desktopToolbar = {
        left:   'prev,next today',
        center: 'title',
        right:  'dayGridMonth,timeGridWeek,timeGridDay',
    };

    const timeFmt = { hour: 'numeric', minute: '2-digit', meridiem: 'short' };

    const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
        initialView: mobile.matches ? 'listWeek' : 'timeGridWeek',
        headerToolbar: mobile.matches ? mobileToolbar : desktopToolbar,
        themeSystem: 'bootstrap5',
        slotMinTime: '06:00:00',
        slotMaxTime: '23:00:00',
        scrollTime: '08:00:00',
        height: 'auto',
        expandRows: true,
        stickyHeaderDates: true,
        nowIndicator: true,
        allDaySlot: false,
        dayMaxEvents: true,           // collapse overflow into a "+n more" link (month view)
        eventTimeFormat: timeFmt,
        slotLabelFormat: timeFmt,
        businessHours: { daysOfWeek: [0,1,2,3,4,5,6], startTime: '07:00', endTime: '22:00' },
        events: function(info, successCallback, failureCallback) {
            const courtIds = [...document.querySelectorAll('.court-filter:checked')].map(e => e.value).join(',');
            const params = new URLSearchParams({
                start: info.startStr,
                end: info.endStr,
                courts: courtIds,
            });
            fetch(`${window.APP_BASE}/admin/bookings/calendar-data?${params}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(async r => {
                    if (!r.ok) {
                        const text = await r.text();
                        console.error('calendar-data failed:', r.status, text.slice(0, 500));
                        throw new Error(`HTTP ${r.status}`);
                    }
                    return r.json();
                })
                .then(successCallback)
                .catch(failureCallback);
        },
        eventClick: function(info) {
            window.location.href = `${window.APP_BASE}/admin/bookings/${info.event.id}`;
        },
    });
    calendar.render();

    // Swap toolbar + default view when crossing the phone/desktop breakpoint
    // (e.g. rotating a tablet). Only force the view if the user is still on a
    // view that doesn't fit the new size.
    mobile.addEventListener('change', (e) => {
        if (e.matches) {
            calendar.setOption('headerToolbar', mobileToolbar);
            if (['timeGridWeek', 'timeGridDay'].includes(calendar.view.type)) {
                calendar.changeView('listWeek');
            }
        } else {
            calendar.setOption('headerToolbar', desktopToolbar);
            if (calendar.view.type === 'listWeek') {
                calendar.changeView('timeGridWeek');
            }
        }
    });

    const courtFilters = document.querySelectorAll('.court-filter');
    courtFilters.forEach(cb => {
        cb.addEventListener('change', () => calendar.refetchEvents());
    });

    // Toggle-all: if any court is unchecked, check them all; otherwise clear all.
    const toggleBtn = document.getElementById('toggleCourts');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            const anyOff = [...courtFilters].some(cb => !cb.checked);
            courtFilters.forEach(cb => { cb.checked = anyOff; });
            calendar.refetchEvents();
        });
    }
});
</script>
@endpush
