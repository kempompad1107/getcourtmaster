<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Services\BranchContext;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly BranchContext $branchContext,
    ) {}

    public function overview(): JsonResponse
    {
        $tenantId = $this->authTenant()->id;
        $branchId = $this->branchContext->current();

        return response()->json([
            'revenue_by_day'  => $this->analytics->revenueByDay($tenantId, 30, $branchId),
            'bookings_by_day' => $this->analytics->bookingsByDay($tenantId, 30, $branchId),
            'bookings_by_hour'=> $this->analytics->bookingsByHour($tenantId, 30, $branchId),
            'top_customers'   => $this->analytics->topCustomersByLtv($tenantId, 10, $branchId),
            'churn_rate'      => $this->analytics->churnRate($tenantId, 60, $branchId),
            'retention_rate'  => $this->analytics->retentionRate($tenantId, 30, $branchId),
        ]);
    }
}
