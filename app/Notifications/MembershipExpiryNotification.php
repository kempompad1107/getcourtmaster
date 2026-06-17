<?php

namespace App\Notifications;

use App\Models\Membership;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipExpiryNotification extends Notification
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(public readonly Membership $membership) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Membership expiring soon',
            'body'  => "{$this->membership->plan?->name} expires in {$this->membership->days_remaining} day(s)",
            'url'   => url('/app/memberships'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = $this->membership->days_remaining;

        return (new MailMessage)
            ->subject("Your Membership Expires in {$days} Days")
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line("Your **{$this->membership->plan->name}** membership will expire in **{$days} days**.")
            ->line('Expiry Date: **' . $this->membership->expires_at->format('F j, Y') . '**')
            ->action('Renew Now', url('/memberships/' . $this->membership->id . '/renew'))
            ->line('Renew now to keep enjoying your benefits!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'membership_id' => $this->membership->id,
            'message' => 'Your membership expires soon.',
            'type' => 'membership_expiry',
        ];
    }
}
