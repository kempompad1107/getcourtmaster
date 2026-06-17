<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Models\WalletTransaction;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    public function model(): string
    {
        return User::class;
    }

    public function search(int $tenantId, string $term, int $limit = 25): Collection
    {
        return $this->forTenant($tenantId)
            ->where('user_type', 'customer')
            ->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            })
            ->limit($limit)
            ->get();
    }

    public function active(int $tenantId): Collection
    {
        return $this->forTenant($tenantId)
            ->where('user_type', 'customer')
            ->where('is_active', true)
            ->get();
    }

    public function topByLifetimeValue(int $tenantId, int $limit = 10): Collection
    {
        return $this->forTenant($tenantId)
            ->where('user_type', 'customer')
            ->withSum(['payments as ltv' => fn ($q) => $q->where('status', 'paid')], 'amount')
            ->orderByDesc('ltv')
            ->limit($limit)
            ->get();
    }

    public function addWalletCredit(User $customer, float $amount, string $reason): User
    {
        return DB::transaction(function () use ($customer, $amount, $reason) {
            $customer->increment('wallet_balance', $amount);

            WalletTransaction::create([
                'user_id' => $customer->id,
                'tenant_id' => $customer->tenant_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $customer->fresh()->wallet_balance,
                'description' => $reason,
            ]);

            return $customer->fresh();
        });
    }
}
