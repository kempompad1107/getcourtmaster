<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use App\Notifications\Messages\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingConfirmationNotification extends Notification
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
            'title' => 'Booking confirmed',
            'body'  => "#{$this->booking->booking_number} • {$this->booking->court?->name}",
            'url'   => url('/app/bookings/' . $this->booking->id),
        ];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return SmsMessage::make(sprintf(
            "Booking %s confirmed: %s on %s at %s. Show QR upon arrival.",
            $this->booking->booking_number,
            $this->booking->court?->name,
            $this->booking->booking_date->format('M d'),
            \Illuminate\Support\Carbon::parse($this->booking->start_time)->format('H:i')
        ));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $graceMinutes = (int) ($this->booking->court->tenant->settings['grace_period_minutes'] ?? 5);

        $mail = (new MailMessage)
            ->subject('Booking Confirmed! - ' . $this->booking->booking_number)
            ->greeting('Great news, ' . $notifiable->name . '!')
            ->line('Your booking has been **confirmed**.')
            ->line('Booking: **' . $this->booking->booking_number . '**')
            ->line('Court: **' . $this->booking->court->name . '**')
            ->line('Date: **' . $this->booking->booking_date->format('F j, Y') . '**')
            ->line('Time: **' . $this->booking->start_time . ' - ' . $this->booking->end_time . '**')
            ->action('View Booking & QR Code', url('/bookings/' . $this->booking->id));

        if ($graceMinutes > 0) {
            $mail->line("**Heads-up:** You get a free **{$graceMinutes}-minute** grace period after your scheduled end time. Any play beyond that is billed at the court rate.");
        }

        return $mail->line('Please show your QR code upon arrival. See you on the court!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'message' => 'Your booking has been confirmed.',
            'type' => 'booking_confirmed',
        ];
    }
}
