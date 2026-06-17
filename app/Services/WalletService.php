<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletService
{
    public function credit(
        User $user,
        float $amount,
        string $description,
        ?Model $transactionable = null,
        ?User $processedBy = null,
        ?string $note = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $description, $transactionable, $processedBy, $note) {
            // Lock the wallet row so the ledger snapshot is consistent even when a
            // credit races a concurrent debit/credit on the same wallet.
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) $locked->wallet_balance;
            $balanceAfter = $balanceBefore + $amount;

            $locked->increment('wallet_balance', $amount);
            $user->wallet_balance = $balanceAfter; // keep the caller's instance in sync

            return WalletTransaction::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'reference' => 'WAL-' . strtoupper((string) Str::ulid()),
                'type' => 'credit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'processed_by' => $processedBy?->id,
                'note' => $note,
                'transactionable_type' => $transactionable ? get_class($transactionable) : null,
                'transactionable_id' => $transactionable?->id,
            ]);
        });
    }

    public function debit(
        User $user,
        float $amount,
        string $description,
        ?Model $transactionable = null,
        ?User $processedBy = null,
        ?string $note = null,
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $description, $transactionable, $processedBy, $note) {
            // Lock the wallet row first so the balance check and the decrement are
            // atomic. Without this, two concurrent debits both read a stale
            // sufficient balance and the wallet can be overdrawn (BOOK-style race).
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->wallet_balance < $amount) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            $balanceBefore = (float) $locked->wallet_balance;
            $balanceAfter = $balanceBefore - $amount;

            $locked->decrement('wallet_balance', $amount);
            $user->wallet_balance = $balanceAfter; // keep the caller's instance in sync

            return WalletTransaction::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'reference' => 'WAL-' . strtoupper((string) Str::ulid()),
                'type' => 'debit',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'processed_by' => $processedBy?->id,
                'note' => $note,
                'transactionable_type' => $transactionable ? get_class($transactionable) : null,
                'transactionable_id' => $transactionable?->id,
            ]);
        });
    }

    public function hasBalance(User $user, float $amount): bool
    {
        return $user->wallet_balance >= $amount;
    }
}
