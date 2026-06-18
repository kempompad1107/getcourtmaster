<?php

use App\Jobs\AutoClockOutShifts;
use App\Jobs\AutoStartTimers;
use App\Jobs\CheckLowStock;
use App\Jobs\CheckOvertimeTimers;
use App\Jobs\ProcessBillingRetries;
use App\Jobs\ProcessMembershipRenewals;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Jobs ────────────────────────────────────────────────────────────

// Drain the database queue every minute from within the scheduler so the app
// does NOT require a separate long-running `queue:work` service (OPS-01). As
// long as `schedule:run` is wired to cron/Task Scheduler, queued notifications
// and jobs are processed. --stop-when-empty returns immediately when idle;
// --max-time caps the run so it never overlaps the next minute.
Schedule::command('queue:work --queue=notifications,default --stop-when-empty --tries=3 --max-time=55 --quiet')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Auto-start timers when a confirmed booking's start time arrives
Schedule::job(new AutoStartTimers)->everyMinute()->withoutOverlapping();

// Auto clock out shifts that have been active for 8 hours.
Schedule::call(fn () => dispatch_sync(new AutoClockOutShifts))
    ->name('auto-clock-out-shifts')
    ->everyMinute()
    ->withoutOverlapping();

// Check for overtime / auto-stop-after-grace timers every minute.
// Run SYNCHRONOUSLY inside schedule:run (dispatch_sync) instead of pushing onto
// the queue — otherwise, with QUEUE_CONNECTION=database and no queue:work running,
// the job would sit unprocessed and sessions would never auto-stop.
Schedule::call(fn () => dispatch_sync(new CheckOvertimeTimers))
    ->name('check-overtime-timers')
    ->everyMinute()
    ->withoutOverlapping();

// Process membership renewals and expiry alerts daily
Schedule::job(new ProcessMembershipRenewals)->dailyAt('01:00');

// Daily low-stock notification sweep across tenants
Schedule::job(new CheckLowStock)->dailyAt('07:00');

// Subscription billing — generate invoices, retry failures, suspend overdue
Schedule::job(new ProcessBillingRetries)->dailyAt('02:00');

// Notify ~5 min before start: the customer (their reminder) AND the front desk
// (owners/staff bell), as redundancy to the auto-start cron.
// Guard: skip if a timer is already running/paused (cron already fired).
Schedule::call(function () {
    $now = now();
    \App\Models\Booking::query()
        ->where('status', 'confirmed')
        ->where('booking_date', $now->toDateString())
        ->where('start_reminder_sent', false)
        ->where('start_time', '>',  $now->format('H:i:s'))
        ->where('start_time', '<=', $now->copy()->addMinutes(5)->format('H:i:s'))
        ->whereDoesntHave('timer', fn ($q) => $q->whereIn('status', ['running', 'paused']))
        ->with(['customer', 'court'])
        ->each(function (\App\Models\Booking $b) {
            // Customer reminder (honors their channel preferences).
            $b->customer?->notify(new \App\Notifications\BookingStartingSoonNotification($b));

            // Front-desk heads-up for owners/staff (in-app bell only).
            $recipients = \App\Models\User::where('tenant_id', $b->tenant_id)
                ->whereIn('user_type', ['business_owner', 'staff'])
                ->where('is_active', true)
                ->get();
            \Illuminate\Support\Facades\Notification::send(
                $recipients,
                new \App\Notifications\CourtStartingSoonNotification($b)
            );

            $b->update(['start_reminder_sent' => true]);
        });
})->name('booking-starting-soon-notify')->everyMinute()->withoutOverlapping();

// Send booking reminders (2 hours before)
Schedule::call(function () {
    \App\Models\Booking::where('status', 'confirmed')
        ->where('reminder_sent', false)
        ->where('booking_date', today())
        ->where('start_time', '<=', now()->addHours(2)->format('H:i'))
        ->where('start_time', '>', now()->format('H:i'))
        ->each(fn ($b) => \App\Jobs\SendBookingReminder::dispatch($b));
})->everyFiveMinutes();

// Clear stale cache
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::flush())->weekly();

// Prune old activity logs
Schedule::command('activitylog:clean --days=90')->monthly();
