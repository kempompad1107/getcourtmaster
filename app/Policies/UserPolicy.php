<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasPermissionTo('staff.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->tenant_id === $target->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('staff.view'));
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasPermissionTo('staff.create');
    }

    public function update(User $user, User $target): bool
    {
        if ($user->tenant_id !== $target->tenant_id) {
            return false;
        }
        if ($target->isBusinessOwner() && $user->id !== $target->id) {
            return false;
        }
        return $user->isBusinessOwner() || $user->hasPermissionTo('staff.update');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->tenant_id === $target->tenant_id
            && $user->id !== $target->id
            && !$target->isBusinessOwner()
            && $user->isBusinessOwner();
    }
}
