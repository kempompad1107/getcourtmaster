<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStartingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(public Booking $booking) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Your court time is about to start',
            'body'  => "{$this->booking->court?->name} • {$this->booking->start_time} – {$this->booking->end_time}",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your court booking is starting soon')
            ->greeting("Hi {$notifiable->name}!")
            ->line('Heads up — your court session is about to begin.')
            ->line("**Court:** {$this->booking->court->name}")
            ->line("**Time:** {$this->booking->start_time} – {$this->booking->end_time}")
            ->line("**Booking #:** {$this->booking->booking_number}")
            ->action('View Booking', url("/admin/bookings/{$this->booking->id}"))
            ->line('The timer will auto-start at the scheduled time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'booking_starting_soon',
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'court_name'     => $this->booking->court->name,
            'booking_date'   => $this->booking->booking_date->toDateString(),
            'start_time'     => $this->booking->start_time,
            'end_time'       => $this->booking->end_time,
            'message'        => "Booking #{$this->booking->booking_number} is starting soon.",
        ];
    }
}
