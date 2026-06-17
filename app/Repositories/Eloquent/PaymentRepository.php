<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Support\Collection;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function model(): string
    {
        return Payment::class;
    }

    public function findByReference(string $reference): ?Payment
    {
        return $this->query()->where('gateway_reference', $reference)->first();
    }

    public function forCustomer(int $customerId, array $statuses = []): Collection
    {
        $q = $this->query()->where('customer_id', $customerId);
        if (!empty($statuses)) {
            $q->whereIn('status', $statuses);
        }
        return $q->latest()->get();
    }

    public function pendingForTenant(int $tenantId): Collection
    {
        return $this->forTenant($tenantId)
            ->whereIn('status', ['pending', 'partial'])
            ->latest()
            ->get();
    }

    public function revenueBetween(int $tenantId, string $from, string $to): float
    {
        return (float) $this->forTenant($tenantId)
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');
    }
}
