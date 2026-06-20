<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TruncateNonDemoData extends Command
{
    protected $signature = 'app:truncate-non-demo-data
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete all records except the superadmin account and the demo pickleball club tenant (and its users/data).';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->warn('This will permanently delete ALL non-demo tenant data and non-superadmin users.');
            if (! $this->confirm('Are you sure you want to continue?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        $demoTenantId = DB::table('tenants')->where('slug', 'demo-pickleball')->value('id');

        if (! $demoTenantId) {
            $this->error('Demo pickleball tenant not found. Run seeders first.');
            return self::FAILURE;
        }

        $this->info("Demo tenant ID: {$demoTenantId}");

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Collect IDs to keep before deletion
        $demoUserIds = DB::table('users')->where('tenant_id', $demoTenantId)->pluck('id');
        $superAdminId = DB::table('users')->where('email', 'admin@courtmaster.app')->value('id');
        $keepUserIds = $demoUserIds->push($superAdminId)->filter()->unique()->values();

        $demoPosOrderIds = DB::table('pos_orders')->where('tenant_id', $demoTenantId)->pluck('id');
        $demoPurchaseOrderIds = DB::table('purchase_orders')->where('tenant_id', $demoTenantId)->pluck('id');
        $demoBookingIds = DB::table('bookings')->where('tenant_id', $demoTenantId)->pluck('id');
        $demoMembershipIds = DB::table('memberships')->where('tenant_id', $demoTenantId)->pluck('id');
        $demoPromotionIds = DB::table('promotions')->where('tenant_id', $demoTenantId)->pluck('id');
        $demoCourtIds = DB::table('courts')->where('tenant_id', $demoTenantId)->pluck('id');

        // booking_addons — via booking_id
        $deleted = DB::table('booking_addons')->whereNotIn('booking_id', $demoBookingIds)->delete();
        $this->line("  booking_addons: deleted {$deleted} rows");

        // booking_timers — via booking_id
        $deleted = DB::table('booking_timers')->whereNotIn('booking_id', $demoBookingIds)->delete();
        $this->line("  booking_timers: deleted {$deleted} rows");

        // pos_payments — via order_id
        $deleted = DB::table('pos_payments')->whereNotIn('order_id', $demoPosOrderIds)->delete();
        $this->line("  pos_payments: deleted {$deleted} rows");

        // pos_order_items — via order_id
        $deleted = DB::table('pos_order_items')->whereNotIn('order_id', $demoPosOrderIds)->delete();
        $this->line("  pos_order_items: deleted {$deleted} rows");

        // purchase_order_items — via purchase_order_id
        $deleted = DB::table('purchase_order_items')->whereNotIn('purchase_order_id', $demoPurchaseOrderIds)->delete();
        $this->line("  purchase_order_items: deleted {$deleted} rows");

        // membership_transactions — via membership_id
        $deleted = DB::table('membership_transactions')->whereNotIn('membership_id', $demoMembershipIds)->delete();
        $this->line("  membership_transactions: deleted {$deleted} rows");

        // promotion_usages — via promotion_id
        $deleted = DB::table('promotion_usages')->whereNotIn('promotion_id', $demoPromotionIds)->delete();
        $this->line("  promotion_usages: deleted {$deleted} rows");

        // court_pricing_rules — via court_id
        $deleted = DB::table('court_pricing_rules')->whereNotIn('court_id', $demoCourtIds)->delete();
        $this->line("  court_pricing_rules: deleted {$deleted} rows");

        // Tables with direct tenant_id
        $tenantScopedTables = [
            'tournament_team_members',
            'tournament_matches',
            'tournament_groups',
            'tournament_divisions',
            'tournament_teams',
            'tournaments',
            'refund_requests',
            'bookings',
            'waitlist_entries',
            'wallet_transactions',
            'memberships',
            'membership_plans',
            'pos_orders',
            'promotions',
            'inventory_movements',
            'purchase_orders',
            'suppliers',
            'products',
            'product_categories',
            'courts',
            'cash_drawer_logs',
            'shifts',
            'staff_profiles',
            'report_presets',
            'customer_notes',
            'tenant_settings',
            'tenant_subscriptions',
            'subscription_invoices',
            'payments',
            'branches',
        ];

        foreach ($tenantScopedTables as $table) {
            $deleted = DB::table($table)->where('tenant_id', '!=', $demoTenantId)->delete();
            $this->line("  {$table}: deleted {$deleted} rows");
        }

        // audit_logs — tenant scoped
        $deleted = DB::table('audit_logs')->where('tenant_id', '!=', $demoTenantId)->delete();
        $this->line("  audit_logs: deleted {$deleted} rows");

        // activity_log — scoped by causer (user)
        $deleted = DB::table('activity_log')
            ->where('causer_type', 'App\\Models\\User')
            ->whereNotIn('causer_id', $keepUserIds)
            ->delete();
        $this->line("  activity_log: deleted {$deleted} rows");

        // User dependent tables
        foreach (['user_sessions', 'otp_codes', 'push_subscriptions'] as $table) {
            $deleted = DB::table($table)->whereNotIn('user_id', $keepUserIds)->delete();
            $this->line("  {$table}: deleted {$deleted} rows");
        }

        $deleted = DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->whereNotIn('model_id', $keepUserIds)
            ->delete();
        $this->line("  model_has_roles (users): deleted {$deleted} rows");

        $deleted = DB::table('users')->whereNotIn('id', $keepUserIds)->delete();
        $this->line("  users: deleted {$deleted} rows");

        // Tenants — keep only the demo tenant
        $deleted = DB::table('tenants')->where('id', '!=', $demoTenantId)->delete();
        $this->line("  tenants: deleted {$deleted} rows");

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('Done. Kept superadmin and demo pickleball club data.');

        return self::SUCCESS;
    }
}
