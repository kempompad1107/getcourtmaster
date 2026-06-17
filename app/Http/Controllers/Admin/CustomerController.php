<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CustomerNote;
use App\Models\Payment;
use App\Models\Scopes\BranchScope;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\PlanLimitGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CustomerController extends Controller
{
    /** Window (days) used for "active at branch" derivation. */
    private const ACTIVITY_WINDOW_DAYS = 90;

    public function __construct(
        private readonly BranchContext $branchContext,
        private readonly PlanLimitGuard $planLimit,
    ) {}

    /**
     * Wallet management is allowed for the tenant owner and any staff user.
     * Owner identity is checked through both Spatie roles and the user_type
     * column so impersonation / role-only setups work too.
     */
    protected function canManageWallet(User $user): bool
    {
        return $user->hasRole('business_owner')
            || $user->isBusinessOwner()
            || $user->isStaff()
            || $user->hasRole('staff');
    }

    public function index(Request $request)
    {
        $tenant = $this->authTenant();
        $filterBranchId = $this->resolveBranchFilter($request);

        $customers = User::where('tenant_id', $tenant->id)
            ->where('user_type', 'customer')
            ->withCount('bookings')
            ->withSum('bookings', 'total_amount')
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->when($filterBranchId, fn ($q, $branchId) => $q->where(function ($q) use ($branchId) {
                // Either this is their home branch (set on creation by an admin
                // working in that branch context) OR they have at least one
                // booking at the branch within the activity window.
                $q->where('home_branch_id', $branchId)
                  ->orWhereHas('bookings', fn ($b) =>
                      $b->withoutGlobalScope(BranchScope::class)
                        ->where('branch_id', $branchId)
                        ->where('booking_date', '>=', now()->subDays(self::ACTIVITY_WINDOW_DAYS))
                  );
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers'        => $customers,
            'filterBranchId'   => $filterBranchId,
            'activityWindow'   => self::ACTIVITY_WINDOW_DAYS,
        ]);
    }

    /**
     * Resolve the branch filter for the customer list. Default = active
     * topbar branch. "all" lifts the filter for owners; staff are clamped
     * to a branch they actually have access to.
     */
    private function resolveBranchFilter(Request $request): ?int
    {
        $raw = $request->query('branch_id', '__default');

        if ($raw === '__default') {
            return $this->branchContext->current();
        }

        if ($raw === '' || $raw === 'all' || $raw === null) {
            if ($this->branchContext->canSeeAllBranches()) {
                return null;
            }
            return $this->branchContext->allowedBranchIds()[0] ?? null;
        }

        $candidate = (int) $raw;
        $allowed = $this->branchContext->allowedBranchIds();
        return in_array($candidate, $allowed, true) ? $candidate : $this->branchContext->current();
    }

    public function create()
    {
        $this->planLimit->assertCanAdd($this->authTenant(), 'customers');
        return view('admin.customers.create');
    }

    public function store(Request $request)
    {
        $tenant   = $this->authTenant();
        $this->planLimit->assertCanAdd($tenant, 'customers');
        $branchId = $this->requireActiveBranch('customer');

        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:30',
            'gender'   => ['nullable', \Illuminate\Validation\Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'password' => ['required', Password::min(8)],
            'is_active' => 'boolean',
        ]);

        User::create([
            'tenant_id'      => $tenant->id,
            'home_branch_id' => $branchId,
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'] ?? null,
            'gender'         => $data['gender'] ?? null,
            'date_of_birth'  => $data['date_of_birth'] ?? null,
            'password'       => Hash::make($data['password']),
            'user_type'      => 'customer',
            'is_active'      => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.customers.index')
            ->with('success', "Customer '{$data['name']}' created successfully.");
    }

    public function edit(User $customer)
    {
        $tenant = $this->authTenant();
        abort_if($customer->tenant_id !== $tenant->id, 403);
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, User $customer)
    {
        $tenant = $this->authTenant();
        abort_if($customer->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email,' . $customer->id,
            'phone'    => 'nullable|string|max:30',
            'gender'   => ['nullable', \Illuminate\Validation\Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'password' => ['nullable', Password::min(8)],
            'is_active' => 'boolean',
        ]);

        $customer->name      = $data['name'];
        $customer->email     = $data['email'];
        $customer->phone     = $data['phone'] ?? null;
        $customer->gender    = $data['gender'] ?? null;
        $customer->date_of_birth = $data['date_of_birth'] ?? null;
        $customer->is_active = $request->boolean('is_active');
        if (!empty($data['password'])) {
            $customer->password = Hash::make($data['password']);
        }
        $customer->save();

        return redirect()->route('admin.customers.show', $customer)
            ->with('success', 'Customer updated.');
    }

    public function show(User $customer)
    {
        $tenant = $this->authTenant();
        abort_if($customer->tenant_id !== $tenant->id, 403);

        $customer->load('homeBranch');

        $bookings = $customer->bookings()->with('court')->latest()->limit(20)->get();
        $payments = Payment::where('customer_id', $customer->id)->latest()->limit(20)->get();
        $notes    = CustomerNote::where('customer_id', $customer->id)->with('createdBy')->latest()->get();
        $walletTransactions = \App\Models\WalletTransaction::where('user_id', $customer->id)
            ->latest()->limit(10)->get();

        // Branches where this customer has actually played. Bypass the global
        // branch scope so we see across all branches in the tenant regardless
        // of who's looking and which branch they have selected.
        $visitedBranchIds = $customer->bookings()
            ->withoutGlobalScope(BranchScope::class)
            ->whereNotNull('branch_id')
            ->distinct()
            ->pluck('branch_id');
        $branchesVisited = Branch::whereIn('id', $visitedBranchIds)
            ->orderByDesc('is_main')->orderBy('name')->get();

        $stats = [
            'total_bookings'    => $customer->bookings()->count(),
            'total_spent'       => $customer->bookings()->sum('total_amount'),
            'wallet_balance'    => $customer->wallet_balance,
            'membership_status' => $customer->activeMembership ? $customer->activeMembership->plan->name : 'None',
        ];

        return view('admin.customers.show', compact(
            'customer', 'bookings', 'payments', 'notes', 'stats', 'branchesVisited', 'walletTransactions'
        ));
    }

    /**
     * Manual wallet top-up by owner/staff. This is the only path that adds
     * balance to a customer's wallet — customer self-service top-up is
     * disabled by design (payments route to the venue, not the SaaS).
     */
    public function addWalletCredit(Request $request, User $user)
    {
        $customer = $user;
        $tenant = $this->authTenant();
        abort_if($customer->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'amount'    => 'required|numeric|min:1|max:100000',
            'note'      => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:120',
        ]);

        $description = 'Manual wallet top-up'
            . (!empty($data['reference']) ? " (ref: {$data['reference']})" : '');

        app(\App\Services\WalletService::class)->credit(
            $customer,
            (float) $data['amount'],
            $description,
            null,
            $this->authUser(),
            $data['note'] ?? null,
        );

        return back()->with('success', "₱" . number_format($data['amount'], 2) . " credited to {$customer->name}'s wallet.");
    }

    public function debitWallet(Request $request, User $user)
    {
        $customer = $user;
        $tenant = $this->authTenant();
        abort_if($customer->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:255',
            'note'   => 'nullable|string|max:500',
        ]);

        $wallet = app(\App\Services\WalletService::class);

        if (! $wallet->hasBalance($customer, (float) $data['amount'])) {
            return back()->with('error', "Insufficient wallet balance. Customer has ₱" . number_format($customer->wallet_balance, 2) . " available.");
        }

        $wallet->debit(
            $customer,
            (float) $data['amount'],
            "Manual debit: " . $data['reason'],
            null,
            $this->authUser(),
            $data['note'] ?? null,
        );

        return back()->with('success', "₱" . number_format($data['amount'], 2) . " debited from {$customer->name}'s wallet.");
    }

    public function search(Request $request)
    {
        $tenant = $this->authTenant();

        $q = trim($request->q ?? '');

        $customers = User::where('tenant_id', $tenant->id)
            ->where('user_type', 'customer')
            ->with(['activeMembership' => fn ($q) => $q->select('id', 'customer_id', 'remaining_credits', 'status', 'expires_at')])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('name', 'like', "%{$q}%")
                       ->orWhere('email', 'like', "%{$q}%")
                       ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->select('id', 'name', 'email', 'phone', 'wallet_balance')
            ->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'email'             => $c->email,
                'phone'             => $c->phone,
                'wallet_balance'    => (float) ($c->wallet_balance ?? 0),
                'remaining_credits' => (int) ($c->activeMembership->remaining_credits ?? 0),
            ]);

        return response()->json(['customers' => $customers]);
    }

    public function addNote(Request $request, User $user)
    {
        $customer = $user;
        $tenant = $this->authTenant();
        abort_if($customer->tenant_id !== $tenant->id, 403);

        $data = $request->validate(['note' => 'required|string|max:1000']);

        CustomerNote::create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $customer->id,
            'created_by'  => Auth::id(),
            'note'        => $data['note'],
        ]);

        return back()->with('success', 'Note added.');
    }
}
