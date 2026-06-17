<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Services\MembershipService;
use Illuminate\Http\Request;

class MembershipApiController extends Controller
{
    public function __construct(private MembershipService $membershipService) {}

    public function plans(Request $request)
    {
        $tenant = $this->authTenant();

        $plans = MembershipPlan::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get(['id', 'name', 'slug', 'billing_cycle', 'price', 'court_credits', 'discount_percent', 'is_vip', 'features']);

        return response()->json(['plans' => $plans]);
    }

    public function index(Request $request)
    {
        $user = $this->authUser();

        $memberships = Membership::where('user_id', $user->id)
            ->with('plan')
            ->latest()
            ->get();

        return response()->json(['memberships' => $memberships]);
    }

    public function active(Request $request)
    {
        $user = $this->authUser();

        $membership = $user->activeMembership?->load('plan');

        return response()->json(['membership' => $membership]);
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'plan_id'        => 'required|exists:membership_plans,id',
            'payment_method' => 'required|in:wallet,cash,gcash,maya,card',
        ]);

        $user = $this->authUser();
        $plan = MembershipPlan::findOrFail($data['plan_id']);

        if ($plan->tenant_id !== $user->tenant_id) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        $membership = $this->membershipService->subscribe($user, $plan, $data['payment_method']);

        return response()->json(['membership' => $membership->load('plan')], 201);
    }

    public function cancel(Request $request, Membership $membership)
    {
        if ($membership->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->membershipService->cancel($membership, $request->reason ?? 'User requested cancellation');

        return response()->json(['message' => 'Membership cancelled.']);
    }
}
