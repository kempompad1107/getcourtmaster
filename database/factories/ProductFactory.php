<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name      = fake()->randomElement(['Gatorade', 'Water 500ml', 'Shuttlecock Tube', 'Grip Tape', 'Wristband', 'Energy Bar', 'Sports Towel']);
        $cost      = fake()->randomFloat(2, 20, 200);
        $selling   = round($cost * fake()->randomFloat(2, 1.2, 2.0), 2);

        return [
            'tenant_id'          => Tenant::factory(),
            'branch_id'          => Branch::factory(),
            'category_id'        => ProductCategory::factory(),
            'name'               => $name,
            'sku'                => strtoupper(Str::random(3)) . '-' . fake()->numerify('###'),
            'barcode'            => fake()->optional()->ean13(),
            'description'        => fake()->optional()->sentence(),
            'cost_price'         => $cost,
            'selling_price'      => $selling,
            'tax_rate'           => 0,
            'stock_quantity'     => fake()->numberBetween(0, 100),
            'low_stock_threshold' => 10,
            'track_inventory'    => true,
            'is_active'          => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['stock_quantity' => 0]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'stock_quantity'     => fake()->numberBetween(1, 9),
            'low_stock_threshold' => 10,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
