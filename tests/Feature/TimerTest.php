<?php

namespace Tests\Feature;

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

class TimerTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;
    private User $customer;
    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::create([
            'name' => 'T', 'slug' => 't', 'email' => 't@t.com',
            'plan' => 'pro', 'status' => 'active',
            'timezone' => 'Asia/Manila', 'currency' => 'PHP',
        ]);
        $branch = Branch::create([
            'tenant_id' => $tenant->id, 'name' => 'Main', 'slug' => 'main',
            'is_main' => true, 'is_active' => true,
        ]);
        $this->court = Court::create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id,
            'name' => 'C1', 'type' => 'indoor', 'status' => 'available',
            'base_hourly_rate' => 600, 'min_booking_minutes' => 60,
            'max_booking_minutes' => 240, 'buffer_minutes' => 0,
            'capacity' => 4, 'is_active' => true,
        ]);
        $this->customer = User::create([
            'name' => 'P', 'email' => 'p@p.com', 'password' => bcrypt('x'),
            'tenant_id' => $tenant->id, 'user_type' => 'customer',
            'is_active' => true,
        ]);
        $this->service = new BookingService(
            new PricingService(), new QrCodeService(), new WalletService(),
        );
    }

    public function test_starting_timer_marks_booking_active_and_court_occupied(): void
    {
        $booking = $this->service->create([
            'court_id'     => $this->court->id,
            'booking_date' => today()->toDateString(),
            'start_time'   => now()->format('H:i'),
            'end_time'     => now()->addHour()->format('H:i'),
        ], $this->customer);

        $timer = $this->service->startTimer($booking);

        $this->assertEquals('running', $timer->status);
        $this->assertEquals('active',   $booking->fresh()->status);
        $this->assertEquals('occupied', $this->court->fresh()->status);
    }

    public function test_pause_resume_increments_paused_seconds(): void
    {
        $booking = $this->service->create([
            'court_id'     => $this->court->id,
            'booking_date' => today()->toDateString(),
            'start_time'   => now()->format('H:i'),
            'end_time'     => now()->addHour()->format('H:i'),
        ], $this->customer);
        $timer = $this->service->startTimer($booking);

        $this->service->pauseTimer($timer);
        $this->assertEquals('paused', $timer->fresh()->status);

        // simulate 1s of pause then resume
        $timer->update(['paused_at' => now()->subSecond()]);
        $this->service->resumeTimer($timer);

        $this->assertEquals('running', $timer->fresh()->status);
        $this->assertGreaterThanOrEqual(1, $timer->fresh()->paused_seconds);
    }

    public function test_extend_pushes_scheduled_end(): void
    {
        $booking = $this->service->create([
            'court_id'     => $this->court->id,
            'booking_date' => today()->toDateString(),
            'start_time'   => now()->format('H:i'),
            'end_time'     => now()->addHour()->format('H:i'),
        ], $this->customer);
        $timer = $this->service->startTimer($booking);
        $beforeEnd = $timer->scheduled_end_at;

        $this->service->extendTimer($timer, 30);

        $this->assertEquals(30 * 60, $timer->fresh()->extension_seconds);
        $this->assertTrue($timer->fresh()->scheduled_end_at->gt($beforeEnd));
    }

    public function test_stop_completes_booking_and_frees_court(): void
    {
        $booking = $this->service->create([
            'court_id'     => $this->court->id,
            'booking_date' => today()->toDateString(),
            'start_time'   => now()->format('H:i'),
            'end_time'     => now()->addHour()->format('H:i'),
        ], $this->customer);
        $timer = $this->service->startTimer($booking);

        $this->service->stopTimer($timer);

        $this->assertEquals('stopped',   $timer->fresh()->status);
        $this->assertEquals('completed', $booking->fresh()->status);
        $this->assertEquals('available', $this->court->fresh()->status);
    }
}
