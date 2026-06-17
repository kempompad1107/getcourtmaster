<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Authenticated tenant scoping. Each user may only subscribe to channels
| belonging to their own tenant. Super-admin can subscribe to any.
*/

Broadcast::channel('tenant.{tenantId}.courts', function (User $user, int $tenantId) {
    return $user->isSuperAdmin() || (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.bookings', function (User $user, int $tenantId) {
    return $user->isSuperAdmin() || (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('tenant.{tenantId}.timers', function (User $user, int $tenantId) {
    return $user->isSuperAdmin() || (int) $user->tenant_id === $tenantId;
});

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});
