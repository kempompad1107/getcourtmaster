<?php

namespace App\Jobs;

use App\Events\TimerUpdated;
use App\Models\BookingTimer;
use App\Models\User;
use App\Notifications\CourtTimeElapsedNotification;
use App\Services\BookingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckOvertimeTimers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BookingService $bookingService): void
    {
        BookingTimer::with('booking.tenant', 'booking.court', 'booking.customer', 'court')
            ->whereIn('status', ['running', 'overtime'])
            ->where('scheduled_end_at', '<=', now())
            ->each(function (BookingTimer $timer) use ($bookingService) {
                $secondsPastEnd = (int) $timer->scheduled_end_at->diffInSeconds(now());
                $grace = (int) $timer->grace_period_seconds;

                // Grace period not yet exhausted — nothing to do.
                if ($secondsPastEnd <= $grace) {
                    return;
                }

                // Tenant policy: auto-stop right at the end of grace, or keep
                // accumulating overtime until staff stops manually. maybeAutoStop
                // re-checks the toggle + grace boundary and pins stopped_at to the
                // exact grace-expiry instant (no overtime, accurate duration).
                if ($bookingService->maybeAutoStop($timer)) {
                    $this->notifyOwnersAndStaff($timer->fresh());
                    return;
                }

                if ($timer->status === 'running') {
                    $timer->update([
                        'status'           => 'overtime',
                        'overtime_seconds' => $secondsPastEnd - $grace,
                        'overtime_charge'  => $timer->computeOvertimeCharge(),
                    ]);
                    event(new TimerUpdated($timer));
                }

                $this->notifyOwnersAndStaff($timer);
            });
    }

    private function notifyOwnersAndStaff(BookingTimer $timer): void
    {
        if ($timer->overtime_alert_acknowledged_at !== null) {
            return;
        }

        $tenantId = $timer->booking?->tenant_id;
        if (!$tenantId) {
            return;
        }

        $recipients = User::where('tenant_id', $tenantId)
            ->whereIn('user_type', ['business_owner', 'staff'])
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $user) {
            $user->notify(new CourtTimeElapsedNotification($timer));
        }

        $timer->forceFill(['overtime_alert_acknowledged_at' => now()])->save();
    }
}
