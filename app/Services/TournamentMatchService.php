<?php

namespace App\Services;

use App\Models\TournamentMatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentMatchService
{
    public function __construct(private readonly BracketService $brackets) {}

    /**
     * Record a final score and advance the winner (and loser, double elim).
     *
     * @param array $sets [['team1' => 11, 'team2' => 7], ...]
     */
    public function recordResult(TournamentMatch $match, array $sets, int $winnerTeamId, bool $override = false): TournamentMatch
    {
        return DB::transaction(function () use ($match, $sets, $winnerTeamId, $override) {
            // Lock + re-read so two staff submitting at once can't double-advance.
            $match = TournamentMatch::whereKey($match->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($match->status, ['finished', 'walkover', 'bye', 'cancelled'], true)) {
                throw ValidationException::withMessages(['match' => 'This match already has a result. Undo it first to correct the score.']);
            }
            if (!$match->team1_id || !$match->team2_id) {
                throw ValidationException::withMessages(['match' => 'Both teams must be set before recording a result.']);
            }
            if (!$match->hasTeam($winnerTeamId)) {
                throw ValidationException::withMessages(['winner_team_id' => 'The winner must be one of the two teams in this match.']);
            }

            $settings = $match->tournament;
            $bestOf = (int) $settings->getSetting('best_of', 3);
            $pointsToWin = (int) $settings->getSetting('points_to_win', 11);
            $winBy2 = (bool) $settings->getSetting('win_by_2', true);

            $sets = array_values(array_map(fn ($s) => [
                'team1' => (int) ($s['team1'] ?? 0),
                'team2' => (int) ($s['team2'] ?? 0),
            ], $sets));

            if ($sets === [] || count($sets) > max($bestOf, 5)) {
                throw ValidationException::withMessages(['sets' => "Enter between 1 and {$bestOf} game scores."]);
            }

            $wins1 = 0;
            $wins2 = 0;
            foreach ($sets as $i => $set) {
                $n = $i + 1;
                if ($set['team1'] === $set['team2']) {
                    throw ValidationException::withMessages(['sets' => "Game {$n} cannot be a tie."]);
                }
                if (!$override) {
                    $hi = max($set['team1'], $set['team2']);
                    $lo = min($set['team1'], $set['team2']);
                    if ($hi < $pointsToWin) {
                        throw ValidationException::withMessages(['sets' => "Game {$n}: the winning side must reach {$pointsToWin} points (use override for time-capped games)."]);
                    }
                    if ($winBy2 && $hi - $lo < 2 && $hi <= $pointsToWin) {
                        throw ValidationException::withMessages(['sets' => "Game {$n}: must be won by 2 points (use override for time-capped games)."]);
                    }
                }
                $set['team1'] > $set['team2'] ? $wins1++ : $wins2++;
            }

            $needed = intdiv($bestOf, 2) + 1;
            if (!$override && max($wins1, $wins2) < $needed && count($sets) < $bestOf) {
                throw ValidationException::withMessages(['sets' => "Best of {$bestOf}: record games until one side wins {$needed}."]);
            }

            $derivedWinnerId = $wins1 >= $wins2 ? $match->team1_id : $match->team2_id;
            if ($wins1 === $wins2) {
                $derivedWinnerId = $winnerTeamId; // override path for tied game counts
                if (!$override) {
                    throw ValidationException::withMessages(['sets' => 'Game wins are tied — record a deciding game.']);
                }
            }
            if ($derivedWinnerId !== $winnerTeamId) {
                throw ValidationException::withMessages(['winner_team_id' => 'The selected winner does not match the game scores.']);
            }

            $match->forceFill([
                'scores' => $sets,
                'winner_team_id' => $winnerTeamId,
                'loser_team_id' => $match->team1_id === $winnerTeamId ? $match->team2_id : $match->team1_id,
                'status' => 'finished',
                'started_at' => $match->started_at ?? now(),
                'finished_at' => now(),
            ])->save();

            $this->brackets->advance($match->refresh());
            $this->brackets->resolveByes($match->division);

            return $match->refresh();
        });
    }

    public function recordWalkover(TournamentMatch $match, int $winnerTeamId): TournamentMatch
    {
        return DB::transaction(function () use ($match, $winnerTeamId) {
            $match = TournamentMatch::whereKey($match->getKey())->lockForUpdate()->firstOrFail();

            if (in_array($match->status, ['finished', 'walkover', 'bye', 'cancelled'], true)) {
                throw ValidationException::withMessages(['match' => 'This match already has a result.']);
            }
            if (!$match->hasTeam($winnerTeamId)) {
                throw ValidationException::withMessages(['winner_team_id' => 'The winner must be one of the two teams in this match.']);
            }

            $match->forceFill([
                'status' => 'walkover',
                'winner_team_id' => $winnerTeamId,
                'loser_team_id' => $match->team1_id === $winnerTeamId ? $match->team2_id : $match->team1_id,
                'finished_at' => now(),
            ])->save();

            $this->brackets->advance($match->refresh());
            $this->brackets->resolveByes($match->division);

            return $match->refresh();
        });
    }

    /** Revert a result — only while nothing downstream has been played. */
    public function undoResult(TournamentMatch $match): TournamentMatch
    {
        return DB::transaction(function () use ($match) {
            $match = TournamentMatch::whereKey($match->getKey())->lockForUpdate()->firstOrFail();

            if (!in_array($match->status, ['finished', 'walkover'], true)) {
                throw ValidationException::withMessages(['match' => 'Only finished or walkover matches can be undone.']);
            }

            $this->assertDownstreamUnplayed($match, $match->winner_team_id, $match->winner_advances_to_match_id, (int) $match->winner_advances_to_slot);
            $this->assertDownstreamUnplayed($match, $match->loser_team_id, $match->loser_advances_to_match_id, (int) $match->loser_advances_to_slot);

            // GF1 → GF2 has no advance pointers (handled in BracketService), so
            // guard the played reset explicitly.
            if ($match->bracket_type === 'grand_final' && (int) $match->round === 1) {
                $resetPlayed = TournamentMatch::where('division_id', $match->division_id)
                    ->where('bracket_type', 'grand_final')->where('round', 2)
                    ->whereIn('status', ['finished', 'walkover'])
                    ->exists();
                if ($resetPlayed) {
                    throw ValidationException::withMessages(['match' => 'The grand final reset has already been played — undo it first.']);
                }
            }

            // Pull the placed teams back out of their next matches.
            $this->vacate($match->winner_advances_to_match_id, (int) $match->winner_advances_to_slot, $match->winner_team_id);
            $this->vacate($match->loser_advances_to_match_id, (int) $match->loser_advances_to_slot, $match->loser_team_id);

            // Grand-final bookkeeping: undoing GF1 restores the pristine reset row.
            if ($match->bracket_type === 'grand_final' && (int) $match->round === 1) {
                TournamentMatch::where('division_id', $match->division_id)
                    ->where('bracket_type', 'grand_final')->where('round', 2)
                    ->whereNotIn('status', ['finished', 'walkover'])
                    ->update(['status' => 'pending', 'team1_id' => null, 'team2_id' => null, 'finished_at' => null]);
            }

            $match->forceFill([
                'scores' => null,
                'winner_team_id' => null,
                'loser_team_id' => null,
                'status' => $match->scheduled_at ? 'scheduled' : 'pending',
                'finished_at' => null,
            ])->save();

            return $match->refresh();
        });
    }

    /** Assign court/referee/time. Overlapping court use warns, not blocks. */
    public function schedule(TournamentMatch $match, ?int $courtId, ?string $refereeName, ?Carbon $scheduledAt, ?string $notes = null): array
    {
        $warning = null;

        if ($courtId && $scheduledAt) {
            $duration = (int) $match->tournament->getSetting('default_match_duration', 30);
            $overlapping = TournamentMatch::where('court_id', $courtId)
                ->whereKeyNot($match->id)
                ->whereNotIn('status', ['finished', 'walkover', 'bye', 'cancelled'])
                ->whereBetween('scheduled_at', [
                    $scheduledAt->copy()->subMinutes($duration),
                    $scheduledAt->copy()->addMinutes($duration),
                ])
                ->count();
            if ($overlapping > 0) {
                $warning = "Heads up: {$overlapping} other match(es) are scheduled on this court within {$duration} minutes.";
            }
        }

        $match->forceFill([
            'court_id' => $courtId,
            'referee_name' => $refereeName !== null && trim($refereeName) !== '' ? trim($refereeName) : null,
            'scheduled_at' => $scheduledAt,
            'notes' => $notes ?? $match->notes,
            'status' => $match->status === 'pending' && $scheduledAt ? 'scheduled' : $match->status,
        ])->save();

        return [$match->refresh(), $warning];
    }

    public function updateStatus(TournamentMatch $match, string $status): TournamentMatch
    {
        $allowed = [
            'pending' => ['scheduled', 'called', 'playing', 'cancelled'],
            'scheduled' => ['called', 'playing', 'cancelled', 'pending'],
            'called' => ['playing', 'scheduled', 'cancelled'],
            'playing' => ['called', 'scheduled'],
            'cancelled' => ['pending'],
        ];

        if (!in_array($status, $allowed[$match->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Cannot move this match from '{$match->status}' to '{$status}'."]);
        }

        $match->forceFill([
            'status' => $status,
            'started_at' => $status === 'playing' ? ($match->started_at ?? now()) : $match->started_at,
        ])->save();

        return $match->refresh();
    }

    private function assertDownstreamUnplayed(TournamentMatch $match, ?int $teamId, ?int $nextMatchId, int $slot): void
    {
        if (!$teamId || !$nextMatchId) {
            return;
        }
        $next = TournamentMatch::find($nextMatchId);
        if ($next && in_array($next->status, ['finished', 'walkover'], true)) {
            throw ValidationException::withMessages([
                'match' => "Match #{$next->match_number} has already been played — undo it first, working backwards.",
            ]);
        }
        // Grand-final reset may have been populated/cancelled off GF1; treat the
        // same way (it is the winner_advances target of nothing, handled above).
    }

    private function vacate(?int $matchId, int $slot, ?int $teamId): void
    {
        if (!$matchId || !$teamId) {
            return;
        }
        $next = TournamentMatch::find($matchId);
        if (!$next) {
            return;
        }
        $column = $slot === 1 ? 'team1_id' : 'team2_id';
        if ($next->{$column} === $teamId) {
            // A bye downstream may have auto-advanced; roll that back too.
            if ($next->status === 'bye') {
                $this->vacate($next->winner_advances_to_match_id, (int) $next->winner_advances_to_slot, $next->winner_team_id);
                $next->forceFill(['status' => 'pending', 'winner_team_id' => null, 'finished_at' => null]);
            }
            $next->forceFill([$column => null])->save();
        }
    }
}
