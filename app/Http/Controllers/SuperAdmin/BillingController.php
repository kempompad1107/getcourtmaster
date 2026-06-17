<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    /* ─────────────────────────────────────────────────────────────────────
     | INVOICES (cross-tenant)
     ───────────────────────────────────────────────────────────────────── */

    public function invoices(Request $request)
    {
        $invoices = SubscriptionInvoice::with('tenant:id,name,slug', 'subscription.plan:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('tenant_id'), fn ($q) => $q->where('tenant_id', $request->integer('tenant_id')))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        $totals = [
            'pending'  => SubscriptionInvoice::where('status', '!=', 'paid')->sum('total'),
            'paid'     => SubscriptionInvoice::where('status', 'paid')->sum('total'),
            'overdue'  => SubscriptionInvoice::where('status', '!=', 'paid')
                ->whereNotNull('due_at')->where('due_at', '<', now())->count(),
        ];

        return view('super.billing.invoices', compact('invoices', 'tenants', 'totals'));
    }

    /** Mark an invoice as paid manually (e.g. bank deposit, cash). */
    public function markInvoicePaid(Request $request, SubscriptionInvoice $invoice): RedirectResponse
    {
        $data = $request->validate([
            'payment_gateway'   => 'nullable|string|max:50',
            'payment_reference' => 'nullable|string|max:120',
            'paid_at'           => 'nullable|date',
        ]);

        $this->billing->markPaid($invoice, [
            'payment_gateway'   => $data['payment_gateway'] ?? 'manual',
            'payment_reference' => $data['payment_reference'] ?? null,
            'paid_at'           => $data['paid_at'] ?? now(),
        ]);

        activity()->on($invoice)->log("Invoice {$invoice->invoice_number} marked paid by super admin");

        return back()->with('success', "Invoice {$invoice->invoice_number} marked paid.");
    }

    /** Retry the off-session gateway charge for a pending/failed invoice. */
    public function retryInvoice(SubscriptionInvoice $invoice): RedirectResponse
    {
        $ok = $this->billing->attemptCharge($invoice);
        return back()->with($ok ? 'success' : 'error',
            $ok ? "Invoice {$invoice->invoice_number} charged successfully."
                : "Retry failed. See logs / saved payment method.");
    }

    /** Generate next renewal invoice for a single subscription right now. */
    public function generateInvoice(TenantSubscription $subscription): RedirectResponse
    {
        $invoice = $this->billing->createInvoice($subscription);
        return back()->with('success', "Invoice {$invoice->invoice_number} generated (₱" . number_format($invoice->total, 2) . ").");
    }

    /* ─────────────────────────────────────────────────────────────────────
     | SUBSCRIPTIONS (per tenant)
     ───────────────────────────────────────────────────────────────────── */

    /** Change a tenant's plan (or create a subscription if none exists). */
    public function changePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id'       => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $plan   = SubscriptionPlan::findOrFail($data['plan_id']);
        $amount = $data['billing_cycle'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        $existing = $tenant->activeSubscription;
        if ($existing) {
            $existing->update([
                'plan_id'       => $plan->id,
                'billing_cycle' => $data['billing_cycle'],
                'amount'        => $amount,
                'renews_at'     => $data['billing_cycle'] === 'yearly'
                    ? now()->addYear()->toDateString()
                    : now()->addMonth()->toDateString(),
            ]);
        } else {
            TenantSubscription::create([
                'tenant_id'     => $tenant->id,
                'plan_id'       => $plan->id,
                'billing_cycle' => $data['billing_cycle'],
                'status'        => 'active',
                'amount'        => $amount,
                'starts_at'     => now(),
                'renews_at'     => $data['billing_cycle'] === 'yearly'
                    ? now()->addYear()->toDateString()
                    : now()->addMonth()->toDateString(),
            ]);
        }

        // Keep tenants.plan denormalised column in sync.
        $tenant->update(['plan' => $plan->slug]);

        activity()->on($tenant)->log("Plan changed to '{$plan->name}' ({$data['billing_cycle']}) by super admin");

        return back()->with('success', "Plan changed to {$plan->name} ({$data['billing_cycle']}).");
    }

    public function cancelSubscription(TenantSubscription $subscription): RedirectResponse
    {
        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'renews_at'    => null,
        ]);

        activity()->on($subscription)->log("Subscription #{$subscription->id} cancelled by super admin");
        return back()->with('success', "Subscription cancelled.");
    }
}
