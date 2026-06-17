<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_paymongo_rejects_bad_signature(): void
    {
        $gateway = new PayMongoGateway('sk_test_xxx', 'whsec_test_secret');
        $payload = json_encode(['data' => ['attributes' => ['type' => 'payment.paid']]]);

        $this->expectException(\RuntimeException::class);
        $gateway->verifyWebhook($payload, 't=1700000000,li=invalid_sig');
    }

    public function test_paymongo_accepts_valid_signature(): void
    {
        $secret  = 'whsec_test_secret';
        $gateway = new PayMongoGateway('sk_test_xxx', $secret);
        $timestamp = (string) time();
        $body = json_encode(['data' => ['attributes' => ['type' => 'payment.paid', 'data' => ['id' => 'evt_1', 'attributes' => ['amount' => 50000]]]]]);
        $sig = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $header = "t={$timestamp},te=,li={$sig}";

        $payload = $gateway->verifyWebhook($body, $header);
        $parsed  = $gateway->parseWebhook($payload);

        $this->assertEquals('paid', $parsed['status']);
        $this->assertEquals('evt_1', $parsed['reference']);
        $this->assertEquals(500.00, $parsed['amount']);
    }

    public function test_stripe_rejects_bad_signature(): void
    {
        $gateway = new StripeGateway('sk_test_xxx', 'whsec_test_secret');
        $payload = json_encode(['type' => 'checkout.session.completed']);

        $this->expectException(\RuntimeException::class);
        $gateway->verifyWebhook($payload, 't=1700000000,v1=invalid_sig');
    }

    public function test_stripe_accepts_valid_signature(): void
    {
        $secret  = 'whsec_test_secret';
        $gateway = new StripeGateway('sk_test_xxx', $secret);
        $timestamp = (string) time();
        $body = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_1', 'amount_total' => 10000]]]);
        $sig = hash_hmac('sha256', $timestamp . '.' . $body, $secret);
        $header = "t={$timestamp},v1={$sig}";

        $payload = $gateway->verifyWebhook($body, $header);
        $parsed  = $gateway->parseWebhook($payload);

        $this->assertEquals('paid', $parsed['status']);
        $this->assertEquals('cs_1', $parsed['reference']);
        $this->assertEquals(100.00, $parsed['amount']);
    }

    public function test_paymongo_webhook_route_marks_payment_paid(): void
    {
        $tenant = Tenant::create([
            'name' => 'T', 'slug' => 't', 'email' => 't@t.com',
            'plan' => 'pro', 'status' => 'active',
            'timezone' => 'Asia/Manila', 'currency' => 'PHP',
        ]);
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'M', 'slug' => 'm', 'is_main' => true, 'is_active' => true]);
        $court  = Court::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'C', 'type' => 'indoor', 'status' => 'available', 'base_hourly_rate' => 400, 'min_booking_minutes' => 60, 'max_booking_minutes' => 240, 'buffer_minutes' => 0, 'capacity' => 4, 'is_active' => true]);
        $user = User::create(['name' => 'X', 'email' => 'x@x.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'user_type' => 'customer', 'is_active' => true]);
        $booking = Booking::create([
            'tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'court_id' => $court->id,
            'customer_id' => $user->id, 'type' => 'online', 'status' => 'pending',
            'booking_date' => today()->toDateString(), 'start_time' => '10:00', 'end_time' => '11:00',
            'duration_minutes' => 60, 'base_amount' => 400, 'tax_amount' => 0, 'total_amount' => 400,
        ]);
        $payment = Payment::create([
            'tenant_id' => $tenant->id, 'customer_id' => $user->id,
            'payable_type' => Booking::class, 'payable_id' => $booking->id,
            'amount' => 400, 'status' => 'pending',
            'gateway' => 'paymongo', 'gateway_reference' => 'evt_test_42',
        ]);

        config(['services.paymongo.webhook_secret' => 'whsec_test_secret']);

        $timestamp = (string) time();
        $body = json_encode([
            'data' => ['attributes' => [
                'type' => 'payment.paid',
                'data' => ['id' => 'evt_test_42', 'attributes' => ['amount' => 40000]],
            ]],
        ]);
        $sig = hash_hmac('sha256', $timestamp . '.' . $body, 'whsec_test_secret');

        $resp = $this->call(
            'POST', '/api/v1/webhooks/paymongo',
            [], [], [], [
                'CONTENT_TYPE'             => 'application/json',
                'HTTP_PAYMONGO-SIGNATURE'  => "t={$timestamp},te=,li={$sig}",
            ],
            $body,
        );

        $resp->assertStatus(200);
        $this->assertEquals('paid', $payment->fresh()->status);
    }
}
