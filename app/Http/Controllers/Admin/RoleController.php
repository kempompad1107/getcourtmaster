<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Roles that are tenant-editable here. business_owner / super_admin are
     * intentionally excluded — they get full access by design.
     */
    private const EDITABLE_ROLES = ['manager', 'front_desk', 'inventory_manager'];

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
        'Tournaments' => ['tournaments.view', 'tournaments.create', 'tournaments.update', 'tournaments.delete',
                          'tournaments.registrations', 'tournaments.brackets', 'tournaments.matches', 'tournaments.reports'],
        'Staff'       => ['staff.view', 'staff.create', 'staff.update', 'staff.manage'],
    ];

    public function index()
    {
        $this->authorizeOwner();

        $roles = Role::whereIn('name', self::EDITABLE_ROLES)
            ->with('permissions')
            ->get()
            ->sortBy(fn ($r) => array_search($r->name, self::EDITABLE_ROLES));

        return view('admin.settings.roles.index', [
            'roles'  => $roles,
            'groups' => self::PERMISSION_GROUPS,
        ]);
    }

    public function edit(Role $role)
    {
        $this->authorizeOwner();
        $this->assertEditable($role);

        $assigned = $role->permissions->pluck('name')->all();

        return view('admin.settings.roles.edit', [
            'role'     => $role,
            'groups'   => self::PERMISSION_GROUPS,
            'assigned' => $assigned,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        $this->authorizeOwner();
        $this->assertEditable($role);

        $allowed = collect(self::PERMISSION_GROUPS)->flatten()->all();

        $data = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => ['string', \Illuminate\Validation\Rule::in($allowed)],
        ]);

        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()
            ->route('admin.roles.index')
            ->with('success', ucfirst(str_replace('_', ' ', $role->name)) . ' permissions updated.');
    }

    private function authorizeOwner(): void
    {
        $user = $this->authUser();
        abort_unless($user->isBusinessOwner(), 403, 'Only the business owner can manage roles.');
    }

    private function assertEditable(Role $role): void
    {
        abort_unless(in_array($role->name, self::EDITABLE_ROLES, true), 403, 'This role is not editable.');
    }
}
