<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to owner + staff when a booking is cancelled. Gated by the venue's
 * "Booking cancelled" notification toggle (see BookingService::notifyOwnerStaff).
 */
class BookingCancelledNotification extends Notification
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(public readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        // On-demand CC (notification_email) has no channel preferences — mail only.
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Booking cancelled — ' . $this->booking->booking_number,
            'body'  => "{$this->booking->court?->name} • " . $this->booking->booking_date?->format('M j'),
            'url'   => url('/admin/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $greetName = $notifiable instanceof AnonymousNotifiable ? 'there' : ($notifiable->name ?? 'there');

        return (new MailMessage)
            ->subject('Booking cancelled — ' . $this->booking->booking_number)
            ->greeting('Hello ' . $greetName)
            ->line('A booking has been cancelled.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Customer: **' . ($this->booking->customer->name ?? '—') . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Date: **' . $this->booking->booking_date?->format('F j, Y') . '**')
            ->line('Time: **' . $this->booking->start_time . ' - ' . $this->booking->end_time . '**')
            ->lineIf((bool) $this->booking->cancellation_reason, 'Reason: ' . $this->booking->cancellation_reason)
            ->action('View Booking', url('/admin/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => 'Booking ' . $this->booking->booking_number . ' was cancelled',
            'type'           => 'booking_cancelled',
            'url'            => url('/admin/bookings/' . $this->booking->id),
        ];
    }
}
