<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tenant-owner / staff wallet management.
 *
 * Customers cannot top up their own wallets — this controller is the only
 * place balances can be added or deducted. All amounts route to the venue
 * (cash, GCash, bank transfer accepted at the desk), and the SaaS owner
 * never sees the money.
 *
 * Tenant isolation: every query filters by the authenticated user's tenant.
 */
class WalletController extends Controller
{
    /** Owners (via role or user_type) and any staff user can manage wallets. */
    protected function canManageWallet(User $user): bool
    {
        return $user->hasRole('business_owner')
            || $user->isBusinessOwner()
            || $user->isStaff()
            || $user->hasRole('staff');
    }

    public function index(Request $request): View
    {
        $tenant = $this->authTenant();

        $search = trim((string) $request->query('search', ''));

        // Customer roster with wallet balance (paginated by name).
        $customers = User::where('tenant_id', $tenant->id)
            ->where('user_type', 'customer')
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // Recent ledger across the tenant — useful for spotting fraud and
        // for the owner's audit overview.
        $recent = WalletTransaction::where('tenant_id', $tenant->id)
            ->with(['user:id,name,email', 'processedBy:id,name'])
            ->latest()
            ->limit(50)
            ->get();

        $stats = [
            'total_balance'   => (float) User::where('tenant_id', $tenant->id)
                ->where('user_type', 'customer')
                ->sum('wallet_balance'),
            'credited_today'  => (float) WalletTransaction::where('tenant_id', $tenant->id)
                ->whereIn('type', ['credit', 'refund', 'reward'])
                ->whereDate('created_at', today())
                ->sum('amount'),
            'debited_today'   => (float) WalletTransaction::where('tenant_id', $tenant->id)
                ->where('type', 'debit')
                ->whereDate('created_at', today())
                ->sum('amount'),
        ];

        return view('admin.wallet.index', compact('customers', 'recent', 'stats', 'search'));
    }

    public function show(Request $request, User $customer): View
    {
        $tenant = $this->authTenant();
        abort_if($customer->tenant_id !== $tenant->id, 403, 'Cross-tenant access denied.');

        $transactions = WalletTransaction::where('user_id', $customer->id)
            ->with('processedBy:id,name')
            ->latest()
            ->paginate(25);

        return view('admin.wallet.show', compact('customer', 'transactions'));
    }
}
