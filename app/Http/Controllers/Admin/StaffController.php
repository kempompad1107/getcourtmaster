<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Services\PlanLimitGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    public function __construct(private readonly PlanLimitGuard $planLimit) {}

    public function index()
    {
        $this->authorize('viewAny', User::class);
        $tenant = $this->authTenant();

        $staff = User::where('tenant_id', $tenant->id)
            ->whereIn('user_type', ['staff', 'manager'])
            ->with(['roles', 'staffProfile.branch'])
            ->paginate(20);

        return view('admin.staff.index', compact('staff'));
    }

    public function create()
    {
        $this->authorize('create', User::class);
        $this->planLimit->assertCanAdd($this->authTenant(), 'staff');
        $branches = $this->authTenant()->branches()->active()->orderBy('name')->get();
        return view('admin.staff.create', compact('branches'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $this->planLimit->assertCanAdd($this->authTenant(), 'staff');
        $tenant = $this->authTenant();
        $tenantBranchIds = $tenant->branches()->pluck('id')->all();

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'email'               => 'required|email|unique:users',
            'phone'               => 'nullable|string|max:30',
            'role'                => 'required|in:front_desk,inventory_manager,manager',
            'password'            => 'required|string|min:8',
            'branch_id'           => ['required', 'integer', \Illuminate\Validation\Rule::in($tenantBranchIds)],
            'assigned_branches'   => 'nullable|array',
            'assigned_branches.*' => ['integer', \Illuminate\Validation\Rule::in($tenantBranchIds)],
        ]);

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'phone'             => $data['phone'] ?? null,
            'password'          => Hash::make($data['password']),
            'tenant_id'         => $tenant->id,
            'user_type'         => 'staff',
            'is_active'         => true,
            'referral_code'     => strtoupper(substr(md5($data['email']), 0, 8)),
            'email_verified_at' => now(),
        ]);

        $user->assignRole($data['role']);

        StaffProfile::create([
            'user_id'           => $user->id,
            'tenant_id'         => $tenant->id,
            'branch_id'         => $data['branch_id'],
            'assigned_branches' => $this->normaliseAssignedBranches($data['assigned_branches'] ?? [], $data['branch_id']),
            'is_active'         => true,
        ]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff member created.');
    }

    public function show(User $staff)
    {
        $this->authorize('view', $staff);

        $staff->load('roles', 'staffProfile');

        $recentAttendance = Shift::where('staff_id', $staff->id)
            ->orderByDesc('shift_date')->orderByDesc('scheduled_start')
            ->limit(10)->get();

        $attendanceSummary = [
            'total_shifts'     => Shift::where('staff_id', $staff->id)->count(),
            'hours_this_month' => Shift::where('staff_id', $staff->id)
                ->whereMonth('shift_date', now()->month)
                ->whereNotNull('clocked_out_at')
                ->get()
                ->sum(fn ($s) => $s->duration_minutes / 60),
        ];

        $recentBookings = \App\Models\Booking::where('created_by', $staff->id)
            ->with('customer', 'court')
            ->latest()->limit(10)->get();

        return view('admin.staff.show', compact('staff', 'recentAttendance', 'attendanceSummary', 'recentBookings'));
    }

    public function edit(User $staff)
    {
        $this->authorize('update', $staff);
        $staff->load('staffProfile', 'permissions', 'roles.permissions');
        $branches = $this->authTenant()->branches()->active()->orderBy('name')->get();

        $rolePermissions = $staff->roles->flatMap->permissions->pluck('name')->unique()->all();
        $directPermissions = $staff->permissions->pluck('name')->all();

        return view('admin.staff.edit', [
            'staff'             => $staff,
            'branches'          => $branches,
            'permissionGroups'  => self::PERMISSION_GROUPS,
            'rolePermissions'   => $rolePermissions,
            'directPermissions' => $directPermissions,
        ]);
    }

    public function update(Request $request, User $staff)
    {
        $this->authorize('update', $staff);
        $tenantBranchIds = $this->authTenant()->branches()->pluck('id')->all();
        $allowedPerms    = collect(self::PERMISSION_GROUPS)->flatten()->all();

        $data = $request->validate([
            'name'                       => 'required|string|max:255',
            'phone'                      => 'nullable|string|max:30',
            'role'                       => 'required|in:front_desk,inventory_manager,manager',
            'is_active'                  => 'boolean',
            'branch_id'                  => ['required', 'integer', \Illuminate\Validation\Rule::in($tenantBranchIds)],
            'assigned_branches'          => 'nullable|array',
            'assigned_branches.*'        => ['integer', \Illuminate\Validation\Rule::in($tenantBranchIds)],
            'extra_permissions'          => 'nullable|array',
            'extra_permissions.*'        => ['string', \Illuminate\Validation\Rule::in($allowedPerms)],
        ]);

        $staff->update([
            'name'      => $data['name'],
            'phone'     => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $staff->syncRoles([$data['role']]);

        // Direct permission overrides — only persist perms NOT already granted by the role,
        // so the staff record stays clean and role changes propagate cleanly.
        if ($this->authUser()->isBusinessOwner()) {
            $rolePerms  = $staff->roles->flatMap->permissions->pluck('name')->unique()->all();
            $extraPerms = array_values(array_diff($data['extra_permissions'] ?? [], $rolePerms));
            $staff->syncPermissions($extraPerms);
        }

        StaffProfile::updateOrCreate(
            ['user_id' => $staff->id, 'tenant_id' => $staff->tenant_id],
            [
                'branch_id'         => $data['branch_id'],
                'assigned_branches' => $this->normaliseAssignedBranches($data['assigned_branches'] ?? [], $data['branch_id']),
                'is_active'         => $data['is_active'] ?? true,
            ]
        );

        return redirect()->route('admin.staff.index')->with('success', 'Staff updated.');
    }

    private const PERMISSION_GROUPS = [
        'Branches'    => ['branches.view', 'branches.manage'],
        'Courts'      => ['courts.view', 'courts.create', 'courts.update', 'courts.delete', 'courts.status'],
        'Bookings'    => ['bookings.view', 'bookings.create', 'bookings.update', 'bookings.cancel', 'timer.manage'],
        'POS'         => ['pos.access', 'pos.void'],
        'Memberships' => ['memberships.view', 'memberships.create', 'memberships.update', 'memberships.cancel'],
        'Customers'   => ['customers.view', 'customers.manage'],
        'Inventory'   => ['inventory.view', 'inventory.manage'],
        'Promotions'  => ['promotions.view', 'promotions.manage'],
        'Reports'     => ['reports.view', 'reports.export'],
        'Staff'       => ['staff.view', 'staff.create', 'staff.update', 'staff.manage'],
    ];

    /**
     * Always include the primary branch in the assigned list, and dedupe.
     */
    private function normaliseAssignedBranches(array $assigned, int $primaryBranchId): array
    {
        return array_values(array_unique(array_map('intval', array_merge($assigned, [$primaryBranchId]))));
    }

    public function destroy(User $staff)
    {
        $this->authorize('delete', $staff);

        $staff->update(['is_active' => false]);

        return redirect()->route('admin.staff.index')->with('success', 'Staff deactivated.');
    }

    public function myShift()
    {
        $user = $this->authUser();

        $activeShift = Shift::where('staff_id', $user->id)
            ->whereNotNull('clocked_in_at')
            ->whereNull('clocked_out_at')
            ->latest('clocked_in_at')
            ->first();

        $todayShift = Shift::where('staff_id', $user->id)
            ->whereDate('shift_date', now()->toDateString())
            ->whereIn('status', ['scheduled', 'late'])
            ->orderBy('scheduled_start')
            ->first();

        $upcoming = Shift::where('staff_id', $user->id)
            ->whereDate('shift_date', '>=', now()->toDateString())
            ->where('status', 'scheduled')
            ->orderBy('shift_date')->orderBy('scheduled_start')
            ->limit(5)->get();

        $recent = Shift::where('staff_id', $user->id)
            ->orderByDesc('shift_date')->orderByDesc('scheduled_start')
            ->limit(5)->get();

        $hoursThisMonth = Shift::where('staff_id', $user->id)
            ->whereMonth('shift_date', now()->month)
            ->whereYear('shift_date', now()->year)
            ->whereNotNull('clocked_out_at')
            ->get()
            ->sum(fn ($s) => $s->duration_minutes / 60);

        $hoursThisWeek = Shift::where('staff_id', $user->id)
            ->whereBetween('shift_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereNotNull('clocked_out_at')
            ->get()
            ->sum(fn ($s) => $s->duration_minutes / 60);

        $monthShifts = Shift::where('staff_id', $user->id)
            ->whereMonth('shift_date', now()->month)
            ->whereYear('shift_date', now()->year)
            ->whereIn('status', ['completed', 'late', 'absent'])
            ->get();

        $daysWorked   = $monthShifts->whereIn('status', ['completed', 'late'])->count();
        $onTimeShifts = $monthShifts->whereIn('status', ['completed', 'late'])
            ->filter(fn ($s) => $s->clocked_in_at && $s->clocked_in_at->lte(\Carbon\Carbon::parse($s->shift_date->format('Y-m-d') . ' ' . $s->scheduled_start)));
        $onTimeRate   = $daysWorked > 0 ? round($onTimeShifts->count() / $daysWorked * 100) : null;

        return view('admin.staff.my-shift', compact(
            'activeShift', 'todayShift', 'upcoming', 'recent',
            'hoursThisMonth', 'hoursThisWeek', 'daysWorked', 'onTimeRate'
        ));
    }

    public function myShiftHistory(Request $request)
    {
        $user = $this->authUser();

        $history = Shift::where('staff_id', $user->id)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->month, fn ($q, $v) => $q->whereMonth('shift_date', $v))
            ->when($request->year,  fn ($q, $v) => $q->whereYear('shift_date',  $v))
            ->orderByDesc('shift_date')->orderByDesc('scheduled_start')
            ->paginate(10)->withQueryString();

        $totalHours = Shift::where('staff_id', $user->id)
            ->whereNotNull('clocked_out_at')
            ->get()->sum(fn ($s) => $s->duration_minutes / 60);

        return view('admin.staff.shift-history', compact('history', 'totalHours'));
    }

    public function shifts(Request $request)
    {
        $tenant = $this->authTenant();

        $shifts = Shift::where('tenant_id', $tenant->id)
            ->with('staff', 'branch')
            ->when($request->date, fn ($q, $v) => $q->whereDate('shift_date', $v))
            ->when($request->staff_id, fn ($q, $v) => $q->where('staff_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('shift_date')
            ->orderBy('scheduled_start')
            ->paginate(30)->withQueryString();

        $staffList = User::where('tenant_id', $tenant->id)
            ->whereIn('user_type', ['staff', 'manager'])
            ->orderBy('name')
            ->get(['id', 'name']);

        $branches = $tenant->branches()->orderBy('name')->get(['id', 'name']);

        return view('admin.staff.shifts', compact('shifts', 'staffList', 'branches'));
    }

    public function storeShift(Request $request)
    {
        $tenant   = $this->authTenant();
        $branchId = $this->requireActiveBranch('shift');

        $data = $request->validate([
            'staff_id'        => [
                'required',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenant->id)),
            ],
            'shift_date'      => ['required', 'date'],
            'scheduled_start' => ['required', 'date_format:H:i'],
            'scheduled_end'   => ['required', 'date_format:H:i', 'after:scheduled_start'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        Shift::create([
            'tenant_id'       => $tenant->id,
            'branch_id'       => $branchId,
            'staff_id'        => $data['staff_id'],
            'shift_date'      => $data['shift_date'],
            'scheduled_start' => $data['scheduled_start'],
            'scheduled_end'   => $data['scheduled_end'],
            'status'          => 'scheduled',
            'notes'           => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Shift scheduled.');
    }

    public function updateShift(Request $request, Shift $shift)
    {
        abort_unless($shift->tenant_id === $this->authTenant()->id, 403);

        if ($shift->clocked_in_at) {
            $data = $request->validate([
                'status' => ['required', 'in:active,completed,late'],
                'notes'  => ['nullable', 'string', 'max:500'],
            ]);
            $shift->update($data);
            return back()->with('success', 'Shift updated.');
        }

        $tenantId = $this->authTenant()->id;
        $data = $request->validate([
            'staff_id'        => [
                'required',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'shift_date'      => ['required', 'date'],
            'scheduled_start' => ['required', 'date_format:H:i'],
            'scheduled_end'   => ['required', 'date_format:H:i', 'after:scheduled_start'],
            'status'          => ['required', 'in:scheduled,absent,cancelled'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $shift->update($data);

        return back()->with('success', 'Shift updated.');
    }

    public function clockIn(Request $request)
    {
        $user = $this->authUser();
        $today = now()->toDateString();

        $openShift = Shift::where('staff_id', $user->id)
            ->whereNotNull('clocked_in_at')
            ->whereNull('clocked_out_at')
            ->first();
        if ($openShift) {
            return back()->with('error', 'You are already clocked in.');
        }

        $shift = Shift::where('staff_id', $user->id)
            ->whereDate('shift_date', $today)
            ->whereIn('status', ['scheduled', 'late'])
            ->orderBy('scheduled_start')
            ->first();

        if ($shift) {
            $isLate = now()->format('H:i:s') > $shift->scheduled_start;
            $shift->update([
                'clocked_in_at' => now(),
                'status'        => $isLate ? 'late' : 'active',
            ]);
        } else {
            $branchId = $request->branch_id
                ?? $user->tenant->branches()->where('is_main', true)->value('id')
                ?? $user->tenant->branches()->value('id');

            if (!$branchId) {
                return back()->with('error', 'No branch configured for this tenant.');
            }

            $shift = Shift::create([
                'tenant_id'       => $user->tenant_id,
                'branch_id'       => $branchId,
                'staff_id'        => $user->id,
                'shift_date'      => $today,
                'scheduled_start' => now()->format('H:i:s'),
                'scheduled_end'   => now()->addHours(8)->format('H:i:s'),
                'clocked_in_at'   => now(),
                'status'          => 'active',
                'notes'           => 'Ad-hoc clock-in (no scheduled shift).',
            ]);
        }

        return back()->with('success', 'Clocked in at ' . now()->format('H:i'));
    }

    public function clockOut()
    {
        $user = $this->authUser();

        $shift = Shift::where('staff_id', $user->id)
            ->whereNotNull('clocked_in_at')
            ->whereNull('clocked_out_at')
            ->latest('clocked_in_at')
            ->first();

        if (! $shift) {
            return back()->with('error', 'No active shift found.');
        }

        $shift->update([
            'clocked_out_at' => now(),
            'status'         => 'completed',
        ]);

        return back()->with('success', 'Clocked out at ' . now()->format('H:i'));
    }
}
