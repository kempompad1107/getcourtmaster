<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    /**
     * Per-tenant signup landing. Customers reach this via the tenant's QR code
     * or a link the venue shared. Tenant + branch context comes from the URL.
     */
    public function showForTenant(Tenant $tenant, Request $request)
    {
        abort_if($tenant->status === 'suspended', 403, 'This venue is currently unavailable.');

        $branches = $tenant->branches()
            ->where('is_active', true)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        abort_if($branches->isEmpty(), 404, 'This venue has no active branches accepting signups yet.');

        // ?branch=ID pre-selects (used by the per-branch QR codes). Otherwise
        // default to the main branch.
        $requestedBranch  = (int) $request->query('branch');
        $selectedBranchId = $branches->firstWhere('id', $requestedBranch)?->id
            ?? $branches->firstWhere('is_main', true)?->id
            ?? $branches->first()->id;

        return view('auth.register-tenant', compact('tenant', 'branches', 'selectedBranchId'));
    }

    public function storeForTenant(Tenant $tenant, Request $request)
    {
        abort_if($tenant->status === 'suspended', 403, 'This venue is currently unavailable.');

        $branchIds = $tenant->branches()->where('is_active', true)->pluck('id')->all();
        abort_if(empty($branchIds), 404);

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'phone'          => 'nullable|string|max:30',
            'password'       => 'required|string|min:8|confirmed',
            'home_branch_id' => ['required', Rule::in($branchIds)],
            'referral_code'  => 'nullable|string|max:20',
            'terms'          => 'accepted',
        ]);

        // Referral codes are tenant-scoped — a code from another tenant should
        // not credit a referrer who can't even see the new customer.
        $referredBy = null;
        if (! empty($data['referral_code'])) {
            $referredBy = User::where('tenant_id', $tenant->id)
                ->where('referral_code', strtoupper($data['referral_code']))
                ->value('id');
        }

        $user = User::create([
            'tenant_id'      => $tenant->id,
            'home_branch_id' => (int) $data['home_branch_id'],
            'name'           => $data['name'],
            'email'          => $data['email'],
            'phone'          => $data['phone'] ?? null,
            'password'       => Hash::make($data['password']),
            'user_type'      => 'customer',
            'is_active'      => true,
            'referral_code'  => strtoupper(substr(md5($data['email'] . time()), 0, 8)),
            'referred_by'   => $referredBy,
        ]);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('customer.dashboard')
            ->with('success', "Welcome to {$tenant->name}, {$user->name}!");
    }
}
