<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Decides which mailer a tenant's BUSINESS notifications use, and registers a
 * runtime "tenant_smtp" mailer from the tenant's own credentials when present.
 *
 * Hybrid model:
 *   - email disabled        -> "array" (discard: no send, no throw)
 *   - own SMTP configured   -> "tenant_smtp" (branded sender, isolated)
 *   - otherwise             -> the platform default mailer (.env fallback)
 *
 * Registered as a singleton so the platform default is snapshotted once, before
 * any per-request override mutates config('mail.default').
 */
class TenantMailManager
{
    private ?string $platformDefault = null;

    /** The original .env default mailer, captured before any override. */
    public function platformDefault(): string
    {
        return $this->platformDefault ??= (string) config('mail.default', 'smtp');
    }

    /** Resolve (and register, if needed) the mailer name for this tenant. */
    public function resolveMailerFor(Tenant $tenant): string
    {
        if (!$tenant->mailEnabled()) {
            return 'array';
        }

        $creds = $tenant->smtpCredentials();
        if ($creds === null) {
            return $this->platformDefault();
        }

        config([
            'mail.mailers.tenant_smtp' => [
                'transport'    => 'smtp',
                'host'         => $creds['host'],
                'port'         => (int) $creds['port'],
                'encryption'   => $creds['encryption'] ?? null,
                'username'     => $creds['username'],
                'password'     => $creds['password'],
                'timeout'      => null,
                'local_domain' => parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST),
            ],
        ]);

        if (!empty($creds['from_address'])) {
            config([
                'mail.from.address' => $creds['from_address'],
                'mail.from.name'    => $creds['from_name'] ?? config('mail.from.name'),
            ]);
        }

        return 'tenant_smtp';
    }

    /** False only when the tenant has turned email off entirely. */
    public function wouldDeliver(Tenant $tenant): bool
    {
        return $tenant->mailEnabled();
    }

    /**
     * Apply the tenant's mailer as the default for the current runtime.
     * Snapshots the platform default first (idempotent enough for one request).
     */
    public function apply(Tenant $tenant): void
    {
        $this->platformDefault();                       // capture before overriding
        config(['mail.default' => $this->resolveMailerFor($tenant)]);
    }
}
