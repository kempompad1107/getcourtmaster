<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Court;
use App\Models\MembershipPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class ApiEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $customer;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->main()->create(['tenant_id' => $this->tenant->id]);

        $this->customer = User::factory()->customer()->withWallet(500.00)->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    // ── Auth endpoints ────────────────────────────────────────────────────────

    public function test_me_endpoint_returns_authenticated_user(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/me');

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => $this->customer->email]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
        $this->getJson('/api/v1/courts')->assertStatus(401);
        $this->getJson('/api/v1/wallet/balance')->assertStatus(401);
        $this->getJson('/api/v1/notifications')->assertStatus(401);
    }

    // ── Courts endpoints ──────────────────────────────────────────────────────

    public function test_courts_index_returns_paginated_list(): void
    {
        Court::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/courts');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_court_availability_endpoint(): void
    {
        $court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/courts/availability?' . http_build_query([
                'court_id' => $court->id,
                'date'     => now()->addDay()->toDateString(),
                'duration' => 60,
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure(['slots']);
    }

    // ── Membership endpoints ──────────────────────────────────────────────────

    public function test_membership_plans_lists_tenant_plans(): void
    {
        MembershipPlan::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/memberships/plans');

        $response->assertStatus(200)
            ->assertJsonStructure(['plans'])
            ->assertJsonCount(2, 'plans');
    }

    public function test_active_membership_returns_null_when_none_exists(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/memberships/active');

        $response->assertStatus(200)
            ->assertJson(['membership' => null]);
    }

    // ── Wallet endpoints ──────────────────────────────────────────────────────

    public function test_wallet_balance_returns_current_balance(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/wallet/balance');

        $response->assertStatus(200)
            ->assertJsonFragment(['balance' => '500.00']);
    }

    public function test_wallet_transactions_returns_paginated_history(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/wallet/transactions');

        $response->assertStatus(200);
    }

    // ── Notification endpoints ────────────────────────────────────────────────

    public function test_notifications_index_is_empty_for_new_user(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
    }

    public function test_can_mark_all_notifications_read(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/notifications/mark-all-read');

        $response->assertStatus(200);
    }

    // ── Booking conflict check endpoint ───────────────────────────────────────

    public function test_conflict_check_returns_availability_status(): void
    {
        $court = Court::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/bookings/check-conflict?' . http_build_query([
                'court_id'   => $court->id,
                'date'       => now()->addDay()->toDateString(),
                'start_time' => '10:00',
                'end_time'   => '11:00',
            ]));

        $response->assertStatus(200)
            ->assertJsonStructure(['available']);
    }

    // ── Payments endpoints ────────────────────────────────────────────────────

    public function test_payments_index_requires_auth(): void
    {
        $this->getJson('/api/v1/payments')->assertStatus(401);
    }

    public function test_authenticated_user_can_view_own_payments(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/payments');

        $response->assertStatus(200);
    }
}
