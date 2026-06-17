<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::withCount('tenants')->orderBy('sort_order')->get();

        return view('super.plans.index', compact('plans'));
    }

    public function show(SubscriptionPlan $plan)
    {
        // Pull tenants currently on this plan via their active subscription,
        // plus useful per-tenant stats so we can spot near-limit usage at a glance.
        $tenants = \App\Models\Tenant::whereHas('activeSubscription', fn ($q) => $q->where('plan_id', $plan->id))
            ->withCount(['users', 'courts', 'bookings', 'branches'])
            ->with('activeSubscription')
            ->orderBy('name')
            ->get();

        return view('super.plans.show', compact('plan', 'tenants'));
    }

    public function create()
    {
        return view('super.plans.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:100',
            'slug'               => 'required|string|max:100|unique:subscription_plans',
            'price_monthly'      => 'required|numeric|min:0',
            'price_yearly'       => 'nullable|numeric|min:0',
            'trial_days'         => 'nullable|integer|min:0',
            'max_branches'       => 'nullable|integer|min:1',
            'max_courts'         => 'nullable|integer|min:1',
            'max_staff'          => 'nullable|integer|min:1',
            'max_customers'      => 'nullable|integer|min:1',
            'features'           => 'nullable|array',
            'is_active'          => 'boolean',
        ]);

        SubscriptionPlan::create($data);

        return redirect()->route('super.plans.index')->with('success', 'Plan created.');
    }

    public function edit(SubscriptionPlan $plan)
    {
        return view('super.plans.edit', compact('plan'));
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly'  => 'nullable|numeric|min:0',
            'trial_days'    => 'nullable|integer|min:0',
            'max_branches'  => 'nullable|integer|min:1',
            'max_courts'    => 'nullable|integer|min:1',
            'max_staff'     => 'nullable|integer|min:1',
            'max_customers' => 'nullable|integer|min:1',
            'features'      => 'nullable|array',
            'is_active'     => 'boolean',
        ]);

        $plan->update($data);

        return redirect()->route('super.plans.index')->with('success', 'Plan updated.');
    }

    public function destroy(SubscriptionPlan $plan)
    {
        if ($plan->tenants()->exists()) {
            return back()->with('error', 'Cannot delete a plan with active tenants.');
        }

        $plan->delete();

        return redirect()->route('super.plans.index')->with('success', 'Plan deleted.');
    }
}
