<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        $types     = ['badminton', 'basketball', 'tennis', 'volleyball', 'futsal'];
        $type      = fake()->randomElement($types);
        $courtNum  = fake()->unique()->numberBetween(1, 20);

        return [
            'tenant_id'           => Tenant::factory(),
            'branch_id'           => Branch::factory(),
            'name'                => 'Court ' . $courtNum,
            'code'                => strtoupper(substr($type, 0, 2)) . str_pad($courtNum, 2, '0', STR_PAD_LEFT),
            'type'                => $type,
            'status'              => 'available',
            'description'         => fake()->optional()->sentence(),
            'amenities'           => fake()->randomElements(['air_conditioning', 'lighting', 'shower', 'locker', 'wifi'], 3),
            'capacity'            => fake()->numberBetween(2, 10),
            'base_hourly_rate'    => fake()->randomElement([200, 300, 400, 500, 600]),
            'peak_hourly_rate'    => fake()->randomElement([400, 500, 600, 700, 800]),
            'peak_hours'          => [
                ['start' => '17:00', 'end' => '21:00', 'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']],
                ['start' => '08:00', 'end' => '20:00', 'days' => ['saturday', 'sunday']],
            ],
            'operating_hours'     => null,
            'min_booking_minutes' => 60,
            'max_booking_minutes' => 240,
            'buffer_minutes'      => 0,
            'is_active'           => true,
            'sort_order'          => $courtNum,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => 'occupied']);
    }

    public function maintenance(): static
    {
        return $this->state(fn () => ['status' => 'maintenance']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function badminton(): static
    {
        return $this->state(fn () => [
            'type'             => 'badminton',
            'base_hourly_rate' => 300,
            'capacity'         => 4,
        ]);
    }
}
