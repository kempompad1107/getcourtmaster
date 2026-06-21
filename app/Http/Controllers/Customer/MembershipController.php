<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\MembershipTransaction;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Services\MembershipService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MembershipController extends Controller
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly MembershipService $service,
        private readonly WalletService $wallet,
    ) {}

    public function index(Request $request): View
    {
        $user   = $request->user();
        $active = $this->memberships->activeForCustomer($user->id);

        // Eager-load the plan + recent transactions so the active card has
        // everything to render in a single query each.
        if ($active) {
            $active->load('plan', 'transactions');
        }

        $plans = MembershipPlan::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('price')
            ->get();

        $history = Membership::where('customer_id', $user->id)
            ->whereNotIn('status', ['active'])
            ->with('plan:id,name,billing_cycle,price')
            ->latest('expires_at')
            ->limit(10)
            ->get();

        return view('customer.memberships.index', compact('user', 'active', 'plans', 'history'));
    }

    public function subscribe(Request $request, MembershipPlan $plan): RedirectResponse
    {
        $user = $request->user();

        // Plan must belong to this customer's tenant — defence against id swaps.
        abort_unless($plan->tenant_id === $user->tenant_id && $plan->is_active, 404);

        // Block if they already have an active membership — they should renew
        // or cancel that one first to avoid double-subscriptions.
        if ($this->memberships->activeForCustomer($user->id)) {
            return back()->with('error', 'You already have an active membership. Cancel it before subscribing to a new plan.');
        }

        $request->validate([
            'payment_method' => 'required|in:wallet,cash,gcash,maya,card',
        ]);

        // MembershipService::subscribe() now handles wallet debiting atomically
        // and throws ValidationException if the balance is short. We just pass
        // the chosen method through.
        try {
            $this->service->subscribe($user, $plan, autoRenew: false, method: $request->payment_method);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->errors()['payment_method'][0] ?? 'Could not complete payment.');
        }

        return redirect()->route('customer.memberships.index')
            ->with('success', "You're now subscribed to {$plan->name}.");
    }

    public function renew(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeOwnership($membership, $request->user()->id);

        $request->validate([
            'payment_method' => 'required|in:wallet,cash,gcash,maya,card',
        ]);

        try {
            $this->service->renew($membership, method: $request->payment_method);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', $e->errors()['payment_method'][0] ?? 'Could not complete payment.');
        }

        return back()->with('success', "Membership renewed. New expiry: {$membership->fresh()->expires_at->format('M j, Y')}.");
    }

    public function freeze(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeOwnership($membership, $request->user()->id);

        $data = $request->validate([
            'days' => 'required|integer|min:1|max:90',
        ]);

        $plan = $membership->plan;

        // Enforce per-plan freeze quotas if the plan defines them. Counts
        // freeze actions within the current membership's billing cycle.
        if ($plan?->freeze_count_per_year) {
            $usedThisYear = MembershipTransaction::where('membership_id', $membership->id)
                ->where('type', 'freeze')
                ->whereYear('created_at', now()->year)
                ->count();
            if ($usedThisYear >= $plan->freeze_count_per_year) {
                return back()->with('error', "You've used all {$plan->freeze_count_per_year} freeze(s) allowed this year.");
            }
        }
        if ($plan?->max_freeze_days && $data['days'] > $plan->max_freeze_days) {
            return back()->with('error', "Max freeze length on your plan is {$plan->max_freeze_days} days.");
        }

        $this->service->freeze($membership, Carbon::today()->addDays((int) $data['days']));

        return back()->with('success', "Membership frozen for {$data['days']} days.");
    }

    public function unfreeze(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeOwnership($membership, $request->user()->id);

        abort_unless($membership->isFrozen(), 422, 'Membership is not frozen.');

        $this->service->unfreeze($membership);

        return back()->with('success', 'Membership unfrozen. Your expiry date has been extended for the remaining frozen days.');
    }

    public function cancel(Request $request, Membership $membership): RedirectResponse
    {
        $this->authorizeOwnership($membership, $request->user()->id);

        $this->service->cancel($membership);
        return back()->with('success', 'Membership cancelled. You can still book until the end of your current cycle.');
    }

    private function authorizeOwnership(Membership $membership, int $userId): void
    {
        abort_unless($membership->customer_id === $userId, 403);
    }
}
