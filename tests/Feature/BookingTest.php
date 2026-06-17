<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Court;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\QrCodeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private User $customer;
    private Branch $branch;
    private Court $court;
    private BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->tenant = Tenant::create([
            'name' => 'Test Club',
            'slug' => 'test-club',
            'email' => 'test@club.com',
            'plan' => 'pro',
            'status' => 'active',
            'timezone' => 'Asia/Manila',
            'currency' => 'PHP',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Main',
            'slug' => 'main',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->court = Court::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Court A',
            'type' => 'indoor',
            'status' => 'available',
            'base_hourly_rate' => 400,
            'min_booking_minutes' => 60,
            'max_booking_minutes' => 240,
            'buffer_minutes' => 0,
            'capacity' => 4,
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'name' => 'Test Player',
            'email' => 'player@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'user_type' => 'customer',
            'referral_code' => 'TESTPLAY',
            'is_active' => true,
        ]);

        $this->bookingService = new BookingService(
            new PricingService(),
            new QrCodeService(),
            new WalletService(),
        );
    }

    public function test_can_create_booking(): void
    {
        $booking = $this->bookingService->create([
            'court_id' => $this->court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'type' => 'online',
        ], $this->customer);

        $this->assertNotNull($booking);
        $this->assertEquals('pending', $booking->status);
        $this->assertEquals(400.00, $booking->base_amount);
        $this->assertNotNull($booking->booking_number);
    }

    public function test_cannot_double_book_court(): void
    {
        $date = now()->addDay()->toDateString();

        $this->bookingService->create([
            'court_id' => $this->court->id,
            'booking_date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $this->customer);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->bookingService->create([
            'court_id' => $this->court->id,
            'booking_date' => $date,
            'start_time' => '10:30',
            'end_time' => '11:30',
        ], $this->customer);
    }

    public function test_can_cancel_booking_with_refund(): void
    {
        $booking = $this->bookingService->create([
            'court_id' => $this->court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
        ], $this->customer);

        $booking->update(['paid_amount' => 400]);
        $this->customer->update(['wallet_balance' => 0]);

        $this->bookingService->cancel($booking, 'Changed plans', true);

        $this->customer->refresh();
        $this->assertEquals('cancelled', $booking->fresh()->status);
        $this->assertEquals(400.0, (float) $this->customer->wallet_balance);
    }

    public function test_availability_check_returns_correct_slots(): void
    {
        $pricingService = new PricingService();
        $slots = $pricingService->getAvailableSlots($this->court, now()->addDay()->toDateString(), 60);

        $this->assertIsArray($slots);
        $this->assertNotEmpty($slots);
        $this->assertArrayHasKey('start', $slots[0]);
        $this->assertArrayHasKey('total', $slots[0]);
    }

    public function test_booking_api_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/bookings', [
            'court_id' => $this->court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_customer_can_book_via_api(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/bookings', [
                'court_id' => $this->court->id,
                'booking_date' => now()->addDay()->toDateString(),
                'start_time' => '09:00',
                'end_time' => '10:00',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['booking' => ['id', 'booking_number', 'status', 'total_amount']]);
    }

    private function giveCustomerCredits(int $minutes): Membership
    {
        $plan = MembershipPlan::factory()->monthly()->create([
            'tenant_id'     => $this->tenant->id,
            'court_credits' => $minutes,
            'price'         => 1000,
        ]);

        return Membership::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'customer_id'       => $this->customer->id,
            'plan_id'           => $plan->id,
            'status'            => 'active',
            'remaining_credits' => $minutes,
            'starts_at'         => now()->subDay(),
            'expires_at'        => now()->addDays(30),
        ]);
    }

    public function test_credit_fully_covers_booking(): void
    {
        $membership = $this->giveCustomerCredits(600);

        $booking = $this->bookingService->create([
            'court_id'     => $this->court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '11:00',
            'end_time'     => '12:00',
            'use_credit'   => true,
        ], $this->customer);

        $membership->refresh();
        $this->assertEquals(540, $membership->remaining_credits);
        $this->assertEquals(0.0, (float) $booking->base_amount);
        $this->assertEquals(0.0, (float) $booking->total_amount);
        $this->assertStringContainsString('60 min', $booking->notes);
    }

    public function test_credit_prorates_partial_coverage(): void
    {
        $membership = $this->giveCustomerCredits(30);

        $booking = $this->bookingService->create([
            'court_id'     => $this->court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '13:00',
            'end_time'     => '14:00',
            'use_credit'   => true,
        ], $this->customer);

        $membership->refresh();
        $this->assertEquals(0, $membership->remaining_credits);
        // 30 of 60 minutes covered → ₱200 free, ₱200 charged.
        $this->assertEquals(200.0, (float) $booking->base_amount);
        $this->assertEquals(200.0, (float) $booking->total_amount);
    }

    public function test_credit_toggle_does_nothing_with_zero_balance(): void
    {
        $membership = $this->giveCustomerCredits(0);

        $booking = $this->bookingService->create([
            'court_id'     => $this->court->id,
            'booking_date' => now()->addDay()->toDateString(),
            'start_time'   => '15:00',
            'end_time'     => '16:00',
            'use_credit'   => true,
        ], $this->customer);

        $membership->refresh();
        $this->assertEquals(0, $membership->remaining_credits);
        $this->assertEquals(400.0, (float) $booking->base_amount);
        $this->assertEquals(400.0, (float) $booking->total_amount);
    }
}
