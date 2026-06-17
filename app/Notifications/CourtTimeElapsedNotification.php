<?php

namespace App\Notifications;

use App\Models\BookingTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CourtTimeElapsedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public BookingTimer $timer) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $booking = $this->timer->booking;
        $court   = $this->timer->court;

        return [
            'type'           => 'court_time_elapsed',
            'timer_id'       => $this->timer->id,
            'booking_id'     => $booking?->id,
            'booking_number' => $booking?->booking_number,
            'court_id'       => $court?->id,
            'court_name'     => $court?->name,
            'customer_name'  => $booking?->customer?->name ?? 'Walk-in',
            'scheduled_end'  => $this->timer->scheduled_end_at?->toIso8601String(),
            'message'        => "Court time elapsed on {$court?->name} (booking #{$booking?->booking_number}).",
            'url'            => $booking ? url("/admin/bookings/{$booking->id}") : null,
        ];
    }
}
