<?php

namespace App\Policies;

use App\Models\Court;
use App\Models\User;

class CourtPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Court $court): bool
    {
        return $user->tenant_id === $court->tenant_id || $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasPermissionTo('courts.create');
    }

    public function update(User $user, Court $court): bool
    {
        return $user->tenant_id === $court->tenant_id
            && ($user->isBusinessOwner() || $user->hasPermissionTo('courts.update'));
    }

    public function delete(User $user, Court $court): bool
    {
        return $user->tenant_id === $court->tenant_id && $user->isBusinessOwner();
    }

    public function updateStatus(User $user, Court $court): bool
    {
        return $user->tenant_id === $court->tenant_id
            && $user->hasPermissionTo('courts.status');
    }
}
