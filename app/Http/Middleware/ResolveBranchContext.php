<?php

namespace App\Http\Middleware;

use App\Services\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    public function __construct(private readonly BranchContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            // Touch current() so it validates and back-fills session defaults.
            $activeId = $this->context->current();
            $available = $this->context->available();
            $active = $activeId
                ? $available->firstWhere('id', $activeId)
                : null;

            View::share('activeBranchId', $activeId);
            View::share('activeBranch', $active);
            View::share('availableBranches', $available);
            View::share('canSeeAllBranches', $this->context->canSeeAllBranches());
        }

        return $next($request);
    }
}
