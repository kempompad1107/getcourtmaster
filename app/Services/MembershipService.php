<?php

namespace App\Services;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    /**
     * Sell a membership and record the payment. The $method argument decides
     * how the payment is recorded — 'wallet' debits the customer's wallet
     * atomically; all other methods record a paid Payment row whose money
     * was collected outside (cash at the desk, GCash QR, etc.) and is now
     * being acknowledged in the ledger.
     */
    public function subscribe(
        User $customer,
        MembershipPlan $plan,
        bool $autoRenew = true,
        string $method = 'cash',
    ): Membership {
        return DB::transaction(function () use ($customer, $plan, $autoRenew, $method) {
            // Wallet payment: pull funds first so we don't issue the membership
            // when the balance isn't actually there.
            if ($method === 'wallet') {
                if ($customer->wallet_balance < $plan->price) {
                    throw ValidationException::withMessages([
                        'payment_method' => "Customer wallet has ₱" . number_format($customer->wallet_balance, 2)
                            . " — short by ₱" . number_format($plan->price - $customer->wallet_balance, 2) . ".",
                    ]);
                }
                app(WalletService::class)->debit(
                    $customer,
                    (float) $plan->price,
                    "Purchased {$plan->name} membership",
                );
            }

            $startsAt = now();
            $expiresAt = $startsAt->copy()->addDays($plan->duration_days);

            $membership = Membership::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'remaining_credits' => $plan->court_credits,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'auto_renew' => $autoRenew,
            ]);

            MembershipTransaction::create([
                'membership_id' => $membership->id,
                'type' => 'purchase',
                'credits_change' => $plan->court_credits,
                'amount' => $plan->price,
                'description' => "Purchased {$plan->name} membership (via {$method})",
            ]);

            Payment::create([
                'tenant_id'    => $customer->tenant_id,
                'customer_id'  => $customer->id,
                'payable_type' => Membership::class,
                'payable_id'   => $membership->id,
                'amount'       => $plan->price,
                'method'       => $method,
                'status'       => 'paid',
                'paid_at'      => now(),
                'notes'        => "Purchased {$plan->name} membership",
            ]);

            return $membership;
        });
    }

    public function renew(Membership $membership, string $method = 'cash'): Membership
    {
        return DB::transaction(function () use ($membership, $method) {
            $plan = $membership->plan;

            if ($method === 'wallet') {
                $customer = $membership->customer;
                if (! $customer || $customer->wallet_balance < $plan->price) {
                    throw ValidationException::withMessages([
                        'payment_method' => "Customer wallet has ₱" . number_format($customer?->wallet_balance ?? 0, 2)
                            . " — short by ₱" . number_format($plan->price - ($customer?->wallet_balance ?? 0), 2) . ".",
                    ]);
                }
                app(WalletService::class)->debit(
                    $customer,
                    (float) $plan->price,
                    "Renewed {$plan->name} membership",
                );
            }

            $expiresAt = $membership->expires_at->isFuture()
                ? $membership->expires_at->addDays($plan->duration_days)
                : now()->addDays($plan->duration_days);

            $membership->update([
                'status' => 'active',
                'expires_at' => $expiresAt,
                'remaining_credits' => $membership->remaining_credits + $plan->court_credits,
            ]);

            MembershipTransaction::create([
                'membership_id' => $membership->id,
                'type' => 'renewal',
                'credits_change' => $plan->court_credits,
                'amount' => $plan->price,
                'description' => "Renewed {$plan->name} membership (via {$method})",
            ]);

            Payment::create([
                'tenant_id'    => $membership->tenant_id,
                'customer_id'  => $membership->customer_id,
                'payable_type' => Membership::class,
                'payable_id'   => $membership->id,
                'amount'       => $plan->price,
                'method'       => $method,
                'status'       => 'paid',
                'paid_at'      => now(),
                'notes'        => "Renewed {$plan->name} membership",
            ]);

            return $membership;
        });
    }

    public function freeze(Membership $membership, Carbon $until): Membership
    {
        $membership->update([
            'status' => 'frozen',
            'frozen_at' => now(),
            'frozen_until' => $until,
        ]);

        MembershipTransaction::create([
            'membership_id' => $membership->id,
            'type' => 'freeze',
            'description' => "Membership frozen until {$until->toDateString()}",
        ]);

        return $membership;
    }

    public function cancel(Membership $membership): Membership
    {
        $membership->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew' => false,
        ]);

        MembershipTransaction::create([
            'membership_id' => $membership->id,
            'type' => 'cancel',
            'description' => 'Membership cancelled',
        ]);

        return $membership;
    }

    public function useCredit(Membership $membership, int $credits = 1): bool
    {
        if ($membership->remaining_credits < $credits) {
            return false;
        }

        $membership->decrement('remaining_credits', $credits);

        MembershipTransaction::create([
            'membership_id' => $membership->id,
            'type' => 'credit_use',
            'credits_change' => -$credits,
            'description' => "Used {$credits} court credit(s)",
        ]);

        return true;
    }

    public function processExpired(): int
    {
        $count = 0;
        Membership::active()->where('expires_at', '<', now())->each(function (Membership $m) use (&$count) {
            $m->update(['status' => 'expired']);
            $count++;
        });
        return $count;
    }
}
