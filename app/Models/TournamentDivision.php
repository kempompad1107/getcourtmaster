<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TournamentDivision extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\BelongsToTenant;

    public const FORMATS = [
        'single_elimination' => 'Single Elimination',
        'double_elimination' => 'Double Elimination',
        'round_robin' => 'Round Robin',
        'group_stage' => 'Group Stage',
        'pool_play' => 'Pool Play',
    ];

    public const GENDERS = [
        'men' => "Men's",
        'women' => "Women's",
        'mixed' => 'Mixed',
        'open' => 'Open',
    ];

    protected $fillable = [
        'tenant_id', 'tournament_id', 'name', 'description', 'skill_level',
        'min_age', 'max_age', 'gender', 'team_size', 'max_entries', 'entry_fee',
        'bracket_format', 'seeding_method', 'bracket_settings',
        'bracket_generated_at', 'sort_order',
    ];

    protected $casts = [
        'entry_fee' => 'decimal:2',
        'bracket_settings' => 'array',
        'bracket_generated_at' => 'datetime',
        'tournament_id' => 'integer',
        'min_age' => 'integer',
        'max_age' => 'integer',
        'team_size' => 'integer',
        'max_entries' => 'integer',
        'sort_order' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'skill_level', 'gender', 'team_size', 'max_entries',
                'entry_fee', 'bracket_format', 'seeding_method', 'bracket_generated_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('tournament_division');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class, 'division_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class, 'division_id')->orderBy('sort_order');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'division_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TournamentTeamMember::class, 'division_id');
    }

    public function confirmedTeams(): HasMany
    {
        return $this->teams()->where('status', 'confirmed');
    }

    public function activeTeams(): HasMany
    {
        return $this->teams()->whereIn('status', ['pending', 'confirmed']);
    }

    public function bracketLocked(): bool
    {
        return $this->bracket_generated_at !== null;
    }

    public function isSingles(): bool
    {
        return (int) $this->team_size === 1;
    }

    public function isFull(): bool
    {
        return $this->max_entries !== null
            && $this->activeTeams()->count() >= $this->max_entries;
    }

    public function formatLabel(): string
    {
        return self::FORMATS[$this->bracket_format] ?? '—';
    }
}
