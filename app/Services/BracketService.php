<?php

namespace App\Services;

use App\Models\TournamentDivision;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Generates and maintains tournament brackets.
 *
 * Generation builds an in-memory list of match "stubs" keyed "{B}:{round}:{pos}"
 * carrying win_to / lose_to pointer keys, inserts them, then back-fills the
 * advance FKs from the key→id map (self-FKs can't be written in one pass).
 * Byes and empty slots cascade through resolveByes(), a fixpoint loop shared
 * by every format — double-elim bye handling falls out of it for free.
 */
class BracketService
{
    private const DECIDED = ['finished', 'walkover', 'bye', 'cancelled'];

    public function __construct(private readonly TournamentRankingService $rankings) {}

    /* ─────────────────────────── Public API ─────────────────────────── */

    public function generate(TournamentDivision $division, string $seedingMethod, array $options = []): void
    {
        DB::transaction(function () use ($division, $seedingMethod, $options) {
            $format = $options['format'] ?? $division->bracket_format;
            if (!$format) {
                throw ValidationException::withMessages(['format' => 'Choose a bracket format for this division first.']);
            }

            $this->reset($division, force: (bool) ($options['force'] ?? false), silent: true);

            $teams = $this->seedTeams($division, $seedingMethod);
            if ($teams->count() < 2) {
                throw ValidationException::withMessages(['teams' => 'At least 2 registered teams are required to generate a bracket.']);
            }

            $stubs = match ($format) {
                'single_elimination' => $this->buildSingleElimination($teams),
                'double_elimination' => $this->buildDoubleElimination($teams),
                'round_robin' => $this->buildRoundRobin($teams, (bool) ($options['double_round_robin'] ?? false)),
                'group_stage', 'pool_play' => $this->buildGroups($division, $teams, [
                    'group_count' => (int) ($options['group_count'] ?? max(1, (int) ceil($teams->count() / 4))),
                    'advance_per_group' => (int) ($options['advance_per_group'] ?? 2),
                    'knockout' => (bool) ($options['knockout'] ?? $format === 'group_stage'),
                ]),
                default => throw ValidationException::withMessages(['format' => "Unknown bracket format '{$format}'."]),
            };

            $this->persistStubs($division, $stubs);

            $division->update([
                'bracket_format' => $format,
                'seeding_method' => $seedingMethod,
                'bracket_generated_at' => now(),
                'bracket_settings' => array_merge($division->bracket_settings ?? [], [
                    'group_count' => $options['group_count'] ?? null,
                    'advance_per_group' => $options['advance_per_group'] ?? null,
                    'double_round_robin' => (bool) ($options['double_round_robin'] ?? false),
                    'knockout' => (bool) ($options['knockout'] ?? $format === 'group_stage'),
                ]),
            ]);

            $this->resolveByes($division);

            activity()->on($division)->log("Bracket generated ({$format}, {$seedingMethod} seeding)");
        });
    }

    public function reset(TournamentDivision $division, bool $force = false, bool $silent = false): void
    {
        $hasResults = $division->matches()->whereIn('status', ['finished', 'walkover'])->exists();
        if ($hasResults && !$force) {
            throw ValidationException::withMessages([
                'bracket' => 'Matches have already been played in this division. Use force-reset to discard those results.',
            ]);
        }

        DB::transaction(function () use ($division, $silent) {
            // Clear self-referencing pointers first so row deletes can't trip the FKs.
            $division->matches()->update([
                'winner_advances_to_match_id' => null,
                'loser_advances_to_match_id' => null,
            ]);
            $division->matches()->delete();
            $division->teams()->update(['group_id' => null]);
            $division->groups()->delete();
            $division->update(['bracket_generated_at' => null]);

            if (!$silent) {
                activity()->on($division)->log('Bracket reset');
            }
        });
    }

    /** Persist a manual seed order (1-based seeds from a drag-and-drop list). */
    public function applyManualSeeds(TournamentDivision $division, array $orderedTeamIds): void
    {
        DB::transaction(function () use ($division, $orderedTeamIds) {
            $valid = $division->activeTeams()->pluck('id')->all();
            $ordered = array_values(array_intersect(array_map('intval', $orderedTeamIds), $valid));

            foreach ($ordered as $i => $teamId) {
                TournamentTeam::whereKey($teamId)->update(['seed' => $i + 1]);
            }

            $division->update(['seeding_method' => 'manual']);
        });
    }

    /** Place winner (and loser, for double elim) into their next matches. */
    public function advance(TournamentMatch $match): void
    {
        // Grand-final logic is explicit rather than pointer-driven.
        if ($match->bracket_type === 'grand_final') {
            $this->advanceGrandFinal($match);
            return;
        }

        if ($match->winner_team_id && $match->winner_advances_to_match_id) {
            $this->placeTeam($match->winner_advances_to_match_id, (int) $match->winner_advances_to_slot, $match->winner_team_id);
        }
        if ($match->loser_team_id && $match->loser_advances_to_match_id) {
            $this->placeTeam($match->loser_advances_to_match_id, (int) $match->loser_advances_to_slot, $match->loser_team_id);
        }
    }

    /**
     * Bye/empty-slot fixpoint. A pending match whose inputs are all final gets
     * resolved: one team → bye (team advances), zero teams → cancelled
     * (emptiness propagates downstream on the next sweep).
     */
    public function resolveByes(TournamentDivision $division): void
    {
        do {
            $matches = $division->matches()->get();
            $pendingFeeders = $this->pendingFeederMap($matches);
            $changed = false;

            foreach ($matches as $match) {
                if ($match->status !== 'pending') {
                    continue;
                }
                // Group / round-robin matches never resolve as byes — both slots
                // are fixed at generation time.
                if ($match->bracket_type === 'group') {
                    continue;
                }

                $slot1Final = $match->team1_id !== null || !$this->slotAwaitsInput($match, 1, $pendingFeeders);
                $slot2Final = $match->team2_id !== null || !$this->slotAwaitsInput($match, 2, $pendingFeeders);

                if (!$slot1Final || !$slot2Final) {
                    continue;
                }

                $teams = array_filter([$match->team1_id, $match->team2_id]);

                if (count($teams) === 1) {
                    $winner = (int) array_values($teams)[0];
                    $match->forceFill([
                        'status' => 'bye',
                        'winner_team_id' => $winner,
                        'finished_at' => now(),
                    ])->save();
                    $this->advance($match->refresh());
                    $changed = true;
                } elseif (count($teams) === 0) {
                    $match->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
                    $changed = true;
                }
                // Two teams: playable, leave pending.
            }
        } while ($changed);
    }

    /** Turn every undecided match of a withdrawn team into a walkover / vacated slot. */
    public function forfeitTeam(TournamentTeam $team): void
    {
        DB::transaction(function () use ($team) {
            $matches = TournamentMatch::where('division_id', $team->division_id)
                ->where(fn ($q) => $q->where('team1_id', $team->id)->orWhere('team2_id', $team->id))
                ->whereNotIn('status', self::DECIDED)
                ->orderBy('round')
                ->get();

            foreach ($matches as $match) {
                $match->refresh();
                if (in_array($match->status, self::DECIDED, true)) {
                    continue; // a cascade may already have settled it
                }

                $opponentId = $match->team1_id === $team->id ? $match->team2_id : $match->team1_id;

                if ($opponentId) {
                    $match->forceFill([
                        'status' => 'walkover',
                        'winner_team_id' => $opponentId,
                        'loser_team_id' => $team->id,
                        'finished_at' => now(),
                    ])->save();
                    $this->advance($match->refresh());
                } else {
                    // Opponent slot still unresolved: vacate our slot and let the
                    // bye sweep give whoever arrives a free pass.
                    $slot = $match->team1_id === $team->id ? 'team1_id' : 'team2_id';
                    $match->forceFill([$slot => null])->save();
                }
            }

            $this->resolveByes($team->division);
        });
    }

    /** Fill the knockout phase from final group standings (group stage / pools). */
    public function seedKnockoutFromGroups(TournamentDivision $division): void
    {
        DB::transaction(function () use ($division) {
            $undecidedGroupMatches = $division->matches()
                ->where('bracket_type', 'group')
                ->whereNotIn('status', self::DECIDED)
                ->count();
            if ($undecidedGroupMatches > 0) {
                throw ValidationException::withMessages([
                    'bracket' => "{$undecidedGroupMatches} group match(es) still need results before seeding the knockout.",
                ]);
            }

            $knockout = $division->matches()->where('bracket_type', 'knockout')->get();
            if ($knockout->isEmpty()) {
                throw ValidationException::withMessages(['bracket' => 'This division has no knockout phase.']);
            }
            if ($knockout->whereIn('status', ['finished', 'walkover'])->isNotEmpty()) {
                throw ValidationException::withMessages(['bracket' => 'The knockout phase has already started.']);
            }

            // "A1" → team id, from final standings per group.
            $labelMap = [];
            foreach ($division->groups()->orderBy('sort_order')->get()->values() as $i => $group) {
                $letter = chr(65 + $i);
                foreach ($this->rankings->standings($division, $group->id) as $rank => $row) {
                    $labelMap[$letter . ($rank + 1)] = $row['team_id'];
                }
            }

            foreach ($knockout as $match) {
                $labels = $match->slot_labels ?? [];
                $dirty = false;
                foreach ([1, 2] as $slot) {
                    $label = $labels[(string) $slot] ?? $labels[$slot] ?? null;
                    if ($label && isset($labelMap[$label]) && $match->{"team{$slot}_id"} === null) {
                        $match->{"team{$slot}_id"} = $labelMap[$label];
                        $dirty = true;
                    }
                }
                if ($dirty) {
                    $match->save();
                }
            }

            $this->resolveByes($division);

            activity()->on($division)->log('Knockout phase seeded from group standings');
        });
    }

    /* ─────────────────────────── Seeding ─────────────────────────── */

    /** @return Collection<int, TournamentTeam> ordered by seed (1 = top seed) */
    private function seedTeams(TournamentDivision $division, string $method): Collection
    {
        $base = $division->activeTeams()->with('members');

        $teams = match ($method) {
            'random' => $base->inRandomOrder()->get(),
            'manual' => $base->orderByRaw('seed IS NULL, seed ASC, id ASC')->get(),
            'rating' => $base->get()
                ->sortByDesc(fn (TournamentTeam $t) => $t->members->whereNotNull('rating')->avg('rating') ?? -1)
                ->values(),
            default => throw ValidationException::withMessages(['seeding' => "Unknown seeding method '{$method}'."]),
        };

        // Persist the resulting order so the bracket and the seed list agree.
        foreach ($teams->values() as $i => $team) {
            if ($team->seed !== $i + 1) {
                $team->forceFill(['seed' => $i + 1])->save();
            }
        }

        return $teams->values();
    }

    /**
     * Standard seed placement: index i of the returned array holds the seed
     * number occupying bracket slot i, so seeds 1 and 2 can only meet in the
     * final (size 8 → [1,8,4,5,2,7,3,6]).
     */
    private function seedPositions(int $bracketSize): array
    {
        $pos = [1];
        for ($size = 2; $size <= $bracketSize; $size *= 2) {
            $next = [];
            foreach ($pos as $p) {
                $next[] = $p;
                $next[] = $size + 1 - $p;
            }
            $pos = $next;
        }
        return $pos;
    }

    /* ─────────────────────── Format builders ─────────────────────── */

    private function roundName(int $round, int $totalRounds): string
    {
        return match ($totalRounds - $round) {
            0 => 'Final',
            1 => 'Semifinal',
            2 => 'Quarterfinal',
            3 => 'Round of 16',
            4 => 'Round of 32',
            default => "Round {$round}",
        };
    }

    private function stub(string $key, array $attrs): array
    {
        return array_merge([
            'key' => $key,
            'bracket_type' => 'main',
            'round' => 1,
            'round_name' => null,
            'bracket_pos' => 0,
            'team1_id' => null,
            'team2_id' => null,
            'slot_labels' => null,
            'group_id' => null,
            'win_to' => null,
            'win_slot' => null,
            'lose_to' => null,
            'lose_slot' => null,
            'status' => 'pending',
        ], $attrs);
    }

    private function buildSingleElimination(Collection $teams, string $type = 'main', string $prefix = 'M'): array
    {
        $n = $teams->count();
        $k = (int) ceil(log(max($n, 2), 2));
        $size = 2 ** $k;
        $positions = $this->seedPositions($size);

        $stubs = [];
        for ($r = 1; $r <= $k; $r++) {
            $count = (int) ($size / (2 ** $r));
            for ($i = 0; $i < $count; $i++) {
                $attrs = [
                    'bracket_type' => $type,
                    'round' => $r,
                    'round_name' => $this->roundName($r, $k),
                    'bracket_pos' => $i,
                ];
                if ($r < $k) {
                    $attrs['win_to'] = "{$prefix}:" . ($r + 1) . ':' . intdiv($i, 2);
                    $attrs['win_slot'] = ($i % 2) + 1;
                }
                if ($r === 1) {
                    $seed1 = $positions[2 * $i];
                    $seed2 = $positions[2 * $i + 1];
                    $attrs['team1_id'] = $teams->get($seed1 - 1)?->id;
                    $attrs['team2_id'] = $teams->get($seed2 - 1)?->id;
                }
                $stubs["{$prefix}:{$r}:{$i}"] = $this->stub("{$prefix}:{$r}:{$i}", $attrs);
            }
        }

        return $stubs;
    }

    private function buildDoubleElimination(Collection $teams): array
    {
        $n = $teams->count();
        $k = (int) ceil(log(max($n, 2), 2));
        $size = 2 ** $k;

        // Winners bracket = single elim with 'winners' type and W: keys.
        $stubs = $this->buildSingleElimination($teams, 'winners', 'W');

        if ($k === 1) {
            // Two teams: WB final loser goes straight to the grand final.
            $stubs['W:1:0']['lose_to'] = 'GF:1:0';
            $stubs['W:1:0']['lose_slot'] = 2;
        } else {
            // Losers bracket, rounds 1..2k-2. m_j = size / 2^(j+1).
            for ($j = 1; $j <= $k - 1; $j++) {
                $mj = (int) ($size / (2 ** ($j + 1)));

                if ($j === 1) {
                    // L1: both slots fed by W1 losers, pairwise.
                    for ($i = 0; $i < $mj; $i++) {
                        $stubs["L:1:{$i}"] = $this->stub("L:1:{$i}", [
                            'bracket_type' => 'losers',
                            'round' => 1,
                            'round_name' => 'Elim Round 1',
                            'bracket_pos' => $i,
                            'win_to' => "L:2:{$i}",
                            'win_slot' => 1,
                        ]);
                        $stubs['W:1:' . (2 * $i)]['lose_to'] = "L:1:{$i}";
                        $stubs['W:1:' . (2 * $i)]['lose_slot'] = 1;
                        $stubs['W:1:' . (2 * $i + 1)]['lose_to'] = "L:1:{$i}";
                        $stubs['W:1:' . (2 * $i + 1)]['lose_slot'] = 2;
                    }
                }

                // Drop-in round L(2j): slot1 from the previous LB round, slot2 = W(j+1) loser.
                $dropRound = 2 * $j;
                $isLastLbRound = $dropRound === 2 * $k - 2;
                for ($i = 0; $i < $mj; $i++) {
                    $attrs = [
                        'bracket_type' => 'losers',
                        'round' => $dropRound,
                        'round_name' => $isLastLbRound ? 'Elim Final' : "Elim Round {$dropRound}",
                        'bracket_pos' => $i,
                    ];
                    if ($isLastLbRound) {
                        $attrs['win_to'] = 'GF:1:0';
                        $attrs['win_slot'] = 2;
                    } else {
                        $attrs['win_to'] = 'L:' . ($dropRound + 1) . ':' . intdiv($i, 2);
                        $attrs['win_slot'] = ($i % 2) + 1;
                    }
                    $stubs["L:{$dropRound}:{$i}"] = $this->stub("L:{$dropRound}:{$i}", $attrs);
                }

                // Map W(j+1) losers into L(2j), alternating the order per round to
                // delay rematches between players who already met in the WB.
                for ($i = 0; $i < $mj; $i++) {
                    $target = $j % 2 === 1
                        ? $mj - 1 - $i                    // reversed
                        : ($i + intdiv($mj, 2)) % max($mj, 1); // half rotation
                    $wKey = 'W:' . ($j + 1) . ":{$i}";
                    $stubs[$wKey]['lose_to'] = "L:{$dropRound}:{$target}";
                    $stubs[$wKey]['lose_slot'] = 2;
                }

                // Internal round L(2j+1): LB winners pair up (skipped after the last drop-in).
                if (!$isLastLbRound) {
                    $internalRound = $dropRound + 1;
                    $mNext = (int) ($mj / 2);
                    $isInternalLast = $internalRound === 2 * $k - 2; // only when k=2 edge: not possible since last is even; guard anyway
                    for ($i = 0; $i < $mNext; $i++) {
                        $stubs["L:{$internalRound}:{$i}"] = $this->stub("L:{$internalRound}:{$i}", [
                            'bracket_type' => 'losers',
                            'round' => $internalRound,
                            'round_name' => $isInternalLast ? 'Elim Final' : "Elim Round {$internalRound}",
                            'bracket_pos' => $i,
                            'win_to' => 'L:' . ($internalRound + 1) . ":{$i}",
                            'win_slot' => 1,
                        ]);
                    }
                }
            }
        }

        // WB final winner → GF slot 1.
        $stubs["W:{$k}:0"]['win_to'] = 'GF:1:0';
        $stubs["W:{$k}:0"]['win_slot'] = 1;

        // Grand final + pre-created reset (used only if the LB champion wins GF1).
        $stubs['GF:1:0'] = $this->stub('GF:1:0', [
            'bracket_type' => 'grand_final',
            'round' => 1,
            'round_name' => 'Grand Final',
            'bracket_pos' => 0,
            'slot_labels' => ['1' => 'Winners champion', '2' => 'Elimination champion'],
        ]);
        $stubs['GF:2:0'] = $this->stub('GF:2:0', [
            'bracket_type' => 'grand_final',
            'round' => 2,
            'round_name' => 'Grand Final Reset',
            'bracket_pos' => 0,
            'slot_labels' => ['1' => 'GF winner (if from elimination side)', '2' => 'GF loser'],
        ]);

        return $stubs;
    }

    private function buildRoundRobin(
        Collection $teams,
        bool $double = false,
        ?int $groupId = null,
        string $keyPrefix = 'R',
        string $namePrefix = '',
        int $startRound = 1,
    ): array {
        $list = $teams->values()->all();
        if (count($list) % 2 === 1) {
            $list[] = null; // bye marker
        }
        $n = count($list);
        $rounds = $n - 1;

        $stubs = [];
        $cycles = $double ? 2 : 1;
        $arr = $list;

        for ($cycle = 0; $cycle < $cycles; $cycle++) {
            $arr = $list;
            for ($r = 1; $r <= $rounds; $r++) {
                $displayRound = $startRound + ($cycle * $rounds) + $r - 1;
                $pos = 0;
                for ($i = 0; $i < intdiv($n, 2); $i++) {
                    $a = $arr[$i];
                    $b = $arr[$n - 1 - $i];
                    if ($a === null || $b === null) {
                        continue; // odd team count: someone sits out this round
                    }
                    [$t1, $t2] = $cycle === 0 ? [$a, $b] : [$b, $a];
                    $key = "{$keyPrefix}:{$displayRound}:{$pos}";
                    $stubs[$key] = $this->stub($key, [
                        'bracket_type' => 'group',
                        'round' => $displayRound,
                        'round_name' => trim("{$namePrefix} Round {$displayRound}"),
                        'bracket_pos' => $pos,
                        'team1_id' => $t1->id,
                        'team2_id' => $t2->id,
                        'group_id' => $groupId,
                    ]);
                    $pos++;
                }
                // Circle method: fix arr[0], rotate the rest clockwise.
                $fixed = array_shift($arr);
                array_unshift($arr, $fixed, array_pop($arr));
            }
        }

        return $stubs;
    }

    private function buildGroups(TournamentDivision $division, Collection $teams, array $options): array
    {
        $groupCount = max(1, min($options['group_count'], $teams->count()));
        $advance = max(1, $options['advance_per_group']);

        // Create the groups ("Group A" …) and snake-seed the teams into them.
        $groups = [];
        for ($g = 0; $g < $groupCount; $g++) {
            $groups[$g] = TournamentGroup::create([
                'tenant_id' => $division->tenant_id,
                'division_id' => $division->id,
                'name' => 'Group ' . chr(65 + $g),
                'sort_order' => $g,
            ]);
        }

        $buckets = array_fill(0, $groupCount, []);
        foreach ($teams->values() as $i => $team) {
            $row = intdiv($i, $groupCount);
            $col = $i % $groupCount;
            $g = $row % 2 === 0 ? $col : $groupCount - 1 - $col; // snake
            $buckets[$g][] = $team;
            $team->forceFill(['group_id' => $groups[$g]->id])->save();
        }

        $stubs = [];
        foreach ($buckets as $g => $bucket) {
            $letter = chr(65 + $g);
            $stubs += $this->buildRoundRobin(
                collect($bucket),
                double: false,
                groupId: $groups[$g]->id,
                keyPrefix: "G{$letter}",
                namePrefix: "Group {$letter} —",
            );
        }

        if ($options['knockout']) {
            $stubs += $this->buildKnockoutShell($groupCount, $advance);
        }

        return $stubs;
    }

    /**
     * Empty knockout bracket whose round-1 slots carry group-position labels
     * ("A1", "B2", …). Standard cross pairing: winners face runners-up from the
     * opposite end of the group list.
     */
    private function buildKnockoutShell(int $groupCount, int $advancePerGroup): array
    {
        // Qualifier labels in seed order: all #1s (groups in order), then all
        // #2s (groups reversed), alternating direction per rank to spread groups.
        $labels = [];
        for ($rank = 1; $rank <= $advancePerGroup; $rank++) {
            $letters = range(0, $groupCount - 1);
            if ($rank % 2 === 0) {
                $letters = array_reverse($letters);
            }
            foreach ($letters as $g) {
                $labels[] = chr(65 + $g) . $rank;
            }
        }

        $q = count($labels);
        $k = (int) ceil(log(max($q, 2), 2));
        $size = 2 ** $k;
        $positions = $this->seedPositions($size);

        $stubs = [];
        for ($r = 1; $r <= $k; $r++) {
            $count = (int) ($size / (2 ** $r));
            for ($i = 0; $i < $count; $i++) {
                $attrs = [
                    'bracket_type' => 'knockout',
                    'round' => $r,
                    'round_name' => $this->roundName($r, $k),
                    'bracket_pos' => $i,
                ];
                if ($r < $k) {
                    $attrs['win_to'] = 'K:' . ($r + 1) . ':' . intdiv($i, 2);
                    $attrs['win_slot'] = ($i % 2) + 1;
                }
                if ($r === 1) {
                    $slotLabels = [];
                    foreach ([0, 1] as $s) {
                        $seed = $positions[2 * $i + $s];
                        if ($seed <= $q) {
                            $slotLabels[(string) ($s + 1)] = $labels[$seed - 1];
                        }
                    }
                    $attrs['slot_labels'] = $slotLabels ?: null;
                }
                $stubs["K:{$r}:{$i}"] = $this->stub("K:{$r}:{$i}", $attrs);
            }
        }

        return $stubs;
    }

    /* ─────────────────────── Persistence & advancement ─────────────────────── */

    private function persistStubs(TournamentDivision $division, array $stubs): void
    {
        // Play order: groups first, then main/winners interleaved before losers
        // of later rounds — round-major within each phase is what desks expect.
        $phaseOrder = ['group' => 0, 'main' => 1, 'winners' => 1, 'losers' => 2, 'knockout' => 3, 'grand_final' => 4];
        $ordered = collect($stubs)->sortBy([
            fn ($a, $b) => ($phaseOrder[$a['bracket_type']] ?? 9) <=> ($phaseOrder[$b['bracket_type']] ?? 9),
            fn ($a, $b) => $a['round'] <=> $b['round'],
            fn ($a, $b) => $a['bracket_pos'] <=> $b['bracket_pos'],
        ])->values();

        // Pass 0: human-readable "Winner of #n" placeholders for elimination slots.
        $numberByKey = [];
        foreach ($ordered as $i => $stub) {
            $numberByKey[$stub['key']] = $i + 1;
        }
        foreach ($ordered as $stub) {
            $num = $numberByKey[$stub['key']];
            foreach ([['win_to', 'win_slot', 'Winner'], ['lose_to', 'lose_slot', 'Loser']] as [$toKey, $slotKey, $word]) {
                $target = $stub[$toKey] ?? null;
                if ($target && isset($stubs[$target]) && $stubs[$target]['bracket_type'] !== 'grand_final') {
                    $idx = $ordered->search(fn ($s) => $s['key'] === $target);
                    if ($idx !== false) {
                        $labels = $ordered[$idx]['slot_labels'] ?? [];
                        $labels[(string) $stub[$slotKey]] ??= "{$word} of #{$num}";
                        $updated = $ordered[$idx];
                        $updated['slot_labels'] = $labels;
                        $ordered[$idx] = $updated;
                    }
                }
            }
        }

        // Pass 1: insert rows, remembering key → id.
        $idByKey = [];
        foreach ($ordered as $i => $stub) {
            $match = TournamentMatch::create([
                'tenant_id' => $division->tenant_id,
                'tournament_id' => $division->tournament_id,
                'division_id' => $division->id,
                'group_id' => $stub['group_id'],
                'match_number' => $i + 1,
                'bracket_type' => $stub['bracket_type'],
                'round' => $stub['round'],
                'round_name' => $stub['round_name'],
                'bracket_pos' => $stub['bracket_pos'],
                'team1_id' => $stub['team1_id'],
                'team2_id' => $stub['team2_id'],
                'slot_labels' => $stub['slot_labels'],
                'status' => $stub['status'],
            ]);
            $idByKey[$stub['key']] = $match->id;
        }

        // Pass 2: back-fill the advancement pointers.
        foreach ($ordered as $stub) {
            $updates = [];
            if ($stub['win_to'] && isset($idByKey[$stub['win_to']])) {
                $updates['winner_advances_to_match_id'] = $idByKey[$stub['win_to']];
                $updates['winner_advances_to_slot'] = $stub['win_slot'];
            }
            if ($stub['lose_to'] && isset($idByKey[$stub['lose_to']])) {
                $updates['loser_advances_to_match_id'] = $idByKey[$stub['lose_to']];
                $updates['loser_advances_to_slot'] = $stub['lose_slot'];
            }
            if ($updates) {
                TournamentMatch::whereKey($idByKey[$stub['key']])->update($updates);
            }
        }
    }

    private function placeTeam(int $matchId, int $slot, int $teamId): void
    {
        $target = TournamentMatch::find($matchId);
        if (!$target || in_array($target->status, self::DECIDED, true)) {
            return;
        }
        $column = $slot === 1 ? 'team1_id' : 'team2_id';
        if ($target->{$column} !== $teamId) {
            $target->forceFill([$column => $teamId])->save();
        }
    }

    private function advanceGrandFinal(TournamentMatch $match): void
    {
        if ((int) $match->round !== 1 || !$match->winner_team_id) {
            return; // GF2 result crowns the champion; nothing downstream
        }

        $reset = TournamentMatch::where('division_id', $match->division_id)
            ->where('bracket_type', 'grand_final')
            ->where('round', 2)
            ->first();
        if (!$reset || in_array($reset->status, ['finished', 'walkover'], true)) {
            return;
        }

        if ($match->winner_team_id === $match->team1_id) {
            // Winners-bracket champion won: no reset needed, tournament decided.
            $reset->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();
        } else {
            // Elimination champion won GF1 — both now have one loss; play it out.
            $reset->forceFill([
                'team1_id' => $match->winner_team_id,
                'team2_id' => $match->loser_team_id,
                'status' => 'pending',
            ])->save();
        }
    }

    /* ─────────────────────────── Helpers ─────────────────────────── */

    /** Map "matchId:slot" → count of undecided feeders targeting that slot. */
    private function pendingFeederMap(Collection $matches): array
    {
        $map = [];
        foreach ($matches as $m) {
            $decided = in_array($m->status, self::DECIDED, true);
            if ($m->winner_advances_to_match_id && !$decided) {
                $map["{$m->winner_advances_to_match_id}:{$m->winner_advances_to_slot}"] = true;
            }
            if ($m->loser_advances_to_match_id && !$decided) {
                $map["{$m->loser_advances_to_match_id}:{$m->loser_advances_to_slot}"] = true;
            }
        }
        return $map;
    }

    private function slotAwaitsInput(TournamentMatch $match, int $slot, array $pendingFeeders): bool
    {
        if (isset($pendingFeeders["{$match->id}:{$slot}"])) {
            return true;
        }
        // Knockout shells wait for group standings; grand finals wait for both
        // bracket champions (their labels are filled by advance(), not byes).
        if (in_array($match->bracket_type, ['knockout', 'grand_final'], true)) {
            $labels = $match->slot_labels ?? [];
            if (isset($labels[(string) $slot]) || isset($labels[$slot])) {
                return true;
            }
        }
        return false;
    }
}
