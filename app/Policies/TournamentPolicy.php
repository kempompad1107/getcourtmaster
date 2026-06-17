<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

/**
 * One policy for the whole tournament module: child entities (divisions,
 * teams, brackets, matches) authorize through their parent tournament,
 * e.g. $this->authorize('manageRegistrations', $team->tournament).
 */
class TournamentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasPermissionTo('tournaments.view');
    }

    public function view(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.view'));
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasPermissionTo('tournaments.create');
    }

    public function update(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.update'));
    }

    public function delete(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.delete'));
    }

    public function duplicate(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.create'));
    }

    public function publish(User $user, Tournament $tournament): bool
    {
        return $this->update($user, $tournament);
    }

    public function archive(User $user, Tournament $tournament): bool
    {
        return $this->update($user, $tournament);
    }

    public function manageRegistrations(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.registrations'));
    }

    public function manageBrackets(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.brackets'));
    }

    public function manageMatches(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.matches'));
    }

    public function viewReports(User $user, Tournament $tournament): bool
    {
        return $user->tenant_id === $tournament->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('tournaments.reports'));
    }
}
