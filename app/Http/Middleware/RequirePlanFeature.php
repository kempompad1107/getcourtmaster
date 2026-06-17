<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route gate that ensures the current tenant's subscription plan includes a feature.
 *
 *   Route::get(...)->middleware('plan:advanced_reports');
 *
 * Falls back gracefully: super-admin always passes; tenants without an active
 * subscription get a 402.
 */
class RequirePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if (!$user) return $next($request);
        if ($user->isSuperAdmin()) return $next($request);

        $tenant = $user->tenant;
        if (!$tenant) return $next($request);

        $subscription = $tenant->subscriptions()->where('status', 'active')->latest()->with('plan')->first();
        $plan = $subscription?->plan;

        if (!$plan || !in_array($feature, $plan->features ?? [], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "This feature ({$feature}) is not included in your plan.",
                ], 402);
            }
            return redirect()->route('admin.dashboard')->with('error', "Upgrade your plan to access \"{$feature}\".");
        }

        return $next($request);
    }
}
