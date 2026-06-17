<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Court;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Services\Promotions\PromotionRuleEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionRuleEngineTest extends TestCase
{
    use RefreshDatabase;

    private PromotionRuleEngine $engine;
    private Tenant $tenant;
    private Court $court;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PromotionRuleEngine();
        $this->tenant = Tenant::create([
            'name' => 'T', 'slug' => 't', 'email' => 't@t.com',
            'plan' => 'pro', 'status' => 'active',
            'timezone' => 'Asia/Manila', 'currency' => 'PHP',
            'settings' => ['holidays' => ['2026-12-25']],
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
    }

    private function promo(array $attrs): Promotion
    {
        return Promotion::create(array_merge([
            'tenant_id'  => $this->tenant->id,
            'name'       => 'P',
            'code'       => 'P' . uniqid(),
            'type'       => 'percentage',
            'value'      => 10,
            'is_active'  => true,
            'used_count' => 0,
        ], $attrs));
    }

    public function test_percentage_discount_applies_inside_time_window(): void
    {
        $promo = $this->promo([
            'value'                => 20,
            'applicable_from_time' => '18:00:00',
            'applicable_to_time'   => '22:00:00',
        ]);
        $start = Carbon::parse('2026-06-01 19:00');
        $discount = $this->engine->discountForCourt($promo, $this->court, $start, $start->copy()->addHour(), 1000);

        $this->assertEquals(200.0, $discount);
    }

    public function test_outside_time_window_returns_zero(): void
    {
        $promo = $this->promo([
            'value'                => 20,
            'applicable_from_time' => '18:00:00',
            'applicable_to_time'   => '22:00:00',
        ]);
        $start = Carbon::parse('2026-06-01 10:00');
        $this->assertEquals(0.0, $this->engine->discountForCourt($promo, $this->court, $start, $start->copy()->addHour(), 1000));
    }

    public function test_day_of_week_filter(): void
    {
        // Only Saturdays (6)
        $promo = $this->promo(['applicable_days' => [6]]);
        $sat = Carbon::parse('2026-06-06 10:00'); // Saturday
        $tue = Carbon::parse('2026-06-02 10:00'); // Tuesday

        $this->assertGreaterThan(0, $this->engine->discountForCourt($promo, $this->court, $sat, $sat->copy()->addHour(), 500));
        $this->assertEquals(0.0,    $this->engine->discountForCourt($promo, $this->court, $tue, $tue->copy()->addHour(), 500));
    }

    public function test_holiday_promo_only_applies_on_listed_dates(): void
    {
        $promo = $this->promo(['name' => 'Christmas Holiday Special', 'code' => 'XMAS']);
        $promo->load('tenant');

        $xmas      = Carbon::parse('2026-12-25 10:00');
        $regular   = Carbon::parse('2026-12-10 10:00');

        $this->assertGreaterThan(0, $this->engine->discountForCourt($promo, $this->court, $xmas, $xmas->copy()->addHour(), 1000));
        $this->assertEquals(0.0,    $this->engine->discountForCourt($promo, $this->court, $regular, $regular->copy()->addHour(), 1000));
    }

    public function test_bundle_promo_gives_one_free_per_n(): void
    {
        $promo = $this->promo(['type' => 'bundle', 'value' => 3]);
        // Buy 7 at ₱100 each → floor(7/3) = 2 free → ₱200 off
        $this->assertEquals(200.0, $this->engine->bundleDiscount($promo, 7, 100));
        // Buy 2 → no free
        $this->assertEquals(0.0,   $this->engine->bundleDiscount($promo, 2, 100));
    }

    public function test_min_spend_blocks_discount(): void
    {
        $promo = $this->promo(['min_spend' => 1000]);
        $start = Carbon::parse('2026-06-01 10:00');
        $this->assertEquals(0.0, $this->engine->discountForCourt($promo, $this->court, $start, $start->copy()->addHour(), 500));
    }

    public function test_max_discount_caps_the_value(): void
    {
        $promo = $this->promo(['value' => 50, 'max_discount' => 100]);
        $start = Carbon::parse('2026-06-01 10:00');
        // 50% of 1000 = 500, capped at 100
        $this->assertEquals(100.0, $this->engine->discountForCourt($promo, $this->court, $start, $start->copy()->addHour(), 1000));
    }
}
