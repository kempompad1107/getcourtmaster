<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Per-request "current branch" context.
 *
 * Owners and super-admins can select any of their tenant's branches, or
 * "All branches" (null). Staff are clamped to their `staff_profile.assigned_branches`
 * — they cannot pick "All", and an out-of-range pick is silently reset.
 */
class BranchContext
{
    private const SESSION_KEY = 'active_branch_id';

    private ?int $cachedId = null;
    private bool $resolved = false;

    public function current(): ?int
    {
        if (! $this->resolved) {
            $this->cachedId = $this->resolve();
            $this->resolved = true;
        }
        return $this->cachedId;
    }

    public function set(?int $branchId): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        // null = "All branches" — only owners and super-admins may pick this.
        if ($branchId === null) {
            if ($this->canSeeAllBranches($user)) {
                Session::put(self::SESSION_KEY, null);
                $this->resetCache();
            }
            return;
        }

        $allowed = $this->allowedBranchIds($user);
        if (in_array($branchId, $allowed, true)) {
            Session::put(self::SESSION_KEY, $branchId);
            $this->resetCache();
        }
    }

    public function available(): Collection
    {
        $user = Auth::user();
        if (! $user || ! $user->tenant_id) {
            return collect();
        }

        $query = Branch::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name');

        if (! $this->canSeeAllBranches($user)) {
            $query->whereIn('id', $this->allowedBranchIds($user) ?: [0]);
        }

        return $query->get(['id', 'name', 'is_main']);
    }

    public function canSeeAllBranches(?User $user = null): bool
    {
        $user ??= Auth::user();
        if (! $user) {
            return false;
        }
        // Customers shop across the entire tenant. Owners and super-admins
        // get the portfolio view by default. Staff are the only role
        // restricted to their assigned branches.
        return $user->isSuperAdmin() || $user->isBusinessOwner() || $user->isCustomer();
    }

    /**
     * Branch IDs a user is permitted to reference. For owners/super-admins
     * this is every active branch in their tenant. For staff this is the
     * intersection of their primary branch + assigned_branches with active
     * branches in the tenant.
     */
    public function allowedBranchIds(?User $user = null): array
    {
        $user ??= Auth::user();
        if (! $user || ! $user->tenant_id) {
            return [];
        }

        $tenantBranchIds = Branch::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->pluck('id')->all();

        if ($this->canSeeAllBranches($user)) {
            return $tenantBranchIds;
        }

        $profile = $user->staffProfile;
        $assigned = $profile?->assigned_branches ?: [];
        if ($profile?->branch_id) {
            $assigned[] = $profile->branch_id;
        }
        $assigned = array_values(array_unique(array_map('intval', $assigned)));

        return array_values(array_intersect($assigned, $tenantBranchIds));
    }

    /**
     * Resolve the active branch ID from session, with safety clamps so
     * a stale or hostile session value cannot escape the user's scope.
     */
    private function resolve(): ?int
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $allowed = $this->allowedBranchIds($user);
        $sessionValue = Session::get(self::SESSION_KEY);

        // "All branches" is only valid for owners/super-admins.
        if ($sessionValue === null) {
            if ($this->canSeeAllBranches($user)) {
                return null;
            }
            // Staff with no choice yet — fall through to default below.
        }

        $sessionInt = is_numeric($sessionValue) ? (int) $sessionValue : null;

        if ($sessionInt !== null && in_array($sessionInt, $allowed, true)) {
            return $sessionInt;
        }

        // Default for staff (or invalid session value): primary branch, or
        // the first branch they can access, or none at all.
        $primary = $user->staffProfile?->branch_id;
        if ($primary && in_array((int) $primary, $allowed, true)) {
            Session::put(self::SESSION_KEY, (int) $primary);
            return (int) $primary;
        }

        $fallback = $allowed[0] ?? null;
        if ($fallback !== null) {
            Session::put(self::SESSION_KEY, $fallback);
        }
        return $fallback;
    }

    private function resetCache(): void
    {
        $this->resolved = false;
        $this->cachedId = null;
    }
}
