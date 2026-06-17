<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the customer the moment they submit a cash booking. The slot is
 * tentative until owner/staff manually approve it.
 */
class BookingPendingApprovalNotification extends Notification
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
            'title' => 'Your booking is pending approval',
            'body'  => "#{$this->booking->booking_number} • cash payment",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking pending approval — ' . $this->booking->booking_number)
            ->greeting('Hello ' . $notifiable->name)
            ->line('Thanks for your booking. Because you selected **Cash** as the payment method, the venue must manually approve it before the slot is finalised.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Date: **' . $this->booking->booking_date?->format('F j, Y') . '**')
            ->line('Time: **' . $this->booking->start_time . ' - ' . $this->booking->end_time . '**')
            ->line('We will email you again the moment the venue approves the booking.')
            ->action('View Booking', url('/app/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => 'Your booking is pending approval',
            'type'           => 'booking_pending_approval',
            'url'            => url('/app/bookings/' . $this->booking->id),
        ];
    }
}
