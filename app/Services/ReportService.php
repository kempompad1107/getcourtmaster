<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Payment statuses representing money that actually arrived — i.e. gross
     * revenue. A refund only flips status (paid → refunded/partial) and records
     * refund_amount; it never reduces the original `amount`. So all three count
     * toward gross, and refunds are subtracted separately to reach net. Filtering
     * gross on status='paid' alone would make a refund silently erase the
     * original collection from every revenue figure.
     */
    private const COLLECTED_STATUSES = ['paid', 'refunded', 'partial'];

    public function __construct(private readonly DashboardCache $cache) {}

    /**
     * Normalize the upper bound of a date range so DATETIME columns include the
     * full end-of-day. The UI sends 'YYYY-MM-DD' strings; without this, MySQL
     * compares against '$to 00:00:00' and silently drops every record stamped
     * after midnight on the to-date — which is why "Today" reported ₱0 even
     * when payments existed.
     */
    private function endOfDay(string $to): string
    {
        return str_contains($to, ' ') ? $to : "{$to} 23:59:59";
    }

    /* ─────────────────────────────────────────────────────────────────────
     | REVENUE
     ───────────────────────────────────────────────────────────────────── */

    /**
     * Monthly Revenue source of truth. Counts only money that actually arrived:
     *
     *   • Membership purchases / renewals (Payment rows from MembershipService)
     *   • Wallet bookings              (Payment rows auto-created on settle)
     *   • Cash bookings — only after staff record collection via the
     *     "Collect cash" action (Payment row with method=cash, status=paid)
     *   • Walk-ins paid via wallet or collected cash (same Payment rows as above)
     *
     * Cash bookings whose cash was never collected leave no Payment row, so
     * they are deliberately excluded. Court-credit settlements also leave no
     * Payment row — that revenue was already booked when the membership was
     * purchased, so excluding it here is correct (no double-counting).
     */
    public function revenueSummary(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $cacheKey = "report.revenue.{$tenantId}.{$from}.{$to}." . ($branchId ?? 'all');

        return $this->cache->remember($tenantId, $cacheKey, 300, function () use ($tenantId, $from, $to, $branchId) {
            // Two-period accounting: revenue is attributed to the period
            // money arrived (paid_at), refunds are attributed to the period
            // they were settled (refunded_at). A booking paid in April but
            // refunded in May correctly increases April and decreases May.
            // 'partial' must sit alongside 'paid'/'refunded' here: a partially
            // refunded payment keeps its full original `amount`, and the refunded
            // slice is subtracted separately via $refunds below. Omitting it would
            // drop the entire original collection from the positive side while
            // still subtracting the refund, driving the total negative.
            $payments = Payment::ofTenant($tenantId)
                ->whereIn('status', self::COLLECTED_STATUSES)
                ->forBranch($branchId)
                ->whereBetween('paid_at', [$from, $to])
                ->selectRaw('DATE(paid_at) as date, SUM(amount) as total, COUNT(*) as count, method')
                ->groupBy('date', 'method')
                ->get();

            $refunds = Payment::ofTenant($tenantId)
                ->where('refund_amount', '>', 0)
                ->forBranch($branchId)
                ->whereBetween('refunded_at', [$from, $to])
                ->selectRaw('DATE(refunded_at) as date, SUM(refund_amount) as total, method')
                ->groupBy('date', 'method')
                ->get();

            // Daily: positive payments + negative refunds in the same buckets.
            $daily = $payments->groupBy('date')->map(fn ($p) => $p->sum('total'));
            foreach ($refunds as $r) {
                $daily->put($r->date, ($daily->get($r->date, 0)) - (float) $r->total);
            }

            // Method breakdown: subtract refunds from the same method tile so
            // a cash refund visibly drains the cash bucket.
            $byMethod = $payments->groupBy('method')->map(fn ($p) => $p->sum('total'));
            foreach ($refunds->groupBy('method') as $method => $rows) {
                $byMethod->put($method, ($byMethod->get($method, 0)) - (float) $rows->sum('total'));
            }

            $refundsTotal = (float) $refunds->sum('total');
            $total        = (float) $payments->sum('total') - $refundsTotal;

            $growth = $this->growthVsPreviousPeriod($from, $to, fn ($f, $t) => (
                (float) Payment::ofTenant($tenantId)->whereIn('status', self::COLLECTED_STATUSES)
                    ->forBranch($branchId)
                    ->whereBetween('paid_at', [$f, $t])
                    ->sum('amount')
                - (float) Payment::ofTenant($tenantId)
                    ->where('refund_amount', '>', 0)
                    ->forBranch($branchId)
                    ->whereBetween('refunded_at', [$f, $t])
                    ->sum('refund_amount')
            ), $total);

            return [
                'total_revenue'     => round($total, 2),
                'daily_breakdown'   => $daily,
                'by_method'         => $byMethod,
                'transaction_count' => (int) $payments->sum('count'),
                'growth_pct'        => $growth['pct'],
                'previous_total'    => $growth['previous'],
            ];
        });
    }

    /**
     * Revenue bucketed by day / week / month / year.
     *
     * Sources every peso that arrived (gross) from collected Payment rows — see
     * self::COLLECTED_STATUSES; refunded/partial rows keep their original amount
     * so they remain in gross. This covers:
     *   • Membership purchases / renewals
     *   • Wallet-settled bookings (auto Payment row on settle)
     *   • Walk-in cash collected at the desk (auto Payment row on settle)
     *   • Scheduled cash bookings whose cash was collected via the "Collect cash" action
     * Pending / uncollected cash bookings have no Payment row and are excluded.
     */
    public function revenueByPeriod(int $tenantId, string $from, string $to, string $period, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $period = in_array($period, ['day', 'week', 'month', 'year']) ? $period : 'day';
        $key = "report.rev_period.{$tenantId}.{$period}.{$from}.{$to}." . ($branchId ?? 'all');

        return $this->cache->remember($tenantId, $key, 300, function () use ($tenantId, $from, $to, $period, $branchId) {
            $fmt = ['day' => '%Y-%m-%d', 'week' => '%x-W%v', 'month' => '%Y-%m', 'year' => '%Y'][$period];

            $rows = Payment::ofTenant($tenantId)->whereIn('status', self::COLLECTED_STATUSES)->forBranch($branchId)
                ->whereBetween('paid_at', [$from, $to])
                ->selectRaw("DATE_FORMAT(paid_at, '{$fmt}') as bucket, SUM(amount) as total")
                ->groupBy('bucket')->orderBy('bucket')
                ->pluck('total', 'bucket');

            return $rows->map(fn ($total, $bucket) => [
                'bucket' => $bucket,
                'total'  => round((float) $total, 2),
            ])->values()->all();
        });
    }

    public function revenueByBranch(int $tenantId, string $from, string $to): array
    {
        $to = $this->endOfDay($to);
        $key = "report.rev_branch.{$tenantId}.{$from}.{$to}";

        return $this->cache->remember($tenantId, $key, 300, function () use ($tenantId, $from, $to) {
            $branches = Branch::where('tenant_id', $tenantId)->get(['id', 'name']);
            return $branches->map(function ($b) use ($tenantId, $from, $to) {
                $total = (float) Payment::ofTenant($tenantId)->whereIn('status', self::COLLECTED_STATUSES)->forBranch($b->id)
                    ->whereBetween('paid_at', [$from, $to])->sum('amount');
                return ['branch_id' => $b->id, 'branch_name' => $b->name, 'total' => round($total, 2)];
            })->sortByDesc('total')->values()->all();
        });
    }

    /**
     * Revenue per court — joins paid Payment rows to their Booking payable.
     * Bookings without a paid Payment (e.g. pending cash) are excluded.
     */
    public function revenueByCourt(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $key = "report.rev_court.{$tenantId}.{$from}.{$to}." . ($branchId ?? 'all');

        return $this->cache->remember($tenantId, $key, 300, function () use ($tenantId, $from, $to, $branchId) {
            $rows = DB::table('payments')
                ->join('bookings', function ($join) {
                    $join->on('bookings.id', '=', 'payments.payable_id')
                         ->where('payments.payable_type', '=', Booking::class);
                })
                ->where('payments.tenant_id', $tenantId)
                ->whereIn('payments.status', self::COLLECTED_STATUSES)
                ->when($branchId, fn ($q, $v) => $q->where('bookings.branch_id', $v))
                ->whereBetween('payments.paid_at', [$from, $to])
                ->whereNull('bookings.deleted_at')
                ->select(
                    'bookings.court_id',
                    DB::raw('SUM(payments.amount) as total'),
                    DB::raw('COUNT(DISTINCT bookings.id) as bookings'),
                )
                ->groupBy('bookings.court_id')
                ->get();

            $courts = Court::withoutGlobalScope(BranchScope::class)
                ->whereIn('id', $rows->pluck('court_id'))->pluck('name', 'id');

            return $rows->map(fn ($r) => [
                'court_id'   => $r->court_id,
                'court_name' => $courts[$r->court_id] ?? "Court #{$r->court_id}",
                'bookings'   => (int) $r->bookings,
                'total'      => round((float) $r->total, 2),
            ])->sortByDesc('total')->values()->all();
        });
    }

    /**
     * Revenue per booking type — joins paid Payment rows to their Booking payable.
     * Bookings without a paid Payment (e.g. pending cash) are excluded.
     */
    public function revenueByBookingType(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $key = "report.rev_btype.{$tenantId}.{$from}.{$to}." . ($branchId ?? 'all');

        return $this->cache->remember($tenantId, $key, 300, function () use ($tenantId, $from, $to, $branchId) {
            return DB::table('payments')
                ->join('bookings', function ($join) {
                    $join->on('bookings.id', '=', 'payments.payable_id')
                         ->where('payments.payable_type', '=', Booking::class);
                })
                ->where('payments.tenant_id', $tenantId)
                ->whereIn('payments.status', self::COLLECTED_STATUSES)
                ->when($branchId, fn ($q, $v) => $q->where('bookings.branch_id', $v))
                ->whereBetween('payments.paid_at', [$from, $to])
                ->whereNull('bookings.deleted_at')
                ->select(
                    'bookings.type',
                    DB::raw('SUM(payments.amount) as total'),
                    DB::raw('COUNT(DISTINCT bookings.id) as count'),
                )
                ->groupBy('bookings.type')
                ->get()
                ->map(fn ($r) => ['type' => $r->type, 'count' => (int) $r->count, 'total' => round((float) $r->total, 2)])
                ->all();
        });
    }

    public function subscriptionRevenue(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $key = "report.sub_rev.{$tenantId}.{$from}.{$to}." . ($branchId ?? 'all');

        return $this->cache->remember($tenantId, $key, 300, function () use ($tenantId, $from, $to, $branchId) {
            // Membership-payable Payments. Memberships are not branch-bound, so
            // branchId only narrows the gross sum when set to the main branch.
            $q = Payment::ofTenant($tenantId)->whereIn('status', self::COLLECTED_STATUSES)
                ->where('payable_type', Membership::class)
                ->whereBetween('paid_at', [$from, $to]);

            if ($branchId !== null) {
                $q->forBranch($branchId);
            }

            $rows = $q->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count'),
            )->groupBy('date')->orderBy('date')->get();

            return [
                'total'  => round((float) $rows->sum('total'), 2),
                'count'  => (int) $rows->sum('count'),
                'daily'  => $rows->map(fn ($r) => ['date' => $r->date, 'total' => round((float) $r->total, 2)])->all(),
            ];
        });
    }

    public function refundReport(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $rows = Payment::ofTenant($tenantId)
            ->whereIn('status', ['refunded', 'partial'])
            ->forBranch($branchId)
            ->whereBetween('refunded_at', [$from, $to])
            ->orderByDesc('refunded_at')
            ->with('customer:id,name,email', 'payable')
            ->limit(500)
            ->get(['id', 'payment_number', 'customer_id', 'amount', 'refund_amount', 'method', 'refunded_at', 'payable_type', 'payable_id', 'refund_reference', 'notes']);

        $total = (float) $rows->sum('refund_amount');
        return [
            'total_refunded' => round($total, 2),
            'count'          => $rows->count(),
            'rows'           => $rows->map(fn ($p) => [
                'payment_number' => $p->payment_number,
                'customer'       => $p->customer?->name,
                'amount'         => (float) $p->amount,
                'refund_amount'  => (float) $p->refund_amount,
                'method'         => $p->method,
                'refunded_at'    => optional($p->refunded_at)->toDateTimeString(),
                'payable_type'   => class_basename($p->payable_type),
                'reference'      => $p->refund_reference,
                'notes'          => $p->notes,
            ])->all(),
        ];
    }


    /* ─────────────────────────────────────────────────────────────────────
     | BOOKINGS
     ───────────────────────────────────────────────────────────────────── */

    public function bookingSummary(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $base = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->whereBetween('booking_date', [$from, $to]);

        $byStatus = (clone $base)->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status');
        $byType   = (clone $base)->select('type', DB::raw('COUNT(*) as c'))->groupBy('type')->pluck('c', 'type');

        $peakHours = (clone $base)->select(DB::raw('HOUR(start_time) as h'), DB::raw('COUNT(*) as c'))
            ->groupBy('h')->orderByDesc('c')->limit(5)->get()
            ->map(fn ($r) => ['hour' => (int) $r->h, 'count' => (int) $r->c])->all();

        $busiestDays = (clone $base)->select(DB::raw('DAYNAME(booking_date) as d'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')->orderByDesc('c')->get()
            ->map(fn ($r) => ['day' => $r->d, 'count' => (int) $r->c])->all();

        $hourly = (clone $base)->select(DB::raw('HOUR(start_time) as h'), DB::raw('COUNT(*) as c'))
            ->groupBy('h')->orderBy('h')->pluck('c', 'h');
        $heatmap = [];
        for ($h = 0; $h < 24; $h++) {
            $heatmap[] = ['hour' => $h, 'count' => (int) ($hourly[$h] ?? 0)];
        }

        return [
            'total'        => (int) (clone $base)->count(),
            'completed'    => (int) ($byStatus['completed'] ?? 0),
            'cancelled'    => (int) ($byStatus['cancelled'] ?? 0),
            'no_show'      => (int) ($byStatus['no_show'] ?? 0),
            'active'       => (int) ($byStatus['active'] ?? 0),
            'pending'      => (int) ($byStatus['pending'] ?? 0),
            'confirmed'    => (int) ($byStatus['confirmed'] ?? 0),
            'by_source'    => $byType->map(fn ($v) => (int) $v)->all(),
            'peak_hours'   => $peakHours,
            'busiest_days' => $busiestDays,
            'heatmap'      => $heatmap,
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
     | COURT PERFORMANCE
     ───────────────────────────────────────────────────────────────────── */

    public function courtPerformance(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $totalDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $totalHoursPerCourt = $totalDays * 15; // 7am-10pm window — matches courtOccupancy()

        $courts = Court::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->with('branch:id,name')
            ->get();

        $rows = $courts->map(function (Court $court) use ($tenantId, $from, $to, $totalHoursPerCourt) {
            $bookings = $court->bookings()
                ->withoutGlobalScope(BranchScope::class)
                ->whereBetween('booking_date', [$from, $to])
                ->whereIn('status', ['completed', 'active']);

            $minutes  = (int) (clone $bookings)->sum('duration_minutes');
            // Revenue per court = sum of collected Payment rows for this court's
            // bookings (cash collected, wallet settled, gateway). Refunded/partial
            // rows keep their original amount and stay in (gross), matching the
            // headline Gross Revenue. Bookings whose cash was never collected
            // leave no Payment row and are deliberately excluded.
            $revenue  = (float) DB::table('payments')
                ->join('bookings', function ($j) {
                    $j->on('bookings.id', '=', 'payments.payable_id')
                      ->where('payments.payable_type', '=', Booking::class);
                })
                ->where('payments.tenant_id', $tenantId)
                ->whereIn('payments.status', self::COLLECTED_STATUSES)
                ->where('bookings.court_id', $court->id)
                ->whereBetween('payments.paid_at', [$from, $to])
                ->whereNull('bookings.deleted_at')
                ->sum('payments.amount');
            $count    = (int) (clone $bookings)->count();
            $hours    = round($minutes / 60, 1);
            $util     = $totalHoursPerCourt > 0 ? round(($hours / $totalHoursPerCourt) * 100, 1) : 0;
            $avgMins  = $count > 0 ? round($minutes / $count, 0) : 0;

            $downtime = (int) Booking::withoutGlobalScope(BranchScope::class)
                ->where('court_id', $court->id)
                ->where('status', 'cancelled')
                ->whereBetween('booking_date', [$from, $to])
                ->sum('duration_minutes');

            return [
                'court_id'         => $court->id,
                'court_name'       => $court->name,
                'branch'           => $court->branch?->name,
                'bookings'         => $count,
                'hours_used'       => $hours,
                'revenue'          => round($revenue, 2),
                'utilization_pct'  => $util,
                'avg_session_mins' => $avgMins,
                'downtime_mins'    => $downtime,
            ];
        })->values();

        return [
            'rows'           => $rows->sortByDesc('revenue')->values()->all(),
            'best_performer' => $rows->sortByDesc('revenue')->first(),
            'worst_performer'=> $rows->sortBy('revenue')->first(),
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
     | MEMBERS
     ───────────────────────────────────────────────────────────────────── */

    public function memberSummary(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $total       = User::where('tenant_id', $tenantId)->where('user_type', 'customer')->count();
        $active      = User::where('tenant_id', $tenantId)->where('user_type', 'customer')->where('is_active', true)->count();
        $inactive    = $total - $active;
        $newSignups  = User::where('tenant_id', $tenantId)->where('user_type', 'customer')
            ->whereBetween('created_at', [$from, $to])->count();

        $expiringSoon = Membership::where('tenant_id', $tenantId)->expiringSoon(30)->count();
        $expired      = Membership::where('tenant_id', $tenantId)->where('status', 'expired')->count();

        $planDistribution = Membership::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select('plan_id', DB::raw('COUNT(*) as c'))
            ->groupBy('plan_id')
            ->with('plan:id,name')
            ->get()
            ->map(fn ($m) => ['plan' => $m->plan?->name ?? 'No plan', 'count' => (int) $m->c])
            ->all();

        $topSpenders = User::where('tenant_id', $tenantId)
            ->where('user_type', 'customer')
            ->withSum(['payments as ltv' => fn ($q) =>
                $q->where('status', 'paid')->forBranch($branchId)
            ], 'amount')
            ->orderByDesc('ltv')
            ->limit(10)
            ->get(['id', 'name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'ltv' => round((float) ($u->ltv ?? 0), 2)])
            ->all();

        $mostFrequent = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->whereBetween('booking_date', [$from, $to])
            ->select('customer_id', DB::raw('COUNT(*) as c'))
            ->groupBy('customer_id')->orderByDesc('c')->limit(10)
            ->with('customer:id,name,email')
            ->get()
            ->map(fn ($r) => [
                'customer_id' => $r->customer_id,
                'name'        => $r->customer?->name,
                'email'       => $r->customer?->email,
                'bookings'    => (int) $r->c,
            ])->all();

        return [
            'total_customers'    => $total,
            'active_customers'   => $active,
            'inactive_customers' => $inactive,
            'new_signups'        => $newSignups,
            'expiring_soon'      => $expiringSoon,
            'expired'            => $expired,
            'plan_distribution'  => $planDistribution,
            'top_spenders'       => $topSpenders,
            'most_frequent'      => $mostFrequent,
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
     | PAYMENTS
     ───────────────────────────────────────────────────────────────────── */

    public function paymentSummary(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $base = Payment::ofTenant($tenantId)->forBranch($branchId)
            ->whereBetween('created_at', [$from, $to]);

        $byStatus = (clone $base)
            ->select('status', DB::raw('COUNT(*) as c'), DB::raw('SUM(amount) as total'))
            ->groupBy('status')->get()
            ->keyBy('status');

        $byMethod = (clone $base)->where('status', 'paid')
            ->select('method', DB::raw('COUNT(*) as c'), DB::raw('SUM(amount) as total'), DB::raw('SUM(fee) as fees'))
            ->groupBy('method')->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'count'  => (int) $r->c,
                'total'  => round((float) $r->total, 2),
                'fees'   => round((float) $r->fees, 2),
            ])->all();

        $statusBuckets = ['paid', 'pending', 'failed', 'refunded', 'partial', 'overdue'];
        $statuses = [];
        foreach ($statusBuckets as $s) {
            $statuses[$s] = [
                'count' => (int) ($byStatus[$s]->c ?? 0),
                'total' => round((float) ($byStatus[$s]->total ?? 0), 2),
            ];
        }

        $fees = (float) (clone $base)->where('status', 'paid')->sum('fee');
        $gross = (float) (clone $base)->where('status', 'paid')->sum('amount');

        return [
            'by_status'   => $statuses,
            'by_method'   => $byMethod,
            'gross'       => round($gross, 2),
            'fees'        => round($fees, 2),
            'net'         => round($gross - $fees, 2),
            'settlement'  => $this->settlementByMethod($byMethod),
        ];
    }

    private function settlementByMethod(array $byMethod): array
    {
        // Cash methods settle instantly at the desk; gateway methods settle
        // T+1/T+2 to the bank account after fees. This is a flat estimate.
        $instant = ['cash', 'wallet'];
        $bank    = ['bank_transfer'];
        $gateway = ['gcash', 'maya', 'paymongo', 'stripe', 'card', 'qr'];

        $sum = ['instant' => 0, 'bank' => 0, 'gateway' => 0];
        foreach ($byMethod as $row) {
            $net = $row['total'] - $row['fees'];
            if (in_array($row['method'], $instant, true)) $sum['instant'] += $net;
            elseif (in_array($row['method'], $bank, true)) $sum['bank'] += $net;
            else $sum['gateway'] += $net;
        }
        return array_map(fn ($v) => round($v, 2), $sum);
    }

    /* ─────────────────────────────────────────────────────────────────────
     | CUSTOMER BEHAVIOR
     ───────────────────────────────────────────────────────────────────── */

    public function behaviorSummary(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $base = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v));

        $period = (clone $base)->whereBetween('booking_date', [$from, $to]);

        $totalBookings = (int) (clone $period)->count();
        $uniqueCustomers = (int) (clone $period)->distinct('customer_id')->count('customer_id');

        $perCustomerCounts = (clone $period)
            ->select('customer_id', DB::raw('COUNT(*) as c'))
            ->groupBy('customer_id')
            ->pluck('c');
        $repeatCustomers = $perCustomerCounts->filter(fn ($c) => $c > 1)->count();

        $repeatRate = $uniqueCustomers > 0 ? round(($repeatCustomers / $uniqueCustomers) * 100, 1) : 0;
        $avgFreq    = $uniqueCustomers > 0 ? round($totalBookings / $uniqueCustomers, 2) : 0;

        $preferredHours = (clone $period)
            ->select(DB::raw('HOUR(start_time) as h'), DB::raw('COUNT(*) as c'))
            ->groupBy('h')->orderByDesc('c')->limit(5)
            ->get()->map(fn ($r) => ['hour' => (int) $r->h, 'count' => (int) $r->c])->all();

        // "First-time" = booked in window but never before.
        $customersInWindow = (clone $period)->pluck('customer_id')->unique();
        $firstTimers = 0;
        foreach ($customersInWindow as $cid) {
            $earlier = (clone $base)->where('customer_id', $cid)->where('booking_date', '<', $from)->exists();
            if (! $earlier) $firstTimers++;
        }
        $returning = $uniqueCustomers - $firstTimers;

        return [
            'repeat_rate_pct'   => $repeatRate,
            'avg_frequency'     => $avgFreq,
            'preferred_hours'   => $preferredHours,
            'first_timers'      => $firstTimers,
            'returning'         => $returning,
            'unique_customers'  => $uniqueCustomers,
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
     | AUDIT (admin activity)
     ───────────────────────────────────────────────────────────────────── */

    public function auditSummary(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $tenantUserIds = User::where('tenant_id', $tenantId)->pluck('id');

        $rows = DB::table('activity_log')
            ->whereIn('causer_id', $tenantUserIds)
            ->whereBetween('created_at', [$from, $to]);

        $byLog = (clone $rows)->select('log_name', DB::raw('COUNT(*) as c'))
            ->groupBy('log_name')->get()
            ->map(fn ($r) => ['log_name' => $r->log_name ?? 'general', 'count' => (int) $r->c])
            ->all();

        $byUser = (clone $rows)->select('causer_id', DB::raw('COUNT(*) as c'))
            ->groupBy('causer_id')->orderByDesc('c')->limit(10)->get();

        $userNames = User::whereIn('id', $byUser->pluck('causer_id'))->pluck('name', 'id');
        $topUsers = $byUser->map(fn ($r) => [
            'user_id' => $r->causer_id,
            'name'    => $userNames[$r->causer_id] ?? 'Unknown',
            'actions' => (int) $r->c,
        ])->all();

        $bookingMods = (int) (clone $rows)->where('log_name', 'booking')->count();
        $paymentMods = (int) (clone $rows)->where('log_name', 'payment')->count();

        return [
            'total_actions'        => (int) (clone $rows)->count(),
            'booking_modifications'=> $bookingMods,
            'payment_modifications'=> $paymentMods,
            'by_log_name'          => $byLog,
            'top_users'            => $topUsers,
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
     | EXISTING (occupancy, retention, financial, products, memberships)
     ───────────────────────────────────────────────────────────────────── */

    public function courtOccupancy(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $cacheKey = "report.occupancy.{$tenantId}.{$from}.{$to}." . ($branchId ?? 'all');

        return $this->cache->remember($tenantId, $cacheKey, 300, function () use ($tenantId, $from, $to, $branchId) {
            $courts = Court::withoutGlobalScope(BranchScope::class)
                ->where('tenant_id', $tenantId)
                ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
                ->get();
            $totalDays = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            $totalHours = $totalDays * 15;

            return $courts->map(function (Court $court) use ($from, $to, $totalHours) {
                $bookedMinutes = $court->bookings()
                    ->withoutGlobalScope(BranchScope::class)
                    ->whereBetween('booking_date', [$from, $to])
                    ->whereIn('status', ['completed', 'active'])
                    ->sum('duration_minutes');

                $bookedHours = $bookedMinutes / 60;
                $occupancyRate = $totalHours > 0 ? round(($bookedHours / $totalHours) * 100, 1) : 0;

                return [
                    'court_id'       => $court->id,
                    'court_name'     => $court->name,
                    'booked_hours'   => round($bookedHours, 1),
                    'total_hours'    => $totalHours,
                    'occupancy_rate' => $occupancyRate,
                ];
            })->toArray();
        });
    }

    public function customerRetention(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $total = User::where('tenant_id', $tenantId)->where('user_type', 'customer')->count();
        $active = Booking::withoutGlobalScope(BranchScope::class)
            ->where('tenant_id', $tenantId)
            ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->whereBetween('booking_date', [$from, $to])
            ->whereIn('status', ['completed', 'active'])
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'total_customers'  => $total,
            'active_customers' => $active,
            'retention_rate'   => $total > 0 ? round(($active / $total) * 100, 1) : 0,
        ];
    }

    public function topProducts(int $tenantId, string $from, string $to, int $limit = 10): Collection
    {
        $to = $this->endOfDay($to);
        return DB::table('pos_order_items')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_order_items.order_id')
            ->where('pos_orders.tenant_id', $tenantId)
            ->whereBetween('pos_orders.created_at', [$from, $to])
            ->where('pos_orders.status', 'completed')
            ->selectRaw('pos_order_items.name, SUM(pos_order_items.quantity) as total_quantity, SUM(pos_order_items.subtotal) as total_revenue')
            ->groupBy('pos_order_items.name')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    public function financialSummary(int $tenantId, string $from, string $to, ?int $branchId = null): array
    {
        $to = $this->endOfDay($to);
        $key = "report.financial.{$tenantId}.{$from}.{$to}." . ($branchId ?? 'all');

        return $this->cache->remember($tenantId, $key, 300, function () use ($tenantId, $from, $to, $branchId) {
            // Gross = every peso that actually arrived, regardless of any later
            // refund. processRefund() only flips status (paid → refunded/partial)
            // and sets refund_amount; it never touches the original `amount`. So
            // refunded/partial rows MUST stay in gross — otherwise issuing a
            // refund would wrongly erase the original collection from gross
            // revenue (and double-count it, since it is also subtracted as a
            // refund below). The refund is reflected only in net_revenue.
            // Pending / uncollected cash bookings have no Payment row and remain
            // excluded.
            $gross = (float) Payment::ofTenant($tenantId)
                ->whereIn('status', self::COLLECTED_STATUSES)
                ->forBranch($branchId)
                ->whereBetween('paid_at', [$from, $to])->sum('amount');

            $refunds = (float) Payment::ofTenant($tenantId)
                ->whereIn('status', ['refunded', 'partial'])
                ->forBranch($branchId)
                ->whereBetween('refunded_at', [$from, $to])->sum('refund_amount');

            // Fees were charged when the money arrived, so they follow gross:
            // a later refund does not un-charge the processing fee.
            $taxes = (float) Payment::ofTenant($tenantId)
                ->whereIn('status', self::COLLECTED_STATUSES)
                ->forBranch($branchId)
                ->whereBetween('paid_at', [$from, $to])->sum('fee');

            $discounts = (float) Booking::withoutGlobalScope(BranchScope::class)
                ->where('tenant_id', $tenantId)
                ->when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
                ->whereIn('status', ['completed', 'active', 'confirmed'])
                ->whereBetween('booking_date', [$from, $to])
                ->sum('discount_amount');

            return [
                'gross_revenue'   => round($gross, 2),
                'discounts'       => round($discounts, 2),
                'refunds'         => round($refunds, 2),
                'taxes_collected' => round($taxes, 2),
                'net_revenue'     => round($gross - $refunds, 2),
            ];
        });
    }

    public function membershipStats(int $tenantId): array
    {
        return [
            'total'         => Membership::where('tenant_id', $tenantId)->count(),
            'active'        => Membership::where('tenant_id', $tenantId)->active()->count(),
            'expiring_soon' => Membership::where('tenant_id', $tenantId)->expiringSoon(7)->count(),
            'expired'       => Membership::where('tenant_id', $tenantId)->where('status', 'expired')->count(),
        ];
    }

    /* ─────────────────────────────────────────────────────────────────────
     | HELPERS
     ───────────────────────────────────────────────────────────────────── */

    /**
     * Compute % growth of $currentTotal vs the same-length period immediately
     * before [$from, $to]. Used to power "vs last period" deltas on tiles.
     */
    public function growthVsPreviousPeriod(string $from, string $to, callable $sumFn, float $currentTotal): array
    {
        $start = Carbon::parse($from);
        $end   = Carbon::parse($to);
        $days  = $start->diffInDays($end) + 1;

        $prevEnd   = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        // Pass end-of-day on the previous-period upper bound so DATETIME columns
        // (paid_at/refunded_at) include the entire final day.
        $previous = (float) $sumFn($prevStart->toDateString(), $prevEnd->toDateString() . ' 23:59:59');

        if ($previous <= 0) {
            return ['previous' => 0.0, 'pct' => null];
        }
        $pct = round((($currentTotal - $previous) / $previous) * 100, 1);
        return ['previous' => round($previous, 2), 'pct' => $pct];
    }
}
