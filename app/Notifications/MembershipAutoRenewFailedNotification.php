<?php

namespace App\Notifications;

use App\Models\Membership;
use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipAutoRenewFailedNotification extends Notification implements ShouldQueue
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
            'title' => 'Auto-renewal failed',
            'body'  => "Your {$this->membership->plan?->name} membership could not be renewed — insufficient wallet balance.",
            'url'   => url('/app/wallet'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Auto-renewal failed for your {$this->membership->plan->name} membership")
            ->greeting("Hi {$notifiable->name}!")
            ->line("We were unable to auto-renew your **{$this->membership->plan->name}** membership because your wallet balance is insufficient.")
            ->line("**Membership expires:** {$this->membership->expires_at->format('F j, Y')}")
            ->line("**Plan price:** ₱" . number_format($this->membership->plan->price, 2))
            ->action('Top Up Wallet', url('/app/wallet'))
            ->line('Please top up your wallet to keep enjoying your membership benefits.');
    }

    public function toStaffMail(object $notifiable): MailMessage
    {
        $customer = $this->membership->customer;

        return (new MailMessage)
            ->subject("Auto-renewal failed: {$customer?->name} – {$this->membership->plan->name}")
            ->greeting('Heads up!')
            ->line("A membership auto-renewal failed due to insufficient wallet balance.")
            ->line("**Customer:** {$customer?->name} ({$customer?->email})")
            ->line("**Plan:** {$this->membership->plan->name}")
            ->line("**Plan price:** ₱" . number_format($this->membership->plan->price, 2))
            ->line("**Wallet balance:** ₱" . number_format($customer?->wallet_balance ?? 0, 2))
            ->line("**Membership expires:** {$this->membership->expires_at->format('F j, Y')}")
            ->action('View Membership', url("/admin/memberships/{$this->membership->id}"))
            ->line('The membership status has been set to **failed**. Please follow up with the customer.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'membership_auto_renew_failed',
            'membership_id' => $this->membership->id,
            'plan_name'     => $this->membership->plan->name,
            'expires_at'    => $this->membership->expires_at->toDateString(),
            'message'       => "Auto-renewal failed for your {$this->membership->plan->name} membership. Please top up your wallet.",
        ];
    }
}
