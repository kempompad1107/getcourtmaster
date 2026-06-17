<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to owner + staff when a customer creates a cash booking.
 * They must approve or deny it before the slot is finalised.
 */
class BookingApprovalRequiredNotification extends Notification
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(public readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'New booking requires cash payment approval',
            'body'  => "#{$this->booking->booking_number} • {$this->booking->court?->name}",
            'url'   => url('/admin/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cash booking awaiting approval — ' . $this->booking->booking_number)
            ->greeting('Hello ' . ($notifiable instanceof AnonymousNotifiable ? 'there' : $notifiable->name))
            ->line('A customer has booked a court and selected **Cash** as the payment method.')
            ->line('Please review and approve or deny the booking.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Customer: **' . ($this->booking->customer->name ?? '—') . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Date: **' . $this->booking->booking_date?->format('F j, Y') . '**')
            ->line('Time: **' . $this->booking->start_time . ' - ' . $this->booking->end_time . '**')
            ->line('Total: **₱' . number_format($this->booking->total_amount, 2) . '**')
            ->action('Review Booking', url('/admin/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => 'New booking requires cash payment approval',
            'type'           => 'booking_approval_required',
            'url'            => url('/admin/bookings/' . $this->booking->id),
        ];
    }
}
