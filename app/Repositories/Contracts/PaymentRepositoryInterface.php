<?php

namespace App\Repositories\Contracts;

use App\Models\Payment;
use Illuminate\Support\Collection;

interface PaymentRepositoryInterface extends BaseRepositoryInterface
{
    public function findByReference(string $reference): ?Payment;

    public function forCustomer(int $customerId, array $statuses = []): Collection;

    public function pendingForTenant(int $tenantId): Collection;

    public function revenueBetween(int $tenantId, string $from, string $to): float;
}
