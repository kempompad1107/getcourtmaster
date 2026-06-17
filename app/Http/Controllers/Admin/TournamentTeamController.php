<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentTeamRequest;
use App\Models\Tournament;
use App\Models\TournamentDivision;
use App\Models\TournamentTeam;
use App\Services\TournamentRegistrationService;
use Illuminate\Http\Request;

class TournamentTeamController extends Controller
{
    public function __construct(private readonly TournamentRegistrationService $registration) {}

    /** Global teams page — all registrations across tournaments, filterable. */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Tournament::class);

        $teams = TournamentTeam::query()
            ->with(['tournament:id,tenant_id,name,entry_fee,currency', 'division:id,name,team_size,entry_fee', 'members.user:id,name,email,phone,avatar'])
            ->when($request->tournament_id, fn ($q, $v) => $q->where('tournament_id', $v))
            ->when($request->division_id, fn ($q, $v) => $q->where('division_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%{$v}%")
                  ->orWhereHas('members.user', fn ($q) => $q->where('name', 'like', "%{$v}%")
                      ->orWhere('email', 'like', "%{$v}%")
                      ->orWhere('phone', 'like', "%{$v}%")
                      ->orWhere('referral_code', 'like', "%{$v}%"));
            }))
            ->latest('id')
            ->paginate(20);

        $tournaments = Tournament::notArchived()->orderBy('name')->get(['id', 'name']);
        $divisions = $request->tournament_id
            ? TournamentDivision::where('tournament_id', $request->tournament_id)->orderBy('sort_order')->get(['id', 'name'])
            : collect();

        return view('admin.tournaments.teams.index', compact('teams', 'tournaments', 'divisions'));
    }

    public function store(TournamentTeamRequest $request, TournamentDivision $division)
    {
        $this->authorize('manageRegistrations', $division->tournament);

        $members = collect($request->validated('members'))->values()->map(fn ($m, $i) => [
            'user_id' => (int) $m['user_id'],
            'skill_level' => $m['skill_level'] ?? null,
            'rating' => isset($m['rating']) && $m['rating'] !== null && $m['rating'] !== '' ? (float) $m['rating'] : null,
            'is_captain' => $i === 0,
        ])->all();

        $team = $this->registration->register(
            $division,
            $members,
            via: 'admin',
            registeredBy: $this->authUser(),
            teamName: $request->validated('name'),
        );

        // Optionally collect every member's fee in the same step.
        if ($method = $request->validated('collect_method')) {
            foreach ($team->members as $member) {
                $this->registration->collectFee($member, $method, $this->authUser());
            }
        }

        return back()->with('success', "'{$team->name}' registered into {$division->name}.");
    }

    public function update(Request $request, TournamentTeam $team)
    {
        $this->authorize('manageRegistrations', $team->tournament);

        // Plain rename, or a partner swap when swap fields are present.
        if ($request->filled('swap_out_user_id')) {
            $data = $request->validate([
                'swap_out_user_id' => 'required|integer',
                'swap_in_user_id' => 'required|integer|different:swap_out_user_id',
                'swap_in_skill_level' => 'nullable|string|max:50',
                'swap_in_rating' => 'nullable|numeric|min:0|max:10',
            ]);

            $this->registration->changePartner($team, (int) $data['swap_out_user_id'], [
                'user_id' => (int) $data['swap_in_user_id'],
                'skill_level' => $data['swap_in_skill_level'] ?? null,
                'rating' => isset($data['swap_in_rating']) && $data['swap_in_rating'] !== '' ? (float) $data['swap_in_rating'] : null,
            ], $this->authUser());

            return back()->with('success', 'Partner changed.');
        }

        $data = $request->validate(['name' => 'required|string|max:150']);
        $team->update($data);

        return back()->with('success', 'Team updated.');
    }

    public function withdraw(Request $request, TournamentTeam $team)
    {
        $this->authorize('manageRegistrations', $team->tournament);

        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
            'refund' => 'boolean',
        ]);

        $this->registration->withdraw(
            $team,
            refund: $request->boolean('refund'),
            reason: $data['reason'] ?? 'Withdrawn by staff',
            processedBy: $this->authUser(),
        );

        return back()->with('success', "'{$team->name}' withdrawn" . ($request->boolean('refund') ? ' and entry fees refunded.' : '.'));
    }

    public function collectFee(Request $request, TournamentTeam $team)
    {
        $this->authorize('manageRegistrations', $team->tournament);

        $data = $request->validate([
            'method' => 'required|in:cash,wallet',
            'member_id' => 'nullable|integer',
        ]);

        $members = $data['member_id'] ?? null
            ? $team->members()->whereKey($data['member_id'])->get()
            : $team->members()->get();

        if ($members->isEmpty()) {
            return back()->with('error', 'No matching team member found.');
        }

        $collected = 0;
        foreach ($members as $member) {
            if (!$member->hasPaid()) {
                $this->registration->collectFee($member, $data['method'], $this->authUser());
                $collected++;
            }
        }

        return back()->with('success', $collected > 0
            ? "Entry fee collected for {$collected} player(s) via {$data['method']}."
            : 'All players on this team have already paid.');
    }

    public function destroy(TournamentTeam $team)
    {
        $this->authorize('manageRegistrations', $team->tournament);

        if ($team->finishedMatches()->exists()) {
            return back()->with('error', 'This team has finished matches and cannot be deleted. Withdraw or disqualify it instead.');
        }
        if ($team->payments()->whereIn('status', ['paid', 'partial'])->exists()) {
            return back()->with('error', 'This team has collected payments. Withdraw it (with refund) instead of deleting.');
        }

        $name = $team->name;
        $team->members()->delete();
        $team->delete();

        return back()->with('success', "'{$name}' removed.");
    }
}
