<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $name = fake()->randomElement(['Main Branch', 'North Court', 'South Court', 'Downtown', 'Uptown']);

        return [
            'tenant_id'       => Tenant::factory(),
            'name'            => $name,
            'slug'            => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 9999),
            'address'         => fake()->streetAddress(),
            'city'            => fake()->city(),
            'phone'           => fake()->phoneNumber(),
            'email'           => fake()->companyEmail(),
            'is_main'         => false,
            'is_active'       => true,
            'latitude'        => fake()->latitude(10, 18),
            'longitude'       => fake()->longitude(118, 126),
            'operating_hours' => $this->defaultOperatingHours(),
        ];
    }

    public function main(): static
    {
        return $this->state(fn () => ['is_main' => true, 'name' => 'Main Branch']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    private function defaultOperatingHours(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[$day] = [
                'is_open' => true,
                'open'    => '06:00',
                'close'   => '22:00',
            ];
        }

        return $hours;
    }
}
