<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Tenant;
use App\Services\Payments\Contracts\PaymentGateway;
use InvalidArgumentException;
use RuntimeException;

class GatewayManager
{
    /** Per-tenant resolved gateway cache: ["{tenantId}:{name}" => PaymentGateway]. */
    private array $resolved = [];

    /** Platform-level gateway cache (SaaS subscription billing). */
    private array $platformResolved = [];

    public function __construct(
        /** @var array<string, mixed> Platform-level credentials, keyed by gateway name. */
        private readonly array $platformConfig = [],
    ) {}

    /**
     * Resolve a gateway for the *current request tenant*. Use this for booking,
     * POS, membership, and walk-in payments. Throws if no tenant is in scope.
     */
    public function gateway(string $name): PaymentGateway
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if (!$tenant instanceof Tenant) {
            throw new RuntimeException("No tenant in scope; use forTenant(), forPayment(), or platform().");
        }
        return $this->forTenant($tenant, $name);
    }

    public function forPayment(Payment $payment, string $name): PaymentGateway
    {
        $tenant = $payment->tenant ?? Tenant::find($payment->tenant_id);
        if (!$tenant) {
            throw new RuntimeException("Payment {$payment->id} has no resolvable tenant.");
        }
        return $this->forTenant($tenant, $name);
    }

    public function forTenant(Tenant $tenant, string $name): PaymentGateway
    {
        $name = $this->normalize($name);
        $key = "{$tenant->id}:{$name}";

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $creds = $tenant->paymentCredentials($name);
        if (!$creds) {
            throw new InvalidArgumentException(
                "Tenant {$tenant->id} has not configured the {$name} gateway."
            );
        }

        return $this->resolved[$key] = $this->build($name, $creds);
    }

    /**
     * Resolve the *platform's* gateway — used by BillingService to charge tenants
     * for their SaaS subscription. Never uses tenant credentials.
     */
    public function platform(string $name): PaymentGateway
    {
        $name = $this->normalize($name);
        if (isset($this->platformResolved[$name])) {
            return $this->platformResolved[$name];
        }

        $creds = $this->platformConfig[$name] ?? null;
        if (!is_array($creds)) {
            throw new InvalidArgumentException("Platform gateway {$name} not configured.");
        }

        return $this->platformResolved[$name] = $this->build($name, $creds);
    }

    /**
     * List the gateways the given tenant has enabled (for UI checkout method pickers).
     *
     * @return array<int, string>
     */
    public function availableForTenant(Tenant $tenant): array
    {
        $out = [];
        foreach (['paymongo', 'stripe'] as $name) {
            if ($tenant->hasGatewayConfigured($name)) {
                $out[] = $name;
            }
        }
        return $out;
    }

    private function build(string $name, array $creds): PaymentGateway
    {
        return match ($name) {
            'paymongo' => new PayMongoGateway(
                $creds['secret_key']     ?? null,
                $creds['webhook_secret'] ?? null,
            ),
            'stripe' => new StripeGateway(
                $creds['secret']         ?? null,
                $creds['webhook_secret'] ?? null,
            ),
            default => throw new InvalidArgumentException("Unknown payment gateway: {$name}"),
        };
    }

    private function normalize(string $name): string
    {
        $name = strtolower($name);
        // PayMongo handles GCash/Maya/PH-card internally.
        return match ($name) {
            'gcash', 'maya', 'paymaya', 'card_ph' => 'paymongo',
            default => $name,
        };
    }
}
