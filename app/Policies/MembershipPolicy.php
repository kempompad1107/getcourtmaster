<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('memberships.view') || $user->isCustomer();
    }

    public function view(User $user, Membership $membership): bool
    {
        if ($user->isCustomer()) {
            return $membership->customer_id === $user->id;
        }
        return $user->tenant_id === $membership->tenant_id
            && $user->hasPermissionTo('memberships.view');
    }

    public function create(User $user): bool
    {
        return $user->isCustomer() || $user->hasPermissionTo('memberships.create');
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->tenant_id === $membership->tenant_id
            && $user->hasPermissionTo('memberships.update');
    }

    public function cancel(User $user, Membership $membership): bool
    {
        if ($user->isCustomer()) {
            return $membership->customer_id === $user->id;
        }
        return $user->tenant_id === $membership->tenant_id
            && $user->hasPermissionTo('memberships.cancel');
    }
}
