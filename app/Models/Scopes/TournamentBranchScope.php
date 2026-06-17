<?php

namespace App\Models\Scopes;

use App\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Branch visibility for tournaments. Layers on top of TenantScope.
 *
 *  - No authenticated user (CLI, queues, public): no-op.
 *  - Super-admin: no-op (operates across the SaaS).
 *  - Customer: sees all-branches tournaments + exclusives matching their
 *    home_branch_id. A customer with no home branch sees only all-branches ones.
 *  - Staff / owner: keyed off the active BranchContext. A selected branch shows
 *    all-branches tournaments + that branch's exclusives. Owners viewing
 *    "All branches" (null context) see everything; staff get an assigned-branch
 *    ceiling as a safety net.
 *
 * All-branches tournaments (is_all_branches = true) are visible to everyone in
 * the tenant. Use withoutGlobalScope(TournamentBranchScope::class) for the rare
 * intentional cross-branch query (reports, super-admin tooling).
 */
class TournamentBranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();
        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        $table = $model->getTable();
        $allBranches = "{$table}.is_all_branches";
        $branchCol = "{$table}.branch_id";

        if ($user->isCustomer()) {
            $builder->where(fn ($q) => $q
                ->where($allBranches, true)
                ->when($user->home_branch_id, fn ($q) => $q->orWhere($branchCol, $user->home_branch_id)));
            return;
        }

        // Staff / owner.
        $context = app(BranchContext::class);
        $current = $context->current();

        if ($current !== null) {
            $builder->where(fn ($q) => $q
                ->where($allBranches, true)
                ->orWhere($branchCol, $current));
            return;
        }

        // Null context: owners/super-admins see all; staff get a hard ceiling.
        if (! $context->canSeeAllBranches($user)) {
            $allowed = $context->allowedBranchIds($user);
            if (empty($allowed)) {
                $builder->where($allBranches, true);
                return;
            }
            $builder->where(fn ($q) => $q
                ->where($allBranches, true)
                ->orWhereIn($branchCol, $allowed));
        }
    }
}
