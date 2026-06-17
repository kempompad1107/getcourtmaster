<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface CustomerRepositoryInterface extends BaseRepositoryInterface
{
    public function search(int $tenantId, string $term, int $limit = 25): Collection;

    public function active(int $tenantId): Collection;

    public function topByLifetimeValue(int $tenantId, int $limit = 10): Collection;

    public function addWalletCredit(User $customer, float $amount, string $reason): User;
}
