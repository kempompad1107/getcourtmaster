<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Notifications\BookingCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendBookingCreatedNotification implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(BookingCreated $event): void
    {
        $event->booking->customer->notify(new BookingCreatedNotification($event->booking));
    }
}
