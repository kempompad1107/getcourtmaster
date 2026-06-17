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
            ->where('clocked_in_at', '<=', now()->subHours(8))
            ->each(function (Shift $shift) {
                $shift->update([
                    'clocked_out_at' => now(),
                    'status'         => 'completed',
                    'notes'          => trim(($shift->notes ? $shift->notes . "\n" : '') . 'Auto clocked out after 8 hours.'),
                ]);
            });
    }
}
