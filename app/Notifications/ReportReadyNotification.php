<?php

namespace App\Notifications;

use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(
        public string $reportType,
        public string $filePath,
        public string $downloadUrl
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable, ['in_app', 'email']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->reportType} report is ready")
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your requested report has been generated and is ready to download.")
            ->line("**Report type:** {$this->reportType}")
            ->action('Download Report', $this->downloadUrl)
            ->line('This download link is valid for 24 hours.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'report_ready',
            'report_type'  => $this->reportType,
            'download_url' => $this->downloadUrl,
            'message'      => "Your {$this->reportType} report is ready for download.",
        ];
    }
}
