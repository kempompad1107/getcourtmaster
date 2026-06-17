<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MembershipService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $customer;
    private MembershipPlan $plan;
    private MembershipService $membershipService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->pro()->create();

        $this->customer = User::factory()->customer()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // court_credits stored as MINUTES (10 hours = 600).
        $this->plan = MembershipPlan::factory()->monthly()->create([
            'tenant_id'     => $this->tenant->id,
            'price'         => 999.00,
            'court_credits' => 600,
        ]);

        $this->membershipService = new MembershipService();
    }

    public function test_can_subscribe_to_membership_plan(): void
    {
        $membership = $this->membershipService->subscribe($this->customer, $this->plan);

        $this->assertNotNull($membership);
        $this->assertEquals('active', $membership->status);
        $this->assertEquals($this->customer->id, $membership->customer_id);
        $this->assertEquals($this->plan->id, $membership->plan_id);
        $this->assertEquals(600, $membership->remaining_credits);
        $this->assertTrue($membership->expires_at->isFuture());
        $this->assertNotNull($membership->membership_number);
    }

    public function test_subscribe_creates_transaction_record(): void
    {
        $membership = $this->membershipService->subscribe($this->customer, $this->plan);

        $this->assertCount(1, $membership->transactions);
        $this->assertEquals('purchase', $membership->transactions->first()->type);
        $this->assertEquals(999.00, (float) $membership->transactions->first()->amount);
    }

    public function test_can_renew_expired_membership(): void
    {
        $membership = Membership::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'customer_id'       => $this->customer->id,
            'plan_id'           => $this->plan->id,
            'status'            => 'expired',
            'expires_at'        => now()->subDay(),
            'remaining_credits' => 0,
            'auto_renew'        => true,
        ]);

        $renewed = $this->membershipService->renew($membership);

        $this->assertEquals('active', $renewed->status);
        $this->assertTrue($renewed->expires_at->isFuture());
        $this->assertEquals(600, $renewed->remaining_credits);
    }

    public function test_renewing_active_membership_extends_expiry(): void
    {
        $membership = $this->membershipService->subscribe($this->customer, $this->plan);
        $originalExpiry = $membership->expires_at->copy();

        $renewed = $this->membershipService->renew($membership);

        $this->assertTrue($renewed->expires_at->isAfter($originalExpiry));
    }

    public function test_can_freeze_membership(): void
    {
        $membership = $this->membershipService->subscribe($this->customer, $this->plan);
        $until = Carbon::now()->addDays(7);

        $frozen = $this->membershipService->freeze($membership, $until);

        $this->assertEquals('frozen', $frozen->status);
        $this->assertNotNull($frozen->frozen_at);
        $this->assertTrue($frozen->frozen_until->equalTo($until));
    }

    public function test_can_cancel_membership(): void
    {
        $membership = $this->membershipService->subscribe($this->customer, $this->plan);

        $cancelled = $this->membershipService->cancel($membership);

        $this->assertEquals('cancelled', $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertFalse($cancelled->auto_renew);
    }

    public function test_can_use_credit(): void
    {
        $membership = $this->membershipService->subscribe($this->customer, $this->plan);
        $initial = $membership->remaining_credits;

        $result = $this->membershipService->useCredit($membership, 2);

        $this->assertTrue($result);
        $membership->refresh();
        $this->assertEquals($initial - 2, $membership->remaining_credits);
    }

    public function test_cannot_use_more_credits_than_available(): void
    {
        $membership = Membership::factory()->create([
            'tenant_id'         => $this->tenant->id,
            'customer_id'       => $this->customer->id,
            'plan_id'           => $this->plan->id,
            'status'            => 'active',
            'remaining_credits' => 1,
            'expires_at'        => now()->addDays(30),
        ]);

        $result = $this->membershipService->useCredit($membership, 5);

        $this->assertFalse($result);
        $membership->refresh();
        $this->assertEquals(1, $membership->remaining_credits);
    }

    public function test_process_expired_marks_expired_memberships(): void
    {
        Membership::factory()->count(3)->create([
            'tenant_id'   => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'plan_id'     => $this->plan->id,
            'status'      => 'active',
            'expires_at'  => now()->subDay(),
        ]);

        $count = $this->membershipService->processExpired();

        $this->assertEquals(3, $count);
        $this->assertEquals(
            3,
            Membership::where('status', 'expired')->count()
        );
    }

    public function test_membership_api_plans_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/memberships/plans');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_view_membership_plans(): void
    {
        MembershipPlan::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/memberships/plans');

        $response->assertStatus(200)
            ->assertJsonStructure(['plans']);
    }

    public function test_authenticated_user_can_view_own_memberships(): void
    {
        $this->membershipService->subscribe($this->customer, $this->plan);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/memberships');

        $response->assertStatus(200);
    }
}
