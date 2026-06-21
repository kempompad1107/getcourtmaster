<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MembershipController extends Controller
{
    public function __construct(private readonly MembershipService $membershipService) {}

    public function index(Request $request)
    {
        $this->authorize('memberships.view');
        $tenantId = $this->authTenant()->id;

        $memberships = Membership::where('tenant_id', $tenantId)
            ->with('user', 'plan')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->plan_id, fn ($q, $v) => $q->where('plan_id', $v))
            ->when($request->search, function ($q, $v) {
                $q->whereHas('user', fn ($cq) => $cq->where('name', 'like', "%{$v}%"));
            })
            ->latest()->paginate(20);

        $plans = MembershipPlan::where('tenant_id', $tenantId)->orderBy('sort_order')->get();

        $customers = User::where('tenant_id', $tenantId)
            ->where('user_type', 'customer')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $stats = [
            'active'        => Membership::where('tenant_id', $tenantId)->where('status', 'active')->count(),
            'expiring_soon' => Membership::where('tenant_id', $tenantId)->where('status', 'active')
                                ->where('expires_at', '<=', now()->addDays(7))->count(),
            'expired'       => Membership::where('tenant_id', $tenantId)->where('status', 'expired')->count(),
            'mrr'           => Membership::where('memberships.tenant_id', $tenantId)->where('memberships.status', 'active')
                                ->join('membership_plans', 'memberships.plan_id', '=', 'membership_plans.id')
                                ->sum('membership_plans.price'),
        ];

        $promotions = Promotion::where('tenant_id', $tenantId)->active()->orderBy('name')->get();

        return view('admin.memberships.index', compact('memberships', 'plans', 'customers', 'stats', 'promotions'));
    }

    public function store(Request $request)
    {
        $this->authorize('memberships.create');
        $tenant = $this->authTenant();

        $data = $request->validate([
            'customer_id'    => 'required|exists:users,id',
            'plan_id'        => 'required|exists:membership_plans,id',
            'payment_method' => 'required|in:cash,wallet,gcash,maya,card,bank_transfer',
            'auto_renew'     => 'boolean',
            'promo_code'     => 'nullable|string',
        ]);

        $customer = User::where('id', $data['customer_id'])
            ->where('tenant_id', $tenant->id)
            ->where('user_type', 'customer')
            ->firstOrFail();

        $plan = MembershipPlan::where('id', $data['plan_id'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        if ($customer->activeMembership) {
            return back()->with('error', 'This customer already has an active membership.');
        }

        $finalPrice = (float) $plan->price;
        $appliedPromotion = null;

        if ($request->filled('promo_code')) {
            $promo = Promotion::where('tenant_id', $tenant->id)
                ->where('code', strtoupper($request->promo_code))
                ->first();

            if (! $promo || ! $promo->isValid()) {
                return back()->with('error', 'Invalid or expired promo code.')->withInput();
            }

            $discount = $promo->calculateDiscount($finalPrice);
            $finalPrice = max(0, $finalPrice - $discount);
            $appliedPromotion = $promo;
        }

        try {
            $membership = $this->membershipService->subscribe(
                $customer,
                $plan,
                $request->boolean('auto_renew', true),
                $data['payment_method'],
                $finalPrice,
            );

            if ($appliedPromotion) {
                PromotionUsage::create([
                    'promotion_id'     => $appliedPromotion->id,
                    'customer_id'      => $customer->id,
                    'usable_type'      => $membership->getMorphClass(),
                    'usable_id'        => $membership->id,
                    'discount_applied' => (float) $plan->price - $finalPrice,
                ]);
                $appliedPromotion->increment('used_count');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->errors()['payment_method'][0] ?? 'Could not complete the sale.');
        }

        $successMsg = "Membership assigned to {$customer->name} (paid via {$data['payment_method']})";
        if ($appliedPromotion) {
            $successMsg .= " with promo '{$appliedPromotion->code}'";
        }

        return redirect()->route('admin.memberships.index')->with('success', $successMsg . '.');
    }

    public function show(Membership $membership)
    {
        $this->authorize('view', $membership);
        $membership->load('user', 'plan', 'payments', 'transactions');
        return view('admin.memberships.show', compact('membership'));
    }

    public function plans()
    {
        $this->authorize('memberships.view');
        $tenantId = $this->authTenant()->id;
        $plans = MembershipPlan::where('tenant_id', $tenantId)
            ->withCount(['memberships as active_memberships_count' => fn($q) => $q->where('status', 'active')])
            ->orderBy('sort_order')->get();
        return view('admin.memberships.plans', compact('plans'));
    }

    public function storePlan(Request $request)
    {
        $this->authorize('memberships.create');
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly,lifetime',
            'price' => 'required|numeric|min:0',
            'court_hours' => 'required|integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'perks' => 'nullable|array',
            'is_vip' => 'boolean',
        ]);

        // court_credits is stored in MINUTES; admin enters whole hours.
        $data['court_credits'] = $data['court_hours'] * 60;
        unset($data['court_hours']);

        MembershipPlan::create(array_merge($data, [
            'tenant_id' => $this->authTenant()->id,
            'slug' => Str::slug($data['name']),
        ]));

        return redirect()->route('admin.memberships.plans')->with('success', 'Plan created.');
    }

    public function updatePlan(Request $request, MembershipPlan $plan)
    {
        $this->authorize('memberships.create');
        $data = $request->validate([
            'name'             => 'required|string|max:100',
            'billing_cycle'    => 'required|in:monthly,quarterly,yearly,lifetime',
            'price'            => 'required|numeric|min:0',
            'court_hours'      => 'required|integer|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'perks'            => 'nullable|array',
            'is_vip'           => 'boolean',
            'is_active'        => 'boolean',
        ]);

        $data['court_credits'] = $data['court_hours'] * 60;
        unset($data['court_hours']);

        $plan->update($data);

        return redirect()->route('admin.memberships.plans')->with('success', 'Plan updated.');
    }

    public function destroyPlan(MembershipPlan $plan)
    {
        $this->authorize('memberships.create');

        if ($plan->memberships()->exists()) {
            return back()->with('error', 'Cannot delete a plan with active memberships.');
        }

        $plan->delete();

        return redirect()->route('admin.memberships.plans')->with('success', 'Plan deleted.');
    }

    public function freeze(Request $request, Membership $membership)
    {
        $this->authorize('update', $membership);
        $request->validate(['until' => 'required|date|after:today']);

        $this->membershipService->freeze($membership, \Carbon\Carbon::parse($request->until));
        return redirect()->back()->with('success', 'Membership frozen.');
    }

    public function cancel(Membership $membership)
    {
        $this->authorize('cancel', $membership);
        $this->membershipService->cancel($membership);
        return redirect()->back()->with('success', 'Membership cancelled.');
    }

    public function toggleAutoRenew(Membership $membership)
    {
        $this->authorize('update', $membership);
        $membership->update(['auto_renew' => ! $membership->auto_renew]);
        $label = $membership->auto_renew ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Auto-renew {$label}.");
    }

    public function renew(Request $request, Membership $membership)
    {
        $this->authorize('update', $membership);
        $data = $request->validate([
            'payment_method' => 'nullable|in:cash,wallet,gcash,maya,card,bank_transfer',
        ]);

        try {
            $this->membershipService->renew($membership, $data['payment_method'] ?? 'cash');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->errors()['payment_method'][0] ?? 'Could not complete renewal.');
        }

        return redirect()->back()->with('success', 'Membership renewed.');
    }
}
