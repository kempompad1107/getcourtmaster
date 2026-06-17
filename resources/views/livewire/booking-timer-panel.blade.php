<div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ $booking->court->name }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Booking #{{ $booking->booking_number }}
            </p>
        </div>
        @if ($booking->timer)
            <span @class([
                'px-2.5 py-1 rounded-full text-xs font-medium',
                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' => $booking->timer->isRunning(),
                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' => $booking->timer->isPaused(),
                'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' => $booking->timer->isOvertime(),
                'bg-slate-100 text-slate-700 dark:bg-slate-900/30 dark:text-slate-300' => !$booking->timer->isRunning() && !$booking->timer->isPaused() && !$booking->timer->isOvertime(),
            ])>
                {{ ucfirst($booking->timer->status) }}
            </span>
        @endif
    </div>

    @if ($booking->timer)
        <div class="flex justify-center my-4">
            <div
                wire:key="timer-display-{{ $booking->timer->id }}-{{ $booking->timer->status }}-{{ $booking->timer->scheduled_end_at?->getTimestamp() }}"
                x-data="timerCountdown({{ $booking->timer->scheduled_end_at?->getTimestamp() * 1000 ?? 0 }}, {{ (int) ($booking->timer->isRunning() || $booking->timer->isOvertime()) }}, {{ (int) $booking->timer->remaining_seconds }})"
                x-text="format()"
                class="font-mono text-5xl font-bold tabular-nums {{ $booking->timer->isOvertime() ? 'text-rose-500' : 'text-emerald-500' }}"
            >
                {{ gmdate('H:i:s', max(0, $booking->timer->remaining_seconds)) }}
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 text-sm">
            <div class="text-slate-500 dark:text-slate-400">Started</div>
            <div class="text-right font-medium text-slate-900 dark:text-slate-100">
                {{ $booking->timer->started_at?->format('H:i:s') }}
            </div>

            <div class="text-slate-500 dark:text-slate-400">Scheduled end</div>
            <div class="text-right font-medium text-slate-900 dark:text-slate-100">
                {{ $booking->timer->scheduled_end_at?->format('H:i:s') }}
            </div>

            @if ($booking->timer->isInGracePeriod())
                <div class="text-slate-500 dark:text-slate-400">Grace period</div>
                <div class="text-right font-semibold text-amber-500">
                    {{ gmdate('i:s', $booking->timer->grace_seconds_remaining) }} free
                </div>
            @endif

            @if ($booking->timer->isOvertime())
                <div class="text-slate-500 dark:text-slate-400">Overtime charge</div>
                <div class="text-right font-semibold text-rose-500">
                    ₱{{ number_format($booking->timer->overtime_charge ?? 0, 2) }}
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-2 pt-2">
            @if ($booking->timer->isRunning())
                <button wire:click="pause" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium">Pause</button>
            @elseif ($booking->timer->isPaused())
                <button wire:click="resume" class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium">Resume</button>
            @endif
            <button wire:click="extend(15)" class="px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-900 dark:text-slate-100 text-sm font-medium">+15 min</button>
            <button wire:click="extend(30)" class="px-4 py-2 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-900 dark:text-slate-100 text-sm font-medium">+30 min</button>
            <button
                wire:click="stop"
                wire:confirm="Stop the timer and complete this booking?"
                class="px-4 py-2 rounded-lg bg-rose-500 hover:bg-rose-600 text-white text-sm font-medium ml-auto"
            >Stop</button>
        </div>

        @if (!empty($pendingOvertime))
            @php
                $segments = $pendingOvertime['segments'] ?? [];
            @endphp
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="overtime-settle-title"
                wire:key="overtime-modal-{{ $booking->id }}"
            >
                <div class="w-full max-w-md mx-4 rounded-2xl bg-white dark:bg-slate-800 border border-rose-300 dark:border-rose-700 shadow-xl">
                    <div class="p-6 space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="size-10 rounded-full bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center shrink-0">
                                <i class="bi bi-stopwatch text-rose-500"></i>
                            </div>
                            <div class="flex-1">
                                <h4 id="overtime-settle-title" class="text-base font-semibold text-slate-900 dark:text-slate-100">
                                    Overtime Summary
                                </h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $booking->court->name }} · Booking #{{ $booking->booking_number }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-y-2 text-sm">
                            <div class="text-slate-500 dark:text-slate-400">Total overtime</div>
                            <div class="text-right font-medium text-slate-900 dark:text-slate-100">
                                {{ $pendingOvertime['minutes'] }} min
                            </div>
                        </div>

                        @if (!empty($segments))
                            <div class="rounded-lg border border-slate-200 dark:border-slate-700 divide-y divide-slate-200 dark:divide-slate-700">
                                <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/30">
                                    Breakdown
                                </div>
                                @foreach ($segments as $seg)
                                    @if (($seg['seconds'] ?? 0) > 0)
                                        <div class="px-3 py-2 flex items-center justify-between text-sm">
                                            <div>
                                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                                    {{ $seg['label'] }}
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $seg['minutes'] }} min @ ₱{{ number_format($seg['rate'], 2) }}/hr
                                                </div>
                                            </div>
                                            <div class="font-semibold text-slate-900 dark:text-slate-100">
                                                ₱{{ number_format($seg['charge'], 2) }}
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                                <div class="px-3 py-2 flex items-center justify-between bg-rose-50 dark:bg-rose-900/20">
                                    <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        Final amount to pay
                                    </div>
                                    <div class="text-base font-bold text-rose-600 dark:text-rose-300">
                                        ₱{{ number_format($pendingOvertime['charge'], 2) }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Pay records a cash payment and adds it to today's revenue.
                            Void waives the fee — the overtime is still logged for the audit trail.
                            You must pick one to close the session.
                        </p>

                        <div class="flex justify-end gap-2 pt-1">
                            <button
                                type="button"
                                wire:click="voidOvertime"
                                wire:confirm="Waive ₱{{ number_format($pendingOvertime['charge'], 2) }} of overtime and close the session?"
                                wire:loading.attr="disabled"
                                class="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-medium disabled:opacity-50"
                            >
                                Void Overtime
                            </button>
                            <button
                                type="button"
                                wire:click="collectOvertime"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium disabled:opacity-50"
                            >
                                Pay Overtime ₱{{ number_format($pendingOvertime['charge'], 2) }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="text-center py-6">
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">Timer has not started yet.</p>
            <button
                wire:click="start"
                class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-medium"
            >Start Timer</button>
        </div>
    @endif

    @push('scripts')
        <script>
            // Drift-free: compute remaining from the absolute scheduled-end timestamp
            // and the device clock each tick (no local-counter drift / poll jumps).
            function timerCountdown(endMs, running, staticRemaining) {
                return {
                    endMs: endMs,
                    remaining: running ? Math.round((endMs - Date.now()) / 1000) : staticRemaining,
                    running: !!running,
                    interval: null,
                    init() {
                        if (this.running) {
                            this.tick();
                            // 250ms tick — see court-status-board: avoids 1s setInterval
                            // jitter making the countdown skip/hold a second.
                            this.interval = setInterval(() => this.tick(), 250);
                        }
                    },
                    tick() {
                        this.remaining = Math.round((this.endMs - Date.now()) / 1000);
                    },
                    destroy() {
                        clearInterval(this.interval);
                    },
                    format() {
                        const r = this.remaining;
                        const sign = r < 0 ? '-' : '';
                        const a = Math.abs(r);
                        const h = String(Math.floor(a / 3600)).padStart(2, '0');
                        const m = String(Math.floor((a % 3600) / 60)).padStart(2, '0');
                        const s = String(a % 60).padStart(2, '0');
                        return `${sign}${h}:${m}:${s}`;
                    },
                };
            }
        </script>
    @endpush
</div>
