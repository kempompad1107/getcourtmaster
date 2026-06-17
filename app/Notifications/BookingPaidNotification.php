<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to owner + staff when a customer pays for a booking with wallet or
 * court credit. The slot is already confirmed by the time this fires.
 */
class BookingPaidNotification extends Notification
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
            'title' => 'New booking paid via ' . $this->booking->paymentMethodLabel(),
            'body'  => "#{$this->booking->booking_number} • {$this->booking->court?->name}",
            'url'   => url('/admin/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New booking paid via ' . $this->booking->paymentMethodLabel())
            ->greeting('Hello ' . ($notifiable instanceof AnonymousNotifiable ? 'there' : $notifiable->name))
            ->line('A new booking was just paid via **' . $this->booking->paymentMethodLabel() . '** and auto-confirmed.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Customer: **' . ($this->booking->customer->name ?? '—') . '**')
            ->line('Court: **' . ($this->booking->court->name ?? '—') . '**')
            ->line('Date: **' . $this->booking->booking_date?->format('F j, Y') . '**')
            ->line('Time: **' . $this->booking->start_time . ' - ' . $this->booking->end_time . '**')
            ->action('View Booking', url('/admin/bookings/' . $this->booking->id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message'        => 'New booking paid via ' . $this->booking->paymentMethodLabel(),
            'type'           => 'booking_paid',
            'url'            => url('/admin/bookings/' . $this->booking->id),
        ];
    }
}
