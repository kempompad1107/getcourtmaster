<?php

namespace App\Services;

use App\Models\CashDrawerLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashDrawerService
{
    public function record(User $user, string $action, float $amount, ?string $reason = null, ?int $branchId = null): CashDrawerLog
    {
        return DB::transaction(function () use ($user, $action, $amount, $reason, $branchId) {
            $tenantId = $user->tenant_id;
            $branchId = $branchId ?? optional($user->staffProfile)->branch_id;

            $current = $this->currentBalance($tenantId, $branchId);

            $delta = match ($action) {
                'open', 'in', 'adjust' => $amount,
                'out', 'close'         => -$amount,
                default                => 0,
            };

            return CashDrawerLog::create([
                'tenant_id'     => $tenantId,
                'branch_id'     => $branchId,
                'user_id'       => $user->id,
                'action'        => $action,
                'amount'        => abs($amount),
                'balance_after' => $current + $delta,
                'reason'        => $reason,
            ]);
        });
    }

    public function currentBalance(int $tenantId, ?int $branchId = null): float
    {
        $row = CashDrawerLog::where('tenant_id', $tenantId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->first();
        return (float) ($row?->balance_after ?? 0);
    }
}
