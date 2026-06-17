<?php

namespace App\Repositories\Contracts;

use App\Models\Membership;
use Illuminate\Support\Collection;

interface MembershipRepositoryInterface extends BaseRepositoryInterface
{
    public function activeForCustomer(int $customerId): ?Membership;

    public function expiringWithin(int $tenantId, int $days = 7): Collection;

    public function dueForRenewal(int $tenantId): Collection;
}
