<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionInvoice;
use App\Services\FileStorageService;
use App\Services\ImageProfile;
use App\Services\InvoicePdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoicePdfService $pdf,
        private readonly FileStorageService $files,
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $this->authTenant()->id;

        $invoices = SubscriptionInvoice::with('subscription.plan')
            ->where('tenant_id', $tenantId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.billing.invoices', compact('invoices'));
    }

    public function downloadInvoice(SubscriptionInvoice $invoice): Response
    {
        abort_unless(
            $this->authUser()->isSuperAdmin() || $invoice->tenant_id === $this->authTenant()->id,
            403
        );
        return $this->pdf->subscriptionInvoice($invoice);
    }

    public function downloadReceipt(Payment $payment): Response
    {
        abort_unless($payment->tenant_id === $this->authTenant()->id, 403);
        return $this->pdf->officialReceipt($payment);
    }

    public function uploadProof(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeCustomerOrTenant($payment);

        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        // Receipt profile keeps scanned text legible; PDF proofs are detected
        // as non-images by the optimiser and stored untouched.
        $path = $this->files->replaceFile(
            $data['proof'],
            $payment->proof_path,
            FileStorageService::FOLDER_RECEIPTS . '/proofs/' . $payment->tenant_id,
            imageProfile: ImageProfile::receipt(),
        );

        $payment->update([
            'proof_path'        => $path,
            'proof_uploaded_at' => now(),
            'status'            => $payment->status === 'paid' ? 'paid' : 'pending',
        ]);

        return back()->with('success', 'Proof uploaded. A cashier will verify it shortly.');
    }

    public function verifyProof(Payment $payment): RedirectResponse
    {
        abort_unless($payment->tenant_id === $this->authTenant()->id, 403);
        abort_unless($this->authUser()->hasRole(['business_owner', 'manager']), 403);

        $payment->update([
            'status'      => 'paid',
            'paid_at'     => $payment->paid_at ?: now(),
            'verified_at' => now(),
            'verified_by' => $this->authUser()->id,
        ]);

        return back()->with('success', 'Payment verified.');
    }

    private function authorizeCustomerOrTenant(Payment $payment): void
    {
        $u = $this->authUser();
        if ($u->isCustomer() && $payment->customer_id === $u->id) return;
        if ($payment->tenant_id === $u->tenant_id) return;
        abort(403);
    }
}
