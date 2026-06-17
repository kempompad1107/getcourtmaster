<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Hard enforcement of SaaS plan quotas (courts, branches, staff, customers).
 *
 * The dashboard alerts banner uses the same data to *warn* operators at 80%
 * usage. This service is the brick wall when they actually try to create
 * something past 100% — it short-circuits the request with a friendly
 * redirect-back rather than letting the row insert and silently breaking
 * the plan agreement.
 *
 * `null` / `0` on a plan column means "unlimited" — those quotas are
 * always allowed.
 */
class PlanLimitGuard
{
    /** Map of resource name → tenant relation/closure that returns current count. */
    private const RESOURCES = [
        'courts'    => 'courts',
        'branches'  => 'branches',
        'staff'     => 'staff',     // resolved specially (user_type='staff')
        'customers' => 'customers', // resolved specially (user_type='customer')
    ];

    /** Throw a redirect-back response if creating one more would exceed quota. */
    public function assertCanAdd(Tenant $tenant, string $resource): void
    {
        $check = $this->check($tenant, $resource);

        if (! $check['allowed']) {
            throw new HttpResponseException(
                redirect()->back()->withInput()->with(
                    'error',
                    "Plan limit reached: {$check['used']} / {$check['max']} {$resource} on the {$check['plan']} plan. "
                    . "Upgrade the subscription or contact your super-admin to lift the limit."
                )
            );
        }
    }

    /** Cheap boolean — handy for hiding "Add" buttons. */
    public function canAdd(Tenant $tenant, string $resource): bool
    {
        return $this->check($tenant, $resource)['allowed'];
    }

    /**
     * Full readout for a single resource — used by the controller to feed
     * the index view ("at_limit" banners on courts/branches/staff/customers).
     */
    public function check(Tenant $tenant, string $resource): array
    {
        if (! isset(self::RESOURCES[$resource])) {
            // Unknown resource — fail open. Don't accidentally block features
            // that haven't been wired up yet.
            return ['allowed' => true, 'used' => 0, 'max' => null, 'plan' => null];
        }

        $plan = $tenant->activeSubscription?->plan;
        $max  = $plan ? $this->maxFor($plan, $resource) : null;
        $used = $this->countFor($tenant, $resource);

        // Null / 0 quota = unlimited. No plan attached = no enforcement (the
        // operator hasn't agreed to any limit yet, so don't surprise them).
        if (! $max) {
            return ['allowed' => true, 'used' => $used, 'max' => null, 'plan' => $plan?->name];
        }

        return [
            'allowed' => $used < $max,
            'used'    => $used,
            'max'     => $max,
            'plan'    => $plan->name,
            'pct'     => round(($used / $max) * 100, 1),
        ];
    }

    /** All resources at once — convenient for a single "you're near your cap" banner. */
    public function summary(Tenant $tenant): array
    {
        return collect(self::RESOURCES)
            ->keys()
            ->mapWithKeys(fn ($r) => [$r => $this->check($tenant, $r)])
            ->all();
    }

    private function maxFor($plan, string $resource): ?int
    {
        return match ($resource) {
            'courts'    => $plan->max_courts,
            'branches'  => $plan->max_branches,
            'staff'     => $plan->max_staff,
            'customers' => $plan->max_customers,
            default     => null,
        };
    }

    private function countFor(Tenant $tenant, string $resource): int
    {
        return match ($resource) {
            'courts'    => $tenant->courts()->count(),
            'branches'  => $tenant->branches()->count(),
            'staff'     => $tenant->users()->where('user_type', 'staff')->count(),
            'customers' => $tenant->users()->where('user_type', 'customer')->count(),
            default     => 0,
        };
    }
}
