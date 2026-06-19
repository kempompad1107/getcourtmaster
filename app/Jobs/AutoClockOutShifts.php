<?php

namespace App\Jobs;

use App\Models\Shift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoClockOutShifts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Shift::where('status', 'active')
            ->whereNotNull('clocked_in_at')
            ->whereNull('clocked_out_at')
            ->with('tenant')
            ->each(function (Shift $shift) {
                // Owners can disable auto-clock-out tenant-wide (Settings → Booking).
                if (! $shift->tenant?->getSetting('shift_auto_clockout', true)) {
                    return;
                }

                // Cap at the shift's scheduled end — NOT now(). The sweep may run
                // late (schedule:run isn't guaranteed every minute) and stamping
                // now() would record the lateness as worked time (e.g. 8.2h). A
                // standard 8h shift ends at exactly 8h; an owner who schedules a
                // longer shift gets that length honoured (the per-shift bypass).
                $end = $shift->scheduledEndAt();
                if (! $end || $end->isFuture()) {
                    return;
                }

                $shift->update([
                    'clocked_out_at' => $end,
                    'status'         => 'completed',
                    'notes'          => trim(($shift->notes ? $shift->notes . "\n" : '') . 'Auto clocked out at scheduled end.'),
                ]);
            });
    }
}
