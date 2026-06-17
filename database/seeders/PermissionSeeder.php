<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Courts
            'courts.view', 'courts.create', 'courts.update', 'courts.delete', 'courts.status',

            // Bookings
            'bookings.view', 'bookings.create', 'bookings.update', 'bookings.cancel',
            'timer.manage',

            // POS
            'pos.access', 'pos.void',

            // Memberships
            'memberships.view', 'memberships.create', 'memberships.update', 'memberships.cancel',

            // Reports
            'reports.view', 'reports.export',

            // Inventory
            'inventory.view', 'inventory.manage',

            // Promotions
            'promotions.view', 'promotions.manage',

            // Staff
            'staff.view', 'staff.create', 'staff.update', 'staff.manage',

            // Settings
            'settings.manage',

            // Branches
            'branches.view', 'branches.manage',

            // Customers
            'customers.view', 'customers.manage',

            // Tournaments
            'tournaments.view', 'tournaments.create', 'tournaments.update', 'tournaments.delete',
            'tournaments.registrations', 'tournaments.brackets', 'tournaments.matches', 'tournaments.reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Roles
        Role::firstOrCreate(['name' => 'super_admin']);
        $businessOwner    = Role::firstOrCreate(['name' => 'business_owner']);
        $manager          = Role::firstOrCreate(['name' => 'manager']);
        $frontDesk        = Role::firstOrCreate(['name' => 'front_desk']);
        $cashier          = Role::firstOrCreate(['name' => 'cashier']);
        $bookingManager   = Role::firstOrCreate(['name' => 'booking_manager']);
        $reportViewer     = Role::firstOrCreate(['name' => 'report_viewer']);
        $inventoryManager = Role::firstOrCreate(['name' => 'inventory_manager']);

        $businessOwner->syncPermissions(Permission::all());

        // Manager — broad operational access, no destructive settings/billing
        if ($manager->permissions()->count() === 0) {
            $manager->givePermissionTo([
                'branches.view',
                'courts.view', 'courts.status', 'courts.update',
                'bookings.view', 'bookings.create', 'bookings.update', 'bookings.cancel',
                'timer.manage',
                'pos.access', 'pos.void',
                'memberships.view', 'memberships.create', 'memberships.update', 'memberships.cancel',
                'reports.view', 'reports.export',
                'inventory.view', 'inventory.manage',
                'promotions.view', 'promotions.manage',
                'staff.view',
                'customers.view', 'customers.manage',
                'tournaments.view', 'tournaments.create', 'tournaments.update',
                'tournaments.registrations', 'tournaments.brackets', 'tournaments.matches', 'tournaments.reports',
            ]);
        }

        if ($frontDesk->permissions()->count() === 0) {
            $frontDesk->givePermissionTo([
                'courts.view', 'courts.status',
                'bookings.view', 'bookings.create', 'bookings.cancel',
                'timer.manage', 'customers.view', 'customers.manage',
                'pos.access',
            ]);
        }

        if ($cashier->permissions()->count() === 0) {
            $cashier->givePermissionTo([
                'pos.access', 'pos.void', 'customers.view',
            ]);
        }

        if ($bookingManager->permissions()->count() === 0) {
            $bookingManager->givePermissionTo([
                'courts.view', 'bookings.view', 'bookings.create',
                'bookings.update', 'bookings.cancel', 'timer.manage',
                'customers.view',
            ]);
        }

        if ($reportViewer->permissions()->count() === 0) {
            $reportViewer->givePermissionTo(['reports.view']);
        }

        if ($inventoryManager->permissions()->count() === 0) {
            $inventoryManager->givePermissionTo([
                'inventory.view', 'inventory.manage',
            ]);
        }
    }
}
