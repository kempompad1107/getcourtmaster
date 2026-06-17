<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TournamentTeam extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tournament_id', 'division_id', 'name', 'seed', 'group_id',
        'status', 'registered_via', 'registered_by', 'withdrawn_at', 'notes',
    ];

    protected $casts = [
        'withdrawn_at' => 'datetime',
        'tournament_id' => 'integer',
        'division_id' => 'integer',
        'group_id' => 'integer',
        'seed' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'seed', 'status', 'group_id', 'withdrawn_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('tournament_team');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(TournamentDivision::class, 'division_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TournamentTeamMember::class, 'team_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /** Entry-fee payments for all current members of this team. */
    public function payments(): Builder
    {
        return Payment::query()
            ->where('payable_type', TournamentTeamMember::class)
            ->whereIn('payable_id', $this->members()->pluck('id'));
    }

    public function hasUser(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true);
    }

    public function averageRating(): ?float
    {
        $avg = $this->members()->whereNotNull('rating')->avg('rating');
        return $avg === null ? null : (float) $avg;
    }

    public function finishedMatches(): Builder
    {
        return TournamentMatch::query()
            ->where(fn ($q) => $q->where('team1_id', $this->id)->orWhere('team2_id', $this->id))
            ->whereIn('status', ['finished', 'walkover']);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'amber',
            'confirmed' => 'green',
            'withdrawn' => 'gray',
            'disqualified' => 'red',
            default => 'gray',
        };
    }
}
