<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\GatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly GatewayManager $gateways,
    ) {}

    public function createPayment(array $data): Payment
    {
        return Payment::create($data);
    }

    /**
     * Process an online payment by delegating to the configured gateway driver.
     * Returns: ['checkout_url' => ..., 'reference' => ..., 'payment_id' => ...]
     */
    public function processOnline(Payment $payment, string $gateway, array $options = []): array
    {
        $driver = $this->gateways->forPayment($payment, $gateway);
        $result = $driver->createCheckout($payment, $options);

        return [
            'checkout_url' => $result['checkout_url'],
            'reference'    => $result['reference'],
            'payment_id'   => $payment->id,
        ];
    }

    public function markPaid(Payment $payment, string $reference): Payment
    {
        return DB::transaction(function () use ($payment, $reference) {
            // Idempotency — webhooks can fire multiple times, and the return-redirect
            // reconcile() can race a webhook for the same payment. Re-read the row
            // under a lock so concurrent callers serialize and the "already paid"
            // guard can't be defeated by a stale in-memory status (would otherwise
            // double-credit a wallet top-up).
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->first() ?? $payment;
            if ($payment->status === 'paid') {
                return $payment;
            }

            $payment->update([
                'status' => 'paid',
                'gateway_reference' => $reference,
                'paid_at' => now(),
                'receipt_number' => $payment->receipt_number ?: 'REC-' . strtoupper((string) Str::ulid()),
            ]);

            // Wallet top-up: credit the customer's wallet on payment completion.
            // The Payment's payable is the customer; method marks it as a top-up.
            if ($payment->method === 'wallet_topup' && $payment->customer) {
                $this->walletService->credit(
                    $payment->customer,
                    (float) $payment->amount,
                    'Wallet top-up',
                    $payment,
                );
                return $payment;
            }

            if ($payment->payable) {
                $payable = $payment->payable;
                $payable->increment('paid_amount', $payment->amount);

                if (method_exists($payable, 'getRemainingBalance') && $payable->getRemainingBalance() <= 0) {
                    if (isset($payable->status) && $payable->status !== 'confirmed') {
                        $payable->update(['status' => 'confirmed']);
                        // Fire BookingConfirmed so the customer gets their confirmation
                        // notification regardless of whether the webhook or the return
                        // redirect was the first to mark this payment paid.
                        if ($payable instanceof \App\Models\Booking) {
                            event(new \App\Events\BookingConfirmed($payable->fresh()));
                        }
                    }
                }
            }

            return $payment;
        });
    }

    public function processRefund(Payment $payment, float $amount, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason) {
            // Lock + re-read so concurrent/duplicate refund calls can't credit the
            // wallet twice.
            $payment = Payment::whereKey($payment->getKey())->lockForUpdate()->first() ?? $payment;

            // Only paid / partially-refunded rows are refundable, and never beyond
            // what's left (M-3). The old guard treated a partial row as already
            // closed while leaving partial rows at status 'paid' elsewhere, so a
            // second full-amount call could overwrite refund_amount and re-credit
            // the wallet for money already returned — a double refund. Track the
            // running refunded total and cap each call to the remaining balance.
            if (! in_array($payment->status, ['paid', 'partial'], true)) {
                return $payment;
            }

            $alreadyRefunded = (float) $payment->refund_amount;
            $refundable      = max(0, (float) $payment->amount - $alreadyRefunded);
            if ($refundable <= 0) {
                return $payment;
            }

            $apply        = round(min($amount, $refundable), 2);
            $refundAmount = round($alreadyRefunded + $apply, 2);

            $payment->update([
                'status'        => $refundAmount + 0.005 >= (float) $payment->amount ? 'refunded' : 'partial',
                'refunded_at'   => now(),
                'refund_amount' => $refundAmount,
                'notes'         => $reason,
            ]);

            // Only refund into the wallet when the money actually lived in the
            // wallet system. Crediting the wallet for a cash/gateway payment would
            // mint spendable balance out of money that was returned out-of-band
            // (cash at the desk, gateway refund handled by PayMongo/Stripe). For
            // those channels we only flip the Payment status so revenue reports
            // drop the refunded amount. Credit only the *incremental* amount.
            if ($apply > 0 && in_array($payment->method, ['wallet', 'wallet_topup'], true) && $payment->customer) {
                $this->walletService->credit(
                    $payment->customer,
                    $apply,
                    "Refund: {$reason}",
                    $payment
                );
            }

            return $payment;
        });
    }

    /**
     * Pull the current status of a Payment from its gateway and apply it
     * locally — used to make the success-redirect feel instant and as a
     * fallback when a webhook delivery is delayed or missed. Idempotent.
     */
    public function reconcile(Payment $payment): Payment
    {
        if ($payment->isPaid() || $payment->isFailed()) {
            return $payment;
        }

        if (!$payment->gateway) {
            return $payment;
        }

        try {
            $driver = $this->gateways->forPayment($payment, $payment->gateway);
            $result = $driver->fetchStatus($payment);
        } catch (\Throwable $e) {
            Log::warning("Reconcile failed for payment {$payment->id}: " . $e->getMessage());
            return $payment;
        }

        if (($result['status'] ?? null) === 'paid') {
            return $this->markPaid($payment, $result['reference'] ?? $payment->gateway_reference ?? '');
        }
        if (($result['status'] ?? null) === 'failed') {
            $payment->update(['status' => 'failed']);
        }

        return $payment->refresh();
    }

    /**
     * Handle an incoming gateway webhook. Webhook URLs are tenant-scoped
     * (e.g. /webhooks/paymongo/{token}) so the controller resolves the tenant
     * from the URL token and passes it here for signature verification.
     */
    public function handleWebhook(Tenant $tenant, string $gateway, string $rawBody, string $signature): void
    {
        try {
            $driver = $this->gateways->forTenant($tenant, $gateway);
            $payload = $driver->verifyWebhook($rawBody, $signature);
            $parsed = $driver->parseWebhook($payload);
        } catch (\Throwable $e) {
            Log::warning("Webhook rejected ({$gateway}, tenant {$tenant->id}): " . $e->getMessage());
            return;
        }

        Log::info("Payment webhook received ({$gateway}, tenant {$tenant->id})", $parsed);

        if (empty($parsed['reference'])) {
            return;
        }

        // Scope payment lookup to the tenant — never trust a webhook to act on
        // another tenant's payment even if references somehow collide.
        $payment = Payment::where('tenant_id', $tenant->id)
            ->where('gateway_reference', $parsed['reference'])
            ->first();
        if (!$payment) {
            return;
        }

        switch ($parsed['status']) {
            case 'paid':
                $this->markPaid($payment, $parsed['reference']);
                break;
            case 'failed':
                $payment->update(['status' => 'failed']);
                break;
            case 'refunded':
                $payment->update(['status' => 'refunded', 'refunded_at' => now()]);
                break;
        }
    }
}
