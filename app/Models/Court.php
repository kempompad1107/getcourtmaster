<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Court extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, LogsActivity, InteractsWithMedia, \App\Models\Concerns\BelongsToBranch, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'code', 'type', 'status',
        'description', 'amenities', 'capacity', 'base_hourly_rate',
        'peak_hourly_rate', 'weekend_hourly_rate',
        'peak_hours', 'holiday_rates', 'operating_hours', 'min_booking_minutes',
        'max_booking_minutes', 'buffer_minutes', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'amenities' => 'array',
        'peak_hours' => 'array',
        'holiday_rates' => 'array',
        'operating_hours' => 'array',
        'base_hourly_rate' => 'decimal:2',
        'peak_hourly_rate' => 'decimal:2',
        'weekend_hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'code', 'type', 'status', 'is_active',
                'base_hourly_rate', 'peak_hourly_rate',
                'min_booking_minutes', 'max_booking_minutes', 'buffer_minutes',
                'branch_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('court');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function pricingRules(): HasMany
    {
        return $this->hasMany(CourtPricingRule::class);
    }

    public function activeTimer(): HasOne
    {
        return $this->hasOne(BookingTimer::class)->whereIn('status', ['running', 'paused']);
    }

    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class)
            ->where('status', 'active')
            ->latest('id');
    }

    public function nextBookingToday(): HasOne
    {
        return $this->hasOne(Booking::class)->ofMany(
            ['start_time' => 'min'],
            fn($q) => $q->where('booking_date', today())
                        ->whereIn('status', ['confirmed', 'pending'])
                        ->where('start_time', '>', now()->format('H:i:s'))
        );
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isOccupied(): bool
    {
        return $this->status === 'occupied';
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available')->where('is_active', true);
    }

    public function scopeOfTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getRateForSlot(\DateTime $start, \DateTime $end): float
    {
        $applicable = $this->pricingRules()
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->get();

        foreach ($applicable as $rule) {
            if ($rule->appliesTo($start, $end)) {
                return (float) $rule->rate;
            }
        }

        $dayOfWeek = (int) $start->format('N');
        if ($dayOfWeek === 6 || $dayOfWeek === 7) {
            return (float) ($this->weekend_hourly_rate ?? $this->base_hourly_rate);
        }

        $time = $start->format('H:i');
        $eveningStart = $this->tenant?->getSetting('evening_start', '18:00');
        $eveningEnd   = $this->tenant?->getSetting('evening_end',   '22:00');

        if ($eveningStart && $eveningEnd && $time >= $eveningStart && $time < $eveningEnd) {
            return (float) ($this->peak_hourly_rate ?? $this->base_hourly_rate);
        }

        // Daytime ("Daylight rate" in the court form) is the base rate. NOTE: the
        // court form exposes only base (daylight) / peak (evening) / weekend rates
        // — there is no UI for off_peak_hourly_rate, so it must NOT shadow the
        // base rate here, otherwise stale seeded off-peak values silently override
        // the configured daylight rate.
        return (float) $this->base_hourly_rate;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'available' => 'green',
            'occupied' => 'red',
            'reserved' => 'yellow',
            'maintenance' => 'orange',
            'closed' => 'gray',
            default => 'gray',
        };
    }
}
