<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TournamentMatch extends Model
{
    use HasFactory, LogsActivity, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tournament_id', 'division_id', 'group_id',
        'match_number', 'bracket_type', 'round', 'round_name', 'bracket_pos',
        'team1_id', 'team2_id', 'slot_labels', 'winner_team_id', 'loser_team_id',
        'winner_advances_to_match_id', 'winner_advances_to_slot',
        'loser_advances_to_match_id', 'loser_advances_to_slot',
        'court_id', 'referee_name', 'scheduled_at', 'started_at', 'finished_at',
        'status', 'scores', 'notes',
    ];

    protected $casts = [
        'slot_labels' => 'array',
        'scores' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        // Cast keys to int so the strict (===) team/slot comparisons in
        // BracketService, TournamentMatchService and TournamentRankingService
        // stay correct even when PDO returns column values as strings.
        'match_number' => 'integer',
        'round' => 'integer',
        'bracket_pos' => 'integer',
        'group_id' => 'integer',
        'team1_id' => 'integer',
        'team2_id' => 'integer',
        'winner_team_id' => 'integer',
        'loser_team_id' => 'integer',
        'winner_advances_to_match_id' => 'integer',
        'winner_advances_to_slot' => 'integer',
        'loser_advances_to_match_id' => 'integer',
        'loser_advances_to_slot' => 'integer',
        'court_id' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'team1_id', 'team2_id', 'winner_team_id',
                'court_id', 'referee_name', 'scheduled_at', 'scores',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('tournament_match');
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

    public function team1(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'team2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'winner_team_id');
    }

    public function loser(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'loser_team_id');
    }

    public function winnerAdvancesTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'winner_advances_to_match_id');
    }

    public function loserAdvancesTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'loser_advances_to_match_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }


    public function isDecided(): bool
    {
        return in_array($this->status, ['finished', 'walkover', 'bye'], true);
    }

    public function isPlayable(): bool
    {
        return $this->team1_id !== null
            && $this->team2_id !== null
            && in_array($this->status, ['pending', 'scheduled', 'called', 'playing'], true);
    }

    public function hasTeam(int $teamId): bool
    {
        return $this->team1_id === $teamId || $this->team2_id === $teamId;
    }

    /** 1 or 2 when the team occupies a slot, null otherwise. */
    public function slotOf(int $teamId): ?int
    {
        return match ($teamId) {
            $this->team1_id => 1,
            $this->team2_id => 2,
            default => null,
        };
    }

    public function scoreSummary(): string
    {
        if (!is_array($this->scores) || $this->scores === []) {
            return $this->status === 'walkover' ? 'W/O' : '—';
        }
        return collect($this->scores)
            ->map(fn ($set) => ($set['team1'] ?? 0) . '–' . ($set['team2'] ?? 0))
            ->implode(', ');
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'scheduled' => 'blue',
            'called' => 'amber',
            'playing' => 'green',
            'finished' => 'emerald',
            'walkover' => 'purple',
            'bye' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
