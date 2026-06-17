<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (!$tenant) {
            abort(403, 'No tenant associated with your account.');
        }

        if ($tenant->status === 'suspended') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account suspended. Please contact support.'], 403);
            }
            return redirect()->route('tenant.suspended');
        }

        if ($tenant->status === 'cancelled') {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account cancelled.'], 403);
            }
            // 'offline' is the closest existing status page (web.php has no tenant.cancelled route)
            return redirect()->route('offline');
        }

        if ($tenant->status === 'trial' && $tenant->trial_ends_at?->isPast()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Trial period has expired. Please subscribe to continue.'], 403);
            }
            return redirect()->route('tenant.trial-expired');
        }

        return $next($request);
    }
}
