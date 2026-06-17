<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-3 months', 'now');
        $expiresAt = (clone $startsAt)->modify('+30 days');

        return [
            'tenant_id'         => Tenant::factory(),
            'customer_id'       => User::factory(),
            'plan_id'           => MembershipPlan::factory(),
            'membership_number' => 'MEM-' . strtoupper(Str::random(8)),
            'status'            => 'active',
            'remaining_credits' => fake()->numberBetween(0, 20),
            'starts_at'         => $startsAt,
            'expires_at'        => $expiresAt,
            'auto_renew'        => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status'     => 'active',
            'expires_at' => now()->addDays(15),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status'     => 'expired',
            'expires_at' => now()->subDay(),
            'auto_renew' => false,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'auto_renew'   => false,
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn () => [
            'status'      => 'frozen',
            'frozen_at'   => now()->subDays(3),
            'frozen_until' => now()->addDays(7),
        ]);
    }
}
