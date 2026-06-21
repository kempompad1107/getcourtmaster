<?php

namespace App\Jobs;

use App\Models\Membership;
use App\Notifications\MembershipAutoRenewFailedNotification;
use App\Notifications\MembershipExpiryNotification;
use App\Services\MembershipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProcessMembershipRenewals implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MembershipService $membershipService): void
    {
        // Auto-unfreeze memberships whose freeze period has ended
        Membership::where('status', 'frozen')
            ->where('frozen_until', '<=', now())
            ->each(function (Membership $m) use ($membershipService) {
                $membershipService->unfreeze($m);
                Log::info("Auto-unfroze membership #{$m->id}");
            });

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
                    $membershipService->renew($membership, 'wallet');
                    Log::info("Auto-renewed membership #{$membership->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to auto-renew membership #{$membership->id}: " . $e->getMessage());

                    $membership->update(['status' => 'failed']);

                    // Notify customer
                    $membership->customer?->notify(new MembershipAutoRenewFailedNotification($membership));

                    // Notify staff/owner via tenant notification email
                    $tenant = $membership->customer?->tenant;
                    if ($tenant && ($email = $tenant->notificationEmail())) {
                        $notification = new MembershipAutoRenewFailedNotification($membership);
                        try {
                            Notification::route('mail', $email)->notify(new class($notification) extends \Illuminate\Notifications\Notification {
                                use \Illuminate\Bus\Queueable;
                                public function __construct(private readonly \Illuminate\Notifications\Notification $inner) {}
                                public function via(object $n): array { return ['mail']; }
                                public function toMail(object $n): mixed { return $this->inner->toStaffMail($n); }
                            });
                        } catch (\Throwable $ex) {
                            Log::warning("Staff notification failed for membership #{$membership->id}: " . $ex->getMessage());
                        }
                    }
                }
            });
    }
}
