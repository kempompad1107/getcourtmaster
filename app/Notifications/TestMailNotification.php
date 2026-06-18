<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** One-off message sent by the Email settings "Send test email" button. */
class TestMailNotification extends Notification
{
    public function __construct(public readonly string $tenantName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test email from ' . $this->tenantName)
            ->greeting('It works!')
            ->line('This is a test email from your CourtMaster email settings.')
            ->line('If you received this, your SMTP configuration is working.');
    }
}
