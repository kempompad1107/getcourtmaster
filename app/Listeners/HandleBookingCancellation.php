<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleBookingCancellation implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(BookingCancelled $event): void
    {
        // Court status is reset synchronously in BookingService::cancel().
        // This queued listener is reserved for future async side-effects
        // (e.g. push notifications, analytics events).
    }
}
