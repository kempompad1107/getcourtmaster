<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'plan_id', 'billing_cycle', 'status', 'amount',
        'starts_at', 'ends_at', 'trial_ends_at', 'cancelled_at',
        'payment_gateway', 'external_subscription_id',
        'renews_at', 'payment_method_token', 'last_charge_at',
    ];

    protected $hidden = ['payment_method_token'];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'renews_at' => 'date',
        'last_charge_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class, 'subscription_id');
    }

    /** How many days before renews_at an owner may pay ahead. */
    public const RENEWAL_WINDOW_DAYS = 7;

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    /** True once we're within RENEWAL_WINDOW_DAYS of the renewal date (or past it). */
    public function withinRenewalWindow(): bool
    {
        return $this->renews_at
            && $this->renews_at->copy()->subDays(self::RENEWAL_WINDOW_DAYS)->isPast();
    }

    /** The owner may self-renew an active sub once inside the renewal window. */
    public function isRenewable(): bool
    {
        return $this->status === 'active' && $this->withinRenewalWindow();
    }

    /** When the renew action becomes available (renews_at minus the window). */
    public function renewalOpensAt(): ?\Illuminate\Support\Carbon
    {
        return $this->renews_at?->copy()->subDays(self::RENEWAL_WINDOW_DAYS);
    }
}
