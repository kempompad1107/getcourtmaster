<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer when owner/staff deny a pending cash booking.
 * The denial reason is always included.
 */
class BookingDeniedNotification extends Notification
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Your booking has been denied',
            'body'  => "#{$this->booking->booking_number} — {$this->reason}",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking denied — ' . $this->booking->booking_number)
            ->greeting('Hello ' . $notifiable->name)
            ->line('We are sorry to inform you that your booking has been **denied** by the venue.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Date: **' . $this->booking->booking_date?->format('F j, Y') . '**')
            ->line('Reason: **' . $this->reason . '**')
            ->line('If you have questions, please contact the venue directly.')
            ->action('View Booking', url('/app/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => 'Your booking has been denied: ' . $this->reason,
            'reason'         => $this->reason,
            'type'           => 'booking_denied',
            'url'            => url('/app/bookings/' . $this->booking->id),
        ];
    }
}
