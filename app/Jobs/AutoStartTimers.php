<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoStartTimers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BookingService $bookings): void
    {
        // Find confirmed bookings whose start_time has arrived (today, on or
        // before now) and don't already have a running/paused timer.
        $now = now();
        $today = $now->toDateString();
        $nowTime = $now->format('H:i:s');

        Booking::query()
            ->where('status', 'confirmed')
            ->where('booking_date', $today)
            ->where('start_time', '<=', $nowTime)
            // Skip bookings that already have a running/paused timer.
            ->whereDoesntHave('timer', fn ($q) => $q->whereIn('status', ['running', 'paused']))
            ->with('court')
            ->each(function (Booking $booking) use ($bookings) {
                try {
                    $bookings->startTimer($booking);
                    Log::info("Auto-started timer for booking #{$booking->booking_number}");
                } catch (\Throwable $e) {
                    Log::warning("Auto-start failed for booking #{$booking->booking_number}: " . $e->getMessage());
                }
            });
    }
}
