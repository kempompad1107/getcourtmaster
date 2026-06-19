<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-module permission gate for the /admin/* back-office.
 *
 * `staff.only` (EnsureStaffOrOwner) only proves the user belongs in the
 * back-office at all. This gate proves they hold the permission a given module
 * requires — the thing the Roles & Permissions screen actually toggles.
 *
 * Without it, unchecking a role permission only hid the sidebar link; the page
 * stayed reachable by typing the URL and in-page actions still fired. This
 * mirrors the sidebar's `$isOwner || $u->can(...)` logic exactly: owners hold
 * every permission by design and super-admins bypass all gates, so both pass.
 *
 * Accepts one or more permissions; access is granted if the user holds ANY of
 * them (e.g. `perm:courts.view,bookings.view`).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        $allowed = $user && (
            $user->isSuperAdmin()
            || $user->isBusinessOwner()
            || $user->hasAnyPermission($permissions)
        );

        if (! $allowed) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have permission to access this area.'], 403);
            }
            abort(403, 'You do not have permission to access this area.');
        }

        return $next($request);
    }
}
