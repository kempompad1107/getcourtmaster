@extends('layouts.app')
@section('title', 'Court Status Board')

@push('styles')
<style>
    /* ── Status Board — accent driven reactively by Alpine :class ── */
    .sb-legend { display: flex; gap: .5rem; flex-wrap: wrap; }
    .sb-legend-item {
        display: inline-flex; align-items: center; gap: .45rem;
        padding: .35rem .8rem; border-radius: 999px; font-size: .75rem; font-weight: 500;
        color: var(--bs-secondary-color);
        background: var(--bs-body-bg-alt, rgba(148,163,184,.06)); border: 1px solid var(--bs-border-color);
    }
    .sb-legend-dot { width: 9px; height: 9px; border-radius: 50%; }

    .sb-card {
        position: relative; overflow: hidden;
        --c: #94a3b8; --crgb: 148,163,184;
        border: 1px solid rgba(var(--crgb), .4) !important;
        transition: border-color .3s ease, box-shadow .3s ease, transform .18s ease;
    }
    .sb-card:hover { transform: translateY(-2px); }
    .sb-card::before {
        content: ""; position: absolute; inset: 0 0 auto 0; height: 3px; z-index: 1;
        background: linear-gradient(90deg, var(--c), rgba(var(--crgb), .2));
    }
    .sb-card.sb-available   { --c: #34d399; --crgb: 52,211,153; }
    .sb-card.sb-occupied    { --c: #fb7185; --crgb: 251,113,133; box-shadow: 0 0 0 1px rgba(251,113,133,.22), 0 14px 32px -22px rgba(251,113,133,.7); }
    .sb-card.sb-reserved    { --c: #fbbf24; --crgb: 251,191,36; }
    .sb-card.sb-muted       { --c: #94a3b8; --crgb: 148,163,184; }
    .sb-card .card-header { background: rgba(var(--crgb), .07); border-bottom-color: rgba(var(--crgb), .18); }
    .sb-card .card-footer { background: transparent; }

    .sb-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--c); box-shadow: 0 0 8px var(--c); display: inline-block; }
    .sb-pulse { animation: sbPulse 1.2s ease-in-out infinite; }
    @keyframes sbPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(var(--crgb), .65); } 50% { box-shadow: 0 0 0 6px rgba(var(--crgb), 0); } }

    .sb-timer {
        font-family: ui-monospace, "JetBrains Mono", monospace; font-weight: 800;
        font-variant-numeric: tabular-nums; letter-spacing: -.02em;
        font-size: 2.3rem; line-height: 1;
    }
    .sb-label { font-size: .68rem; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .sb-next-label { font-size: .66rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .sb-foot-chip { font-size: .8rem; color: var(--bs-secondary-color); }
    .sb-foot-chip i { color: var(--c); }
</style>
@endpush

@section('content')

<div x-data="courtBoard()">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div class="sb-legend">
            <span class="sb-legend-item"><span class="sb-legend-dot" style="background:#34d399"></span>Available</span>
            <span class="sb-legend-item"><span class="sb-legend-dot" style="background:#fb7185"></span>Occupied</span>
            <span class="sb-legend-item"><span class="sb-legend-dot" style="background:#fbbf24"></span>Reserved</span>
            <span class="sb-legend-item"><span class="sb-legend-dot" style="background:#94a3b8"></span>Maintenance</span>
        </div>
        <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Walk-in Booking
        </a>
    </div>

    {{-- Court grid --}}
    <div class="row g-3">
        @foreach($courts as $court)
        <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        @php
            $ct = $court->activeTimer;
            $ctScheduledEnd = $ct?->scheduled_end_at ? $ct->scheduled_end_at->getTimestamp() * 1000 : 0;
            $customerName = $ct?->booking?->customer?->name;
        @endphp
        <div x-data='courtCard({
                "courtId": {{ $court->id }},
                "initialStatus": @json($court->status),
                "timerId": {{ $ct?->id ?? 'null' }},
                "scheduledEndMs": {{ $ctScheduledEnd }},
                "hasTimer": {{ $ct ? 'true' : 'false' }},
                "customerName": @json($customerName),
                "bookNewUrl": @json(route('admin.bookings.create') . '?court_id=' . $court->id)
             })'
             class="card sb-card h-100 d-flex flex-column"
             :class="{
                'sb-available': status === 'available',
                'sb-occupied':  status === 'occupied',
                'sb-reserved':  status === 'reserved',
                'sb-muted': ['maintenance','closed'].includes(status),
             }">

            {{-- Card header --}}
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="min-w-0">
                    <h6 class="mb-0 fw-semibold text-truncate">{{ $court->name }}</h6>
                    <small class="text-muted">{{ $court->branch->name }} &bull; {{ ucfirst($court->type) }}</small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-2">
                    <span class="sb-dot" :class="{ 'sb-pulse': status === 'occupied' }"></span>
                    <small class="fw-semibold text-capitalize" x-text="status"></small>
                </div>
            </div>

            {{-- Card body --}}
            <div class="card-body text-center d-flex flex-column">
                {{-- Active session view --}}
                <div x-show="hasTimer">
                    <div class="sb-timer mb-1"
                         :class="isOvertime ? 'text-danger' : ''"
                         x-text="formattedTime"></div>
                    <p class="sb-label mb-3" x-show="!isOvertime">Remaining</p>
                    <p class="sb-label text-danger mb-3" x-show="isOvertime">⚠ Overtime</p>

                    <p class="fw-semibold mb-3" x-show="customerName" x-text="customerName"></p>

                    <div class="d-flex justify-content-center gap-2">
                        <button @click="extend(30)" :disabled="busy"
                                class="btn btn-outline-primary btn-sm">
                            <span x-show="!busy">+30m</span>
                            <span x-show="busy">…</span>
                        </button>
                        <button @click="stop()" :disabled="busy"
                                class="btn btn-outline-danger btn-sm">Stop</button>
                    </div>
                </div>

                {{-- Idle view --}}
                <div x-show="!hasTimer" class="py-2">
                    <i class="bi bi-circle-fill d-block mx-auto mb-2" style="color:var(--c);opacity:.35;font-size:1.6rem"></i>
                    <p class="text-muted small mb-3">No active session</p>
                    {{-- Bookable: links straight to the booking form with this court pre-selected --}}
                    <template x-if="!['maintenance','closed'].includes(status)">
                        <a :href="bookNewUrl" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Book Now
                        </a>
                    </template>
                    {{-- Maintenance / closed: not bookable --}}
                    <template x-if="['maintenance','closed'].includes(status)">
                        <button type="button" class="btn btn-secondary btn-sm disabled" disabled style="pointer-events:none">
                            <i class="bi bi-slash-circle me-1"></i>
                            <span x-text="status === 'closed' ? 'Closed' : 'Unavailable'"></span>
                        </button>
                    </template>
                </div>

                {{-- Next booking (server-rendered; refreshes on full reload) --}}
                @if($court->nextBookingToday)
                <div class="border-top mt-3 pt-3 text-start">
                    <p class="sb-next-label mb-1">
                        <i class="bi bi-calendar-event me-1"></i>Next Booking
                    </p>
                    <div class="d-flex justify-content-between align-items-center small">
                        <span class="fw-semibold">
                            {{ $court->nextBookingToday->start_time->format('g:i A') }}
                        </span>
                        <span class="text-muted">
                            {{ $court->nextBookingToday->customer?->name ?? 'Walk-in' }}
                        </span>
                    </div>
                    @if($court->nextBookingToday->end_time)
                    <div class="text-muted" style="font-size:.7rem">
                        Until {{ $court->nextBookingToday->end_time->format('g:i A') }}
                    </div>
                    @endif
                </div>
                @endif
            </div>

            {{-- Card footer --}}
            <div class="card-footer d-flex justify-content-between align-items-center">
                <span class="sb-foot-chip"><i class="bi bi-cash-coin me-1"></i>₱{{ number_format($court->base_hourly_rate) }}/hr</span>
                <span class="sb-foot-chip"><i class="bi bi-people me-1"></i>{{ $court->capacity }} players</span>
            </div>
        </div>
        </div>
        @endforeach
    </div>

    {{-- Overtime settlement modal — driven by 'open-overtime-modal' events
         dispatched from each courtCard's stop() handler. Blocks page until
         staff picks Pay or Void. --}}
    <div
        x-data="overtimeSettlementModal()"
        :class="open ? 'd-flex' : 'd-none'"
        @open-overtime-modal.window="show($event.detail)"
        style="position:fixed;inset:0;z-index:1080;align-items:center;justify-content:center;background:rgba(15,23,42,0.55);backdrop-filter:blur(2px)"
        role="alertdialog" aria-modal="true"
    >
        <div class="card shadow-lg" style="width:100%;max-width:480px;margin:1rem">
            <div class="card-body">
                <div class="d-flex align-items-start gap-2 mb-3">
                    <i class="bi bi-stopwatch text-danger fs-4"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Overtime Summary</h6>
                        <div class="small text-muted" x-text="courtName + ' · Booking #' + bookingNumber"></div>
                    </div>
                </div>

                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Total overtime</span>
                    <span class="fw-medium" x-text="(preview?.minutes || 0) + ' min'"></span>
                </div>

                <div class="border rounded mb-3" x-show="(preview?.segments || []).length">
                    <div class="px-3 py-2 bg-body-tertiary small text-uppercase fw-semibold text-muted">Breakdown</div>
                    <template x-for="seg in (preview?.segments || []).filter(s => s.seconds > 0)" :key="seg.tier + '-' + seg.seconds">
                        <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium" x-text="seg.label"></div>
                                <div class="small text-muted">
                                    <span x-text="seg.minutes"></span> min @ ₱<span x-text="Number(seg.rate).toFixed(2)"></span>/hr
                                </div>
                            </div>
                            <div class="fw-semibold">₱<span x-text="Number(seg.charge).toFixed(2)"></span></div>
                        </div>
                    </template>
                    <div class="px-3 py-2 border-top d-flex justify-content-between bg-danger-subtle">
                        <span class="fw-semibold">Final amount to pay</span>
                        <span class="fw-bold text-danger">₱<span x-text="Number(preview?.charge || 0).toFixed(2)"></span></span>
                    </div>
                </div>

                <p class="small text-muted">
                    Pay records a cash payment and adds it to today's revenue.
                    Void waives the fee — the overtime is still logged for the audit trail.
                    Pick one to close the session.
                </p>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            :disabled="busy"
                            @click="settle('void')">Void Overtime</button>
                    <button type="button" class="btn btn-success btn-sm"
                            :disabled="busy"
                            @click="settle('collect')">
                        Pay Overtime ₱<span x-text="Number(preview?.charge || 0).toFixed(2)"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const CSRF = '{{ csrf_token() }}';
const TENANT_ID = {{ (int) auth()->user()->tenant_id }};

// Shared cross-tab bus so the booking page and status board stay in sync
// without needing Pusher/WebSockets.
const TIMER_BUS = ('BroadcastChannel' in window) ? new BroadcastChannel('courtmaster-timer') : null;
function publishTimer(msg) { if (TIMER_BUS) TIMER_BUS.postMessage(msg); }

const TIMER_STATE_URL = @json(route('admin.courts.timer-state'));

function courtBoard() {
    return {
        pollInterval: null,
        lastSync: '—',
        init() {
            if (window.Echo) {
                window.Echo.channel(`tenant.${TENANT_ID}.courts`)
                    .listen('.court.status.changed', (e) => {
                        this.$dispatch('court-updated', e);
                    });
            }

            // Server-state polling — every 3 seconds. Catches missed BroadcastChannel
            // messages, cross-browser sessions, and any DOM caching weirdness.
            this.pollInterval = setInterval(() => this.poll(), 3000);
            this.poll();

            // Poll immediately when the tab becomes visible (user switched back
            // from the booking page after stopping a timer).
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.poll();
            });
        },
        async poll() {
            try {
                // Cache-bust the URL and explicitly bypass HTTP cache + service worker cache.
                const url = TIMER_STATE_URL + (TIMER_STATE_URL.includes('?') ? '&' : '?') + '_=' + Date.now();
                const r = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!r.ok) {
                    console.warn('timer-state poll failed', r.status);
                    return;
                }
                const data = await r.json();
                this.lastSync = new Date().toLocaleTimeString();
                window.dispatchEvent(new CustomEvent('timer-state', { detail: data.courts || {} }));
            } catch (e) {
                console.warn('timer-state poll error', e.message);
            }
        },
    };
}

function courtCard(cfg) {
    return {
        courtId:         cfg.courtId,
        timerId:         cfg.timerId,
        status:          cfg.initialStatus,
        scheduledEndMs:  cfg.scheduledEndMs || 0,
        hasTimer:        cfg.hasTimer,
        customerName:    cfg.customerName || '',
        bookNewUrl:      cfg.bookNewUrl,
        remainingSeconds: 0,
        isOvertime:      false,
        formattedTime:   '',
        interval:        null,
        busy:            false,

        init() {
            this.recompute();

            window.addEventListener('court-updated', (e) => {
                if (e.detail.court_id === this.courtId) this.status = e.detail.status;
            });

            // Server-authoritative reconciliation. Polled by courtBoard().
            window.addEventListener('timer-state', (e) => this.reconcile(e.detail));

            // Cross-tab sync: react to extend/stop actions taken on other pages.
            if (TIMER_BUS) {
                TIMER_BUS.addEventListener('message', (ev) => this.onBus(ev.data));
            }

            if (this.hasTimer) {
                // Tick from absolute timestamp — zero drift vs. any booking page using
                // the same scheduled_end_at.
                this.interval = setInterval(() => this.recompute(), 1000);
            }
        },

        reconcile(state) {
            const s = state[this.courtId];
            if (!s) return;

            // Timer disappeared (stopped elsewhere) → flip card to idle immediately.
            // No reload needed — Alpine swaps the x-if templates in place.
            if (this.hasTimer && (!s.has_timer || s.timer_id !== this.timerId)) {
                this.markIdle();
                // Fall through so status/etc still updates below.
            }

            // A new timer started elsewhere → reload to pull full booking info.
            if (!this.hasTimer && s.has_timer) {
                window.location.reload();
                return;
            }

            // Snap scheduledEndMs to server value if it drifted (e.g., extend happened on another browser).
            if (this.hasTimer && s.has_timer && s.scheduled_end_ms
                && Math.abs(s.scheduled_end_ms - this.scheduledEndMs) > 1500) {
                this.scheduledEndMs = s.scheduled_end_ms;
                this.recompute();
            }

            if (s.status && s.status !== this.status) this.status = s.status;
        },

        markIdle() {
            if (this.interval) { clearInterval(this.interval); this.interval = null; }
            this.hasTimer       = false;
            this.timerId        = null;
            this.scheduledEndMs = 0;
            this.isOvertime     = false;
            this.customerName   = '';
            this.status         = 'available';
            this.formattedTime  = '00:00:00';
            console.log('[courtCard] marked idle for court', this.courtId);
            // Belt-and-braces: if Alpine x-show doesn't visibly flip for any reason,
            // a delayed reload guarantees the user sees the fresh state.
            setTimeout(() => window.location.reload(), 800);
        },

        onBus(msg) {
            if (!msg) return;
            const isMine = (msg.timerId && msg.timerId === this.timerId)
                        || (msg.courtId && msg.courtId === this.courtId);
            if (!isMine) return;

            if (msg.type === 'extend' && msg.minutes) {
                this.scheduledEndMs += msg.minutes * 60 * 1000;
                this.recompute();
            } else if (msg.type === 'stop') {
                // Flip to idle immediately — no reload (which can race with browser caching).
                this.markIdle();
            }
        },

        recompute() {
            const now = Date.now();
            this.remainingSeconds = Math.max(0, Math.floor((this.scheduledEndMs - now) / 1000));
            this.isOvertime = this.hasTimer && this.remainingSeconds <= 0;

            const r = this.remainingSeconds;
            const h = Math.floor(r / 3600);
            const m = Math.floor((r % 3600) / 60);
            const s = r % 60;
            this.formattedTime =
                String(h).padStart(2,'0') + ':' +
                String(m).padStart(2,'0') + ':' +
                String(s).padStart(2,'0');
        },

        async extend(minutes) {
            if (this.busy || !this.timerId) return;
            this.busy = true;
            try {
                const r = await fetch(`${window.APP_BASE}/admin/timers/${this.timerId}/extend`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body: JSON.stringify({ minutes }),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) {
                    alert(d.message || `Extend failed (${r.status})`);
                    return;
                }
                this.scheduledEndMs += minutes * 60 * 1000;
                this.recompute();
                publishTimer({ type: 'extend', timerId: this.timerId, courtId: this.courtId, minutes });
            } catch (e) {
                alert('Network error: ' + e.message);
            } finally {
                this.busy = false;
            }
        },

        async stop(settlement = null) {
            if (this.busy || !this.timerId) return;
            // Only ask for the simple confirm on the FIRST tap — the
            // settlement modal is the confirmation when overtime is owed.
            if (!settlement && !confirm('Stop this session?')) return;
            this.busy = true;
            try {
                const body = settlement ? JSON.stringify({ settlement }) : undefined;
                const r = await fetch(`${window.APP_BASE}/admin/timers/${this.timerId}/stop`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': CSRF,
                    },
                    body,
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok) {
                    alert(d.message || `Stop failed (${r.status})`);
                    this.busy = false;
                    return;
                }

                // Overtime owed and no decision yet — pop the settlement modal.
                if (d.requires_settlement) {
                    this.busy = false;
                    window.dispatchEvent(new CustomEvent('open-overtime-modal', {
                        detail: {
                            timerId:       this.timerId,
                            courtId:       this.courtId,
                            preview:       d.overtime,
                            bookingNumber: d.booking_number,
                            courtName:     d.court_name,
                            onSettle:      (s) => this.stop(s),
                        },
                    }));
                    return;
                }

                if (this.interval) clearInterval(this.interval);
                publishTimer({ type: 'stop', timerId: this.timerId, courtId: this.courtId });
                window.location.reload();
            } catch (e) {
                alert('Network error: ' + e.message);
                this.busy = false;
            }
        },
    };
}

function overtimeSettlementModal() {
    return {
        open: false,
        busy: false,
        preview: null,
        bookingNumber: '',
        courtName: '',
        onSettle: null,

        show(detail) {
            this.preview       = detail.preview;
            this.bookingNumber = detail.bookingNumber || '';
            this.courtName     = detail.courtName || '';
            this.onSettle      = typeof detail.onSettle === 'function' ? detail.onSettle : null;
            this.open          = true;
        },
        async settle(choice) {
            if (this.busy || !this.onSettle) return;
            if (choice === 'void') {
                const amt = Number(this.preview?.charge || 0).toFixed(2);
                if (!confirm(`Waive ₱${amt} of overtime and close the session?`)) return;
            }
            this.busy = true;
            try {
                await this.onSettle(choice);
                // Caller reloads the page on success; we just close defensively.
                this.open = false;
            } finally {
                this.busy = false;
            }
        },
    };
}
</script>
@endpush
