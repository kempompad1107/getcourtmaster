<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\PosOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PosOrder>
 */
class PosOrderFactory extends Factory
{
    protected $model = PosOrder::class;

    public function definition(): array
    {
        $subtotal  = fake()->randomFloat(2, 50, 2000);
        $discount  = 0;
        $tax       = 0;
        $total     = $subtotal - $discount + $tax;
        $tendered  = ceil($total / 50) * 50;

        return [
            'tenant_id'       => Tenant::factory(),
            'branch_id'       => Branch::factory(),
            'customer_id'     => null,
            'cashier_id'      => User::factory(),
            'order_number'    => 'ORD-' . strtoupper(Str::random(8)),
            'status'          => 'completed',
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'tax_amount'      => $tax,
            'total'           => $total,
            'amount_tendered' => $tendered,
            'change_amount'   => $tendered - $total,
            'notes'           => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status'    => 'voided',
            'voided_at' => now(),
        ]);
    }

    public function withCustomer(): static
    {
        return $this->state(fn () => ['customer_id' => User::factory()]);
    }
}
