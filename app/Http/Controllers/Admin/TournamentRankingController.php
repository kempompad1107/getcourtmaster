<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentDivision;
use App\Services\TournamentRankingService;
use Illuminate\Http\Request;

class TournamentRankingController extends Controller
{
    public function __construct(private readonly TournamentRankingService $rankings) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Tournament::class);

        $tournaments = Tournament::notArchived()->orderBy('name')->get(['id', 'name']);

        $divisions = TournamentDivision::query()
            ->whereNotNull('bracket_generated_at')
            ->whereHas('tournament', fn ($q) => $q->whereNull('archived_at'))
            ->when($request->tournament_id, fn ($q, $v) => $q->where('tournament_id', $v))
            ->with('tournament:id,name')
            ->orderBy('tournament_id')->orderBy('sort_order')
            ->get();

        $division = null;
        $standings = [];
        $groupStandings = [];

        if ($request->division_id) {
            $division = $divisions->firstWhere('id', (int) $request->division_id);
            if ($division) {
                $division->load('groups');
                if ($division->groups->isNotEmpty()) {
                    foreach ($division->groups as $group) {
                        $groupStandings[$group->name] = $this->rankings->standings($division, $group->id);
                    }
                } else {
                    $standings = $this->rankings->standings($division);
                }
            }
        }

        return view('admin.tournaments.rankings.index', compact('tournaments', 'divisions', 'division', 'standings', 'groupStandings'));
    }

    /** Standings partial for one division (linked from bracket pages). */
    public function show(TournamentDivision $division)
    {
        $this->authorize('view', $division->tournament);

        return redirect()->route('admin.tournaments.rankings.index', [
            'tournament_id' => $division->tournament_id,
            'division_id' => $division->id,
        ]);
    }
}
