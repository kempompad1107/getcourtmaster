<?php

namespace Database\Factories;

use App\Models\MembershipPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        $names = ['Bronze', 'Silver', 'Gold', 'Platinum', 'VIP'];
        $name  = fake()->unique()->randomElement($names) . ' Member';

        return [
            'tenant_id'              => Tenant::factory(),
            'name'                   => $name,
            'slug'                   => Str::slug($name),
            'description'            => fake()->sentence(),
            'billing_cycle'          => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'price'                  => fake()->randomElement([499, 799, 1299, 1999, 2999]),
            'court_credits'          => fake()->numberBetween(4, 20),
            'discount_percent'       => fake()->randomElement([5, 10, 15, 20]),
            'perks'                  => ['Priority booking', 'Guest passes', 'Locker access'],
            'is_vip'                 => false,
            'max_freeze_days'        => 30,
            'freeze_count_per_year'  => 2,
            'is_active'              => true,
            'sort_order'             => fake()->numberBetween(1, 10),
        ];
    }

    public function vip(): static
    {
        return $this->state(fn () => [
            'name'             => 'VIP Member',
            'slug'             => 'vip-member',
            'is_vip'           => true,
            'price'            => 4999,
            'discount_percent' => 25,
            'court_credits'    => 40,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['billing_cycle' => 'monthly']);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['billing_cycle' => 'yearly']);
    }
}
