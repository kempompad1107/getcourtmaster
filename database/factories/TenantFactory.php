<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company() . ' Sports';

        return [
            'name'            => $name,
            'slug'            => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 999),
            'email'           => fake()->companyEmail(),
            'phone'           => fake()->phoneNumber(),
            'address'         => fake()->streetAddress(),
            'city'            => fake()->city(),
            'state'           => fake()->state(),
            'country'         => 'PH',
            'timezone'        => 'Asia/Manila',
            'currency'        => 'PHP',
            'plan'            => fake()->randomElement(['starter', 'pro', 'enterprise']),
            'status'          => 'active',
            'commission_rate' => fake()->randomElement([0, 2.5, 5.0]),
            'trial_ends_at'   => null,
            'plan_expires_at' => now()->addYear(),
            'settings'        => [
                'booking_lead_time_hours'  => 1,
                'cancellation_cutoff_hours' => 24,
                'require_payment_upfront'  => false,
            ],
            'features' => ['bookings', 'pos', 'memberships', 'reports'],
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'status'        => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    public function pro(): static
    {
        return $this->state(fn () => ['plan' => 'pro']);
    }
}
