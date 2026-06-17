<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Membership extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\BelongsToTenant;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'plan_id', 'remaining_credits',
                'starts_at', 'expires_at',
                'frozen_at', 'frozen_until',
                'cancelled_at', 'auto_renew',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('membership');
    }

    protected $fillable = [
        'tenant_id', 'customer_id', 'plan_id', 'membership_number',
        'status', 'remaining_credits', 'starts_at', 'expires_at',
        'frozen_at', 'frozen_until', 'cancelled_at', 'auto_renew', 'referred_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'frozen_at' => 'datetime',
        'frozen_until' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MembershipTransaction::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->expires_at->isFuture();
    }

    public function isFrozen(): bool
    {
        return $this->status === 'frozen';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->expires_at->isPast();
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->expires_at, false));
    }

    public function getCreditsLabelAttribute(): string
    {
        $m = (int) $this->remaining_credits;
        return intdiv($m, 60) . 'h ' . ($m % 60) . 'm';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->active()
            ->where('expires_at', '<=', now()->addDays($days));
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Membership $membership) {
            if (empty($membership->membership_number)) {
                $membership->membership_number = 'MEM-' . strtoupper(uniqid());
            }
        });
    }
}
