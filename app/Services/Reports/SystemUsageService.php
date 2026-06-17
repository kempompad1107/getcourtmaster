<?php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SaaS platform-level reporting for the super-admin console. Aggregates
 * usage data across tenants and surfaces plan-limit usage so we can warn
 * operators before they hit a hard cap.
 */
class SystemUsageService
{
    /** High-level platform KPIs for the system reports dashboard. */
    public function platformOverview(): array
    {
        $tenants = Tenant::with('activeSubscription.plan')->get();
        $totalTenants = $tenants->count();

        $activeSubs = TenantSubscription::where('status', 'active')->with('plan')->get();
        $mrr = $activeSubs->sum(fn ($s) => $s->billing_cycle === 'yearly'
            ? ($s->plan->price_yearly ?? 0) / 12
            : ($s->plan->price_monthly ?? 0));

        $arpu = $totalTenants > 0 ? round($mrr / $totalTenants, 2) : 0;

        // Recent activity proxies — bookings + revenue last 30 days across all tenants.
        $since = Carbon::today()->subDays(30);
        $bookings30 = Booking::where('booking_date', '>=', $since)
            ->whereIn('status', ['completed', 'active'])->count();
        $revenue30  = (float) Payment::where('status', 'paid')
            ->where('paid_at', '>=', $since)->sum('amount');

        return [
            'total_tenants'  => $totalTenants,
            'active_tenants' => $tenants->where('status', 'active')->count(),
            'on_trial'       => $tenants->where('status', 'trial')->count(),
            'suspended'      => $tenants->where('status', 'suspended')->count(),
            'mrr'            => round($mrr, 2),
            'arpu'           => $arpu,
            'bookings_30d'   => $bookings30,
            'revenue_30d'    => round($revenue30, 2),
        ];
    }

    /**
     * Per-tenant usage rows for the system reports table. Each row contains
     * the plan, the relevant quotas, and how close the tenant is to each
     * quota — so the UI can highlight tenants approaching their limits.
     */
    public function tenantUsage(): Collection
    {
        $tenants = Tenant::with('activeSubscription.plan')->get();

        // Pre-aggregate so we don't do N queries per tenant.
        $courtCounts    = Court::select('tenant_id', DB::raw('COUNT(*) as c'))->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $branchCounts   = Branch::select('tenant_id', DB::raw('COUNT(*) as c'))->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $customerCounts = User::where('user_type', 'customer')->select('tenant_id', DB::raw('COUNT(*) as c'))->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $staffCounts    = User::where('user_type', 'staff')->select('tenant_id', DB::raw('COUNT(*) as c'))->groupBy('tenant_id')->pluck('c', 'tenant_id');

        $monthStart = Carbon::now()->startOfMonth();
        $monthlyBookings = Booking::where('booking_date', '>=', $monthStart)
            ->select('tenant_id', DB::raw('COUNT(*) as c'))->groupBy('tenant_id')->pluck('c', 'tenant_id');

        return $tenants->map(function (Tenant $t) use ($courtCounts, $branchCounts, $customerCounts, $staffCounts, $monthlyBookings) {
            $plan = $t->activeSubscription?->plan;

            $usage = [
                'courts'    => ['used' => (int) ($courtCounts[$t->id] ?? 0),    'max' => $plan?->max_courts],
                'branches'  => ['used' => (int) ($branchCounts[$t->id] ?? 0),   'max' => $plan?->max_branches],
                'customers' => ['used' => (int) ($customerCounts[$t->id] ?? 0), 'max' => $plan?->max_customers],
                'staff'     => ['used' => (int) ($staffCounts[$t->id] ?? 0),    'max' => $plan?->max_staff],
                'bookings_month' => ['used' => (int) ($monthlyBookings[$t->id] ?? 0), 'max' => $plan?->max_bookings_per_month],
            ];

            $worstPct = 0;
            foreach ($usage as $u) {
                if (! $u['max']) continue;
                $pct = ($u['used'] / $u['max']) * 100;
                if ($pct > $worstPct) $worstPct = $pct;
            }

            return [
                'id'         => $t->id,
                'name'       => $t->name,
                'slug'       => $t->slug,
                'status'     => $t->status,
                'plan_name'  => $plan?->name ?? '—',
                'usage'      => $usage,
                'worst_pct'  => round($worstPct, 1),
                'at_limit'   => $worstPct >= 80,
            ];
        })->sortByDesc('worst_pct')->values();
    }

    /** Plan-distribution rollup for a quick "who's on what" view. */
    public function planDistribution(): array
    {
        return TenantSubscription::where('status', 'active')
            ->with('plan')->get()
            ->groupBy(fn ($s) => $s->plan?->name ?? 'No plan')
            ->map(fn ($group, $planName) => [
                'plan'   => $planName,
                'count'  => $group->count(),
                'mrr'    => round($group->sum(fn ($s) => $s->billing_cycle === 'yearly'
                    ? ($s->plan->price_yearly ?? 0) / 12
                    : ($s->plan->price_monthly ?? 0)), 2),
            ])->values()->all();
    }

    /** Failed login attempts in the last 24h (from activity_log). */
    public function recentFailedLogins(int $limit = 50): array
    {
        return DB::table('activity_log')
            ->where('log_name', 'auth')
            ->where('description', 'like', '%failed%')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['description', 'properties', 'created_at'])
            ->map(fn ($r) => [
                'description' => $r->description,
                'properties'  => json_decode($r->properties, true) ?: [],
                'created_at'  => $r->created_at,
            ])
            ->all();
    }

    /** Tenant-level activity in the last 7 days — branch + booking + payment activity. */
    public function branchActivity(int $days = 7): array
    {
        $since = Carbon::today()->subDays($days);

        $rows = Booking::where('booking_date', '>=', $since)
            ->select('tenant_id', 'branch_id', DB::raw('COUNT(*) as c'))
            ->groupBy('tenant_id', 'branch_id')
            ->get();

        $tenantNames = Tenant::whereIn('id', $rows->pluck('tenant_id'))->pluck('name', 'id');
        $branchNames = Branch::whereIn('id', $rows->pluck('branch_id'))->pluck('name', 'id');

        return $rows->map(fn ($r) => [
            'tenant' => $tenantNames[$r->tenant_id] ?? "Tenant #{$r->tenant_id}",
            'branch' => $branchNames[$r->branch_id] ?? "Branch #{$r->branch_id}",
            'bookings' => (int) $r->c,
        ])->sortByDesc('bookings')->values()->all();
    }

    /** Approximate storage usage from the media table (Spatie). Returns MB. */
    public function storageBytesPerTenant(): array
    {
        // Storage is correlated to media files. We don't have a direct tenant
        // link on media, but we can derive it via the model_type/model_id when
        // those models have a tenant_id (Court, User, etc.). This is a best-
        // effort estimate — accuracy improves as more models report storage.
        $rows = DB::table('media')
            ->select(DB::raw('SUM(size) as bytes'), 'model_type')
            ->groupBy('model_type')
            ->get();

        return $rows->map(fn ($r) => [
            'model'    => class_basename($r->model_type),
            'bytes'    => (int) $r->bytes,
            'mb'       => round(((int) $r->bytes) / 1048576, 2),
        ])->sortByDesc('bytes')->values()->all();
    }
}
