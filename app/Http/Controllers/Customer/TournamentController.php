<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerTournamentRegistrationRequest;
use App\Models\Tournament;
use App\Models\TournamentDivision;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamMember;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\TournamentRankingService;
use App\Services\TournamentRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TournamentController extends Controller
{
    public function __construct(
        private readonly TournamentRegistrationService $registration,
        private readonly TournamentRankingService $rankings,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request)
    {
        $user = $this->authUser();
        $tab  = $request->get('tab', 'all');

        $q = Tournament::publicVisible()
            ->notArchived()
            ->whereIn('status', ['registration_open', 'registration_closed', 'ongoing', 'completed'])
            ->withCount(['teams' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
            ->orderByRaw("FIELD(status, 'registration_open', 'ongoing', 'registration_closed', 'completed')")
            ->orderByRaw('starts_at IS NULL, starts_at ASC');

        if ($tab === 'open')    $q->where('status', 'registration_open');
        if ($tab === 'ongoing') $q->whereIn('status', ['ongoing', 'registration_closed']);
        if ($tab === 'past')    $q->where('status', 'completed');

        $tournaments = $q->get();

        $myTeams = TournamentTeam::whereIn('status', ['pending', 'confirmed'])
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->with(['tournament:id,name,slug,status,starts_at', 'division:id,name', 'members.user:id,name'])
            ->latest('id')
            ->get();

        return view('customer.tournaments.index', compact('tournaments', 'myTeams', 'tab'));
    }

    public function show(Tournament $tournament)
    {
        $this->assertVisible($tournament);
        $user = $this->authUser();

        $tournament->load(['divisions' => fn ($q) => $q->withCount([
            'teams' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed']),
        ])]);

        $myDivisionIds = TournamentTeamMember::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->whereHas('team', fn ($q) => $q->whereIn('status', ['pending', 'confirmed']))
            ->pluck('division_id');

        $myTeams = TournamentTeam::where('tournament_id', $tournament->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->with(['division:id,name', 'members.user:id,name'])
            ->get();

        $registrationOpen = $tournament->isRegistrationOpen()
            && (bool) $tournament->getSetting('enable_public_registration', true)
            && $user->is_active;

        return view('customer.tournaments.show', compact('tournament', 'myDivisionIds', 'myTeams', 'registrationOpen'));
    }

    public function register(CustomerTournamentRegistrationRequest $request, TournamentDivision $division)
    {
        $tournament = $division->tournament;
        $this->assertVisible($tournament);
        $user = $this->authUser();

        if (!$tournament->isRegistrationOpen() || !$tournament->getSetting('enable_public_registration', true)) {
            return back()->with('error', 'Registration is closed for this tournament.');
        }
        if (!$user->is_active) {
            return back()->with('error', 'Your membership is inactive. Please contact the front desk.');
        }

        $members = [[
            'user_id' => $user->id,
            'skill_level' => $request->validated('skill_level'),
            'rating' => $request->validated('rating') !== null ? (float) $request->validated('rating') : null,
            'is_captain' => true,
        ]];

        if (!$division->isSingles()) {
            if (!$request->validated('partner_user_id')) {
                throw ValidationException::withMessages(['partner_user_id' => 'Pick a partner for this doubles division.']);
            }
            $members[] = [
                'user_id' => (int) $request->validated('partner_user_id'),
                'skill_level' => $request->validated('partner_skill_level'),
                'rating' => $request->validated('partner_rating') !== null ? (float) $request->validated('partner_rating') : null,
                'is_captain' => false,
            ];
        }

        $team = $this->registration->register(
            $division,
            $members,
            via: 'portal',
            registeredBy: $user,
            teamName: $request->validated('team_name'),
        );

        $fee = $tournament->effectiveEntryFee($division);
        $message = "You're registered in {$division->name}!";

        if ($fee > 0 && $request->boolean('pay_with_wallet')) {
            try {
                // Captain covers the whole team from their wallet.
                foreach ($team->members as $member) {
                    $this->registration->collectFee($member, 'wallet', $user);
                }
                $message .= ' Entry fees were paid from your wallet.';
            } catch (\RuntimeException $e) {
                $message .= ' Your wallet balance was not enough for the entry fee — please settle it at the front desk.';
            }
        } elseif ($fee > 0) {
            $message .= " Please settle the {$tournament->currency} " . number_format($fee, 2) . ' entry fee per player at the front desk.';
        }

        return redirect()->route('customer.tournaments.show', $tournament)->with('success', $message);
    }

    public function withdraw(Request $request, TournamentTeam $team)
    {
        $user = $this->authUser();
        $tournament = $team->tournament;
        $this->assertVisible($tournament);

        abort_unless($team->hasUser($user->id), 403);

        if (!in_array($tournament->status, ['registration_open', 'registration_closed'], true)) {
            return back()->with('error', 'The tournament has started — please see the front desk to withdraw.');
        }
        if ($team->finishedMatches()->exists()) {
            return back()->with('error', 'Your team already has results and cannot withdraw online.');
        }

        // Wallet-paid fees are auto-refunded by PaymentService; cash fees are
        // recorded as refund-due and settled at the desk.
        $this->registration->withdraw($team, refund: true, reason: 'Withdrawn by member from portal', processedBy: $user);

        return back()->with('success', 'Your registration was withdrawn. Wallet payments were refunded automatically.');
    }

    public function bracket(Tournament $tournament, TournamentDivision $division)
    {
        $this->assertVisible($tournament);
        abort_unless($division->tournament_id === $tournament->id, 404);

        $matches = $division->matches()
            ->with(['team1:id,name,seed', 'team2:id,name,seed', 'court:id,name'])
            ->orderBy('round')->orderBy('bracket_pos')
            ->get();

        $groupStandings = [];
        if ($division->bracketLocked() && in_array($division->bracket_format, ['round_robin', 'group_stage', 'pool_play'], true)) {
            $division->load('groups');
            if ($division->groups->isNotEmpty()) {
                foreach ($division->groups as $group) {
                    $groupStandings[$group->name] = $this->rankings->standings($division, $group->id);
                }
            } else {
                $groupStandings['Standings'] = $this->rankings->standings($division);
            }
        }

        return view('customer.tournaments.bracket', compact('tournament', 'division', 'matches', 'groupStandings'));
    }

    /** Privacy-limited partner lookup: id, name and a masked email only. */
    public function partnerSearch(Request $request)
    {
        $user = $this->authUser();
        $q = trim((string) $request->q);

        if (mb_strlen($q) < 3) {
            return response()->json(['members' => []]);
        }

        $members = User::where('tenant_id', $user->tenant_id)
            ->where('user_type', 'customer')
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $this->maskEmail($member->email),
            ]);

        return response()->json(['members' => $members]);
    }

    private function assertVisible(Tournament $tournament): void
    {
        // TenantScope guarantees same-tenant; additionally hide drafts/private/archived.
        abort_if($tournament->visibility !== 'public', 404);
        abort_if($tournament->archived_at !== null, 404);
        abort_if($tournament->status === 'draft', 404);
    }

    private function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible . str_repeat('•', max(2, mb_strlen($local) - 2)) . '@' . $domain;
    }
}
