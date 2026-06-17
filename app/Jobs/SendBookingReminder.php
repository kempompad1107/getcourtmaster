<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Notifications\BookingReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBookingReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public readonly Booking $booking) {}

    public function handle(): void
    {
        if ($this->booking->status !== 'confirmed' || $this->booking->reminder_sent) {
            return;
        }

        $this->booking->customer->notify(new BookingReminderNotification($this->booking));
        $this->booking->update(['reminder_sent' => true]);
    }
}
