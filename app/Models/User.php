<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\CausesActivity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, CausesActivity, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'email', 'phone', 'user_type', 'is_active',
                'tenant_id', 'two_factor_enabled', 'referred_by',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('user');
    }

    protected $fillable = [
        'tenant_id', 'home_branch_id', 'name', 'email', 'password', 'phone', 'avatar',
        'gender', 'date_of_birth',
        // NOTE: wallet_balance is intentionally NOT mass-assignable — it only ever
        // moves through WalletService (locked increment/decrement). Keeping it out of
        // $fillable prevents a stray update([...]) from minting spendable balance (L-3).
        'user_type', 'referral_code', 'referred_by',
        'is_active', 'last_login_at', 'google_id', 'facebook_id',
        'two_factor_enabled', 'two_factor_secret',
        'two_factor_recovery_codes', 'two_factor_confirmed_at',
        'notification_preferences', 'preferences',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'wallet_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'notification_preferences' => 'array',
        'preferences' => 'array',
        'date_of_birth' => 'date',
    ];

    /** Age in whole years as of $asOf (default now), or null if DOB unknown. */
    public function ageAt(?\DateTimeInterface $asOf = null): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }
        return (int) $this->date_of_birth->diffInYears($asOf ?? now());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'customer_id');
    }

    public function activeMembership(): HasOne
    {
        return $this->hasOne(Membership::class, 'customer_id')
            ->where('status', 'active')
            ->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function routeNotificationForSms(?Notification $notification = null): ?string
    {
        return $this->phone;
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin';
    }

    public function isBusinessOwner(): bool
    {
        return $this->user_type === 'business_owner';
    }

    public function isStaff(): bool
    {
        return $this->user_type === 'staff';
    }

    public function isCustomer(): bool
    {
        return $this->user_type === 'customer';
    }

    public function scopeOfTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getAvatarUrlAttribute(): string
    {
        // file_url() returns the storage URL for uploaded avatars and passes
        // through absolute URLs (e.g. avatars from a social login provider).
        return file_url($this->avatar)
            ?? 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=059669&color=fff';
    }
}
