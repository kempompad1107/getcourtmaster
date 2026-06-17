<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayMongoGateway implements PaymentGateway
{
    private const BASE = 'https://api.paymongo.com/v1';

    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $webhookSecret,
    ) {}

    public function name(): string
    {
        return 'paymongo';
    }

    public function createCheckout(Payment $payment, array $options = []): array
    {
        $result = $this->createGenericCheckout((float) $payment->amount, array_merge([
            'description' => "Payment #{$payment->id}",
            'line_name'   => 'Court rental / order',
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

        $amountCentavos = (int) round($amount * 100);
        $attributes = [
            'send_email_receipt' => false,
            'show_description'   => true,
            'show_line_items'    => true,
            'description'        => $options['description'] ?? 'Payment',
            'line_items' => [[
                'name'     => $options['line_name'] ?? 'Payment',
                'quantity' => 1,
                'currency' => strtoupper($options['currency'] ?? 'PHP'),
                'amount'   => $amountCentavos,
            ]],
            'payment_method_types' => $options['methods'] ?? ['gcash', 'paymaya', 'card', 'qrph'],
        ];
        if (!empty($options['success_url'])) {
            $attributes['success_url'] = $options['success_url'];
        }
        if (!empty($options['cancel_url'])) {
            $attributes['cancel_url'] = $options['cancel_url'];
        }
        if (!empty($options['reference'])) {
            $attributes['reference_number'] = (string) $options['reference'];
        }

        $response = $this->client()->post(self::BASE . '/checkout_sessions', ['data' => ['attributes' => $attributes]]);

        if (!$response->successful()) {
            throw new RuntimeException('PayMongo checkout failed: ' . $response->body());
        }

        $body = $response->json();

        return [
            'checkout_url' => $body['data']['attributes']['checkout_url'] ?? null,
            'reference'    => $body['data']['id'] ?? null,
            'raw'          => $body,
        ];
    }

    public function fetchSessionStatus(string $reference): array
    {
        $this->ensureConfigured();

        $response = $this->client()->get(self::BASE . "/checkout_sessions/{$reference}");
        if (!$response->successful()) {
            return ['status' => 'unknown', 'reference' => $reference];
        }

        $payments = $response->json('data.attributes.payments') ?? [];
        $status = 'pending';
        foreach ($payments as $p) {
            $s = $p['attributes']['status'] ?? null;
            if ($s === 'paid')   { $status = 'paid';   break; }
            if ($s === 'failed') { $status = 'failed'; }
        }

        return ['status' => $status, 'reference' => $reference];
    }

    public function chargeOffSession(string $paymentMethodToken, float $amount, string $currency = 'PHP', array $metadata = []): array
    {
        $this->ensureConfigured();

        // PayMongo flow: create a PaymentIntent attached to a saved payment method,
        // confirm it server-side. Currency must be 'PHP' (only supported live currency).
        $payload = [
            'data' => [
                'attributes' => [
                    'amount'                 => (int) round($amount * 100),
                    'currency'               => strtoupper($currency),
                    'payment_method_allowed' => ['card', 'gcash', 'paymaya'],
                    'metadata'               => array_map('strval', $metadata),
                ],
            ],
        ];

        $intent = $this->client()->post(self::BASE . '/payment_intents', $payload);
        if (!$intent->successful()) {
            return ['status' => 'failed', 'reference' => null, 'raw' => $intent->json()];
        }
        $intentId = $intent->json('data.id');

        $attach = $this->client()->post(self::BASE . "/payment_intents/{$intentId}/attach", [
            'data' => ['attributes' => [
                'payment_method' => $paymentMethodToken,
                'client_key'     => $intent->json('data.attributes.client_key'),
            ]],
        ]);

        $body = $attach->json();
        $status = $attach->json('data.attributes.status') === 'succeeded' ? 'paid' : 'failed';

        return [
            'status'    => $status,
            'reference' => $intentId,
            'raw'       => $body,
        ];
    }

    public function verifyWebhook(string $rawBody, string $signatureHeader): array
    {
        // PayMongo signature header: "t=<timestamp>,te=<test_sig>,li=<live_sig>"
        if (!$this->webhookSecret) {
            throw new RuntimeException('PayMongo webhook secret not configured.');
        }
        $parts = [];
        foreach (explode(',', $signatureHeader) as $kv) {
            [$k, $v] = array_pad(explode('=', $kv, 2), 2, null);
            if ($k !== null) $parts[trim($k)] = trim((string) $v);
        }
        $timestamp = $parts['t'] ?? '';
        $live = $parts['li'] ?? '';
        $test = $parts['te'] ?? '';
        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $this->webhookSecret);

        $valid = hash_equals($expected, $live) || hash_equals($expected, $test);
        if (!$valid) {
            throw new RuntimeException('PayMongo webhook signature mismatch.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new RuntimeException('PayMongo webhook payload not JSON.');
        }
        return $payload;
    }

    public function fetchStatus(Payment $payment): array
    {
        $sessionId = $payment->gateway_reference;
        if (!$sessionId) {
            return ['status' => 'unknown', 'reference' => null];
        }

        // PayMongo: payments[] is empty until the customer pays; once paid, each
        // entry has attributes.status of 'paid' (or 'failed').
        return $this->fetchSessionStatus($sessionId);
    }

    public function parseWebhook(array $payload): array
    {
        // Event examples: source.chargeable, payment.paid, payment.failed
        $event   = $payload['data']['attributes']['type'] ?? null;
        $object  = $payload['data']['attributes']['data'] ?? [];
        $attrs   = $object['attributes'] ?? [];
        $ref     = $object['id'] ?? null;
        $amount  = isset($attrs['amount']) ? ((int) $attrs['amount']) / 100 : null;

        $status = match (true) {
            $event === 'payment.paid'                  => 'paid',
            $event === 'payment.failed'                => 'failed',
            $event === 'checkout_session.payment.paid' => 'paid',
            default => 'pending',
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
        return Http::withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    private function ensureConfigured(): void
    {
        if (!$this->secretKey) {
            throw new RuntimeException('PayMongo secret key not configured.');
        }
    }
}
