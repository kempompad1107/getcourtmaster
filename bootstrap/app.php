<?php

use App\Http\Middleware\EnsureBranchSelected;
use App\Http\Middleware\EnsureStaffOrOwner;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\RequirePlanFeature;
use App\Http\Middleware\ResolveBranchContext;
use App\Http\Middleware\SetTenantContext;
use App\Http\Middleware\TrackUserSession;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.active'      => EnsureTenantIsActive::class,
            'staff.only'         => EnsureStaffOrOwner::class,
            'perm'               => \App\Http\Middleware\EnsurePermission::class,
            'tenant.context'     => SetTenantContext::class,
            'branch.context'     => ResolveBranchContext::class,
            'branch.required'    => EnsureBranchSelected::class,
            'track.session'      => TrackUserSession::class,
            'plan'               => RequirePlanFeature::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SetTenantMailer::class,
        ]);

        $middleware->api(append: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
            }
            return redirect()->back()->with('error', 'Unauthorized action.');
        });
    })->create();
