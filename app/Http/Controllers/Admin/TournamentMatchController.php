<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchScoreRequest;
use App\Http\Requests\TournamentMatchRequest;
use App\Models\Court;
use App\Models\Scopes\BranchScope;
use App\Models\Tournament;
use App\Models\TournamentDivision;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\TournamentMatchService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TournamentMatchController extends Controller
{
    public function __construct(private readonly TournamentMatchService $matches) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Tournament::class);

        $matches = TournamentMatch::query()
            ->with([
                // tenant_id is required: the view runs the manageMatches policy
                // against this partial model, and the policy compares tenant_id.
                'tournament:id,tenant_id,name,settings,currency,is_all_branches,branch_id', 'division:id,name', 'group:id,name',
                'team1:id,name,seed', 'team2:id,name,seed', 'winner:id,name',
                'court:id,name',
            ])
            ->when($request->tournament_id, fn ($q, $v) => $q->where('tournament_id', $v))
            ->when($request->division_id, fn ($q, $v) => $q->where('division_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->court_id, fn ($q, $v) => $q->where('court_id', $v))
            ->when($request->date, fn ($q, $v) => $q->whereDate('scheduled_at', $v))
            // Hide bracket plumbing rows unless a status filter asks for them.
            ->when(!$request->status, fn ($q) => $q->whereNotIn('status', ['bye', 'cancelled']))
            ->orderByRaw("FIELD(status, 'playing', 'called', 'scheduled', 'pending', 'finished', 'walkover', 'bye', 'cancelled')")
            ->orderByRaw('scheduled_at IS NULL, scheduled_at ASC')
            ->orderBy('match_number')
            ->paginate(25);

        $tournaments = Tournament::notArchived()->orderBy('name')->get(['id', 'name']);
        $divisions = $request->tournament_id
            ? TournamentDivision::where('tournament_id', $request->tournament_id)->orderBy('sort_order')->get(['id', 'name'])
            : collect();

        // BranchScope would silently filter by the topbar branch; match courts
        // are venue-wide, so bypass it (tenant isolation still applies).
        $courts = Court::withoutGlobalScope(BranchScope::class)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'branch_id']);

        // Referee is free text; staff names are offered as datalist suggestions.
        $refereeSuggestions = User::whereIn('user_type', ['business_owner', 'staff'])
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        return view('admin.tournaments.matches.index', compact('matches', 'tournaments', 'divisions', 'courts', 'refereeSuggestions'));
    }

    /** Reschedule / assign court & referee / notes. */
    public function update(TournamentMatchRequest $request, TournamentMatch $match)
    {
        $this->authorize('manageMatches', $match->tournament);

        [, $warning] = $this->matches->schedule(
            $match,
            $request->validated('court_id') ? (int) $request->validated('court_id') : null,
            $request->validated('referee_name'),
            $request->validated('scheduled_at') ? Carbon::parse($request->validated('scheduled_at')) : null,
            $request->validated('notes'),
        );

        $redirect = back()->with('success', "Match #{$match->match_number} updated.");
        if ($warning) {
            $redirect->with('warning', $warning);
        }
        return $redirect;
    }

    public function score(MatchScoreRequest $request, TournamentMatch $match)
    {
        $this->authorize('manageMatches', $match->tournament);

        $this->matches->recordResult(
            $match,
            $request->validated('sets'),
            (int) $request->validated('winner_team_id'),
            $request->boolean('override'),
        );

        return back()->with('success', "Result recorded for match #{$match->match_number}. Winner advanced.");
    }

    public function walkover(Request $request, TournamentMatch $match)
    {
        $this->authorize('manageMatches', $match->tournament);

        $data = $request->validate(['winner_team_id' => 'required|integer']);

        $this->matches->recordWalkover($match, (int) $data['winner_team_id']);

        return back()->with('success', "Walkover recorded for match #{$match->match_number}.");
    }

    public function status(Request $request, TournamentMatch $match)
    {
        $this->authorize('manageMatches', $match->tournament);

        $data = $request->validate(['status' => 'required|in:pending,scheduled,called,playing,cancelled']);

        $this->matches->updateStatus($match, $data['status']);

        return back()->with('success', "Match #{$match->match_number} is now {$data['status']}.");
    }

    public function undo(TournamentMatch $match)
    {
        $this->authorize('manageMatches', $match->tournament);

        $this->matches->undoResult($match);

        return back()->with('success', "Result for match #{$match->match_number} undone.");
    }
}
