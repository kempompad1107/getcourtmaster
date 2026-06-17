<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentDivisionRequest;
use App\Models\Tournament;
use App\Models\TournamentDivision;
use Illuminate\Http\Request;

class TournamentDivisionController extends Controller
{
    /** Global divisions picker — every division across tournaments, filterable. */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Tournament::class);

        $divisions = TournamentDivision::query()
            ->with('tournament:id,tenant_id,name,status,archived_at')
            ->withCount(['teams' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->when($request->tournament_id, fn ($q, $v) => $q->where('tournament_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->whereHas('tournament', fn ($q) => $q->whereNull('archived_at'))
            ->latest('id')
            ->paginate(20);

        $tournaments = Tournament::notArchived()->orderBy('name')->get(['id', 'name']);

        return view('admin.tournaments.divisions.index', compact('divisions', 'tournaments'));
    }

    public function store(TournamentDivisionRequest $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $division = TournamentDivision::create(array_merge($request->validated(), [
            'tenant_id' => $tournament->tenant_id,
            'tournament_id' => $tournament->id,
        ]));

        return redirect()->route('admin.tournaments.show', [$tournament, 'tab' => 'divisions'])
            ->with('success', "Division '{$division->name}' added.");
    }

    public function update(TournamentDivisionRequest $request, TournamentDivision $division)
    {
        $this->authorize('update', $division->tournament);

        if ($division->bracketLocked()) {
            $locked = ['team_size', 'bracket_format'];
            $changedLocked = collect($locked)
                ->filter(fn ($f) => $request->filled($f) && (string) $request->input($f) !== (string) $division->{$f});
            if ($changedLocked->isNotEmpty()) {
                return back()->with('error', 'Team size and format are locked once the bracket is generated. Reset the bracket first.');
            }
        }

        $division->update($request->validated());

        return redirect()->route('admin.tournaments.show', [$division->tournament_id, 'tab' => 'divisions'])
            ->with('success', "Division '{$division->name}' updated.");
    }

    public function destroy(TournamentDivision $division)
    {
        $this->authorize('update', $division->tournament);

        if ($division->teams()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return back()->with('error', 'This division has registered teams. Withdraw them before deleting it.');
        }

        $name = $division->name;
        $tournamentId = $division->tournament_id;
        $division->delete();

        return redirect()->route('admin.tournaments.show', [$tournamentId, 'tab' => 'divisions'])
            ->with('success', "Division '{$name}' deleted.");
    }
}
