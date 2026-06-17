<?php

namespace App\Services;

use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Payments\GatewayManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService
{
    public function __construct(private readonly GatewayManager $gateways) {}

    /** Sweep active subscriptions and generate invoices for ones that have rolled past their renew date. */
    public function generateRenewalInvoices(): int
    {
        $count = 0;
        TenantSubscription::where('status', 'active')
            ->whereDate('renews_at', '<=', today())
            ->with('plan', 'tenant')
            ->chunkById(50, function ($subs) use (&$count) {
                foreach ($subs as $sub) {
                    $this->createInvoice($sub);
                    $count++;
                }
            });
        return $count;
    }

    /**
     * Mark an invoice paid and roll the subscription forward. Shared by the
     * super-admin manual flow, off-session retries, and owner online checkout.
     *
     * @param array{payment_gateway?:string,payment_reference?:string,paid_at?:mixed} $meta
     */
    public function markPaid(SubscriptionInvoice $invoice, array $meta = []): void
    {
        // Re-read inside the transaction so we always hold the freshest status.
        DB::transaction(function () use ($invoice, $meta) {
            $invoice = SubscriptionInvoice::lockForUpdate()->find($invoice->id) ?? $invoice;

            // Idempotency: if already paid (e.g. concurrent webhook + manual mark),
            // do nothing rather than double-advancing the renewal date.
            if ($invoice->status === 'paid') {
                return;
            }

            $paidAt = $meta['paid_at'] ?? now();

            $invoice->update([
                'status'            => 'paid',
                'paid_at'           => $paidAt,
                'payment_gateway'   => $meta['payment_gateway'] ?? $invoice->payment_gateway ?? 'manual',
                'payment_reference' => $meta['payment_reference'] ?? $invoice->payment_reference,
                'failed_attempts'   => 0,
                'next_retry_at'     => null,
            ]);

            // Move the subscription's renewal date forward so the next billing sweep
            // doesn't immediately regenerate an invoice.
            $sub = $invoice->subscription()->with('plan')->first();
            if ($sub) {
                $sub->update([
                    'last_charge_at' => $invoice->paid_at,
                    'renews_at'      => $this->nextRenewalDate($sub)->toDateString(),
                    'status'         => 'active',
                ]);
            }

            // Lift any non-payment suspension on the tenant.
            if ($invoice->tenant && $invoice->tenant->status === 'suspended') {
                $invoice->tenant->update(['status' => 'active']);
            }
        });
    }

    /**
     * Start an owner-initiated online checkout for a subscription invoice using
     * the *platform's* gateway. Stores the session reference on the invoice and
     * returns the hosted checkout URL.
     */
    public function startOnlineCheckout(SubscriptionInvoice $invoice, string $gateway, array $urls): string
    {
        $driver = $this->gateways->platform($gateway);

        $result = $driver->createGenericCheckout((float) $invoice->total, [
            'description' => "Subscription {$invoice->invoice_number}",
            'line_name'   => "Subscription {$invoice->invoice_number}",
            'currency'    => 'PHP',
            'success_url' => $urls['success_url'] ?? null,
            'cancel_url'  => $urls['cancel_url'] ?? null,
            'reference'   => $invoice->invoice_number,
        ]);

        if (empty($result['checkout_url'])) {
            throw new \RuntimeException('Gateway did not return a checkout URL.');
        }

        $invoice->update([
            'payment_gateway'   => $gateway,
            'payment_reference' => $result['reference'],
        ]);

        return $result['checkout_url'];
    }

    /**
     * Confirm an online checkout by polling the platform gateway. Marks the
     * invoice paid (and rolls the subscription forward) when settled.
     */
    public function confirmOnlineCheckout(SubscriptionInvoice $invoice): bool
    {
        if ($invoice->status === 'paid') {
            return true;
        }
        if (!$invoice->payment_gateway || !$invoice->payment_reference) {
            return false;
        }

        try {
            $driver = $this->gateways->platform($invoice->payment_gateway);
            $result = $driver->fetchSessionStatus($invoice->payment_reference);
        } catch (\Throwable $e) {
            Log::warning("Subscription checkout confirm failed for invoice {$invoice->id}: " . $e->getMessage());
            return false;
        }

        if (($result['status'] ?? null) === 'paid') {
            $this->markPaid($invoice, [
                'payment_gateway'   => $invoice->payment_gateway,
                'payment_reference' => $invoice->payment_reference,
            ]);
            return true;
        }

        return false;
    }

    public function createInvoice(TenantSubscription $sub): SubscriptionInvoice
    {
        $amount = (float) ($sub->amount ?? $sub->plan?->price ?? 0);
        $tax    = round($amount * 0.12, 2);
        return SubscriptionInvoice::create([
            'tenant_id'       => $sub->tenant_id,
            'subscription_id' => $sub->id,
            'invoice_number'  => 'INV-' . strtoupper(uniqid()),
            'amount'          => $amount,
            'tax'             => $tax,
            'total'           => $amount + $tax,
            'status'          => 'pending',
            'due_at'          => now()->addDays(7),
            'next_retry_at'   => now()->addDay(),
        ]);
    }

    /**
     * Retry pending/failed invoices using the gateway. Exponential backoff:
     * attempts 1→2 →4 →7 →stop & suspend.
     */
    public function retryFailedInvoices(): int
    {
        $now = now();
        $retried = 0;

        SubscriptionInvoice::where('status', '!=', 'paid')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', $now)
            ->chunkById(50, function ($invoices) use (&$retried) {
                foreach ($invoices as $inv) {
                    $retried += (int) $this->attemptCharge($inv);
                }
            });

        return $retried;
    }

    public function attemptCharge(SubscriptionInvoice $invoice): bool
    {
        // Guard against duplicate charges when the cron overlaps itself or retries
        // fire while a previous attempt's DB write is still in-flight.
        if ($invoice->fresh()->status === 'paid') {
            return true;
        }

        try {
            $sub = $invoice->subscription()->with('tenant')->first();
            $gatewayName = $sub?->payment_gateway;
            $tokenCipher = $sub?->payment_method_token;

            // No saved payment method → can't auto-charge. Schedule retry; tenant must add a card.
            if (!$gatewayName || !$tokenCipher) {
                $this->scheduleNextRetry($invoice);
                return false;
            }

            try {
                $token = \Illuminate\Support\Facades\Crypt::decryptString($tokenCipher);
            } catch (\Throwable $e) {
                Log::warning("Invoice {$invoice->id}: payment token decrypt failed: " . $e->getMessage());
                $this->scheduleNextRetry($invoice);
                return false;
            }

            // SaaS subscription dues go to the platform's account, NOT the tenant's.
            $gateway = $this->gateways->platform($gatewayName);
            $result  = $gateway->chargeOffSession(
                $token,
                (float) $invoice->total,
                'PHP',
                ['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]
            );

            if (($result['status'] ?? null) === 'paid') {
                // Route through markPaid() so invoice + subscription + tenant
                // suspension lift all happen atomically in one transaction.
                $this->markPaid($invoice, [
                    'payment_reference' => $result['reference'] ?? null,
                    'paid_at'           => now(),
                ]);
                return true;
            }

            $this->scheduleNextRetry($invoice);
            return false;
        } catch (\Throwable $e) {
            Log::warning('Billing retry failed for invoice ' . $invoice->id . ': ' . $e->getMessage());
            $this->scheduleNextRetry($invoice);
            return false;
        }
    }

    protected function scheduleNextRetry(SubscriptionInvoice $invoice): void
    {
        $invoice->increment('failed_attempts');
        $invoice->update([
            'next_retry_at' => match ((int) $invoice->failed_attempts) {
                1       => now()->addDays(2),
                2       => now()->addDays(4),
                3       => now()->addDays(7),
                default => null, // exhausted — suspendOverdueTenants() takes over
            },
            'status' => 'pending',
        ]);
    }

    protected function nextRenewalDate(TenantSubscription $sub): \Carbon\Carbon
    {
        // Extend from the current expiry when it's still in the future so paying
        // early keeps the remaining days; otherwise extend from today.
        $base = $sub->renews_at && $sub->renews_at->isFuture()
            ? $sub->renews_at->copy()
            : now();

        return match ($sub->billing_cycle) {
            'yearly'  => $base->addYear(),
            'monthly' => $base->addMonth(),
            default   => $base->addMonth(),
        };
    }

    /** Suspend tenants that have an unpaid invoice past its grace period (7 days after due). */
    public function suspendOverdueTenants(int $gracePeriodDays = 7): int
    {
        $cutoff = now()->subDays($gracePeriodDays);
        $count = 0;

        $tenantIds = SubscriptionInvoice::where('status', '!=', 'paid')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $cutoff)
            ->pluck('tenant_id')
            ->unique();

        Tenant::whereIn('id', $tenantIds)
            ->where('status', '!=', 'suspended')
            ->each(function (Tenant $t) use (&$count) {
                $t->update(['status' => 'suspended']);
                $count++;
            });

        return $count;
    }
}
