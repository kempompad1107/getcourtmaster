<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'                  => 'Starter',
                'slug'                  => 'starter',
                'description'           => 'Perfect for small venues getting started.',
                'price_monthly'         => 999,
                'price_yearly'          => 9990,
                'trial_days'            => 14,
                'max_courts'            => 5,
                'max_staff'             => 5,
                'max_bookings_per_month' => 500,
                'features'              => ['bookings', 'pos', 'basic_reports'],
                'sort_order'            => 1,
            ],
            [
                'name'                  => 'Pro',
                'slug'                  => 'pro',
                'description'           => 'For growing pickleball businesses.',
                'price_monthly'         => 2499,
                'price_yearly'          => 24990,
                'trial_days'            => 14,
                'max_courts'            => 20,
                'max_staff'             => 20,
                'max_bookings_per_month' => 2000,
                'features'              => ['bookings', 'pos', 'advanced_reports', 'memberships', 'inventory', 'api_access', 'promotions'],
                'sort_order'            => 2,
            ],
            [
                'name'                  => 'Enterprise',
                'slug'                  => 'enterprise',
                'description'           => 'For multi-branch enterprises with full features.',
                'price_monthly'         => 5999,
                'price_yearly'          => 59990,
                'trial_days'            => 30,
                'max_courts'            => 999,
                'max_staff'             => 999,
                'max_bookings_per_month' => 999999,
                'features'              => ['bookings', 'pos', 'advanced_reports', 'memberships', 'inventory', 'api_access', 'promotions', 'multi_branch', 'white_label', 'priority_support'],
                'sort_order'            => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
