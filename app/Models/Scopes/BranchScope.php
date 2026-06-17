<?php

namespace App\Models\Scopes;

use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope that filters queries by the active branch context.
 *
 * Behavior:
 *  - No authenticated user (CLI, queues, public pages): scope is a no-op.
 *  - Active branch is set in session: rows are restricted to that branch.
 *  - Active branch is null AND user can see all branches: no branch filter.
 *  - Staff users always get a hard "in (assigned_branches)" ceiling, even
 *    if their session context is somehow null.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        /** @var BranchContext $context */
        $context = app(BranchContext::class);
        $column = $model->getTable() . '.branch_id';

        $current = $context->current();
        if ($current !== null) {
            $builder->where($column, $current);
            return;
        }

        // Null context = "All branches" view, which is only legal for owners
        // and super-admins. Enforce the assigned-branches ceiling for staff.
        if (! $context->canSeeAllBranches($user)) {
            $allowed = $context->allowedBranchIds($user);
            if (empty($allowed)) {
                $builder->whereRaw('1 = 0');
                return;
            }
            $builder->whereIn($column, $allowed);
        }
    }
}
