<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Court;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Branch $branch;
    private User $cashier;
    private Product $product;
    private PosService $posService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->main()->create(['tenant_id' => $this->tenant->id]);

        $this->cashier = User::factory()->staff()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $category = ProductCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->product = Product::factory()->create([
            'tenant_id'      => $this->tenant->id,
            'branch_id'      => $this->branch->id,
            'category_id'    => $category->id,
            'stock_quantity' => 50,
            'selling_price'  => 150.00,
            'track_inventory' => true,
        ]);

        $this->posService = new PosService();
    }

    public function test_can_create_pos_order(): void
    {
        $order = $this->posService->createOrder([
            'branch_id' => $this->branch->id,
            'items' => [[
                'product_id' => $this->product->id,
                'name'       => $this->product->name,
                'quantity'   => 2,
                'unit_price' => 150.00,
            ]],
        ], $this->cashier);

        $this->assertNotNull($order);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals(300.00, (float) $order->subtotal);
        $this->assertEquals(300.00, (float) $order->total);
        $this->assertNotNull($order->order_number);
        $this->assertStringStartsWith('ORD-', $order->order_number);
    }

    public function test_order_deducts_inventory_on_creation(): void
    {
        $initialStock = $this->product->stock_quantity;

        $this->posService->createOrder([
            'branch_id' => $this->branch->id,
            'items' => [[
                'product_id' => $this->product->id,
                'name'       => $this->product->name,
                'quantity'   => 3,
            ]],
        ], $this->cashier);

        $this->product->refresh();
        $this->assertEquals($initialStock - 3, $this->product->stock_quantity);
    }

    public function test_can_process_payment_and_complete_order(): void
    {
        $order = $this->posService->createOrder([
            'branch_id' => $this->branch->id,
            'items' => [[
                'product_id' => $this->product->id,
                'name'       => $this->product->name,
                'quantity'   => 1,
                'unit_price' => 150.00,
            ]],
        ], $this->cashier);

        $this->posService->processPayment($order, [
            ['method' => 'cash', 'amount' => 150.00],
        ], 200.00);

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertEquals(200.00, (float) $order->amount_tendered);
        $this->assertEquals(50.00, (float) $order->change_amount);
        $this->assertCount(1, $order->posPayments);
    }

    public function test_can_process_split_payment(): void
    {
        $order = $this->posService->createOrder([
            'branch_id' => $this->branch->id,
            'items' => [[
                'product_id' => $this->product->id,
                'name'       => $this->product->name,
                'quantity'   => 2,
                'unit_price' => 150.00,
            ]],
        ], $this->cashier);

        $this->posService->processPayment($order, [
            ['method' => 'cash', 'amount' => 200.00],
            ['method' => 'gcash', 'amount' => 100.00],
        ], 300.00);

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertCount(2, $order->posPayments);
    }

    public function test_can_void_order_and_restore_inventory(): void
    {
        $initialStock = $this->product->stock_quantity;

        $order = $this->posService->createOrder([
            'branch_id' => $this->branch->id,
            'items' => [[
                'product_id' => $this->product->id,
                'name'       => $this->product->name,
                'quantity'   => 5,
            ]],
        ], $this->cashier);

        $this->product->refresh();
        $this->assertEquals($initialStock - 5, $this->product->stock_quantity);

        $this->actingAs($this->cashier);
        $this->posService->voidOrder($order, 'Customer changed mind');

        $order->refresh();
        $this->product->refresh();

        $this->assertEquals('voided', $order->status);
        $this->assertNotNull($order->voided_at);
        $this->assertEquals($initialStock, $this->product->stock_quantity);
    }

    public function test_order_applies_discount(): void
    {
        $order = $this->posService->createOrder([
            'branch_id'       => $this->branch->id,
            'discount_amount' => 50.00,
            'items' => [[
                'product_id' => $this->product->id,
                'name'       => $this->product->name,
                'quantity'   => 2,
                'unit_price' => 150.00,
            ]],
        ], $this->cashier);

        $this->assertEquals(300.00, (float) $order->subtotal);
        $this->assertEquals(50.00, (float) $order->discount_amount);
        $this->assertEquals(250.00, (float) $order->total);
    }

    public function test_pos_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/bookings');
        $response->assertStatus(401);
    }

    public function test_pos_blocks_overselling_a_tracked_product(): void
    {
        $lowStockProduct = Product::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'branch_id'       => $this->branch->id,
            'category_id'     => $this->product->category_id,
            'stock_quantity'  => 2,
            'selling_price'   => 100.00,
            'track_inventory' => true,
        ]);

        try {
            $this->posService->createOrder([
                'branch_id' => $this->branch->id,
                'items' => [[
                    'product_id' => $lowStockProduct->id,
                    'name'       => $lowStockProduct->name,
                    'quantity'   => 3,
                    'unit_price' => 100.00,
                ]],
            ], $this->cashier);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        $lowStockProduct->refresh();
        $this->assertEquals(2, $lowStockProduct->stock_quantity);
    }

    public function test_pos_blocks_overselling_across_duplicate_lines(): void
    {
        // Two cart lines for the SAME product must be summed before the stock
        // check — neither line exceeds stock alone, but together they do.
        $product = Product::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'branch_id'       => $this->branch->id,
            'category_id'     => $this->product->category_id,
            'stock_quantity'  => 2,
            'selling_price'   => 100.00,
            'track_inventory' => true,
        ]);

        try {
            $this->posService->createOrder([
                'branch_id' => $this->branch->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'name'       => $product->name,
                        'quantity'   => 1,
                        'unit_price' => 100.00,
                    ],
                    [
                        'product_id' => $product->id,
                        'name'       => $product->name,
                        'quantity'   => 2,
                        'unit_price' => 100.00,
                    ],
                ],
            ], $this->cashier);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('items', $e->errors());
        }

        $product->refresh();
        $this->assertEquals(2, $product->stock_quantity);
    }
}
