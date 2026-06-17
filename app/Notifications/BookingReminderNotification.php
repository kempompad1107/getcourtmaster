<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminderNotification extends Notification implements ShouldQueue
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
            'title' => 'Court booking tomorrow',
            'body'  => "{$this->booking->court?->name} at {$this->booking->start_time}",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Reminder: Your court booking is tomorrow")
            ->greeting("Hi {$notifiable->name}!")
            ->line("This is a reminder that you have an upcoming court booking.")
            ->line("**Court:** {$this->booking->court->name}")
            ->line("**Date:** {$this->booking->booking_date->format('l, F j, Y')}")
            ->line("**Time:** {$this->booking->start_time} – {$this->booking->end_time}")
            ->line("**Booking #:** {$this->booking->booking_number}")
            ->action('View Booking', url("/admin/bookings/{$this->booking->id}"))
            ->line('Please arrive at least 5 minutes before your session starts. See you on the court!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'booking_reminder',
            'booking_id'     => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'court_name'     => $this->booking->court->name,
            'booking_date'   => $this->booking->booking_date->toDateString(),
            'start_time'     => $this->booking->start_time,
            'end_time'       => $this->booking->end_time,
            'message'        => "Reminder: your booking #{$this->booking->booking_number} is tomorrow.",
        ];
    }
}
