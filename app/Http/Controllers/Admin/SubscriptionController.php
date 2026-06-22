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

        $subscription = $tenant->activeSubscription()->with('plan', 'pendingPlan')->first();

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get();

        $outstandingInvoice = SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->whereNotIn('status', ['paid', 'cancelled'])
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
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->exists();
        if ($hasOutstanding) {
            return redirect()->route('admin.subscription')
                ->with('error', 'You have an outstanding invoice — please settle it before changing your plan.');
        }

        $plan       = SubscriptionPlan::findOrFail($data['plan_id']);
        $fullAmount = $data['billing_cycle'] === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
        $totalDays  = $data['billing_cycle'] === 'yearly' ? 365 : 30;

        $subscription  = $tenant->activeSubscription;
        $proRataAmount = null;
        $remainingDays = 0;

        if ($subscription) {
            // Block if they're trying to pick the same plan + cycle they already have pending.
            if ((int) $subscription->pending_plan_id === $plan->id
                && $subscription->pending_billing_cycle === $data['billing_cycle']) {
                return redirect()->route('admin.subscription')
                    ->with('info', 'That plan change is already pending — pay the invoice below to activate it.');
            }

            // Pro-rata: charge only the remaining days of the current period at the new rate.
            if ($subscription->renews_at && $subscription->renews_at->isFuture()) {
                $remainingDays = max(0, (int) ceil(now()->diffInDays($subscription->renews_at, false)));
            }
            if ($remainingDays > 0 && $remainingDays < $totalDays) {
                $proRataAmount = round(($fullAmount / $totalDays) * $remainingDays, 2);
            }

            // Store the intended plan as pending — the plan only activates after payment.
            $subscription->update([
                'pending_plan_id'       => $plan->id,
                'pending_billing_cycle' => $data['billing_cycle'],
            ]);
        } else {
            // No existing subscription — create one in pending state awaiting first payment.
            $subscription = TenantSubscription::create([
                'tenant_id'             => $tenant->id,
                'plan_id'               => $plan->id,
                'billing_cycle'         => $data['billing_cycle'],
                'status'                => 'pending',
                'amount'                => $fullAmount,
                'starts_at'             => now(),
                'renews_at'             => $data['billing_cycle'] === 'yearly'
                    ? now()->addYear()->toDateString()
                    : now()->addMonth()->toDateString(),
            ]);
        }

        // Generate the invoice for the pending plan amount.
        $invoice = $this->billing->createInvoice($subscription->fresh('plan'), $proRataAmount ?? $fullAmount);

        activity()->on($tenant)->log("Owner requested plan change to '{$plan->name}' ({$data['billing_cycle']}) — awaiting payment");

        return redirect()->route('admin.subscription')
            ->with('info', "Invoice {$invoice->invoice_number} generated for {$plan->name}. Pay it below to activate the plan.");
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
            ->whereNotIn('status', ['paid', 'cancelled'])
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
