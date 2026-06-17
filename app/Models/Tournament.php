<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tournament extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\BelongsToTenant;

    public const STATUSES = [
        'draft', 'registration_open', 'registration_closed',
        'ongoing', 'completed', 'cancelled',
    ];

    /** Allowed forward transitions; cancelled is reachable from any non-terminal status. */
    public const STATUS_TRANSITIONS = [
        'draft'               => ['registration_open', 'cancelled'],
        'registration_open'   => ['registration_closed', 'ongoing', 'cancelled'],
        'registration_closed' => ['ongoing', 'registration_open', 'cancelled'],
        'ongoing'             => ['completed', 'cancelled'],
        'completed'           => [],
        'cancelled'           => [],
    ];

    public const DEFAULT_SETTINGS = [
        'points_to_win' => 11,
        'win_by_2' => true,
        'best_of' => 3,
        'default_match_duration' => 30,
        'court_count' => 2,
        'auto_generate_brackets' => false,
        'allow_late_registration' => false,
        'enable_public_registration' => true,
    ];

    protected $fillable = [
        'tenant_id', 'is_all_branches', 'branch_id',
        'name', 'slug', 'description', 'cover_image', 'logo',
        'venue', 'address', 'google_maps_url', 'organizer_name',
        'contact_phone', 'contact_email',
        'registration_opens_at', 'registration_closes_at', 'starts_at', 'ends_at',
        'max_participants', 'rules', 'waiver', 'entry_fee', 'currency',
        'visibility', 'status', 'settings', 'archived_at', 'created_by',
    ];

    protected $casts = [
        'registration_opens_at' => 'datetime',
        'registration_closes_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'archived_at' => 'datetime',
        'entry_fee' => 'decimal:2',
        'is_all_branches' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\TournamentBranchScope());

        static::creating(function (Tournament $tournament) {
            if (blank($tournament->slug)) {
                $tournament->slug = static::uniqueSlug($tournament->name, (int) $tournament->tenant_id);
            }
        });
    }

    public static function uniqueSlug(string $name, int $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($name, 140, '')) ?: 'tournament';
        $slug = $base;
        $i = 2;
        while (static::withTrashed()
            ->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'status', 'visibility', 'entry_fee', 'venue',
                'registration_opens_at', 'registration_closes_at',
                'starts_at', 'ends_at', 'max_participants', 'archived_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('tournament');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(TournamentDivision::class)->orderBy('sort_order')->orderBy('id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default ?? (self::DEFAULT_SETTINGS[$key] ?? null));
    }

    public function isRegistrationOpen(): bool
    {
        if ($this->archived_at) {
            return false;
        }
        if ($this->status !== 'registration_open') {
            return false;
        }
        if ($this->registration_opens_at && $this->registration_opens_at->isFuture()) {
            return false;
        }
        if ($this->registration_closes_at && $this->registration_closes_at->isPast()) {
            return (bool) $this->getSetting('allow_late_registration', false);
        }
        return true;
    }

    public function effectiveEntryFee(TournamentDivision $division): float
    {
        return (float) ($division->entry_fee ?? $this->entry_fee);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopePublicVisible($query)
    {
        return $query->where('visibility', 'public');
    }

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'registration_open' => 'green',
            'registration_closed' => 'amber',
            'ongoing' => 'blue',
            'completed' => 'emerald',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
