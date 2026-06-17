<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the /admin/* back-office. Only business owners and staff (and
 * super-admins) may enter. Customers — who share the same authenticated
 * session and tenant — must be turned away here, since several admin
 * controllers authorize by tenant only and would otherwise leak data or
 * allow privileged writes (see SEC-01/SEC-04).
 */
class EnsureStaffOrOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $allowed = $user && ($user->isSuperAdmin() || $user->isBusinessOwner() || $user->isStaff());

        if (! $allowed) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have access to this area.'], 403);
            }
            // Customers landing on an admin URL are bounced to their own portal.
            if ($user && $user->isCustomer()) {
                return redirect()->route('customer.dashboard')
                    ->with('error', 'That area is for venue staff only.');
            }
            abort(403, 'You do not have access to this area.');
        }

        return $next($request);
    }
}
