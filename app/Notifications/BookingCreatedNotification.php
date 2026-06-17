<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingCreatedNotification extends Notification
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
            'title' => 'Booking received',
            'body'  => "#{$this->booking->booking_number} pending confirmation",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Booking Received - ' . $this->booking->booking_number)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your booking has been received and is pending confirmation.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Court: **' . $this->booking->court->name . '**')
            ->line('Date: **' . $this->booking->booking_date->format('F j, Y') . '**')
            ->line('Time: **' . $this->booking->start_time . ' - ' . $this->booking->end_time . '**')
            ->line('Total: **₱' . number_format($this->booking->total_amount, 2) . '**')
            ->action('View Booking', url('/bookings/' . $this->booking->id))
            ->line('Thank you for booking with us!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message' => 'Your booking has been received.',
            'type' => 'booking_created',
        ];
    }
}
