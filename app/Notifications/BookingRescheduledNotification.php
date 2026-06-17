<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a customer whose booking was moved to a new time — currently fired
 * when a walk-in "bump" shifts subsequent bookings forward on the same court.
 * Carries the old and new windows so the customer knows exactly what changed.
 */
class BookingRescheduledNotification extends Notification
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $oldStart,
        public readonly string $oldEnd,
        public readonly string $newStart,
        public readonly string $newEnd,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Your booking time has changed',
            'body'  => "#{$this->booking->booking_number} • now {$this->newStart}–{$this->newEnd}",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking time changed — ' . $this->booking->booking_number)
            ->greeting('Hi ' . $notifiable->name . ',')
            ->line('Your booking time at the venue has been adjusted.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Was: **' . $this->oldStart . ' – ' . $this->oldEnd . '**')
            ->line('Now: **' . $this->newStart . ' – ' . $this->newEnd . '**')
            ->line('If this new time does not work for you, please contact the venue.')
            ->action('View Booking', url('/app/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => "Your booking moved to {$this->newStart}–{$this->newEnd}",
            'type'           => 'booking_rescheduled',
            'old_start'      => $this->oldStart,
            'old_end'        => $this->oldEnd,
            'new_start'      => $this->newStart,
            'new_end'        => $this->newEnd,
            'url'            => url('/app/bookings/' . $this->booking->id),
        ];
    }
}
