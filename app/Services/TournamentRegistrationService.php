<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\TournamentDivision;
use App\Models\TournamentTeam;
use App\Models\TournamentTeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentRegistrationService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Register a team (singles team = 1 member) into a division.
     *
     * @param array $memberPayload [['user_id' => int, 'skill_level' => ?string,
     *                              'rating' => ?float, 'is_captain' => bool], ...]
     */
    public function register(
        TournamentDivision $division,
        array $memberPayload,
        string $via,
        User $registeredBy,
        ?string $teamName = null,
    ): TournamentTeam {
        return DB::transaction(function () use ($division, $memberPayload, $via, $registeredBy, $teamName) {
            $tournament = $division->tournament;

            if ($division->bracketLocked()) {
                throw ValidationException::withMessages([
                    'division' => 'The bracket for this division has already been generated. Reset it before adding teams.',
                ]);
            }

            if (count($memberPayload) !== (int) $division->team_size) {
                throw ValidationException::withMessages([
                    'members' => "This division requires exactly {$division->team_size} player(s) per entry.",
                ]);
            }

            // Capacity check under lock so concurrent registrations can't oversell
            // the last slot.
            if ($division->max_entries !== null) {
                $current = TournamentTeam::where('division_id', $division->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->lockForUpdate()
                    ->count();
                if ($current >= $division->max_entries) {
                    throw ValidationException::withMessages([
                        'division' => 'This division is full.',
                    ]);
                }
            }

            $userIds = array_column($memberPayload, 'user_id');

            if (count($userIds) !== count(array_unique($userIds))) {
                throw ValidationException::withMessages([
                    'members' => 'The same member cannot fill both slots of a team.',
                ]);
            }

            $users = User::where('tenant_id', $tournament->tenant_id)
                ->where('user_type', 'customer')
                ->where('is_active', true)
                ->whereIn('id', $userIds)
                ->get()
                ->keyBy('id');

            foreach ($userIds as $userId) {
                if (!$users->has($userId)) {
                    throw ValidationException::withMessages([
                        'members' => 'Only active members of this venue can be registered.',
                    ]);
                }
            }

            $alreadyIn = TournamentTeamMember::where('division_id', $division->id)
                ->whereIn('user_id', $userIds)
                ->whereHas('team', fn ($q) => $q->whereIn('status', ['pending', 'confirmed']))
                ->with('user:id,name')
                ->first();
            if ($alreadyIn) {
                throw ValidationException::withMessages([
                    'members' => "{$alreadyIn->user->name} is already registered in this division.",
                ]);
            }

            // Withdrawn/disqualified teams leave their member rows behind (payments
            // hang off them), and those rows still occupy the unique
            // (division_id, user_id) slot — so re-registration must clear the
            // payment-free leftovers first or the INSERT would 1062-crash.
            $this->clearStaleRegistrations($division->id, $userIds);

            $this->assertDivisionEligibility($division, $users->values()->all(), $tournament);

            // Portal self-registration honors branch exclusivity by the member's
            // home branch. Staff desk registration (via = 'admin') is the override
            // and intentionally skips this check.
            if ($via === 'portal' && ! $tournament->is_all_branches && $tournament->branch_id) {
                foreach ($users as $user) {
                    if ((int) $user->home_branch_id !== (int) $tournament->branch_id) {
                        throw ValidationException::withMessages([
                            'members' => "{$user->name} is not a member of this tournament's branch — please ask the front desk to register you.",
                        ]);
                    }
                }
            }

            $fee = $tournament->effectiveEntryFee($division);

            $team = TournamentTeam::create([
                'tenant_id' => $tournament->tenant_id,
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
                'name' => $teamName ?: $this->defaultTeamName($users->values()->all()),
                'status' => $fee <= 0 ? 'confirmed' : 'pending',
                'registered_via' => $via,
                'registered_by' => $registeredBy->id,
            ]);

            foreach ($memberPayload as $i => $member) {
                TournamentTeamMember::create([
                    'tenant_id' => $tournament->tenant_id,
                    'team_id' => $team->id,
                    'tournament_id' => $tournament->id,
                    'division_id' => $division->id,
                    'user_id' => $member['user_id'],
                    'is_captain' => (bool) ($member['is_captain'] ?? $i === 0),
                    'skill_level' => $member['skill_level'] ?? null,
                    'rating' => $member['rating'] ?? null,
                ]);
            }

            activity()->on($team)->log("Team registered into {$division->name}");

            return $team->load('members.user');
        });
    }

    /** Collect one player's entry fee in cash or from their wallet. */
    public function collectFee(TournamentTeamMember $member, string $method, User $processedBy): ?Payment
    {
        $division = $member->division;
        $tournament = $member->tournament;
        $fee = $tournament->effectiveEntryFee($division);

        if ($fee <= 0) {
            $this->confirmIfFullyPaid($member->team);
            return null;
        }

        if ($member->hasPaid()) {
            throw ValidationException::withMessages([
                'payment' => "{$member->user->name} has already paid the entry fee.",
            ]);
        }

        return DB::transaction(function () use ($member, $method, $processedBy, $fee, $tournament, $division) {
            if ($method === 'wallet') {
                // Throws RuntimeException on insufficient balance (locked check).
                $this->wallet->debit(
                    $member->user,
                    $fee,
                    "Entry fee — {$tournament->name} / {$division->name}",
                    $member,
                    $processedBy,
                );
            }

            $payment = Payment::create([
                'tenant_id' => $tournament->tenant_id,
                'customer_id' => $member->user_id,
                'payable_type' => TournamentTeamMember::class,
                'payable_id' => $member->id,
                'amount' => $fee,
                'currency' => $tournament->currency,
                'method' => $method,
                'status' => 'paid',
                'paid_at' => now(),
                'processed_by' => $processedBy->id,
                'notes' => "Tournament entry fee — {$tournament->name} / {$division->name}",
            ]);

            $this->confirmIfFullyPaid($member->team);

            return $payment;
        });
    }

    /** Withdraw a team, optionally refunding every paid entry fee. */
    public function withdraw(TournamentTeam $team, bool $refund, string $reason, User $processedBy): void
    {
        if ($team->status === 'withdrawn') {
            return; // idempotent
        }

        if ($team->finishedMatches()->exists()) {
            throw ValidationException::withMessages([
                'team' => 'This team has finished matches. Disqualify it instead of withdrawing.',
            ]);
        }

        DB::transaction(function () use ($team, $refund, $reason, $processedBy) {
            $team->update([
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
                'notes' => trim(($team->notes ? $team->notes . "\n" : '') . "Withdrawn: {$reason}"),
            ]);

            if ($team->division->bracketLocked()) {
                app(BracketService::class)->forfeitTeam($team);
            }

            if ($refund) {
                foreach ($team->members as $member) {
                    foreach ($member->payments()->whereIn('status', ['paid', 'partial'])->get() as $payment) {
                        // Handles its own locks; credits wallet only for wallet-method
                        // payments. Cash refunds are recorded on the payment row and
                        // settled at the desk.
                        $this->payments->processRefund($payment, (float) $payment->amount, $reason);
                    }
                }
            }

            activity()->causedBy($processedBy)->on($team)->log("Team withdrawn: {$reason}");
        });
    }

    /** Swap one member of a team for another (partner change). */
    public function changePartner(TournamentTeam $team, int $removeUserId, array $newMember, User $processedBy): void
    {
        DB::transaction(function () use ($team, $removeUserId, $newMember, $processedBy) {
            $division = $team->division;
            $tournament = $team->tournament;

            if ($team->finishedMatches()->exists()) {
                throw ValidationException::withMessages([
                    'team' => 'This team has finished matches; the roster is locked.',
                ]);
            }

            $outgoing = $team->members()->where('user_id', $removeUserId)->first();
            if (!$outgoing) {
                throw ValidationException::withMessages([
                    'team' => 'That member is not part of this team.',
                ]);
            }

            $user = User::where('tenant_id', $tournament->tenant_id)
                ->where('user_type', 'customer')
                ->where('is_active', true)
                ->find($newMember['user_id']);
            if (!$user) {
                throw ValidationException::withMessages([
                    'members' => 'Only active members of this venue can be registered.',
                ]);
            }

            $alreadyIn = TournamentTeamMember::where('division_id', $division->id)
                ->where('user_id', $user->id)
                ->whereHas('team', fn ($q) => $q->whereIn('status', ['pending', 'confirmed']))
                ->exists();
            if ($alreadyIn) {
                throw ValidationException::withMessages([
                    'members' => "{$user->name} is already registered in this division.",
                ]);
            }

            // Free any payment-free leftover row from a prior withdrawn/disqualified
            // team so the incoming partner doesn't collide with the unique index.
            $this->clearStaleRegistrations($division->id, [$user->id]);

            // The swapped roster must still satisfy the division's gender/age rules.
            $remainingUserIds = $team->members()->where('user_id', '!=', $removeUserId)->pluck('user_id')->all();
            $roster = User::whereIn('id', array_merge($remainingUserIds, [$user->id]))->get()->all();
            $this->assertDivisionEligibility($division, $roster, $tournament);

            $wasCaptain = $outgoing->is_captain;
            $outgoing->delete();

            TournamentTeamMember::create([
                'tenant_id' => $tournament->tenant_id,
                'team_id' => $team->id,
                'tournament_id' => $tournament->id,
                'division_id' => $division->id,
                'user_id' => $user->id,
                'is_captain' => $wasCaptain,
                'skill_level' => $newMember['skill_level'] ?? null,
                'rating' => $newMember['rating'] ?? null,
            ]);

            // New player owes the fee; re-evaluate confirmation.
            $fee = $tournament->effectiveEntryFee($division);
            if ($fee > 0) {
                $team->update(['status' => 'pending']);
                $this->confirmIfFullyPaid($team);
            }

            activity()->causedBy($processedBy)->on($team)->log('Partner changed');
        });
    }

    /**
     * Enforce a division's gender and age restrictions against the team roster.
     * A member whose gender/DOB is unknown is allowed through (we can only
     * enforce what we know); known data that conflicts is rejected.
     *
     * @param \App\Models\User[] $users
     */
    private function assertDivisionEligibility(TournamentDivision $division, array $users, \App\Models\Tournament $tournament): void
    {
        // ── Age ──────────────────────────────────────────────────────────────
        if ($division->min_age !== null || $division->max_age !== null) {
            $asOf = $tournament->starts_at ?? now();
            foreach ($users as $user) {
                $age = $user->ageAt($asOf);
                if ($age === null) {
                    continue; // unknown DOB → allow
                }
                if ($division->min_age !== null && $age < $division->min_age) {
                    throw ValidationException::withMessages([
                        'members' => "{$user->name} is under the minimum age ({$division->min_age}) for {$division->name}.",
                    ]);
                }
                if ($division->max_age !== null && $age > $division->max_age) {
                    throw ValidationException::withMessages([
                        'members' => "{$user->name} is over the maximum age ({$division->max_age}) for {$division->name}.",
                    ]);
                }
            }
        }

        // ── Gender ───────────────────────────────────────────────────────────
        if ($division->gender === 'men' || $division->gender === 'women') {
            $required = $division->gender === 'men' ? 'male' : 'female';
            $label = $division->gender === 'men' ? "Men's" : "Women's";
            foreach ($users as $user) {
                if ($user->gender !== null && $user->gender !== $required) {
                    throw ValidationException::withMessages([
                        'members' => "{$user->name} does not meet the {$label} division requirement.",
                    ]);
                }
            }
        } elseif ($division->gender === 'mixed' && count($users) === 2) {
            // Mixed doubles: one of each, but only when both genders are known.
            $genders = array_values(array_filter(array_map(fn ($u) => $u->gender, $users)));
            if (count($genders) === 2 && $genders[0] === $genders[1]) {
                throw ValidationException::withMessages([
                    'members' => 'A mixed division team must pair one man with one woman.',
                ]);
            }
        }
        // 'open' → no gender restriction.
    }

    /**
     * Remove leftover member rows from withdrawn/disqualified teams that block the
     * unique (division_id, user_id) slot. Rows with payment history are kept (to
     * preserve fee/refund records) and instead surface a clear, recoverable error.
     *
     * @param int[] $userIds
     */
    private function clearStaleRegistrations(int $divisionId, array $userIds): void
    {
        $stale = TournamentTeamMember::where('division_id', $divisionId)
            ->whereIn('user_id', $userIds)
            ->whereHas('team', fn ($q) => $q->whereIn('status', ['withdrawn', 'disqualified']))
            ->with('user:id,name')
            ->get();

        foreach ($stale as $row) {
            if ($row->payments()->exists()) {
                throw ValidationException::withMessages([
                    'members' => "{$row->user->name} has an earlier registration with payment history in this division — please re-register from the front desk so the fees can be reconciled.",
                ]);
            }
            $row->delete();
        }
    }

    private function confirmIfFullyPaid(TournamentTeam $team): void
    {
        $allPaid = $team->members()
            ->get()
            ->every(fn (TournamentTeamMember $m) => $m->hasPaid());

        $fee = $team->tournament->effectiveEntryFee($team->division);

        if (($allPaid || $fee <= 0) && $team->status === 'pending') {
            $team->update(['status' => 'confirmed']);
        }
    }

    private function defaultTeamName(array $users): string
    {
        $lastNames = array_map(function (User $user) {
            $parts = preg_split('/\s+/', trim($user->name));
            return end($parts) ?: $user->name;
        }, $users);

        return implode(' / ', $lastNames);
    }
}
