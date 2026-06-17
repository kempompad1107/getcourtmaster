<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Notifications\BookingConfirmationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingConfirmation implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(BookingConfirmed $event): void
    {
        $event->booking->customer->notify(new BookingConfirmationNotification($event->booking));
    }
}
