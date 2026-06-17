<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startHour    = fake()->numberBetween(6, 20);
        $durationHrs  = fake()->randomElement([1, 1.5, 2]);
        $endHour      = $startHour + $durationHrs;
        $rate         = fake()->randomElement([300, 400, 500, 600]);
        $baseAmount   = $rate * $durationHrs;

        return [
            'tenant_id'      => Tenant::factory(),
            'branch_id'      => Branch::factory(),
            'court_id'       => Court::factory(),
            'customer_id'    => User::factory(),
            'created_by'     => null,
            'booking_number' => 'BK-' . strtoupper(Str::random(8)),
            'type'           => fake()->randomElement(['online', 'walk_in', 'staff']),
            'status'         => 'confirmed',
            'booking_date'   => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'start_time'     => sprintf('%02d:00:00', $startHour),
            'end_time'       => sprintf('%02d:00:00', min((int) $endHour, 22)),
            'duration_minutes' => (int) ($durationHrs * 60),
            'base_amount'    => $baseAmount,
            'addon_amount'   => 0,
            'discount_amount' => 0,
            'tax_amount'     => 0,
            'total_amount'   => $baseAmount,
            'paid_amount'    => 0,
            'reminder_sent'  => false,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['status' => 'confirmed']);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'        => 'completed',
            'booking_date'  => fake()->dateTimeBetween('-30 days', 'yesterday')->format('Y-m-d'),
            'paid_amount'   => $attrs['total_amount'],
            'checked_in_at'  => now()->subHours(2),
            'checked_out_at' => now()->subHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'paid_amount' => $attrs['total_amount'],
        ]);
    }
}
