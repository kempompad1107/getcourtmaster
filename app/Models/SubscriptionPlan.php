<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description',
        'price_monthly', 'price_yearly',
        'trial_days', 'features',
        'max_courts', 'max_staff', 'max_branches', 'max_customers',
        'max_bookings_per_month',
        'is_active', 'sort_order',
        'stripe_monthly_price_id', 'stripe_yearly_price_id',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_yearly'  => 'decimal:2',
        'features'      => 'array',
        'is_active'     => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    public function tenants(): HasManyThrough
    {
        return $this->hasManyThrough(
            Tenant::class,
            TenantSubscription::class,
            'plan_id',
            'id',
            'id',
            'tenant_id'
        );
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }
}
