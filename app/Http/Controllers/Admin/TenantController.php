<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Database\Seeders\DemoTenantSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total'     => Tenant::count(),
            'active'    => Tenant::where('status', 'active')->count(),
            'suspended' => Tenant::where('status', 'suspended')->count(),
            'trial'     => Tenant::whereNotNull('trial_ends_at')->where('trial_ends_at', '>=', now())->count(),
        ];

        $tenants = Tenant::withCount(['users', 'courts', 'bookings'])
            ->when($request->search, fn ($q, $v) =>
                $q->where(fn ($q2) =>
                    $q2->where('name', 'like', "%$v%")->orWhere('email', 'like', "%$v%")
                )
            )
            ->when($request->status, fn ($q, $v) =>
                $v === 'trial'
                    ? $q->whereNotNull('trial_ends_at')->where('trial_ends_at', '>=', now())
                    : $q->where('status', $v)
            )
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('super.tenants.index', compact('tenants', 'stats'));
    }

    public function create()
    {
        $timezones = timezone_identifiers_list();
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')->orderBy('price_monthly')
            ->get();
        return view('super.tenants.create', compact('timezones', 'plans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            // Tenant
            'name'     => 'required|string|max:255',
            'slug'     => 'required|string|max:120|alpha_dash|unique:tenants,slug',
            'email'    => 'required|email|max:255',
            'phone'    => 'nullable|string|max:30',
            'address'  => 'nullable|string|max:500',
            'city'     => 'nullable|string|max:100',
            'country'  => 'nullable|string|max:5',
            'timezone' => 'required|string',
            'currency' => 'required|string|size:3',
            'status'   => 'required|in:active,trial',

            // Owner (business_owner user)
            'owner_name'     => 'required|string|max:255',
            'owner_email'    => 'required|email|max:255|unique:users,email',
            'owner_phone'    => 'nullable|string|max:30',
            'owner_password' => 'required|string|min:8',

            // First (main) branch — required so customer signups work right away
            'branch_name'    => 'required|string|max:120',
            'branch_address' => 'nullable|string|max:255',
            'branch_city'    => 'nullable|string|max:120',

            // Subscription plan (optional)
            'plan_id'        => 'nullable|exists:subscription_plans,id',
            'billing_cycle'  => 'required_with:plan_id|in:monthly,yearly',
        ]);

        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name'          => $data['name'],
                'slug'          => $data['slug'],
                'email'         => $data['email'],
                'phone'         => $data['phone'] ?? null,
                'address'       => $data['address'] ?? null,
                'city'          => $data['city'] ?? null,
                'country'       => $data['country'] ?? null,
                'timezone'      => $data['timezone'],
                'currency'      => strtoupper($data['currency']),
                'status'        => $data['status'],
                'trial_ends_at' => $data['status'] === 'trial' ? now()->addDays(14) : null,
            ]);

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['branch_name'],
                'slug'      => Str::slug($data['branch_name']),
                'address'   => $data['branch_address'] ?? null,
                'city'      => $data['branch_city'] ?? $data['city'] ?? null,
                'is_main'   => true,
                'is_active' => true,
            ]);

            $owner = User::create([
                'tenant_id'         => $tenant->id,
                'home_branch_id'    => $branch->id,
                'name'              => $data['owner_name'],
                'email'             => $data['owner_email'],
                'phone'             => $data['owner_phone'] ?? null,
                'password'          => Hash::make($data['owner_password']),
                'user_type'         => 'business_owner',
                'is_active'         => true,
                'email_verified_at' => now(),
                'referral_code'     => strtoupper(substr(md5($data['owner_email']), 0, 8)),
            ]);

            // Spatie role — soft-fail if not seeded; user_type already grants
            // policy access via the Gate::before / role helpers.
            try {
                $owner->assignRole('business_owner');
            } catch (\Throwable $e) {
                // ignore — role can be assigned later via super admin
            }

            // Attach a plan as an active subscription if one was picked.
            if (! empty($data['plan_id'])) {
                $plan       = SubscriptionPlan::find($data['plan_id']);
                $cycle      = $data['billing_cycle'];
                $amount     = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
                $isTrialing = $data['status'] === 'trial';

                TenantSubscription::create([
                    'tenant_id'     => $tenant->id,
                    'plan_id'       => $plan->id,
                    'billing_cycle' => $cycle,
                    'status'        => $isTrialing ? 'trialing' : 'active',
                    'amount'        => $amount,
                    'starts_at'     => now(),
                    'trial_ends_at' => $isTrialing ? $tenant->trial_ends_at : null,
                    'renews_at'     => $cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                ]);
            }

            activity()->on($tenant)->log('Tenant created by super admin');

            return $tenant;
        });

        return redirect()->route('super.tenants.show', $tenant)
            ->with('success', "Tenant '{$tenant->name}' created with main branch and owner account.");
    }

    public function edit(Tenant $tenant)
    {
        $timezones  = timezone_identifiers_list();
        $plans      = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')->orderBy('price_monthly')->get();
        $mainBranch = $tenant->branches()->where('is_main', true)->first()
            ?? $tenant->branches()->first(); // fall back to any branch if none flagged main
        $owner      = $tenant->users()->where('user_type', 'business_owner')->first();
        $subscription = $tenant->activeSubscription;

        return view('super.tenants.edit', compact(
            'tenant', 'timezones', 'plans', 'mainBranch', 'owner', 'subscription'
        ));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $owner      = $tenant->users()->where('user_type', 'business_owner')->first();
        $mainBranch = $tenant->branches()->where('is_main', true)->first()
            ?? $tenant->branches()->first();

        $data = $request->validate([
            // Venue
            'name'       => 'required|string|max:255',
            'slug'       => [
                'required', 'string', 'max:120', 'alpha_dash',
                Rule::unique('tenants', 'slug')->ignore($tenant->id),
            ],
            'email'      => 'required|email|max:255',
            'phone'      => 'nullable|string|max:30',
            'address'    => 'nullable|string|max:500',
            'city'       => 'nullable|string|max:100',
            'country'    => 'nullable|string|max:5',
            'timezone'   => 'required|string',
            'currency'   => 'required|string|size:3',
            'status'     => 'required|in:active,trial,suspended,cancelled',

            // Features
            'features'   => 'nullable|array',
            'features.*' => 'string|in:bookings,pos,memberships,inventory,promotions,advanced_reports,basic_reports,api_access,multi_branch,white_label,priority_support',

            // Main branch
            'branch_name'    => 'required|string|max:120',
            'branch_city'    => 'nullable|string|max:120',
            'branch_address' => 'nullable|string|max:255',

            // Subscription
            'plan_id'        => 'nullable|exists:subscription_plans,id',
            'billing_cycle'  => 'required_with:plan_id|in:monthly,yearly',

            // Owner — password optional on update
            'owner_name'     => 'required|string|max:255',
            'owner_email'    => [
                'required', 'email', 'max:255',
                $owner ? Rule::unique('users', 'email')->ignore($owner->id) : 'unique:users,email',
            ],
            'owner_phone'    => 'nullable|string|max:30',
            'owner_password' => 'nullable|string|min:8',
        ]);

        DB::transaction(function () use ($data, $tenant, $owner, $mainBranch) {
            // Venue + features
            $tenant->update([
                'name'     => $data['name'],
                'slug'     => $data['slug'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? null,
                'address'  => $data['address'] ?? null,
                'city'     => $data['city'] ?? null,
                'country'  => $data['country'] ?? null,
                'timezone' => $data['timezone'],
                'currency' => strtoupper($data['currency']),
                'status'   => $data['status'],
                'features' => $data['features'] ?? [],
            ]);

            // Main branch
            if ($mainBranch) {
                $mainBranch->update([
                    'name'    => $data['branch_name'],
                    'city'    => $data['branch_city'] ?? $data['city'] ?? null,
                    'address' => $data['branch_address'] ?? null,
                ]);
            } else {
                Branch::create([
                    'tenant_id' => $tenant->id,
                    'name'      => $data['branch_name'],
                    'slug'      => Str::slug($data['branch_name']),
                    'address'   => $data['branch_address'] ?? null,
                    'city'      => $data['branch_city'] ?? $data['city'] ?? null,
                    'is_main'   => true,
                    'is_active' => true,
                ]);
            }

            // Subscription plan
            if (! empty($data['plan_id'])) {
                $plan   = SubscriptionPlan::find($data['plan_id']);
                $cycle  = $data['billing_cycle'];
                $amount = $cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly;
                $existing = $tenant->activeSubscription;

                if ($existing) {
                    $existing->update([
                        'plan_id'       => $plan->id,
                        'billing_cycle' => $cycle,
                        'amount'        => $amount,
                        'renews_at'     => $cycle === 'yearly'
                            ? now()->addYear()->toDateString()
                            : now()->addMonth()->toDateString(),
                    ]);
                } else {
                    TenantSubscription::create([
                        'tenant_id'     => $tenant->id,
                        'plan_id'       => $plan->id,
                        'billing_cycle' => $cycle,
                        'status'        => 'active',
                        'amount'        => $amount,
                        'starts_at'     => now(),
                        'renews_at'     => $cycle === 'yearly'
                            ? now()->addYear()->toDateString()
                            : now()->addMonth()->toDateString(),
                    ]);
                }

                // Keep denormalised tenants.plan column in sync.
                $tenant->update(['plan' => $plan->slug]);
            }

            // Owner — password only changes when a new one is provided.
            if ($owner) {
                $ownerData = [
                    'name'  => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'phone' => $data['owner_phone'] ?? null,
                ];
                if (! empty($data['owner_password'])) {
                    $ownerData['password'] = Hash::make($data['owner_password']);
                }
                $owner->update($ownerData);
            }
        });

        activity()->on($tenant)->log('Tenant updated by super admin');

        return redirect()->route('super.tenants.show', $tenant)
            ->with('success', "Tenant '{$tenant->name}' updated.");
    }

    public function show(Tenant $tenant)
    {
        $tenant->loadCount(['users', 'courts', 'bookings']);
        $tenant->load(['activeSubscription.plan']);

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')->orderBy('price_monthly')->get();

        $recentInvoices = \App\Models\SubscriptionInvoice::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')->limit(10)->get();

        return view('super.tenants.show', compact('tenant', 'plans', 'recentInvoices'));
    }

    public function suspend(Tenant $tenant)
    {
        $tenant->update(['status' => 'suspended']);
        activity()->on($tenant)->log('Tenant suspended by super admin');
        return back()->with('success', "Tenant {$tenant->name} suspended.");
    }

    public function activate(Tenant $tenant)
    {
        $tenant->update(['status' => 'active']);
        activity()->on($tenant)->log('Tenant activated by super admin');
        return back()->with('success', "Tenant {$tenant->name} activated.");
    }

    public function setTrial(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['trial_days' => 'required|integer|min:1|max:365']);
        $days = (int) $data['trial_days'];
        $tenant->update([
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays($days),
        ]);
        activity()->on($tenant)->log("Set to trial for {$data['trial_days']} days by super admin");
        return back()->with('success', "{$tenant->name} set to trial for {$data['trial_days']} days.");
    }

    public function extendTrial(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['days' => 'required|integer|min:1|max:365']);
        $newEnd = ($tenant->trial_ends_at?->isFuture() ? $tenant->trial_ends_at : now())->addDays((int) $data['days']);
        $tenant->update([
            'trial_ends_at' => $newEnd,
            'status'        => $tenant->status === 'suspended' ? 'trial' : $tenant->status,
        ]);
        activity()->on($tenant)->log("Trial extended by {$data['days']} days by super admin");
        return back()->with('success', "Trial extended to {$newEnd->format('M j, Y')}.");
    }

    public function cancel(Tenant $tenant)
    {
        $tenant->update(['status' => 'cancelled']);
        activity()->on($tenant)->log('Tenant cancelled by super admin');
        return back()->with('success', "Tenant {$tenant->name} cancelled.");
    }

    public function toggleDemo(Tenant $tenant)
    {
        $tenant->update(['is_demo' => ! $tenant->is_demo]);
        $label = $tenant->is_demo ? 'marked as demo' : 'removed from demo mode';
        activity()->on($tenant)->log("Tenant {$label} by super admin");
        return back()->with('success', "{$tenant->name} has been {$label}.");
    }

    public function resetDemoData(Tenant $tenant)
    {
        abort_unless($tenant->is_demo, 403, 'This tenant is not flagged as a demo account.');

        DB::transaction(function () use ($tenant) {
            $tid = $tenant->id;

            // Delete in FK-safe order (children first)
            DB::table('wallet_transactions')->where('tenant_id', $tid)->delete();
            DB::table('refund_requests')->where('tenant_id', $tid)->delete();
            DB::table('payments')->where('tenant_id', $tid)->delete();
            DB::table('pos_payments')->whereIn('order_id', function ($q) use ($tid) {
                $q->select('id')->from('pos_orders')->where('tenant_id', $tid);
            })->delete();
            DB::table('pos_order_items')->whereIn('order_id', function ($q) use ($tid) {
                $q->select('id')->from('pos_orders')->where('tenant_id', $tid);
            })->delete();
            DB::table('pos_orders')->where('tenant_id', $tid)->delete();
            DB::table('inventory_movements')->where('tenant_id', $tid)->delete();
            DB::table('cash_drawer_logs')->where('tenant_id', $tid)->delete();
            DB::table('purchase_order_items')->whereIn('purchase_order_id', function ($q) use ($tid) {
                $q->select('id')->from('purchase_orders')->where('tenant_id', $tid);
            })->delete();
            DB::table('purchase_orders')->where('tenant_id', $tid)->delete();
            DB::table('booking_timers')->whereIn('booking_id', function ($q) use ($tid) {
                $q->select('id')->from('bookings')->where('tenant_id', $tid);
            })->delete();
            DB::table('bookings')->where('tenant_id', $tid)->delete();
            DB::table('membership_transactions')->whereIn('membership_id', function ($q) use ($tid) {
                $q->select('id')->from('memberships')->where('tenant_id', $tid);
            })->delete();
            DB::table('memberships')->where('tenant_id', $tid)->delete();
            DB::table('membership_plans')->where('tenant_id', $tid)->delete();
            DB::table('tournament_team_members')->where('tenant_id', $tid)->delete();
            DB::table('tournament_matches')->where('tenant_id', $tid)->delete();
            DB::table('tournament_teams')->where('tenant_id', $tid)->delete();
            DB::table('tournament_groups')->where('tenant_id', $tid)->delete();
            DB::table('tournament_divisions')->where('tenant_id', $tid)->delete();
            DB::table('tournaments')->where('tenant_id', $tid)->delete();
            DB::table('shifts')->where('tenant_id', $tid)->delete();
            DB::table('customer_notes')->where('tenant_id', $tid)->delete();
            DB::table('promotions')->where('tenant_id', $tid)->delete();
            DB::table('products')->where('tenant_id', $tid)->delete();
            DB::table('product_categories')->where('tenant_id', $tid)->delete();
            DB::table('suppliers')->where('tenant_id', $tid)->delete();
            DB::table('courts')->where('tenant_id', $tid)->delete();
            DB::table('branches')->where('tenant_id', $tid)->delete();
            DB::table('audit_logs')->where('tenant_id', $tid)->delete();

            $tenantUserIds = DB::table('users')->where('tenant_id', $tid)->pluck('id');
            DB::table('activity_log')
                ->where('causer_type', 'App\\Models\\User')
                ->whereIn('causer_id', $tenantUserIds)
                ->delete();

            // Remove staff and customer users, keep the business owner
            DB::table('users')
                ->where('tenant_id', $tid)
                ->whereIn('user_type', ['staff', 'customer'])
                ->delete();

            // Reset owner wallet
            DB::table('users')
                ->where('tenant_id', $tid)
                ->where('user_type', 'business_owner')
                ->update(['wallet_balance' => 0]);
        });

        // Restore sample data
        app(DemoTenantSeeder::class)->runForTenant($tenant);

        activity()->on($tenant)->log('Demo data reset by super admin');

        return back()->with('success', "Demo data for \"{$tenant->name}\" has been reset successfully.");
    }

    public function destroy(Tenant $tenant)
    {
        $name = $tenant->name;
        $tenant->delete(); // soft delete
        activity()->log("Tenant '{$name}' soft-deleted by super admin");
        return redirect()->route('super.tenants.index')->with('success', "Tenant '{$name}' deleted (soft).");
    }

    public function impersonate(Tenant $tenant)
    {
        $owner = $tenant->users()->where('user_type', 'business_owner')->first();

        if (! $owner) {
            return back()->with('error', 'No business owner found for this tenant.');
        }

        // Remember the super-admin so they can return via stopImpersonating().
        session([
            'impersonator_id'        => auth()->id(),
            'impersonating_tenant_id' => $tenant->id,
        ]);
        auth()->login($owner);

        return redirect()->route('admin.dashboard');
    }

    /**
     * Return to the super-admin account after impersonating a tenant owner.
     * Accessible while logged in as the impersonated owner; no-op otherwise.
     */
    public function stopImpersonating(Request $request)
    {
        $superAdminId = session('impersonator_id');

        if (! $superAdminId) {
            return redirect()->route('admin.dashboard');
        }

        $superAdmin = User::find($superAdminId);

        if (! $superAdmin || ! $superAdmin->isSuperAdmin()) {
            session()->forget(['impersonator_id', 'impersonating_tenant_id']);
            return redirect()->route('login');
        }

        session()->forget(['impersonator_id', 'impersonating_tenant_id']);
        auth()->login($superAdmin);

        return redirect()->route('super.tenants.index')
            ->with('success', 'Returned to super-admin account.');
    }

    /* ─────────────────────────────────────────────────────────────────────
     | USERS — view and manage all users for a single tenant
     ───────────────────────────────────────────────────────────────────── */

    public function users(Tenant $tenant)
    {
        $users = User::where('tenant_id', $tenant->id)
            ->with('homeBranch:id,name')
            ->orderByRaw("FIELD(user_type, 'business_owner', 'staff', 'customer')")
            ->orderBy('name')
            ->paginate(50);

        // Group counts so the page can show "5 staff, 12 customers" at a glance.
        $counts = User::where('tenant_id', $tenant->id)
            ->selectRaw('user_type, COUNT(*) as c')
            ->groupBy('user_type')->pluck('c', 'user_type');

        return view('super.tenants.users', compact('tenant', 'users', 'counts'));
    }

    /**
     * Reset a user's password to a randomly generated string and return it
     * to the super-admin in the flash message. The temp password is shown
     * exactly once — the operator is responsible for handing it off securely.
     */
    public function resetPassword(Tenant $tenant, User $user)
    {
        abort_unless($user->tenant_id === $tenant->id, 404);
        abort_if($user->isSuperAdmin(), 403, 'Cannot reset a super-admin password from this UI.');

        $temp = \Illuminate\Support\Str::password(12, true, true, false);
        $user->update(['password' => Hash::make($temp)]);

        activity()->on($user)->log("Password reset by super admin (tenant: {$tenant->name})");

        return back()->with('success', "Temporary password for {$user->name}: {$temp} — share securely; this is the only time it will be shown.");
    }

    /**
     * Clear a user's two-factor enrolment so they can log in with their
     * password alone and re-enroll a fresh authenticator. Used when an owner
     * loses the device holding their TOTP secret. Nulls the same columns the
     * user's own "disable 2FA" flow does.
     */
    public function disableTwoFactor(Tenant $tenant, User $user)
    {
        abort_unless($user->tenant_id === $tenant->id, 404);
        abort_if($user->isSuperAdmin(), 403, 'Cannot modify a super-admin from this UI.');

        $user->update([
            'two_factor_enabled'        => false,
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ]);

        activity()->on($user)->log("Two-factor authentication removed by super admin (tenant: {$tenant->name})");

        return back()->with('success', "Two-factor authentication removed for {$user->name}. They can now log in with their password and re-enroll a new authenticator.");
    }
}
