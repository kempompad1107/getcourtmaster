<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionInvoice;
use App\Services\BillingService;
use App\Services\Payments\GatewayManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PlatformWebhookController extends Controller
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly BillingService $billing,
    ) {}

    public function paymongo(Request $request): Response
    {
        return $this->dispatch($request, 'paymongo', 'Paymongo-Signature');
    }

    public function stripe(Request $request): Response
    {
        return $this->dispatch($request, 'stripe', 'Stripe-Signature');
    }

    private function dispatch(Request $request, string $gateway, string $signatureHeader): Response
    {
        try {
            $driver  = $this->gateways->platform($gateway);
            $payload = $driver->verifyWebhook($request->getContent(), (string) $request->header($signatureHeader, ''));
            $parsed  = $driver->parseWebhook($payload);
        } catch (\Throwable $e) {
            Log::warning("Platform {$gateway} webhook rejected: " . $e->getMessage());
            return response('', 400);
        }

        Log::info("Platform {$gateway} webhook received", $parsed);

        if (empty($parsed['reference']) || $parsed['status'] !== 'paid') {
            return response('', 200);
        }

        $invoice = SubscriptionInvoice::where('invoice_number', $parsed['reference'])
            ->where('status', '!=', 'paid')
            ->first();

        if ($invoice) {
            $this->billing->markPaid($invoice, [
                'payment_gateway'   => $gateway,
                'payment_reference' => $parsed['reference'],
            ]);
        }

        return response('', 200);
    }
}
