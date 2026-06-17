<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Services\PlanLimitGuard;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(private readonly PlanLimitGuard $planLimit) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Branch::class);
        $tenantId = $this->authTenant()->id;

        $branches = Branch::where('tenant_id', $tenantId)
            ->withCount(['courts', 'staff'])
            ->when($request->search, fn ($q, $v) =>
                $q->where(fn ($w) => $w->where('name', 'like', "%{$v}%")
                                       ->orWhere('city', 'like', "%{$v}%")))
            ->when($request->status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        // Network-wide summary (all branches for the tenant, ignoring filters).
        // Branch counts are plan-limited, so loading them to aggregate is cheap
        // and keeps the totals tenant-safe without re-deriving global scopes.
        $summaryRows = Branch::where('tenant_id', $tenantId)
            ->withCount(['courts', 'staff'])
            ->get(['id', 'is_active']);

        $stats = [
            'total'    => $summaryRows->count(),
            'active'   => $summaryRows->where('is_active', true)->count(),
            'inactive' => $summaryRows->where('is_active', false)->count(),
            'courts'   => $summaryRows->sum('courts_count'),
            'staff'    => $summaryRows->sum('staff_count'),
        ];

        return view('admin.branches.index', compact('branches', 'stats'));
    }

    public function create()
    {
        $this->authorize('create', Branch::class);
        $this->planLimit->assertCanAdd($this->authTenant(), 'branches');
        return view('admin.branches.create');
    }

    public function store(BranchRequest $request)
    {
        $this->authorize('create', Branch::class);
        $this->planLimit->assertCanAdd($this->authTenant(), 'branches');

        $data = $request->validated();
        $data['tenant_id'] = $this->authTenant()->id;

        $branch = Branch::create($data);

        if ($branch->is_main) {
            $this->demoteOtherMains($branch);
        }

        activity()->on($branch)->log('Branch created');

        return redirect()->route('admin.branches.index')
            ->with('success', "Branch '{$branch->name}' created.");
    }

    public function edit(Branch $branch)
    {
        $this->authorize('update', $branch);
        return view('admin.branches.edit', compact('branch'));
    }

    public function update(BranchRequest $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());

        if ($branch->is_main) {
            $this->demoteOtherMains($branch);
        }

        activity()->on($branch)->log('Branch updated');

        return redirect()->route('admin.branches.index')
            ->with('success', "Branch '{$branch->name}' updated.");
    }

    public function destroy(Branch $branch)
    {
        $this->authorize('delete', $branch);

        if ($branch->courts()->exists() || $branch->bookings()->exists()) {
            return back()->with('error',
                "Cannot delete '{$branch->name}': it still has courts or bookings attached.");
        }

        if ($branch->is_main) {
            return back()->with('error', 'Cannot delete the main branch. Designate another branch as main first.');
        }

        $branch->delete();
        activity()->on($branch)->log('Branch deleted');

        return redirect()->route('admin.branches.index')
            ->with('success', 'Branch removed.');
    }

    private function demoteOtherMains(Branch $branch): void
    {
        Branch::where('tenant_id', $branch->tenant_id)
            ->where('id', '!=', $branch->id)
            ->where('is_main', true)
            ->update(['is_main' => false]);
    }
}
