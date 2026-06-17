<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BracketGenerateRequest;
use App\Models\Tournament;
use App\Models\TournamentDivision;
use App\Services\BracketService;
use App\Services\TournamentRankingService;
use Illuminate\Http\Request;

class TournamentBracketController extends Controller
{
    public function __construct(
        private readonly BracketService $brackets,
        private readonly TournamentRankingService $rankings,
    ) {}

    /** Landing page: pick a division to view/manage its bracket. */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Tournament::class);

        $divisions = TournamentDivision::query()
            ->with('tournament:id,tenant_id,name,status,archived_at')
            ->withCount(['teams' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->whereHas('tournament', fn ($q) => $q->whereNull('archived_at'))
            ->when($request->tournament_id, fn ($q, $v) => $q->where('tournament_id', $v))
            ->latest('id')
            ->paginate(20);

        $tournaments = Tournament::notArchived()->orderBy('name')->get(['id', 'name']);

        return view('admin.tournaments.brackets.index', compact('divisions', 'tournaments'));
    }

    public function show(TournamentDivision $division)
    {
        $this->authorize('view', $division->tournament);

        $division->load(['tournament', 'groups']);

        $matches = $division->matches()
            ->with(['team1.members.user:id,name', 'team2.members.user:id,name', 'court:id,name', 'winner:id,name'])
            ->orderBy('round')->orderBy('bracket_pos')
            ->get();

        $teams = $division->activeTeams()->with('members.user:id,name')
            ->orderByRaw('seed IS NULL, seed ASC, id ASC')
            ->get();

        $groupStandings = [];
        if (in_array($division->bracket_format, ['round_robin', 'group_stage', 'pool_play'], true) && $division->bracketLocked()) {
            if ($division->groups->isNotEmpty()) {
                foreach ($division->groups as $group) {
                    $groupStandings[$group->id] = $this->rankings->standings($division, $group->id);
                }
            } else {
                $groupStandings[0] = $this->rankings->standings($division);
            }
        }

        return view('admin.tournaments.brackets.show', compact('division', 'matches', 'teams', 'groupStandings'));
    }

    public function generate(BracketGenerateRequest $request, TournamentDivision $division)
    {
        $this->authorize('manageBrackets', $division->tournament);

        $this->brackets->generate($division, $request->validated('seeding_method'), [
            'format' => $request->validated('format'),
            'group_count' => $request->validated('group_count'),
            'advance_per_group' => $request->validated('advance_per_group'),
            'double_round_robin' => $request->boolean('double_round_robin'),
            'knockout' => $request->validated('format') === 'group_stage' ? true : $request->boolean('knockout'),
            'force' => $request->boolean('force'),
        ]);

        return redirect()->route('admin.tournaments.brackets.show', $division)
            ->with('success', 'Bracket generated.');
    }

    public function reset(Request $request, TournamentDivision $division)
    {
        $this->authorize('manageBrackets', $division->tournament);

        $this->brackets->reset($division, force: $request->boolean('force'));

        return redirect()->route('admin.tournaments.brackets.show', $division)
            ->with('success', 'Bracket reset. Teams are kept; regenerate when ready.');
    }

    public function seeds(Request $request, TournamentDivision $division)
    {
        $this->authorize('manageBrackets', $division->tournament);

        if ($division->bracketLocked()) {
            return back()->with('error', 'Seeds are locked once the bracket is generated. Reset it first.');
        }

        $data = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer',
        ]);

        $this->brackets->applyManualSeeds($division, $data['ordered_ids']);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Seed order saved.');
    }

    public function seedKnockout(TournamentDivision $division)
    {
        $this->authorize('manageBrackets', $division->tournament);

        $this->brackets->seedKnockoutFromGroups($division);

        return back()->with('success', 'Knockout phase seeded from group standings.');
    }
}
