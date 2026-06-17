<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Services\BookingService;
use Illuminate\Http\Request;

class RefundRequestController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request)
    {
        $tenantId = $this->authTenant()->id;

        $status = $request->query('status', 'pending');
        $allowed = ['pending', 'processed', 'denied', 'all'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $refunds = RefundRequest::where('tenant_id', $tenantId)
            ->with(['booking:id,booking_number,booking_date,payment_method', 'customer:id,name,email', 'processedBy:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'pending'   => RefundRequest::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
            'processed' => RefundRequest::where('tenant_id', $tenantId)->where('status', 'processed')->count(),
            'denied'    => RefundRequest::where('tenant_id', $tenantId)->where('status', 'denied')->count(),
        ];

        return view('admin.refund-requests.index', compact('refunds', 'status', 'counts'));
    }

    public function process(Request $request, RefundRequest $refundRequest)
    {
        $this->ensureSameTenant($refundRequest);
        abort_unless($this->authUser()->hasRole(['business_owner', 'manager']), 403);
        $request->validate(['reference' => 'nullable|string|max:100']);

        $this->bookingService->processCashRefund(
            $refundRequest,
            $this->authUser(),
            $request->input('reference')
        );

        return back()->with('success', "Refund #{$refundRequest->id} marked as paid out.");
    }

    public function deny(Request $request, RefundRequest $refundRequest)
    {
        $this->ensureSameTenant($refundRequest);
        abort_unless($this->authUser()->hasRole(['business_owner', 'manager']), 403);
        $request->validate(['denial_note' => 'required|string|max:255']);

        $this->bookingService->denyCashRefund(
            $refundRequest,
            $this->authUser(),
            $request->input('denial_note')
        );

        return back()->with('success', "Refund #{$refundRequest->id} denied.");
    }

    protected function ensureSameTenant(RefundRequest $refundRequest): void
    {
        abort_if($refundRequest->tenant_id !== $this->authTenant()->id, 403, 'Cross-tenant access denied.');
    }
}
