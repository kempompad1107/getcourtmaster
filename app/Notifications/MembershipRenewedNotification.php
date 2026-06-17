<?php

namespace App\Notifications;

use App\Models\Membership;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipRenewedNotification extends Notification implements ShouldQueue
{
    use Queueable, HonorsUserChannelPreferences;

    public function __construct(public Membership $membership) {}

    public function via(object $notifiable): array
    {
        return $this->channelsForUser($notifiable);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Membership renewed',
            'body'  => "{$this->membership->plan?->name} renewed successfully.",
            'url'   => url('/app/memberships'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your {$this->membership->plan->name} membership has been renewed")
            ->greeting("Hi {$notifiable->name}!")
            ->line("Your membership has been successfully renewed.")
            ->line("**Plan:** {$this->membership->plan->name}")
            ->line("**Valid until:** {$this->membership->ends_at->format('F j, Y')}")
            ->line("**Court time left:** {$this->membership->credits_label}")
            ->action('View Membership', url('/admin/memberships'))
            ->line('Thank you for being a valued member!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'membership_renewed',
            'membership_id' => $this->membership->id,
            'plan_name'     => $this->membership->plan->name,
            'ends_at'       => $this->membership->ends_at->toDateString(),
            'message'       => "Your {$this->membership->plan->name} membership has been renewed.",
        ];
    }
}
