<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\QrCodeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundRuleTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;
    private Tenant $tenant;
    private User $customer;
    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create([
            'name' => 'T', 'slug' => 't', 'email' => 't@t.com',
            'plan' => 'pro', 'status' => 'active',
            'timezone' => 'Asia/Manila', 'currency' => 'PHP',
            'settings' => ['refund' => [
                'full_window_hours'    => 24,
                'partial_window_hours' => 6,
                'partial_percent'      => 50,
            ]],
        ]);
        $branch = Branch::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main', 'slug' => 'main',
            'is_main' => true, 'is_active' => true,
        ]);
        $this->court = Court::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $branch->id,
            'name' => 'C', 'type' => 'indoor', 'status' => 'available',
            'base_hourly_rate' => 500, 'min_booking_minutes' => 60,
            'max_booking_minutes' => 240, 'buffer_minutes' => 0,
            'capacity' => 4, 'is_active' => true,
        ]);
        $this->customer = User::create([
            'name' => 'C', 'email' => 'c@c.com', 'password' => bcrypt('x'),
            'tenant_id' => $this->tenant->id, 'user_type' => 'customer', 'is_active' => true,
        ]);
        $this->service = new BookingService(new PricingService(), new QrCodeService(), new WalletService());
    }

    private function bookingAt(string $when, float $paid): Booking
    {
        $dt = \Carbon\Carbon::parse($when);
        $booking = $this->service->create([
            'court_id'     => $this->court->id,
            'booking_date' => $dt->toDateString(),
            'start_time'   => $dt->format('H:i'),
            'end_time'     => $dt->copy()->addHour()->format('H:i'),
        ], $this->customer);
        $booking->update(['paid_amount' => $paid]);
        return $booking;
    }

    public function test_full_refund_outside_24h_window(): void
    {
        $b = $this->bookingAt(now()->addDays(2)->setTime(10, 0)->toDateTimeString(), 500);
        $this->assertEquals(500.0, $this->service->computeRefundAmount($b));
    }

    public function test_half_refund_inside_partial_window(): void
    {
        // 10 hours from now → inside 24h, outside 6h → 50%
        $b = $this->bookingAt(now()->addHours(10)->toDateTimeString(), 500);
        $this->assertEquals(250.0, $this->service->computeRefundAmount($b));
    }

    public function test_no_refund_inside_partial_window(): void
    {
        // 2 hours from now → inside 6h window → 0
        $b = $this->bookingAt(now()->addHours(2)->toDateTimeString(), 500);
        $this->assertEquals(0.0, $this->service->computeRefundAmount($b));
    }
}
