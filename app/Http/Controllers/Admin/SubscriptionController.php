<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    /** Owner-only self-service subscription management. */
    public function index(): View
    {
        $this->authorizeOwner();
        $tenant = $this->authTenant();

        $subscription = $tenant->activeSubscription()->with('plan')->first();

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get();

        $outstandingInvoice = SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'paid')
            ->orderByDesc('created_at')
            ->first();

        // Renew is only offered when nothing is already due and we're inside the
        // pay-ahead window (or overdue); otherwise the button is disabled.
        $canRenew = $subscription
            && $subscription->isRenewable()
            && !$outstandingInvoice;

        return view('admin.subscription.index', [
            'tenant'             => $tenant,
            'subscription'       => $subscription,
            'plans'              => $plans,
            'outstandingInvoice' => $outstandingInvoice,
            'onlineGateways'     => $this->enabledPlatformGateways(),
            'canRenew'           => $canRenew,
            'renewOpensAt'       => $subscription?->renewalOpensAt(),
        ]);
    }

    /** Upgrade / change plan — takes effect immediately, then generates an invoice. */
    public function changePlan(Request $request): RedirectResponse
    {
        $this->authorizeOwner();
        $tenant = $this->authTenant();

        $data = $request->validate([
            'plan_id'       => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
        ]);

        $hasOutstanding = SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'paid')
            ->exists();
        if ($hasOutstanding) {
            return redirect()->route('admin.subscription')
                ->with('error', 'You have an outstanding invoice — please settle it before changing your plan.');
        }

        $plan   = SubscriptionPlan::findOrFail($data['plan_id']);
        $amount = $data['billing_cycle'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly;

        $subscription = $tenant->activeSubscription;
        if ($subscription) {
            // Keep the existing renews_at — the new plan takes effect now but the
            // tenant rides out the remainder of their current period before paying again.
            $subscription->update([
                'plan_id'       => $plan->id,
                'billing_cycle' => $data['billing_cycle'],
                'amount'        => $amount,
            ]);
        } else {
            $subscription = TenantSubscription::create([
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

        // Keep the denormalised tenants.plan column in sync.
        // Promote trial tenants to active now that they have a subscription.
        $tenantUpdate = ['plan' => $plan->slug];
        if ($tenant->status === 'trial') {
            $tenantUpdate['status'] = 'active';
        }
        $tenant->update($tenantUpdate);

        // Generate the invoice the owner now needs to settle for this change.
        $invoice = $this->billing->createInvoice($subscription->fresh('plan'));

        activity()->on($tenant)->log("Owner changed plan to '{$plan->name}' ({$data['billing_cycle']})");

        return redirect()->route('admin.subscription')
            ->with('success', "Plan changed to {$plan->name}. Your current billing period stays the same — the new rate applies from your next renewal. Invoice {$invoice->invoice_number} is ready.");
    }

    /** Generate a renewal invoice for the current plan right now. */
    public function renew(): RedirectResponse
    {
        $this->authorizeOwner();
        $tenant = $this->authTenant();

        $subscription = $tenant->activeSubscription()->with('plan')->first();
        if (!$subscription) {
            return redirect()->route('admin.subscription')
                ->with('error', 'No active subscription to renew. Choose a plan first.');
        }

        // Don't stack invoices — if one is already open, send them to pay it.
        $hasOutstanding = SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->where('status', '!=', 'paid')
            ->exists();
        if ($hasOutstanding) {
            return redirect()->route('admin.subscription')
                ->with('info', 'You already have an invoice due — pay it below instead of creating another.');
        }

        // Only allow paying ahead inside the renewal window (or when overdue).
        if (!$subscription->isRenewable()) {
            $opens = $subscription->renewalOpensAt();
            return redirect()->route('admin.subscription')
                ->with('error', 'Renewal opens ' . ($opens?->format('M j, Y') ?? 'closer to your renewal date') . '.');
        }

        $invoice = $this->billing->createInvoice($subscription);

        return redirect()->route('admin.subscription')
            ->with('success', "Renewal invoice {$invoice->invoice_number} created — pay online or settle it from your invoices.");
    }

    /** Start an online checkout for an outstanding invoice via the platform gateway. */
    public function checkout(Request $request, SubscriptionInvoice $invoice): RedirectResponse
    {
        $this->authorizeOwner();
        $tenant = $this->authTenant();

        abort_unless($invoice->tenant_id === $tenant->id, 403);

        if ($invoice->status === 'paid') {
            return redirect()->route('admin.subscription')->with('info', 'That invoice is already paid.');
        }

        $data = $request->validate([
            'gateway' => 'required|in:paymongo,stripe',
        ]);

        if (!in_array($data['gateway'], $this->enabledPlatformGateways(), true)) {
            return redirect()->route('admin.subscription')
                ->with('error', 'Online payment is not available right now. Please settle the invoice manually.');
        }

        $returnUrl = route('admin.subscription.checkout.return', ['invoice' => $invoice->id]);

        try {
            $url = $this->billing->startOnlineCheckout($invoice, $data['gateway'], [
                'success_url' => $returnUrl . '&status=success',
                'cancel_url'  => $returnUrl . '&status=cancel',
            ]);
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('admin.subscription')
                ->with('error', 'Could not start the online payment. Please try again or settle the invoice manually.');
        }

        return redirect()->away($url);
    }

    /** Handle the gateway redirect back; confirm and reflect the result. */
    public function checkoutReturn(Request $request): RedirectResponse
    {
        $this->authorizeOwner();
        $tenant = $this->authTenant();

        $invoice = SubscriptionInvoice::where('id', $request->integer('invoice'))
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$invoice) {
            return redirect()->route('admin.subscription')->with('error', 'Invoice not found.');
        }

        if ($request->query('status') === 'cancel') {
            return redirect()->route('admin.subscription')->with('info', 'Payment cancelled. The invoice is still open.');
        }

        if ($this->billing->confirmOnlineCheckout($invoice)) {
            return redirect()->route('admin.subscription')->with('success', "Payment received — invoice {$invoice->invoice_number} is now paid.");
        }

        return redirect()->route('admin.subscription')
            ->with('info', 'Payment is still being confirmed. We will update the invoice as soon as it settles.');
    }

    private function authorizeOwner(): void
    {
        abort_unless($this->authUser()->isBusinessOwner(), 403, 'Only the business owner can manage the subscription.');
    }

    /** @return array<int,string> platform gateways the super admin has enabled */
    private function enabledPlatformGateways(): array
    {
        $creds = PlatformSetting::paymentCredentials();
        $out = [];
        if (!empty($creds['paymongo']['enabled']) && !empty($creds['paymongo']['secret_key'])) {
            $out[] = 'paymongo';
        }
        if (!empty($creds['stripe']['enabled']) && !empty($creds['stripe']['secret'])) {
            $out[] = 'stripe';
        }
        return $out;
    }
}
