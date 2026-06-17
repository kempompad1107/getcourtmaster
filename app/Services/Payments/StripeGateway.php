<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeGateway implements PaymentGateway
{
    private const BASE = 'https://api.stripe.com/v1';

    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $webhookSecret,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function createCheckout(Payment $payment, array $options = []): array
    {
        $result = $this->createGenericCheckout((float) $payment->amount, array_merge([
            'line_name'   => "Payment #{$payment->id}",
            'success_url' => url('/payments/return?status=success&payment=' . $payment->id),
            'cancel_url'  => url('/payments/return?status=cancel&payment='  . $payment->id),
            'reference'   => (string) $payment->id,
        ], $options));

        $payment->update([
            'gateway' => $this->name(),
            'gateway_reference' => $result['reference'],
            'gateway_checkout_url' => $result['checkout_url'],
            'status' => 'pending',
        ]);

        return $result;
    }

    public function createGenericCheckout(float $amount, array $options = []): array
    {
        $this->ensureConfigured();
        $amountCents = (int) round($amount * 100);

        $form = [
            'mode' => 'payment',
            'payment_method_types[]' => 'card',
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => strtolower($options['currency'] ?? 'php'),
            'line_items[0][price_data][unit_amount]' => $amountCents,
            'line_items[0][price_data][product_data][name]' => $options['line_name'] ?? 'Payment',
        ];
        if (!empty($options['success_url'])) {
            $form['success_url'] = $options['success_url'];
        }
        if (!empty($options['cancel_url'])) {
            $form['cancel_url'] = $options['cancel_url'];
        }
        if (!empty($options['reference'])) {
            $form['client_reference_id'] = (string) $options['reference'];
        }

        $response = $this->client()->asForm()->post(self::BASE . '/checkout/sessions', $form);

        if (!$response->successful()) {
            throw new RuntimeException('Stripe checkout failed: ' . $response->body());
        }

        $body = $response->json();

        return [
            'checkout_url' => $body['url'] ?? null,
            'reference'    => $body['id'] ?? null,
            'raw'          => $body,
        ];
    }

    public function fetchSessionStatus(string $reference): array
    {
        $this->ensureConfigured();

        $response = $this->client()->get(self::BASE . "/checkout/sessions/{$reference}");
        if (!$response->successful()) {
            return ['status' => 'unknown', 'reference' => $reference];
        }

        // Stripe Checkout: payment_status is 'paid', 'unpaid', or 'no_payment_required'.
        $paymentStatus = $response->json('payment_status');
        $sessionStatus = $response->json('status'); // 'open' | 'complete' | 'expired'

        $status = match (true) {
            $paymentStatus === 'paid'    => 'paid',
            $sessionStatus === 'expired' => 'failed',
            default                      => 'pending',
        };

        return ['status' => $status, 'reference' => $reference];
    }

    public function chargeOffSession(string $paymentMethodToken, float $amount, string $currency = 'PHP', array $metadata = []): array
    {
        $this->ensureConfigured();

        // Token format: "cus_xxx|pm_yyy" (customer id + payment method id)
        [$customerId, $paymentMethodId] = array_pad(explode('|', $paymentMethodToken, 2), 2, null);
        if (!$customerId || !$paymentMethodId) {
            throw new RuntimeException('Stripe token must be "customer_id|payment_method_id".');
        }

        $form = [
            'amount'         => (int) round($amount * 100),
            'currency'       => strtolower($currency),
            'customer'       => $customerId,
            'payment_method' => $paymentMethodId,
            'off_session'    => 'true',
            'confirm'        => 'true',
        ];
        foreach ($metadata as $k => $v) {
            $form["metadata[{$k}]"] = (string) $v;
        }

        $response = $this->client()->asForm()->post(self::BASE . '/payment_intents', $form);
        $body = $response->json();

        if (!$response->successful()) {
            return ['status' => 'failed', 'reference' => $body['error']['payment_intent']['id'] ?? null, 'raw' => $body];
        }

        $status = ($body['status'] ?? '') === 'succeeded' ? 'paid' : 'failed';
        return [
            'status'    => $status,
            'reference' => $body['id'] ?? null,
            'raw'       => $body,
        ];
    }

    public function verifyWebhook(string $rawBody, string $signatureHeader): array
    {
        if (!$this->webhookSecret) {
            throw new RuntimeException('Stripe webhook secret not configured.');
        }
        $parts = [];
        foreach (explode(',', $signatureHeader) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, null);
            if ($k !== null) $parts[trim($k)] = trim((string) $v);
        }
        $timestamp = $parts['t'] ?? '';
        $expectedSig = $parts['v1'] ?? '';
        $signedPayload = $timestamp . '.' . $rawBody;
        $computed = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        if (!hash_equals($computed, $expectedSig)) {
            throw new RuntimeException('Stripe webhook signature mismatch.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Stripe webhook payload not JSON.');
        }
        return $payload;
    }

    public function fetchStatus(Payment $payment): array
    {
        $sessionId = $payment->gateway_reference;
        if (!$sessionId) {
            return ['status' => 'unknown', 'reference' => null];
        }

        return $this->fetchSessionStatus($sessionId);
    }

    public function parseWebhook(array $payload): array
    {
        $event = $payload['type'] ?? null;
        $object = $payload['data']['object'] ?? [];
        $ref = $object['id'] ?? null;
        $amount = isset($object['amount_total']) ? ((int) $object['amount_total']) / 100 : null;

        $status = match ($event) {
            'checkout.session.completed', 'payment_intent.succeeded' => 'paid',
            'payment_intent.payment_failed'                          => 'failed',
            'charge.refunded'                                        => 'refunded',
            default                                                  => 'pending',
        };

        return [
            'reference' => $ref,
            'status'    => $status,
            'amount'    => $amount,
            'event'     => $event,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->timeout(15);
    }

    private function ensureConfigured(): void
    {
        if (!$this->secretKey) {
            throw new RuntimeException('Stripe secret key not configured.');
        }
    }
}
