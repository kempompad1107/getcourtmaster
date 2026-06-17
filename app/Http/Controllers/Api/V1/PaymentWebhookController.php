<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function paymongo(Request $request, string $token): Response
    {
        return $this->dispatch($request, $token, 'paymongo', 'Paymongo-Signature');
    }

    public function stripe(Request $request, string $token): Response
    {
        return $this->dispatch($request, $token, 'stripe', 'Stripe-Signature');
    }

    private function dispatch(Request $request, string $token, string $gateway, string $signatureHeader): Response
    {
        $tenant = Tenant::where('webhook_token', $token)->first();
        if (!$tenant) {
            // 404 + no body — do not leak the existence of webhook tokens.
            return response('', 404);
        }

        $signature = (string) $request->header($signatureHeader, '');
        $rawBody   = $request->getContent();

        try {
            $this->paymentService->handleWebhook($tenant, $gateway, $rawBody, $signature);
        } catch (\Throwable $e) {
            Log::error("{$gateway} webhook error (tenant {$tenant->id}): " . $e->getMessage());
            return response('', 500);
        }

        return response('', 200);
    }
}
