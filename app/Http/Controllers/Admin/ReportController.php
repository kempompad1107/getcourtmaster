<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ReportPreset;
use App\Services\BranchContext;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly BranchContext $branchContext,
    ) {}

    /* ─────────────────────────────────────────────────────────────────────
     | INDEX (tabbed shell)
     ───────────────────────────────────────────────────────────────────── */

    public function index()
    {
        $this->authorize('reports.view');

        $availableBranches  = $this->branchContext->available();
        $canSeeAllBranches  = $this->branchContext->canSeeAllBranches();
        $activeBranchId     = $this->branchContext->current();

        $presets = ReportPreset::where('tenant_id', $this->authTenant()->id)
            ->where(function ($q) {
                $q->where('user_id', auth()->id())->orWhere('is_shared', true);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'report_type', 'filters', 'is_shared', 'user_id']);

        return view('admin.reports.index', compact(
            'availableBranches', 'canSeeAllBranches', 'activeBranchId', 'presets'
        ));
    }

    /* ─────────────────────────────────────────────────────────────────────
     | JSON ENDPOINTS (one per report tab)
     ───────────────────────────────────────────────────────────────────── */

    public function revenue(Request $request)
    {
        return response()->json($this->revenueData($request));
    }

    public function occupancy(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->courtOccupancy(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function financial(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->financialSummary(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function customers(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->customerRetention(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function bookings(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->bookingSummary(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function courts(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->courtPerformance(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function members(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->memberSummary(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function payments(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->paymentSummary(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function behavior(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->behaviorSummary(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function audit(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->auditSummary(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function refunds(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->refundReport(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function revenueByPeriod(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate([
            'from' => 'required|date', 'to' => 'required|date',
            'period' => 'required|in:day,week,month,year',
        ]);

        return response()->json($this->reportService->revenueByPeriod(
            $this->authTenant()->id, $request->from, $request->to, $request->period, $this->resolveBranchFilter($request)
        ));
    }

    public function revenueByBranch(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->revenueByBranch(
            $this->authTenant()->id, $request->from, $request->to
        ));
    }

    public function revenueByCourt(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->revenueByCourt(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function revenueByBookingType(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->revenueByBookingType(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    public function subscriptionRevenue(Request $request)
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        return response()->json($this->reportService->subscriptionRevenue(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        ));
    }

    /* ─────────────────────────────────────────────────────────────────────
     | EXPORTS (sync direct-download for PDF, Excel, CSV)
     ───────────────────────────────────────────────────────────────────── */

    public function downloadPdf(Request $request)
    {
        $this->authorize('reports.export');
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);

        $tenantId   = $this->authTenant()->id;
        $branchId   = $this->resolveBranchFilter($request);
        $branchName = $branchId ? Branch::where('id', $branchId)->value('name') : 'All branches';

        $data = [
            'revenue'     => $this->reportService->revenueSummary($tenantId, $request->from, $request->to, $branchId),
            'financial'   => $this->reportService->financialSummary($tenantId, $request->from, $request->to, $branchId),
            'occupancy'   => $this->reportService->courtOccupancy($tenantId, $request->from, $request->to, $branchId),
            'bookings'    => $this->reportService->bookingSummary($tenantId, $request->from, $request->to, $branchId),
            'courts'      => $this->reportService->courtPerformance($tenantId, $request->from, $request->to, $branchId),
            'members'     => $this->reportService->memberSummary($tenantId, $request->from, $request->to, $branchId),
            'payments'    => $this->reportService->paymentSummary($tenantId, $request->from, $request->to, $branchId),
            'from'        => $request->from,
            'to'          => $request->to,
            'tenant'      => $this->authTenant(),
            'branch_name' => $branchName,
        ];

        $pdf = Pdf::loadView('admin.reports.pdf', $data)->setPaper('a4');
        $slug = Str::slug($branchName);
        return $pdf->download("report-{$slug}-{$request->from}-{$request->to}.pdf");
    }

    /**
     * Excel / CSV download. Same query string as the JSON endpoints + a
     * `type` (revenue|bookings|courts|members|payments|refunds) telling us
     * how to shape the rows. Synchronous because reports are small enough
     * to fit in a single request.
     */
    public function downloadSpreadsheet(Request $request)
    {
        $this->authorize('reports.export');
        $request->validate([
            'type'   => 'required|in:revenue,bookings,courts,members,payments,refunds,audit',
            'format' => 'required|in:excel,csv',
            'from'   => 'required|date',
            'to'     => 'required|date',
        ]);

        $tenantId = $this->authTenant()->id;
        $branchId = $this->resolveBranchFilter($request);
        [$rows, $headings, $title] = $this->shapeForSpreadsheet($request->type, $tenantId, $request->from, $request->to, $branchId);

        $writer = $request->format === 'csv' ? ExcelType::CSV : ExcelType::XLSX;
        $ext    = $request->format === 'csv' ? 'csv' : 'xlsx';
        $slug   = Str::slug("{$request->type}-{$request->from}-to-{$request->to}");

        return Excel::download(new ReportExport($rows, $headings, $title), "{$slug}.{$ext}", $writer);
    }

    private function shapeForSpreadsheet(string $type, int $tenantId, string $from, string $to, ?int $branchId): array
    {
        return match ($type) {
            'revenue' => (function () use ($tenantId, $from, $to, $branchId) {
                $data = $this->reportService->revenueSummary($tenantId, $from, $to, $branchId);
                $rows = [];
                foreach (($data['daily_breakdown'] ?? []) as $date => $total) {
                    $rows[] = ['date' => $date, 'total' => round((float) $total, 2)];
                }
                return [$rows, ['Date', 'Revenue (PHP)'], 'Revenue'];
            })(),

            'bookings' => (function () use ($tenantId, $from, $to, $branchId) {
                $data = $this->reportService->bookingSummary($tenantId, $from, $to, $branchId);
                $rows = [
                    ['metric' => 'Total',      'value' => $data['total']],
                    ['metric' => 'Completed',  'value' => $data['completed']],
                    ['metric' => 'Cancelled',  'value' => $data['cancelled']],
                    ['metric' => 'No-show',    'value' => $data['no_show']],
                    ['metric' => 'Active',     'value' => $data['active']],
                    ['metric' => 'Pending',    'value' => $data['pending']],
                    ['metric' => 'Confirmed',  'value' => $data['confirmed']],
                ];
                foreach (($data['by_source'] ?? []) as $src => $count) {
                    $rows[] = ['metric' => "Source: {$src}", 'value' => $count];
                }
                return [$rows, ['Metric', 'Value'], 'Bookings'];
            })(),

            'courts' => (function () use ($tenantId, $from, $to, $branchId) {
                $data = $this->reportService->courtPerformance($tenantId, $from, $to, $branchId);
                return [$data['rows'] ?? [], ['Court ID', 'Court', 'Branch', 'Bookings', 'Hours Used', 'Revenue', 'Utilization %', 'Avg Session (mins)', 'Downtime (mins)'], 'Courts'];
            })(),

            'members' => (function () use ($tenantId, $from, $to, $branchId) {
                $data = $this->reportService->memberSummary($tenantId, $from, $to, $branchId);
                $rows = collect($data['top_spenders'] ?? [])->map(fn ($r) => [
                    'id'    => $r['id'],
                    'name'  => $r['name'],
                    'email' => $r['email'],
                    'ltv'   => $r['ltv'],
                ])->all();
                return [$rows, ['ID', 'Name', 'Email', 'Lifetime Value'], 'Top Members'];
            })(),

            'payments' => (function () use ($tenantId, $from, $to, $branchId) {
                $data = $this->reportService->paymentSummary($tenantId, $from, $to, $branchId);
                $rows = [];
                foreach (($data['by_method'] ?? []) as $r) {
                    $rows[] = ['method' => $r['method'], 'count' => $r['count'], 'gross' => $r['total'], 'fees' => $r['fees'], 'net' => round($r['total'] - $r['fees'], 2)];
                }
                return [$rows, ['Method', 'Count', 'Gross', 'Fees', 'Net'], 'Payments'];
            })(),

            'refunds' => (function () use ($tenantId, $from, $to, $branchId) {
                $data = $this->reportService->refundReport($tenantId, $from, $to, $branchId);
                return [$data['rows'] ?? [], ['Payment #', 'Customer', 'Amount', 'Refunded', 'Method', 'Refunded At', 'Payable', 'Reference', 'Notes'], 'Refunds'];
            })(),

            'audit' => (function () use ($tenantId, $from, $to, $branchId) {
                $data = $this->reportService->auditSummary($tenantId, $from, $to, $branchId);
                return [$data['top_users'] ?? [], ['User ID', 'Name', 'Actions'], 'Audit — Top Users'];
            })(),

            default => [[], [], 'Report'],
        };
    }

    /* ─────────────────────────────────────────────────────────────────────
     | SAVED PRESETS
     ───────────────────────────────────────────────────────────────────── */

    public function presetsStore(Request $request)
    {
        $this->authorize('reports.view');
        $data = $request->validate([
            'name'        => 'required|string|max:80',
            'report_type' => 'required|in:revenue,bookings,courts,members,payments,audit,behavior,overview',
            'filters'     => 'required|array',
            'is_shared'   => 'sometimes|boolean',
        ]);

        $preset = ReportPreset::create([
            'tenant_id'   => $this->authTenant()->id,
            'user_id'     => auth()->id(),
            'name'        => $data['name'],
            'report_type' => $data['report_type'],
            'filters'     => $data['filters'],
            'is_shared'   => (bool) ($data['is_shared'] ?? false),
        ]);

        return response()->json($preset, 201);
    }

    public function presetsDestroy(ReportPreset $preset)
    {
        $this->authorize('reports.view');

        abort_unless($preset->tenant_id === $this->authTenant()->id, 404);
        abort_unless($preset->user_id === auth()->id() || $this->authUser()->isBusinessOwner(), 403);

        $preset->delete();
        return response()->noContent();
    }

    /* ─────────────────────────────────────────────────────────────────────
     | LEGACY async export (kept for backward compat)
     ───────────────────────────────────────────────────────────────────── */

    public function export(Request $request)
    {
        $this->authorize('reports.export');
        $request->validate([
            'type'   => 'required|in:revenue,occupancy,financial,customers',
            'from'   => 'required|date',
            'to'     => 'required|date',
            'format' => 'required|in:pdf,excel,csv',
        ]);

        \App\Jobs\GenerateReportExport::dispatch(
            $this->authTenant()->id,
            $request->type,
            $request->from,
            $request->to,
            auth()->id()
        );

        return response()->json(['message' => 'Report is being generated. You will be notified when ready.']);
    }

    /* ─────────────────────────────────────────────────────────────────────
     | HELPERS
     ───────────────────────────────────────────────────────────────────── */

    private function revenueData(Request $request): array
    {
        $this->authorize('reports.view');
        $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from']);

        return $this->reportService->revenueSummary(
            $this->authTenant()->id, $request->from, $request->to, $this->resolveBranchFilter($request)
        );
    }

    /**
     * Branch_id validation against the user's allowed branches. Staff can never
     * resolve to null — they get clamped to one of their assigned branches.
     */
    private function resolveBranchFilter(Request $request): ?int
    {
        $raw = $request->input('branch_id');

        if ($raw === null || $raw === '' || $raw === 'all') {
            if ($this->branchContext->canSeeAllBranches()) {
                return null;
            }
            $allowed = $this->branchContext->allowedBranchIds();
            return $allowed[0] ?? null;
        }

        $candidate = (int) $raw;
        $allowed = $this->branchContext->allowedBranchIds();

        return in_array($candidate, $allowed, true) ? $candidate : null;
    }
}
