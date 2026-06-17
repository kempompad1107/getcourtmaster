<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\DashboardCache;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly BranchContext $branchContext,
        private readonly DashboardCache $dashboardCache,
    ) {}

    public function index(Request $request)
    {
        $user = $this->authUser();

        if ($user->isSuperAdmin() || $user->hasRole('super_admin')) {
            return redirect()->route('super.dashboard');
        }

        $tenant = $this->authTenant();
        $tenantId = $tenant->id;
        $branchId = $this->branchContext->current(); // null = all branches
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        // Branch-aware cache key so per-branch and "all branches" views don't
        // overwrite each other; "active courts" etc. also vary by current state.
        $cacheKey = sprintf('dashboard.%d.%s', $tenantId, $branchId ?? 'all');

        // Pull revenue first so the monthly tile reads from the same source of
        // truth used by the chart and reports. Counts only collected money:
        // memberships, wallet bookings, and cash bookings whose cash has been
        // recorded via "Collect cash". Uncollected cash and court-credit
        // settlements are excluded by design.
        $revenue = $this->reportService->revenueSummary($tenantId, $monthStart, $monthEnd, $branchId);
        $todayRevenue = $this->reportService->revenueSummary($tenantId, $today, $today, $branchId);

        $alerts = $this->buildAlerts($tenant, $tenantId, $branchId);

        $stats = $this->dashboardCache->remember($tenantId, $cacheKey, 60, function () use ($tenantId, $branchId, $today, $revenue) {
            // Booking / Court queries are auto-filtered by BranchScope based
            // on the current branch context — no need to repeat branch_id.
            //
            // Customers are tenant-wide by design (a customer can book at any
            // branch). When viewing a specific branch we instead derive
            // "active customers" — distinct customers who booked there in the
            // last 90 days. On "All branches" we show the raw tenant count.
            $customers = $branchId
                ? Booking::withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branchId)
                    ->where('booking_date', '>=', now()->subDays(90))
                    ->distinct('customer_id')->count('customer_id')
                : User::where('tenant_id', $tenantId)
                    ->where('user_type', 'customer')->count();

            return [
                'todays_bookings' => Booking::where('tenant_id', $tenantId)
                    ->where('booking_date', $today)->count(),
                'active_courts' => Court::where('tenant_id', $tenantId)
                    ->where('status', 'occupied')->count(),
                'available_courts' => Court::where('tenant_id', $tenantId)
                    ->where('status', 'available')->where('is_active', true)->count(),
                'monthly_revenue'   => (float) ($revenue['total_revenue'] ?? 0),
                'customers'         => $customers,
                'customers_scoped'  => (bool) $branchId,
                'pending_bookings'  => Booking::where('tenant_id', $tenantId)
                    ->where('status', 'pending')->count(),
            ];
        });

        $recentBookings = Booking::where('tenant_id', $tenantId)
            ->with('customer', 'court')
            ->latest()->limit(10)->get();

        $courtStatuses = Court::where('tenant_id', $tenantId)
            ->with('activeTimer', 'branch')
            ->orderBy('name')->get();

        $occupancy = $this->reportService->courtOccupancy($tenantId, $monthStart, $monthEnd, $branchId);
        $avgUtilization = count($occupancy) > 0
            ? round(array_sum(array_column($occupancy, 'occupancy_rate')) / count($occupancy), 1)
            : 0;

        return view('admin.dashboard', compact(
            'stats', 'recentBookings', 'courtStatuses', 'revenue',
            'todayRevenue', 'alerts', 'avgUtilization'
        ));
    }

    /**
     * Build the dashboard alerts banner — surfaces pending bookings, expiring
     * memberships, and SaaS plan-limit warnings (>80% of any quota).
     */
    private function buildAlerts(\App\Models\Tenant $tenant, int $tenantId, ?int $branchId): array
    {
        $alerts = [];

        $pending = Booking::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        if ($pending > 0) {
            $alerts[] = [
                'key'   => 'pending-bookings',
                'level' => 'warning',
                'icon'  => 'bi-hourglass-split',
                'text'  => "{$pending} pending booking" . ($pending === 1 ? '' : 's') . ' need confirmation',
                'href'  => route('admin.bookings.index', ['status' => 'pending']),
            ];
        }

        $expiring = \App\Models\Membership::where('tenant_id', $tenantId)->expiringSoon(7)->count();
        if ($expiring > 0) {
            $alerts[] = [
                'key'   => 'expiring-memberships',
                'level' => 'info',
                'icon'  => 'bi-calendar-x',
                'text'  => "{$expiring} membership" . ($expiring === 1 ? '' : 's') . ' expiring in the next 7 days',
                'href'  => route('admin.memberships.index'),
            ];
        }

        // Plan-limit warnings — once a tenant crosses 80% of any quota, nag them
        // before they hit the wall so they have time to upgrade.
        $sub = $tenant->activeSubscription;
        if ($sub && $sub->plan) {
            $plan = $sub->plan;
            $checks = [
                ['label' => 'courts',    'used' => $tenant->courts()->count(),                                              'max' => $plan->max_courts],
                ['label' => 'staff',     'used' => $tenant->users()->where('user_type', 'staff')->count(),                  'max' => $plan->max_staff],
                ['label' => 'customers', 'used' => $tenant->users()->where('user_type', 'customer')->count(),               'max' => $plan->max_customers],
                ['label' => 'branches',  'used' => $tenant->branches()->count(),                                            'max' => $plan->max_branches],
            ];
            foreach ($checks as $c) {
                if (! $c['max']) continue; // null/0 = unlimited
                $pct = ($c['used'] / $c['max']) * 100;
                if ($pct >= 80) {
                    $alerts[] = [
                        'key'   => 'limit-' . $c['label'],
                        'level' => $pct >= 100 ? 'danger' : 'warning',
                        'icon'  => 'bi-graph-up',
                        'text'  => sprintf('%s usage at %d%% (%d / %d) on %s plan', ucfirst($c['label']), $pct, $c['used'], $c['max'], $plan->name),
                        'href'  => null,
                    ];
                }
            }
        }

        return $alerts;
    }

    public function superAdmin()
    {
        // Route is already guarded by middleware('role:super_admin'); no authorize() needed

        $stats = Cache::remember('super.dashboard', 120, function () {
            $activeTenants = \App\Models\Tenant::where('status', 'active')->count();
            $suspended     = \App\Models\Tenant::where('status', 'suspended')->count();
            $onTrial       = \App\Models\Tenant::where('status', 'trial')->count();

            $mrr = \App\Models\TenantSubscription::where('status', 'active')
                ->with('plan')
                ->get()
                ->sum(fn ($sub) => $sub->billing_cycle === 'yearly'
                    ? ($sub->plan->price_yearly ?? 0) / 12
                    : ($sub->plan->price_monthly ?? 0)
                );

            return [
                'total_tenants'  => $activeTenants + $suspended + $onTrial,
                'active_tenants' => $activeTenants,
                'suspended'      => $suspended,
                'on_trial'       => $onTrial,
                'mrr'            => round($mrr),
            ];
        });

        $planBreakdown = \App\Models\SubscriptionPlan::withCount('tenants')
            ->orderByDesc('tenants_count')
            ->get();

        $recentTenants = \App\Models\Tenant::latest()->limit(10)->get();

        $mrrTrend = Cache::remember('super.mrr_trend', 3600, function () {
            return collect(range(5, 0))->map(function ($monthsAgo) {
                $date = now()->subMonths($monthsAgo);
                $mrr = \App\Models\TenantSubscription::where('status', 'active')
                    ->whereDate('created_at', '<=', $date->endOfMonth())
                    ->with('plan')
                    ->get()
                    ->sum(fn ($sub) => $sub->billing_cycle === 'yearly'
                        ? ($sub->plan->price_yearly ?? 0) / 12
                        : ($sub->plan->price_monthly ?? 0)
                    );
                return ['month' => $date->format('M Y'), 'mrr' => round($mrr)];
            });
        });

        return view('super.dashboard', compact('stats', 'planBreakdown', 'recentTenants', 'mrrTrend'));
    }
}
