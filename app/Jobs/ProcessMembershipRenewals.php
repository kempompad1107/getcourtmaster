<?php

namespace App\Jobs;

use App\Models\Membership;
use App\Notifications\MembershipExpiryNotification;
use App\Services\MembershipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMembershipRenewals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MembershipService $membershipService): void
    {
        // Expire overdue memberships
        $expired = $membershipService->processExpired();
        Log::info("Expired {$expired} memberships");

        // Send expiry alerts for memberships expiring in 7 days
        Membership::expiringSoon(7)
            ->whereDoesntHave('customer.notifications', function ($q) {
                $q->where('type', 'App\Notifications\MembershipExpiryNotification')
                  ->where('created_at', '>=', now()->subDays(1));
            })
            ->with('customer.tenant', 'plan')
            ->each(function (Membership $membership) {
                // Respect the venue's "Membership expiring" notification toggle.
                if (!($membership->customer?->tenant?->wantsNotification('notify_membership_expiry') ?? true)) {
                    return;
                }
                $membership->customer->notify(new MembershipExpiryNotification($membership));
            });

        // Auto-renew memberships
        Membership::where('auto_renew', true)
            ->where('status', 'active')
            ->where('expires_at', '<=', now()->addDays(1))
            ->with('plan')
            ->each(function (Membership $membership) use ($membershipService) {
                try {
                    $membershipService->renew($membership);
                    Log::info("Auto-renewed membership #{$membership->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to auto-renew membership #{$membership->id}: " . $e->getMessage());
                }
            });
    }
}
