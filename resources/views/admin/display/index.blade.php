<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="tenant-id" content="{{ $tenant->id }}">
    <title>{{ $tenant->name }} — Court Status</title>
    @vite(['resources/scss/app.scss'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700;12..96,800&family=JetBrains+Mono:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-0: #070b16;
            --bg-1: #0b1120;
            --panel: rgba(22, 31, 48, .62);
            --panel-brd: rgba(148, 163, 184, .14);
            --ink: #f1f5f9;
            --ink-dim: #8ca0bd;
            --ink-faint: #5d6f8c;
            --emerald: #34d399;
            --emerald-deep: #10b981;
            --font-display: 'Bricolage Grotesque', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, monospace;
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            font-family: var(--font-display);
            color: var(--ink);
            background:
                radial-gradient(120% 90% at 12% -10%, rgba(16,185,129,.16) 0%, transparent 42%),
                radial-gradient(120% 90% at 100% 0%, rgba(56,189,248,.10) 0%, transparent 45%),
                linear-gradient(180deg, var(--bg-1) 0%, var(--bg-0) 100%);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
            position: relative;
            overflow-x: hidden;
        }

        /* faint grid + grain atmosphere */
        body::before {
            content: "";
            position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background-image:
                linear-gradient(rgba(148,163,184,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,.045) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(130% 90% at 50% 0%, #000 35%, transparent 80%);
        }
        body > * { position: relative; z-index: 1; }

        .board { padding: 2.4rem clamp(1.25rem, 3.5vw, 3.5rem); }

        /* ── Header ─────────────────────────────────────────── */
        .board-head {
            display: flex; align-items: flex-end; justify-content: space-between;
            gap: 2rem; flex-wrap: wrap; margin-bottom: 2.2rem;
        }
        .brand-eyebrow {
            display: inline-flex; align-items: center; gap: .55rem;
            font-family: var(--font-mono); font-size: .72rem; font-weight: 600;
            letter-spacing: .32em; text-transform: uppercase; color: var(--emerald);
            margin: 0 0 .55rem;
        }
        .live-dot {
            width: 8px; height: 8px; border-radius: 50%; background: var(--emerald);
            box-shadow: 0 0 0 0 rgba(52,211,153,.7); animation: livePulse 2s infinite;
        }
        @keyframes livePulse {
            0%   { box-shadow: 0 0 0 0 rgba(52,211,153,.55); }
            70%  { box-shadow: 0 0 0 9px rgba(52,211,153,0); }
            100% { box-shadow: 0 0 0 0 rgba(52,211,153,0); }
        }
        .brand-name {
            font-size: clamp(2rem, 4.4vw, 3.6rem); font-weight: 800; line-height: .98;
            letter-spacing: -.02em; margin: 0;
            background: linear-gradient(96deg, #ffffff 0%, #cde9dc 55%, var(--emerald) 100%);
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }
        .head-right { text-align: right; }
        .clock {
            font-family: var(--font-mono); font-weight: 700; font-variant-numeric: tabular-nums;
            font-size: clamp(2rem, 4vw, 3.1rem); line-height: 1; letter-spacing: -.01em;
            margin: 0; color: var(--ink); text-shadow: 0 0 26px rgba(56,189,248,.18);
        }
        .clock-date {
            font-family: var(--font-mono); font-size: .8rem; letter-spacing: .14em;
            text-transform: uppercase; color: var(--ink-dim); margin: .45rem 0 0;
        }

        /* ── Summary strip ──────────────────────────────────── */
        .summary {
            display: flex; flex-wrap: wrap; gap: .65rem; margin-bottom: 2rem;
        }
        .stat {
            display: flex; align-items: center; gap: .7rem;
            padding: .6rem 1.1rem .6rem .8rem; border-radius: 999px;
            background: var(--panel); border: 1px solid var(--panel-brd);
            backdrop-filter: blur(8px);
        }
        .stat-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 12px var(--accent); }
        .stat-num { font-family: var(--font-mono); font-weight: 700; font-size: 1.15rem; line-height: 1; }
        .stat-lbl { font-family: var(--font-mono); font-size: .68rem; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-dim); }

        /* ── Grid ───────────────────────────────────────────── */
        .court-grid {
            display: grid; gap: 1.25rem;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }
        /* On wide / full-screen computer displays, lock to a clean 4-up layout
           so cards stay large instead of shrinking into 5–6 narrow columns. */
        @media (min-width: 1200px) {
            .court-grid { grid-template-columns: repeat(4, 1fr); }
        }

        /* status accent tokens */
        .s-available   { --accent: #34d399; --accent-rgb: 52,211,153; }
        .s-occupied    { --accent: #fb7185; --accent-rgb: 251,113,133; }
        .s-reserved    { --accent: #fbbf24; --accent-rgb: 251,191,36; }
        .s-maintenance { --accent: #94a3b8; --accent-rgb: 148,163,184; }

        /* ── Court card ─────────────────────────────────────── */
        .court-card {
            position: relative; overflow: hidden;
            border-radius: 22px; padding: 1.5rem 1.5rem 1.35rem;
            background:
                linear-gradient(180deg, rgba(var(--accent-rgb), .07) 0%, rgba(var(--accent-rgb), 0) 38%),
                var(--panel);
            border: 1px solid rgba(var(--accent-rgb), .26);
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 40px -24px rgba(0,0,0,.8), inset 0 1px 0 rgba(255,255,255,.04);
            transition: border-color .5s, box-shadow .5s, transform .25s;
            animation: cardIn .55s cubic-bezier(.22,1,.36,1) both;
        }
        @keyframes cardIn { from { opacity: 0; transform: translateY(14px) scale(.985); } }
        .court-card::before {
            content: ""; position: absolute; inset: 0 0 auto 0; height: 3px;
            background: linear-gradient(90deg, var(--accent), rgba(var(--accent-rgb), .15));
        }
        .court-card::after {
            content: ""; position: absolute; top: -60%; right: -30%;
            width: 70%; height: 160%; pointer-events: none;
            background: radial-gradient(circle, rgba(var(--accent-rgb), .14) 0%, transparent 65%);
        }

        .cc-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-bottom: 1.1rem; }
        .cc-name { font-size: 1.3rem; font-weight: 700; letter-spacing: -.01em; margin: 0; }
        .chip {
            display: inline-flex; align-items: center; gap: .45rem;
            padding: .32rem .72rem; border-radius: 999px;
            font-family: var(--font-mono); font-size: .66rem; font-weight: 600;
            letter-spacing: .12em; text-transform: uppercase;
            color: var(--accent);
            background: rgba(var(--accent-rgb), .12);
            border: 1px solid rgba(var(--accent-rgb), .3);
            white-space: nowrap;
        }
        .chip-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
        .chip-dot.is-live { animation: blink 1.1s step-start infinite; }

        /* timer */
        .timer-wrap { text-align: center; padding: .6rem 0 .2rem; }
        .timer-display {
            font-family: var(--font-mono); font-weight: 800; font-variant-numeric: tabular-nums;
            font-size: clamp(2.6rem, 6vw, 3.5rem); line-height: 1; letter-spacing: -.02em;
            color: var(--ink); text-shadow: 0 0 30px rgba(var(--accent-rgb), .35); margin: 0;
        }
        .timer-display.text-danger { color: #fb7185 !important; text-shadow: 0 0 30px rgba(251,113,133,.5); }
        .timer-label {
            font-family: var(--font-mono); font-size: .68rem; letter-spacing: .26em;
            text-transform: uppercase; color: var(--ink-dim); margin: .8rem 0 0;
        }
        .timer-customer { font-weight: 600; font-size: 1.02rem; margin: .55rem 0 0; color: var(--ink); }

        /* idle state */
        .idle-state { text-align: center; padding: 1.1rem 0 .6rem; }
        .idle-ico {
            width: 64px; height: 64px; margin: 0 auto .75rem; border-radius: 18px;
            display: grid; place-items: center; font-size: 1.9rem; color: var(--accent);
            background: rgba(var(--accent-rgb), .1); border: 1px solid rgba(var(--accent-rgb), .24);
        }
        .idle-label { font-weight: 600; font-size: 1.05rem; margin: 0; color: var(--ink); }
        .idle-sub { font-family: var(--font-mono); font-size: .7rem; letter-spacing: .16em; text-transform: uppercase; color: var(--ink-dim); margin: .35rem 0 0; }

        /* next booking footer */
        .cc-next {
            margin-top: 1.25rem; padding-top: 1rem;
            border-top: 1px solid rgba(148,163,184,.13);
            display: flex; align-items: center; justify-content: space-between; gap: .75rem;
        }
        .cc-next-label { font-family: var(--font-mono); font-size: .62rem; letter-spacing: .18em; text-transform: uppercase; color: var(--ink-faint); }
        .cc-next-time { font-family: var(--font-mono); font-weight: 600; font-size: .9rem; color: var(--emerald); }
        .cc-next-who { font-size: .85rem; color: var(--ink-dim); }

        /* ── Alert states (JS toggles these class names) ────── */
        .court-card-ending {
            border-color: #fbbf24 !important;
            box-shadow: 0 0 0 1px rgba(251,191,36,.5), 0 0 38px rgba(251,191,36,.28), 0 18px 40px -24px rgba(0,0,0,.8) !important;
        }
        .court-card-elapsed {
            border-color: #fb7185 !important;
            animation: elapsedPulse 1.4s ease-in-out infinite;
        }
        @keyframes elapsedPulse {
            0%, 100% { box-shadow: 0 0 0 1px rgba(244,63,94,.55), 0 0 34px rgba(244,63,94,.3); }
            50%      { box-shadow: 0 0 0 2px rgba(244,63,94,.8), 0 0 56px rgba(244,63,94,.55); }
        }
        .court-alert-badge {
            position: absolute; top: 0; left: 50%; transform: translate(-50%, -45%); z-index: 3;
            padding: .3rem .85rem; border-radius: 999px;
            font-family: var(--font-mono); font-size: .64rem; font-weight: 700;
            letter-spacing: .14em; text-transform: uppercase; white-space: nowrap;
            box-shadow: 0 6px 18px -6px rgba(0,0,0,.7);
        }
        .court-alert-badge-ending  { background: #fbbf24; color: #1a1205; }
        .court-alert-badge-elapsed { background: #f43f5e; color: #fff; animation: blink 1s step-start infinite; }

        .blink { animation: blink 1s step-start infinite; }
        @keyframes blink { 50% { opacity: .25; } }

        .empty {
            text-align: center; padding: 5rem 1rem; color: var(--ink-dim);
            font-family: var(--font-mono); letter-spacing: .12em; text-transform: uppercase;
        }

        @media (prefers-reduced-motion: reduce) {
            .court-card, .live-dot, .court-card-elapsed, .blink, .chip-dot.is-live { animation: none !important; }
        }
    </style>
</head>
<body>
@php
    $byStatus = $courts->groupBy('status');
    $summary = [
        ['key' => 'available',   'label' => 'Available',   'cls' => 's-available'],
        ['key' => 'occupied',    'label' => 'In Play',     'cls' => 's-occupied'],
        ['key' => 'reserved',    'label' => 'Reserved',    'cls' => 's-reserved'],
        ['key' => 'maintenance', 'label' => 'Out',         'cls' => 's-maintenance'],
    ];
@endphp

    <div class="board">
        <header class="board-head">
            <div>
                <p class="brand-eyebrow"><span class="live-dot"></span> Live Court Status</p>
                <h1 class="brand-name">{{ $tenant->name }}</h1>
            </div>
            <div class="head-right">
                <p id="clock" class="clock">--:--:--</p>
                <p class="clock-date">{{ now()->format('l · F j, Y') }}</p>
            </div>
        </header>

        <div class="summary">
            @foreach($summary as $s)
            <div class="stat {{ $s['cls'] }}">
                <span class="stat-dot"></span>
                <span class="stat-num">{{ optional($byStatus->get($s['key']))->count() ?? 0 }}</span>
                <span class="stat-lbl">{{ $s['label'] }}</span>
            </div>
            @endforeach
        </div>

        <div class="court-grid">
            @forelse($courts as $court)
            @php
                $statusClass = match($court->status) {
                    'available'   => 's-available',
                    'occupied'    => 's-occupied',
                    'reserved'    => 's-reserved',
                    default       => 's-maintenance',
                };
                $idleIcon = match($court->status) {
                    'available'   => 'bi-check2-circle',
                    'reserved'    => 'bi-calendar2-check',
                    'maintenance' => 'bi-cone-striped',
                    default       => 'bi-slash-circle',
                };
                $nextBooking = $court->nextBookingToday;

                $remaining    = $court->activeTimer?->remaining_seconds;
                $isOvertime   = $court->activeTimer?->isOvertime() ?? false;
                $isEndingSoon = $court->activeTimer && !$isOvertime && $remaining !== null && $remaining > 0 && $remaining <= 300;
                $isElapsed    = $court->activeTimer && ($isOvertime || ($remaining !== null && $remaining <= 0));
                $alertClass   = $isElapsed ? 'court-card-elapsed' : ($isEndingSoon ? 'court-card-ending' : '');
            @endphp
            <div class="court-card {{ $statusClass }} {{ $alertClass }}"
                 style="animation-delay: {{ min($loop->index * 60, 600) }}ms"
                 data-alert-state="{{ $isElapsed ? 'elapsed' : ($isEndingSoon ? 'ending' : 'normal') }}">
                @if ($isElapsed)
                    <span class="court-alert-badge court-alert-badge-elapsed">⚠ Time Elapsed</span>
                @elseif ($isEndingSoon)
                    <span class="court-alert-badge court-alert-badge-ending">Ending Soon</span>
                @endif

                <div class="cc-head">
                    <h2 class="cc-name">{{ $court->name }}</h2>
                    <span class="chip">
                        <span class="chip-dot {{ $court->status === 'occupied' ? 'is-live' : '' }}"></span>
                        {{ $court->status }}
                    </span>
                </div>

                @if($court->activeTimer)
                    <div class="timer-wrap">
                        <p id="timer-{{ $court->id }}"
                           class="timer-display {{ $court->activeTimer->isOvertime() ? 'text-danger' : '' }}"
                           data-remaining="{{ $court->activeTimer->remaining_seconds }}"
                           data-end="{{ $court->activeTimer->scheduled_end_at?->getTimestamp() * 1000 ?? 0 }}"
                           data-overtime="{{ $court->activeTimer->isOvertime() ? '1' : '0' }}">
                            {{ gmdate('H:i:s', max(0, (int) $court->activeTimer->remaining_seconds)) }}
                        </p>
                        <p class="timer-label">{{ $court->activeTimer->isOvertime() ? '⚠ Overtime' : 'Time Remaining' }}</p>
                        @if(($showNames ?? false) && $court->activeTimer->booking)
                            <p class="timer-customer">{{ $court->activeTimer->booking->customer->name }}</p>
                        @endif
                    </div>
                @else
                    <div class="idle-state">
                        <div class="idle-ico"><i class="bi {{ $idleIcon }}"></i></div>
                        @if($court->status === 'available')
                            <p class="idle-label">Ready to Play</p>
                            <p class="idle-sub">Open Court</p>
                        @else
                            <p class="idle-label">{{ ucfirst($court->status) }}</p>
                            <p class="idle-sub">Unavailable</p>
                        @endif
                    </div>
                @endif

                @if($nextBooking)
                    <div class="cc-next">
                        <div>
                            <span class="cc-next-label">Next</span>
                            <div class="cc-next-time">
                                {{ $nextBooking->start_time->format('g:i A') }}@if($nextBooking->end_time) – {{ $nextBooking->end_time->format('g:i A') }}@endif
                            </div>
                        </div>
                        <span class="cc-next-who">{{ ($showNames ?? false) ? ($nextBooking->customer?->name ?? 'Walk-in') : 'Reserved' }}</span>
                    </div>
                @endif
            </div>
            @empty
            <div class="empty" style="grid-column: 1 / -1;">No active courts to display</div>
            @endforelse
        </div>
    </div>

    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent =
                now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        function playAlertBeep(isElapsed) {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return;
                const ctx = new Ctx();
                const beeps = isElapsed ? 3 : 1;
                for (let i = 0; i < beeps; i++) {
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.type = 'sine';
                    osc.frequency.value = isElapsed ? 880 : 660;
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    const t = ctx.currentTime + i * 0.45;
                    gain.gain.setValueAtTime(0.0001, t);
                    gain.gain.exponentialRampToValueAtTime(0.3, t + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.3);
                    osc.start(t);
                    osc.stop(t + 0.32);
                }
            } catch (_) {
                // Browser may block audio until first user interaction.
            }
        }

        // Beep once on page load if any court is already in an alert state.
        document.querySelectorAll('.court-card[data-alert-state]').forEach(card => {
            const state = card.dataset.alertState;
            if (state === 'elapsed') playAlertBeep(true);
            else if (state === 'ending') playAlertBeep(false);
        });

        document.querySelectorAll('[id^="timer-"]').forEach(el => {
            let seconds = parseInt(el.dataset.remaining);
            const endMs = parseInt(el.dataset.end) || 0;   // absolute scheduled end (drift-free)
            let isOvertime = el.dataset.overtime === '1';
            const card = el.closest('.court-card');
            let endingFired  = card?.dataset.alertState === 'ending'  || card?.dataset.alertState === 'elapsed';
            let elapsedFired = card?.dataset.alertState === 'elapsed';

            // If the server already says this timer is in overtime, freeze the display at 00:00:00.
            if (isOvertime) {
                seconds = 0;
                el.textContent = '00:00:00';
                el.classList.add('text-danger');
            }

            setInterval(() => {
                if (isOvertime) {
                    return; // Frozen at 00:00:00 once elapsed.
                }
                // Derive from the absolute end timestamp so the display never drifts
                // (background-tab throttling can't accumulate error); fall back to a
                // local decrement only if no end timestamp was provided.
                seconds = endMs ? Math.round((endMs - Date.now()) / 1000) : (seconds - 1);
                if (!endingFired && seconds > 0 && seconds <= 300) {
                    endingFired = true;
                    if (card && !card.classList.contains('court-card-elapsed')) {
                        card.classList.add('court-card-ending');
                        card.dataset.alertState = 'ending';
                        if (!card.querySelector('.court-alert-badge')) {
                            const badge = document.createElement('span');
                            badge.className = 'court-alert-badge court-alert-badge-ending';
                            badge.textContent = 'Ending Soon';
                            card.prepend(badge);
                        }
                    }
                    playAlertBeep(false);
                }
                if (seconds <= 0) {
                    isOvertime = true;
                    seconds = 0;
                    el.classList.add('text-danger');
                    el.textContent = '00:00:00';
                    if (!elapsedFired) {
                        elapsedFired = true;
                        if (card) {
                            card.classList.remove('court-card-ending');
                            card.classList.add('court-card-elapsed');
                            card.dataset.alertState = 'elapsed';
                            const existing = card.querySelector('.court-alert-badge');
                            if (existing) existing.remove();
                            const badge = document.createElement('span');
                            badge.className = 'court-alert-badge court-alert-badge-elapsed';
                            badge.textContent = '⚠ Time Elapsed';
                            card.prepend(badge);
                        }
                        playAlertBeep(true);
                    }
                    return;
                }
                const h = Math.floor(seconds / 3600);
                const m = Math.floor((seconds % 3600) / 60);
                const s = seconds % 60;
                el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
            }, 1000);
        });
    </script>
</body>
</html>
