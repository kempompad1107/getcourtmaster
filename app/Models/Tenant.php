<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'email', 'phone', 'address',
        'city', 'state', 'country', 'timezone', 'currency', 'logo',
        'plan', 'status', 'trial_ends_at', 'plan_expires_at',
        'settings', 'features', 'stripe_customer_id', 'subscription_id', 'commission_rate',
        'payment_credentials', 'webhook_token', 'mail_credentials',
        'is_demo',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'plan_expires_at' => 'datetime',
        'settings' => 'array',
        'features' => 'array',
        'commission_rate' => 'decimal:2',
        // Whole JSON blob is encrypted at rest. Sensitive secret keys live inside.
        'payment_credentials' => 'encrypted:array',
        'mail_credentials' => 'encrypted:array',
        'is_demo' => 'boolean',
    ];

    protected $hidden = ['payment_credentials', 'mail_credentials'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)->where('status', 'active')->latest();
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Whether this tenant wants the given owner/staff notification category
     * (e.g. "notify_low_stock"). Toggles live under settings.notifications and
     * default to ON, so a venue that never visited the Notifications tab keeps
     * receiving every alert (preserves pre-toggle behaviour).
     */
    public function wantsNotification(string $key): bool
    {
        return (bool) data_get($this->settings, "notifications.$key", true);
    }

    /** Optional address that should also receive owner/staff alerts (CC). */
    public function notificationEmail(): ?string
    {
        $email = data_get($this->settings, 'notifications.notification_email');
        return is_string($email) && $email !== '' ? $email : null;
    }

    /** Whether this tenant wants business email at all (default ON). */
    public function mailEnabled(): bool
    {
        return (bool) data_get($this->settings, 'notifications.email_enabled', true);
    }

    /** Whether the tenant insists on their own SMTP (warn if falling back). */
    public function requireSmtp(): bool
    {
        return (bool) data_get($this->settings, 'mail.require_smtp', false);
    }

    /**
     * Decrypted SMTP credentials, but only when complete enough to connect.
     * Returns null if any required field is missing (host/port/username/password).
     */
    public function smtpCredentials(): ?array
    {
        $c = $this->mail_credentials;
        if (!is_array($c)) {
            return null;
        }
        foreach (['host', 'port', 'username', 'password'] as $required) {
            if (empty($c[$required])) {
                return null;
            }
        }
        return $c;
    }

    /** True when this tenant will send through its OWN SMTP this request. */
    public function usesOwnSmtp(): bool
    {
        return $this->mailEnabled() && $this->smtpCredentials() !== null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getMainBranch(): ?Branch
    {
        return $this->branches()->where('is_main', true)->first();
    }

    /**
     * Read this tenant's credentials for a given gateway (e.g. 'paymongo', 'stripe').
     * Returns null when the tenant has not configured + enabled that gateway.
     */
    public function paymentCredentials(string $gateway): ?array
    {
        $creds = $this->payment_credentials[$gateway] ?? null;
        if (!is_array($creds)) {
            return null;
        }
        if (empty($creds['enabled'])) {
            return null;
        }
        return $creds;
    }

    public function hasGatewayConfigured(string $gateway): bool
    {
        $creds = $this->payment_credentials[$gateway] ?? null;
        if (!is_array($creds) || empty($creds['enabled'])) {
            return false;
        }
        return match ($gateway) {
            'paymongo' => !empty($creds['secret_key']),
            'stripe'   => !empty($creds['secret']),
            default    => false,
        };
    }

    public function setGatewayCredentials(string $gateway, array $creds): void
    {
        $all = $this->payment_credentials ?? [];
        $all[$gateway] = array_merge($all[$gateway] ?? [], $creds);
        $this->payment_credentials = $all;
    }

    public function ensureWebhookToken(): string
    {
        if (!$this->webhook_token) {
            $this->forceFill(['webhook_token' => bin2hex(random_bytes(24))])->save();
        }
        return $this->webhook_token;
    }
}
