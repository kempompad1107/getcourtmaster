<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\Reports\SystemUsageService;
use Illuminate\Http\Request;

class SystemReportsController extends Controller
{
    public function __construct(
        private readonly SystemUsageService $usage,
    ) {}

    public function index()
    {
        // Route is already guarded by middleware('role:super_admin').
        return view('super.reports.index', [
            'overview'         => $this->usage->platformOverview(),
            'tenantUsage'      => $this->usage->tenantUsage(),
            'planDistribution' => $this->usage->planDistribution(),
            'branchActivity'   => $this->usage->branchActivity(),
            'storage'          => $this->usage->storageBytesPerTenant(),
            'failedLogins'     => $this->usage->recentFailedLogins(),
        ]);
    }
}
