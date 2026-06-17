<div
    wire:poll.15s="refreshBoard"
    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
>
    @forelse ($this->courts as $court)
        @php
            $activeBooking = $court->bookings->first();
            $timer = $activeBooking?->timer;
            $statusColor = match ($court->status) {
                'available' => 'bg-emerald-500',
                'occupied' => 'bg-rose-500',
                'reserved' => 'bg-amber-500',
                'maintenance' => 'bg-slate-500',
                'closed' => 'bg-zinc-700',
                default => 'bg-slate-400',
            };
        @endphp

        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">
                        {{ $court->name }}
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ ucfirst($court->type ?? 'court') }}
                    </p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-white px-2.5 py-1 rounded-full {{ $statusColor }}">
                    <span class="size-2 rounded-full bg-white/80 animate-pulse"></span>
                    {{ ucfirst($court->status) }}
                </span>
            </div>

            <div class="p-4 space-y-3 min-h-[120px]">
                @if ($activeBooking)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Player</span>
                        <span class="font-medium text-slate-900 dark:text-slate-100">
                            {{ $activeBooking->customer?->name ?? 'Walk-in' }}
                        </span>
                    </div>

                    @if ($timer)
                        <div class="flex items-center justify-between text-sm" wire:key="timer-wrap-{{ $timer->id }}">
                            <span class="text-slate-500 dark:text-slate-400">Time</span>
                            <span
                                wire:key="timer-{{ $timer->id }}-{{ $timer->status }}-{{ $timer->scheduled_end_at?->getTimestamp() }}"
                                class="font-mono font-semibold {{ $timer->isOvertime() ? 'text-rose-500' : 'text-emerald-500' }}"
                                x-data="timerTick({{ $timer->scheduled_end_at?->getTimestamp() * 1000 ?? 0 }}, {{ (int) ($timer->isRunning() || $timer->isOvertime()) }}, {{ (int) $timer->remaining_seconds }})"
                                x-text="format()"
                            >
                                {{ gmdate('H:i:s', max(0, $timer->remaining_seconds)) }}
                            </span>
                        </div>

                        @if ($timer->isOvertime())
                            <div class="text-xs text-rose-500 font-medium">
                                Overtime · ₱{{ number_format($timer->overtime_charge ?? 0, 2) }}
                            </div>
                        @endif
                    @endif
                @else
                    <div class="flex items-center justify-center h-full text-sm text-slate-400 dark:text-slate-500 italic">
                        No active session
                    </div>
                @endif

                @if ($court->nextBookingToday)
                    <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate-500 dark:text-slate-400">Next</span>
                            <span class="font-medium text-slate-700 dark:text-slate-300">
                                {{ $court->nextBookingToday->start_time->format('g:i A') }}
                                @if($court->nextBookingToday->end_time)
                                    <span class="text-slate-400 dark:text-slate-500 font-normal">– {{ $court->nextBookingToday->end_time->format('g:i A') }}</span>
                                @endif
                            </span>
                        </div>
                        <div class="flex justify-end text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                            {{ $court->nextBookingToday->customer?->name ?? 'Walk-in' }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="col-span-full p-10 text-center text-slate-500 dark:text-slate-400">
            No courts found.
        </div>
    @endforelse

    @push('scripts')
        <script>
            // Drift-free countdown: derive remaining from the absolute scheduled-end
            // timestamp + the device clock on every tick, instead of decrementing a
            // local counter. Background-tab throttling and the 15s wire:poll can no
            // longer make the number drift or jump — every render computes the same
            // continuous value. Paused timers freeze (no tick).
            function timerTick(endMs, running, staticRemaining) {
                return {
                    endMs: endMs,
                    running: !!running,
                    remaining: running ? Math.round((endMs - Date.now()) / 1000) : staticRemaining,
                    interval: null,
                    init() {
                        if (this.running) {
                            this.tick();
                            // 250ms tick so the displayed second updates right at the
                            // boundary (setInterval jitter at 1s can skip/hold a second).
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
                        const r = Math.max(0, this.remaining);
                        const h = String(Math.floor(r / 3600)).padStart(2, '0');
                        const m = String(Math.floor((r % 3600) / 60)).padStart(2, '0');
                        const s = String(r % 60).padStart(2, '0');
                        return `${h}:${m}:${s}`;
                    },
                };
            }
        </script>
    @endpush
</div>
