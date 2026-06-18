<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Front-desk heads-up sent to owners/staff ~5 minutes before a booking's
 * scheduled start. In-app (bell) only — mirrors CourtTimeElapsedNotification.
 */
class CourtStartingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $court    = $this->booking->court;
        $startsAt = $this->booking->start_time?->format('g:i A');

        return [
            'type'           => 'court_starting_soon',
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'court_id'       => $court?->id,
            'court_name'     => $court?->name,
            'customer_name'  => $this->booking->customer?->name ?? 'Walk-in',
            'start_time'     => $startsAt,
            'message'        => "Court time starting soon on {$court?->name} at {$startsAt} (booking #{$this->booking->booking_number}).",
            'url'            => url("/admin/bookings/{$this->booking->id}"),
        ];
    }
}
