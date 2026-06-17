<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasPermissionTo('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasPermissionTo('branches.create');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('branches.update'));
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->tenant_id === $branch->tenant_id && $user->isBusinessOwner();
    }
}
