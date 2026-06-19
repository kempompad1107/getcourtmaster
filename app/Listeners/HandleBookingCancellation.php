<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;

class HandleBookingCancellation implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;

        // Free up the court if still marked occupied by this booking
        $court = $booking->court;
        if ($court->status === 'reserved' || $court->status === 'occupied') {
            $hasOtherActive = $court->bookings()
                ->where('id', '!=', $booking->id)
                ->whereIn('status', ['active', 'confirmed'])
                ->where('booking_date', today())
                ->exists();

            if (!$hasOtherActive) {
                $court->update(['status' => 'available']);
            }
        }

    }
}
