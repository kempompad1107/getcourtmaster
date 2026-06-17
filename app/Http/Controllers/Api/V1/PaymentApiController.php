<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class PaymentApiController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(Request $request)
    {
        $user = $this->authUser();

        $payments = Payment::where('customer_id', $user->id)
            ->with('payable')
            ->latest()
            ->paginate(20);

        return response()->json(['payments' => $payments]);
    }

    public function show(Payment $payment)
    {
        if ($payment->customer_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['payment' => $payment->load('payable')]);
    }

    /**
     * Customer-initiated online payment intents are disabled. Payments now
     * route through venue staff (cash) or are auto-settled from the customer's
     * pre-loaded wallet / court credit. This endpoint stays only to return a
     * 410 so older mobile clients fail clearly.
     */
    public function createIntent(Request $request)
    {
        return response()->json([
            'message' => 'Online payment intents are no longer supported. Bookings are paid using wallet balance, court credit, or cash at the venue.',
        ], 410);
    }

    public function refund(Request $request, Payment $payment)
    {
        // Refunds are a privileged, money-moving action — only venue staff may
        // issue them. A customer must NEVER be able to refund their own payment:
        // processRefund credits the wallet regardless of the original channel, so
        // a self-refund of a cash payment would mint spendable balance while the
        // booking stays active. Staff-only closes that hole (FIN/C-1).
        if (! $this->authUser()->hasRole(['business_owner', 'manager'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $this->paymentService->processRefund(
            $payment,
            (float) $payment->amount,
            $data['reason'] ?? 'Staff-issued refund'
        );

        return response()->json(['message' => 'Refund processed.', 'payment' => $payment->fresh()]);
    }
}
