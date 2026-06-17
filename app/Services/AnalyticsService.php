<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /** Total revenue (paid payments) grouped by day for the last N days. */
    public function revenueByDay(int $tenantId, int $days = 30, ?int $branchId = null): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $rows = Payment::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->forBranch($branchId)
            ->whereBetween('paid_at', [$from, Carbon::tomorrow()])
            ->select(DB::raw('DATE(paid_at) as day'), DB::raw('SUM(amount) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $d, 'total' => (float) ($rows[$d] ?? 0)];
        }
        return $series;
    }

    public function bookingsByDay(int $tenantId, int $days = 30, ?int $branchId = null): array
    {
        $from = Carbon::today()->subDays($days - 1);

        $rows = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->whereBetween('booking_date', [$from, Carbon::today()])
            ->select(DB::raw('booking_date as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $d, 'total' => (int) ($rows[$d] ?? 0)];
        }
        return $series;
    }

    /** Hourly distribution (peak hours). */
    public function bookingsByHour(int $tenantId, int $days = 30, ?int $branchId = null): array
    {
        $from = Carbon::today()->subDays($days - 1)->toDateString();

        $rows = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->where('booking_date', '>=', $from)
            ->select(DB::raw('HOUR(start_time) as hour'), DB::raw('COUNT(*) as total'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('total', 'hour');

        $series = [];
        for ($h = 0; $h < 24; $h++) {
            $series[] = ['hour' => $h, 'total' => (int) ($rows[$h] ?? 0)];
        }
        return $series;
    }

    /** Customer LTV — totals paid per customer. */
    public function topCustomersByLtv(int $tenantId, int $limit = 10, ?int $branchId = null): array
    {
        return User::where('tenant_id', $tenantId)
            ->where('user_type', 'customer')
            ->withSum(
                ['payments as ltv' => fn ($q) =>
                    $q->where('status', 'paid')->forBranch($branchId)
                ],
                'amount'
            )
            ->orderByDesc('ltv')
            ->limit($limit)
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'ltv' => (float) ($u->ltv ?? 0)])
            ->all();
    }

    /** Churn = customers with no booking in the last 60 days / active total. */
    public function churnRate(int $tenantId, int $sinceDays = 60, ?int $branchId = null): float
    {
        $active = User::where('tenant_id', $tenantId)->where('user_type', 'customer')->where('is_active', true)->count();
        if ($active === 0) return 0.0;
        $churned = User::where('tenant_id', $tenantId)
            ->where('user_type', 'customer')
            ->where('is_active', true)
            ->whereDoesntHave('bookings', fn ($q) =>
                $q->withoutGlobalScope(BranchScope::class)
                  ->when($branchId, fn ($qq, $v) => $qq->where('branch_id', $v))
                  ->where('booking_date', '>=', Carbon::today()->subDays($sinceDays))
            )
            ->count();
        return round($churned * 100 / $active, 1);
    }

    /** Retention = % of customers who booked in BOTH the previous period and current period. */
    public function retentionRate(int $tenantId, int $windowDays = 30, ?int $branchId = null): float
    {
        $current = Carbon::today()->subDays($windowDays);
        $previous = Carbon::today()->subDays($windowDays * 2);

        $prevSet = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->whereBetween('booking_date', [$previous, $current])
            ->distinct('customer_id')->pluck('customer_id');
        if ($prevSet->isEmpty()) return 0.0;

        $currSet = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->whereBetween('booking_date', [$current, Carbon::today()])
            ->whereIn('customer_id', $prevSet)
            ->distinct('customer_id')->pluck('customer_id');

        return round($currSet->count() * 100 / $prevSet->count(), 1);
    }
}
