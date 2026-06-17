<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant) {
            $tenant = $user->tenant;

            // Bind the tenant on the container so non-request-bound services
            // (GatewayManager, etc.) can resolve it without a request reference.
            app()->instance('currentTenant', $tenant);

            View::share('currentTenant', $tenant);
            View::share('tenantSettings', $tenant->settings ?? []);

            if ($tenant->timezone) {
                config(['app.timezone' => $tenant->timezone]);
            }
        }

        return $next($request);
    }
}
