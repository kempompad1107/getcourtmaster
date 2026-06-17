<?php

namespace App\Http\Middleware;

use App\Services\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks branch-scoped writes when the user is viewing "All branches".
 *
 * Owners and super-admins can view the portfolio across every branch
 * (BranchContext::current() === null), but writing in that mode is
 * ambiguous — which branch should the new row belong to? This guard
 * forces them to pick a specific branch first.
 *
 * Reads (GET/HEAD/OPTIONS) and staff (who can't pick "All") pass through.
 */
class EnsureBranchSelected
{
    public function __construct(private readonly BranchContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Staff are clamped to specific branches by BranchContext already,
        // so they can never be in the ambiguous "All" state.
        if (! $this->context->canSeeAllBranches($user)) {
            return $next($request);
        }

        if ($this->context->current() !== null) {
            return $next($request);
        }

        $message = 'Please select a specific branch before making changes. '
            . 'Use the branch switcher in the top bar to pick one.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'code'    => 'branch_required',
            ], 422);
        }

        return redirect()->back()
            ->with('error', $message)
            ->withInput();
    }
}
