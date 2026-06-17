<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Court;
use App\Models\MembershipPlan;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $plan = SubscriptionPlan::where('slug', 'pro')->first();

        // Create demo tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-pickleball'],
            [
                'name' => 'Demo Pickleball Club',
                'email' => 'owner@demo.courtmaster.app',
                'phone' => '+63-912-345-6789',
                'address' => '123 Sports Complex Ave',
                'city' => 'Makati',
                'country' => 'PH',
                'timezone' => 'Asia/Manila',
                'currency' => 'PHP',
                'plan' => 'pro',
                'status' => 'active',
                'settings' => ['tax_rate' => 12, 'grace_period_minutes' => 5],
                'features' => ['bookings', 'pos', 'memberships', 'inventory', 'promotions', 'api_access'],
            ]
        );

        // Business Owner
        $owner = User::firstOrCreate(
            ['email' => 'owner@demo.courtmaster.app'],
            [
                'name' => 'Demo Owner',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'user_type' => 'business_owner',
                'email_verified_at' => now(),
                'is_active' => true,
                'referral_code' => 'DEMO001',
            ]
        );
        $owner->assignRole('business_owner');

        // Staff Member
        $staff = User::firstOrCreate(
            ['email' => 'staff@demo.courtmaster.app'],
            [
                'name' => 'Demo Staff',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'user_type' => 'staff',
                'email_verified_at' => now(),
                'is_active' => true,
                'referral_code' => 'STAFF01',
            ]
        );
        $staff->assignRole('front_desk');

        // Sample Customer
        $customer = User::firstOrCreate(
            ['email' => 'player@demo.courtmaster.app'],
            [
                'name' => 'Demo Player',
                'password' => Hash::make('password'),
                'tenant_id' => $tenant->id,
                'user_type' => 'customer',
                'email_verified_at' => now(),
                'is_active' => true,
                'referral_code' => 'PLAY001',
            ]
        );

        // wallet_balance is no longer mass-assignable (security: only WalletService
        // moves it). Set the demo seed balance explicitly via forceFill.
        if ((float) $customer->wallet_balance < 500) {
            $customer->forceFill(['wallet_balance' => 500])->save();
        }

        // Branch
        $branch = Branch::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'main'],
            [
                'name' => 'Main Branch',
                'address' => '123 Sports Complex Ave, Makati',
                'phone' => '+63-912-345-6789',
                'is_main' => true,
                'is_active' => true,
                'operating_hours' => [
                    'monday' => ['is_open' => true, 'open' => '07:00', 'close' => '22:00'],
                    'tuesday' => ['is_open' => true, 'open' => '07:00', 'close' => '22:00'],
                    'wednesday' => ['is_open' => true, 'open' => '07:00', 'close' => '22:00'],
                    'thursday' => ['is_open' => true, 'open' => '07:00', 'close' => '22:00'],
                    'friday' => ['is_open' => true, 'open' => '07:00', 'close' => '23:00'],
                    'saturday' => ['is_open' => true, 'open' => '06:00', 'close' => '23:00'],
                    'sunday' => ['is_open' => true, 'open' => '06:00', 'close' => '22:00'],
                ],
            ]
        );

        // Courts
        $courtNames = ['Court A', 'Court B', 'Court C', 'Court D'];
        foreach ($courtNames as $i => $name) {
            Court::firstOrCreate(
                ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => $name],
                [
                    'type' => $i % 2 === 0 ? 'indoor' : 'outdoor',
                    'status' => 'available',
                    'capacity' => 4,
                    'base_hourly_rate' => 400 + ($i * 50),
                    'peak_hourly_rate' => 600 + ($i * 50),
                    'min_booking_minutes' => 60,
                    'max_booking_minutes' => 240,
                    'buffer_minutes' => 15,
                    'is_active' => true,
                    'sort_order' => $i,
                    'amenities' => ['paddles', 'balls', 'water', 'lighting'],
                ]
            );
        }

        // Membership Plans
        $memPlans = [
            ['name' => 'Monthly Pass', 'billing_cycle' => 'monthly', 'price' => 2500, 'court_credits' => 10, 'discount_percent' => 10],
            ['name' => 'Quarterly Pass', 'billing_cycle' => 'quarterly', 'price' => 6500, 'court_credits' => 35, 'discount_percent' => 15],
            ['name' => 'Annual VIP', 'billing_cycle' => 'yearly', 'price' => 24000, 'court_credits' => 150, 'discount_percent' => 25, 'is_vip' => true],
        ];

        foreach ($memPlans as $mp) {
            MembershipPlan::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => \Str::slug($mp['name'])],
                array_merge($mp, ['tenant_id' => $tenant->id, 'slug' => \Str::slug($mp['name']), 'is_active' => true])
            );
        }

        // Product Categories & Products
        $categories = [
            ['name' => 'Beverages', 'products' => [
                ['name' => 'Water (500ml)', 'selling_price' => 30, 'stock_quantity' => 100],
                ['name' => 'Sports Drink', 'selling_price' => 80, 'stock_quantity' => 50],
                ['name' => 'Energy Drink', 'selling_price' => 120, 'stock_quantity' => 30],
            ]],
            ['name' => 'Equipment', 'products' => [
                ['name' => 'Paddle Rental', 'selling_price' => 100, 'track_inventory' => false],
                ['name' => 'Ball (per game)', 'selling_price' => 50, 'stock_quantity' => 200],
                ['name' => 'Grip Tape', 'selling_price' => 80, 'stock_quantity' => 30],
            ]],
            ['name' => 'Snacks', 'products' => [
                ['name' => 'Banana', 'selling_price' => 25, 'stock_quantity' => 50],
                ['name' => 'Protein Bar', 'selling_price' => 150, 'stock_quantity' => 20],
            ]],
        ];

        foreach ($categories as $catData) {
            $category = ProductCategory::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => \Str::slug($catData['name'])],
                ['name' => $catData['name'], 'tenant_id' => $tenant->id, 'is_active' => true]
            );
            foreach ($catData['products'] as $prod) {
                Product::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $prod['name']],
                    array_merge($prod, [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'category_id' => $category->id,
                        'cost_price' => $prod['selling_price'] * 0.6,
                        'track_inventory' => $prod['track_inventory'] ?? true,
                        'is_active' => true,
                        'low_stock_threshold' => 5,
                    ])
                );
            }
        }
    }
}
