<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'billing_cycle',
        'price', 'court_credits', 'discount_percent', 'perks',
        'is_vip', 'max_freeze_days', 'freeze_count_per_year', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'perks' => 'array',
        'is_vip' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'plan_id');
    }

    public function getDurationDaysAttribute(): int
    {
        return match ($this->billing_cycle) {
            'monthly' => 30,
            'quarterly' => 90,
            'yearly' => 365,
            'lifetime' => 36500,
            default => 30,
        };
    }

    public function getCourtHoursAttribute(): int
    {
        return intdiv((int) $this->court_credits, 60);
    }

    public function getCreditsLabelAttribute(): string
    {
        $m = (int) $this->court_credits;
        return intdiv($m, 60) . 'h ' . ($m % 60) . 'm';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
