<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer when owner/staff approve a pending cash booking.
 * Distinct from BookingConfirmationNotification: this one specifically
 * confirms the cash-approval workflow ended in approval.
 */
class BookingApprovedNotification extends Notification
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(public readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Your booking has been approved',
            'body'  => "#{$this->booking->booking_number} • {$this->booking->court?->name}",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking approved — ' . $this->booking->booking_number)
            ->greeting('Good news, ' . $notifiable->name . '!')
            ->line('Your booking has been **approved** by the venue.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Date: **' . $this->booking->booking_date?->format('F j, Y') . '**')
            ->line('Time: **' . $this->booking->start_time . ' - ' . $this->booking->end_time . '**')
            ->line('Please pay in cash at the venue when you arrive.')
            ->action('View Booking', url('/app/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => 'Your booking has been approved',
            'type'           => 'booking_approved',
            'url'            => url('/app/bookings/' . $this->booking->id),
        ];
    }
}
