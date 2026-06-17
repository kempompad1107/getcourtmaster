<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose = 'login',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your verification code')
            ->greeting('Hello!')
            ->line("Your {$this->purpose} code is:")
            ->line(new \Illuminate\Support\HtmlString('<h2 style="letter-spacing:6px;font-family:monospace">' . $this->code . '</h2>'))
            ->line('This code expires in 10 minutes.');
    }
}
