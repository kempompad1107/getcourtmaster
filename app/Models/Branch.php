<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'address', 'city', 'phone',
        'email', 'map_url', 'operating_hours', 'is_main', 'is_active', 'latitude', 'longitude',
    ];

    protected $casts = [
        'operating_hours' => 'array',
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function posOrders(): HasMany
    {
        return $this->hasMany(PosOrder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope route-model binding to the current user's tenant so a 1:1 leak
     * (404 vs 403) does not let attackers enumerate which branch IDs exist
     * across other tenants. Super admins bypass this scope.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();
        $query = static::where($field, $value);

        $user = auth()->user();
        if ($user && ! $user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query->first();
    }

    public function isOpenAt(\DateTime $dateTime): bool
    {
        $dayOfWeek = strtolower($dateTime->format('l'));
        $time = $dateTime->format('H:i');
        $hours = $this->operating_hours[$dayOfWeek] ?? null;

        if (!$hours || !($hours['is_open'] ?? false)) {
            return false;
        }

        return $time >= $hours['open'] && $time <= $hours['close'];
    }
}
