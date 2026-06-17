<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Beverages', 'Snacks', 'Equipment', 'Apparel', 'Accessories', 'Rackets', 'Shoes', 'Balls',
        ]);

        return [
            'tenant_id'  => Tenant::factory(),
            'name'       => $name,
            'slug'       => Str::slug($name),
            'icon'       => fake()->randomElement(['🥤', '🍿', '🏸', '👕', '🎾', '👟']),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active'  => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
