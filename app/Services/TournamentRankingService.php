<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentDivision;
use App\Models\TournamentMatch;

class TournamentRankingService
{
    /**
     * Standings for a division (optionally one group), computed from finished
     * and walkover matches. No stored table: divisions are small and computing
     * on read keeps corrections/regenerations consistent automatically.
     *
     * @return array<int, array{team_id:int, team:?\App\Models\TournamentTeam, played:int, wins:int, losses:int, points_for:int, points_against:int, point_diff:int}>
     */
    public function standings(TournamentDivision $division, ?int $groupId = null): array
    {
        $matches = $division->matches()
            ->whereIn('status', ['finished', 'walkover'])
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->get();

        $rows = [];
        $ensure = function (?int $teamId) use (&$rows) {
            if ($teamId && !isset($rows[$teamId])) {
                $rows[$teamId] = [
                    'team_id' => $teamId,
                    'played' => 0, 'wins' => 0, 'losses' => 0,
                    'points_for' => 0, 'points_against' => 0, 'point_diff' => 0,
                ];
            }
        };

        // Head-to-head winners, for two-way ties: "winnerId:loserId" => true.
        $headToHead = [];

        foreach ($matches as $match) {
            $ensure($match->team1_id);
            $ensure($match->team2_id);

            if (!$match->winner_team_id) {
                continue;
            }
            $winnerId = (int) $match->winner_team_id;
            $loserId = $match->team1_id === $winnerId ? $match->team2_id : $match->team1_id;

            $ensure($winnerId);
            $rows[$winnerId]['played']++;
            $rows[$winnerId]['wins']++;

            if ($loserId) {
                $rows[$loserId]['played']++;
                $rows[$loserId]['losses']++;
                $headToHead["{$winnerId}:{$loserId}"] = true;
            }

            // Points only from real scored sets; walkovers count W/L only.
            if ($match->status === 'finished' && is_array($match->scores)) {
                foreach ($match->scores as $set) {
                    $p1 = (int) ($set['team1'] ?? 0);
                    $p2 = (int) ($set['team2'] ?? 0);
                    if ($match->team1_id) {
                        $rows[$match->team1_id]['points_for'] += $p1;
                        $rows[$match->team1_id]['points_against'] += $p2;
                    }
                    if ($match->team2_id) {
                        $rows[$match->team2_id]['points_for'] += $p2;
                        $rows[$match->team2_id]['points_against'] += $p1;
                    }
                }
            }
        }

        // Include teams with no results yet so group tables show everyone.
        $teamsQuery = $division->activeTeams()->with('members.user:id,name');
        if ($groupId) {
            $teamsQuery->where('group_id', $groupId);
        }
        $teams = $teamsQuery->get()->keyBy('id');
        foreach ($teams as $team) {
            $ensure($team->id);
        }

        foreach ($rows as &$row) {
            $row['point_diff'] = $row['points_for'] - $row['points_against'];
            $row['team'] = $teams->get($row['team_id']);
        }
        unset($row);

        // Drop teams no longer active (withdrawn rows linger in old matches).
        $rows = array_filter($rows, fn ($r) => $r['team'] !== null);

        $sorted = array_values($rows);
        usort($sorted, function ($a, $b) use ($headToHead) {
            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }
            // Two-way tie: direct result decides.
            if (isset($headToHead["{$a['team_id']}:{$b['team_id']}"])) {
                return -1;
            }
            if (isset($headToHead["{$b['team_id']}:{$a['team_id']}"])) {
                return 1;
            }
            if ($a['point_diff'] !== $b['point_diff']) {
                return $b['point_diff'] <=> $a['point_diff'];
            }
            if ($a['points_for'] !== $b['points_for']) {
                return $b['points_for'] <=> $a['points_for'];
            }
            return ($a['team']->seed ?? PHP_INT_MAX) <=> ($b['team']->seed ?? PHP_INT_MAX);
        });

        return $sorted;
    }

    /**
     * Champion (and runner-up) per division: the decided final/grand final for
     * elimination formats, standings #1 for round robin / pure pools.
     *
     * @return array<int, array{division:TournamentDivision, champion:?\App\Models\TournamentTeam, runner_up:?\App\Models\TournamentTeam}>
     */
    public function champions(Tournament $tournament): array
    {
        $result = [];

        foreach ($tournament->divisions()->with('teams')->get() as $division) {
            $champion = null;
            $runnerUp = null;

            if (in_array($division->bracket_format, ['single_elimination', 'double_elimination'], true)
                || ($division->bracket_settings['knockout'] ?? false)) {
                $final = $this->decidedFinal($division);
                if ($final) {
                    $champion = $final->winner;
                    $loserId = $final->team1_id === $final->winner_team_id ? $final->team2_id : $final->team1_id;
                    $runnerUp = $loserId ? $division->teams->firstWhere('id', $loserId) : null;
                }
            } else {
                $standings = $this->standings($division);
                $allDone = $division->bracketLocked()
                    && !$division->matches()->whereNotIn('status', ['finished', 'walkover', 'bye', 'cancelled'])->exists();
                if ($allDone && $standings !== []) {
                    $champion = $standings[0]['team'] ?? null;
                    $runnerUp = $standings[1]['team'] ?? null;
                }
            }

            $result[] = [
                'division' => $division,
                'champion' => $champion,
                'runner_up' => $runnerUp,
            ];
        }

        return $result;
    }

    private function decidedFinal(TournamentDivision $division): ?TournamentMatch
    {
        if ($division->bracket_format === 'double_elimination') {
            // GF2 if it was played, else GF1.
            $gf2 = $division->matches()->where('bracket_type', 'grand_final')->where('round', 2)
                ->whereIn('status', ['finished', 'walkover'])->with('winner')->first();
            if ($gf2) {
                return $gf2;
            }
            return $division->matches()->where('bracket_type', 'grand_final')->where('round', 1)
                ->whereIn('status', ['finished', 'walkover'])->with('winner')->first();
        }

        $type = $division->bracket_format === 'single_elimination' ? 'main' : 'knockout';
        return $division->matches()->where('bracket_type', $type)
            ->whereIn('status', ['finished', 'walkover'])
            ->with('winner')
            ->orderByDesc('round')
            ->first();
    }
}
