<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global tenant isolation (ARCH-01).
 *
 * Keyed off the authenticated user's tenant so it works for both web and API
 * requests without depending on SetTenantContext. Fails *open* only in the
 * cases that must remain cross-tenant or pre-tenant:
 *   - no authenticated user (CLI, queues, webhooks, login/registration)
 *   - super-admins (the SaaS operator, who works across all tenants)
 *   - users with no tenant_id
 *
 * In every authenticated tenant-user request it adds `tenant_id = <user tenant>`,
 * so a forgotten manual scope or a hostile route-model-bound id can no longer
 * read another tenant's rows. Use `withoutGlobalScope(TenantScope::class)` for
 * the rare intentional cross-tenant query.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        // Unauthenticated contexts (CLI, queues, webhooks, login/registration) and
        // the super-admin operator legitimately span tenants.
        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        // An authenticated non-super-admin with no tenant is an orphan account
        // (e.g. a tenant-less social login or API signup). It must NEVER read
        // another tenant's rows, so fail CLOSED — previously this returned early
        // and silently disabled tenant isolation for the whole request.
        if ($user->tenant_id === null) {
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
    }
}
