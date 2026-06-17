<?php

namespace App\Repositories\Eloquent;

use App\Models\Membership;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use Illuminate\Support\Collection;

class MembershipRepository extends BaseRepository implements MembershipRepositoryInterface
{
    public function model(): string
    {
        return Membership::class;
    }

    public function activeForCustomer(int $customerId): ?Membership
    {
        return $this->query()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    public function expiringWithin(int $tenantId, int $days = 7): Collection
    {
        return $this->forTenant($tenantId)
            ->where('status', 'active')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->get();
    }

    public function dueForRenewal(int $tenantId): Collection
    {
        return $this->forTenant($tenantId)
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->where('expires_at', '<=', now())
            ->get();
    }
}
