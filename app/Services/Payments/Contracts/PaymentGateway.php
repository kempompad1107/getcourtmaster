<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;

interface PaymentGateway
{
    /**
     * Create a checkout / payment session and return a structured response:
     * [
     *   'checkout_url' => string|null,
     *   'reference'    => string,
     *   'raw'          => array,
     * ]
     */
    public function createCheckout(Payment $payment, array $options = []): array;

    /**
     * Create a checkout session for an arbitrary amount that is NOT backed by a
     * tenant Payment record — used for platform-level SaaS subscription dues.
     * Returns ['checkout_url' => string|null, 'reference' => string, 'raw' => array].
     *
     * Recognised $options: description, line_name, success_url, cancel_url,
     * currency, methods.
     */
    public function createGenericCheckout(float $amount, array $options = []): array;

    /**
     * Fetch the status of a checkout session by its gateway reference (session id).
     * Returns ['status' => 'paid'|'failed'|'pending'|'unknown', 'reference' => string|null].
     */
    public function fetchSessionStatus(string $reference): array;

    /**
     * Off-session charge against a saved payment method (subscription renewal).
     * Returns ['status' => 'paid'|'failed', 'reference' => string|null, 'raw' => array].
     * Throws on hard error (network, auth, invalid token).
     */
    public function chargeOffSession(string $paymentMethodToken, float $amount, string $currency = 'PHP', array $metadata = []): array;

    /**
     * Verify webhook signature; returns the decoded payload, or throws if invalid.
     */
    public function verifyWebhook(string $rawBody, string $signatureHeader): array;

    /**
     * Map a webhook payload into a normalized status update:
     * [
     *   'reference' => string,
     *   'status'    => 'paid' | 'failed' | 'pending' | 'refunded',
     *   'amount'    => float|null,
     * ]
     */
    public function parseWebhook(array $payload): array;

    public function name(): string;

    /**
     * Best-effort status fetch for a Payment we previously sent to the gateway.
     * Used to confirm status immediately on the success-redirect and as a
     * fallback when a webhook delivery is delayed or missed.
     *
     * Returns ['status' => 'paid'|'failed'|'pending'|'unknown', 'reference' => string|null].
     */
    public function fetchStatus(Payment $payment): array;
}
