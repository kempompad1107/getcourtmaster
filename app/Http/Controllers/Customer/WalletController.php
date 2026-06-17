<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Services\PaymentService;
use App\Services\Payments\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function index(Request $request, GatewayManager $gateways): View
    {
        $user   = $request->user();
        $tenant = $user->tenant;

        $query = WalletTransaction::where('user_id', $user->id)
            ->when($request->filled('type'),  fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('from'),  fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'),    fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));

        $transactions = (clone $query)->with('processedBy:id,name')->latest()->paginate(20)->withQueryString();

        // Lifetime stats — independent of the filter so the cards stay stable.
        $base = WalletTransaction::where('user_id', $user->id);
        $stats = [
            'total_credited'   => (float) (clone $base)->whereIn('type', ['credit', 'refund', 'reward'])->sum('amount'),
            'total_debited'    => (float) (clone $base)->where('type', 'debit')->sum('amount'),
            'top_ups_count'    => (int)   (clone $base)->where('type', 'credit')->count(),
            'last_activity'    => (clone $base)->latest()->value('created_at'),
        ];

        // Which online gateways has this venue configured? If none, self-serve
        // top-up is hidden and the existing "ask staff" notice stays.
        $availableGateways = $tenant ? $gateways->availableForTenant($tenant) : [];

        // Break out the PayMongo "umbrella" gateway into the individual methods
        // the venue has actually activated, so the customer can pick one directly
        // (skipping PayMongo's own method-picker step at checkout).
        $paymongoMethods = [];
        if (in_array('paymongo', $availableGateways)) {
            $paymongoMethods = $tenant->payment_credentials['paymongo']['methods']
                ?? ['gcash', 'paymaya', 'card', 'qrph'];
        }

        return view('customer.wallet.index', compact(
            'user', 'transactions', 'stats', 'availableGateways', 'paymongoMethods'
        ));
    }

    public function topup(Request $request, PaymentService $payments): RedirectResponse
    {
        $user   = $request->user();
        $tenant = $user->tenant;

        $data = $request->validate([
            'amount'  => 'required|numeric|min:50|max:50000',
            'gateway' => 'required|string|in:paymongo,stripe',
            'method'  => 'nullable|string|in:gcash,paymaya,card,qrph',
        ]);

        if (!$tenant?->hasGatewayConfigured($data['gateway'])) {
            return back()->with('error', 'That payment method is not available at this venue.');
        }

        // If the customer chose a specific PayMongo method, make sure the venue
        // has it enabled — otherwise reject before we hit PayMongo.
        if ($data['gateway'] === 'paymongo' && !empty($data['method'])) {
            $enabled = $tenant->payment_credentials['paymongo']['methods'] ?? [];
            if (!in_array($data['method'], $enabled, true)) {
                return back()->with('error', 'That payment method is not available at this venue.');
            }
        }

        $payment = Payment::create([
            'tenant_id'   => $tenant->id,
            'customer_id' => $user->id,
            // payable = the customer themselves so markPaid can credit their wallet.
            'payable_type' => $user::class,
            'payable_id'   => $user->id,
            'amount'      => $data['amount'],
            'currency'    => $tenant->currency ?: 'PHP',
            'method'      => 'wallet_topup',
            'status'      => 'pending',
        ]);

        $options = [
            'description' => 'Wallet top-up',
            'line_name'   => "Wallet top-up — {$tenant->name}",
            'success_url' => route('customer.wallet.topup.return', ['payment' => $payment->id, 'status' => 'success']),
            'cancel_url'  => route('customer.wallet.topup.return', ['payment' => $payment->id, 'status' => 'cancel']),
        ];

        // PayMongo: pin checkout to the single method the customer picked when
        // present, else fall back to the tenant's full enabled-method list.
        if ($data['gateway'] === 'paymongo') {
            if (!empty($data['method'])) {
                $options['methods'] = [$data['method']];
            } else {
                $methods = $tenant->payment_credentials['paymongo']['methods'] ?? null;
                if (!empty($methods)) {
                    $options['methods'] = $methods;
                }
            }
        }

        try {
            $result = $payments->processOnline($payment, $data['gateway'], $options);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'notes' => $e->getMessage()]);
            return back()->with('error', 'Could not start checkout: ' . $e->getMessage());
        }

        if (empty($result['checkout_url'])) {
            return back()->with('error', 'Gateway did not return a checkout URL.');
        }

        return redirect()->away($result['checkout_url']);
    }

    /**
     * After the gateway redirects the customer back, reconcile against the
     * gateway's API so the wallet is credited immediately — independent of
     * whether the webhook has fired yet. Then show a status banner.
     */
    public function topupReturn(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        if ($payment->customer_id !== $request->user()->id) {
            abort(404);
        }

        $status = $request->string('status')->toString();

        // On success-redirect, ask the gateway directly whether the payment
        // landed. This hides webhook delay so the wallet credits instantly
        // when the customer returns. Idempotent.
        if ($status === 'success' && !$payment->isPaid() && !$payment->isFailed()) {
            $payment = $payments->reconcile($payment);
        }

        $flash = match (true) {
            $payment->isPaid()       => ['success', 'Top-up successful! Your wallet has been credited.'],
            $status === 'cancel'     => ['warning', 'Top-up cancelled.'],
            $payment->isFailed()     => ['error',   'Top-up failed. Please try again.'],
            default                  => ['info',    'Top-up is being processed. Your balance will update shortly.'],
        };

        return redirect()->route('customer.wallet.index')->with($flash[0], $flash[1]);
    }
}
