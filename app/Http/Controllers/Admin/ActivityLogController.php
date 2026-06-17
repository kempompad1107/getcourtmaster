<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        // SEC (cross-tenant): the activity_log table has no tenant_id column and
        // spans every tenant plus super-admin actions. Constrain to THIS tenant's
        // users so an owner/manager can never read another venue's activity —
        // mirrors the causer-based scoping in ReportService::auditSummary(). Also
        // gate on reports.view so roles without audit access can't reach the page.
        $this->authorize('reports.view');

        $tenantUserIds = User::where('tenant_id', $this->authTenant()->id)->pluck('id');

        $query = Activity::query()
            ->whereIn('causer_id', $tenantUserIds)
            ->latest();

        if ($logName = $request->query('log')) {
            $query->where('log_name', $logName);
        }
        if ($event = $request->query('event')) {
            $query->where('event', $event);
        }
        if ($causer = $request->query('causer')) {
            // A hostile causer id from another tenant must not widen the result set.
            if ($tenantUserIds->contains((int) $causer)) {
                $query->where('causer_id', $causer);
            }
        }

        $logs = $query->paginate(25)->withQueryString();

        $logNames = Activity::query()
            ->whereIn('causer_id', $tenantUserIds)
            ->distinct()->pluck('log_name')->filter()->values();

        return view('admin.audit.index', compact('logs', 'logNames'));
    }
}
