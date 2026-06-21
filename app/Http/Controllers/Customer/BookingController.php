<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Promotion;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\Payments\GatewayManager;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly BookingService $service,
        private readonly PricingService $pricingService,
        private readonly AvailabilityService $availability,
    ) {}

    public function index(Request $request): View
    {
        $tab = $request->get('tab', 'upcoming');
        $statuses = match($tab) {
            'upcoming'  => ['pending', 'confirmed', 'active'],
            'past'      => ['completed'],
            'cancelled' => ['cancelled', 'denied'],
            default     => [],
        };
        $list = $this->bookings->forCustomer($request->user()->id, $statuses);
        return view('customer.bookings.index', ['bookings' => $list, 'tab' => $tab]);
    }

    public function create(Request $request, GatewayManager $gateways): View
    {
        $user   = $request->user();
        $tenant = $user->tenant;

        $courts = Court::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', '!=', 'closed')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'status', 'base_hourly_rate', 'branch_id', 'min_booking_minutes', 'max_booking_minutes']);

        $availableGateways = $tenant ? $gateways->availableForTenant($tenant) : [];
        $paymongoMethods   = [];
        if (in_array('paymongo', $availableGateways)) {
            $paymongoMethods = $tenant->payment_credentials['paymongo']['methods']
                ?? ['gcash', 'paymaya', 'card', 'qrph'];
        }
        $requirePayment = (bool) ($tenant?->getSetting('require_payment', false));

        return view('customer.bookings.create', compact(
            'courts', 'availableGateways', 'paymongoMethods', 'requirePayment'
        ));
    }

    public function store(Request $request, PaymentService $payments, GatewayManager $gateways): RedirectResponse
    {
        $data = $request->validate([
            'court_id'       => ['required', 'exists:courts,id'],
            'booking_date'   => ['required', 'date', 'after_or_equal:today'],
            'start_time'     => ['required', 'date_format:H:i'],
            'end_time'       => ['required', 'date_format:H:i', 'after:start_time'],
            'payment_method' => ['required', 'in:wallet,court_credit,cash,online'],
            'gateway'        => ['nullable', 'string', 'in:paymongo,stripe'],
            'gateway_method' => ['nullable', 'string', 'in:gcash,paymaya,card,qrph'],
            'promo_code'     => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        // Defence: the court must belong to this customer's tenant.
        $court = Court::where('id', $data['court_id'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        // Validate the chosen gateway is actually configured for this tenant.
        if ($data['payment_method'] === 'online') {
            $gateway = $data['gateway'] ?? null;
            if (!$gateway || !$request->user()->tenant->hasGatewayConfigured($gateway)) {
                return back()->with('error', 'The selected payment gateway is not available. Please choose another payment method.');
            }
            if ($gateway === 'paymongo' && !empty($data['gateway_method'])) {
                $enabledMethods = $request->user()->tenant->payment_credentials['paymongo']['methods'] ?? [];
                if (!in_array($data['gateway_method'], $enabledMethods, true)) {
                    return back()->with('error', 'That payment method is not available at this venue.');
                }
            }
        }

        $booking = $this->service->create(
            $data + ['type' => 'online'],
            $request->user()
        );

        // Online payment: create a pending Payment record and redirect to the gateway.
        if ($data['payment_method'] === 'online') {
            $gateway       = $data['gateway'];
            $gatewayMethod = $data['gateway_method'] ?? null;
            $tenant        = $request->user()->tenant;

            $payment = Payment::create([
                'tenant_id'    => $booking->tenant_id,
                'customer_id'  => $booking->customer_id,
                'payable_type' => \App\Models\Booking::class,
                'payable_id'   => $booking->id,
                'payment_number' => 'PAY-' . strtoupper((string) Str::ulid()),
                'amount'       => $booking->total_amount,
                'currency'     => $tenant->currency ?: 'PHP',
                'method'       => 'online',
                'status'       => 'pending',
            ]);

            $options = [
                'description' => "Booking #{$booking->booking_number} — {$tenant->name}",
                'line_name'   => "Court booking #{$booking->booking_number}",
                'success_url' => route('customer.bookings.payment.return', ['booking' => $booking->id, 'status' => 'success']),
                'cancel_url'  => route('customer.bookings.payment.return', ['booking' => $booking->id, 'status' => 'cancel']),
                'reference'   => (string) $payment->id,
            ];

            if ($gateway === 'paymongo') {
                $options['methods'] = $gatewayMethod
                    ? [$gatewayMethod]
                    : ($tenant->payment_credentials['paymongo']['methods'] ?? null);
            }

            try {
                $result = $payments->processOnline($payment, $gateway, $options);
            } catch (\Throwable $e) {
                $payment->update(['status' => 'failed', 'notes' => $e->getMessage()]);
                $this->service->cancel($booking, 'Payment could not be initiated.');
                return back()->with('error', 'Could not start checkout: ' . $e->getMessage());
            }

            if (empty($result['checkout_url'])) {
                $this->service->cancel($booking, 'Gateway did not return a checkout URL.');
                return back()->with('error', 'Gateway did not return a checkout URL. Please try again.');
            }

            return redirect()->away($result['checkout_url']);
        }

        $flash = match ($booking->payment_method) {
            'wallet'       => 'Paid from wallet. Booking #' . $booking->booking_number . ' is confirmed.',
            'court_credit' => 'Paid with court credit. Booking #' . $booking->booking_number . ' is confirmed.',
            'cash'         => 'Booking #' . $booking->booking_number . ' submitted. It is pending venue approval.',
            default        => 'Booking created. Booking #' . $booking->booking_number,
        };

        return redirect()->route('customer.bookings.show', $booking)->with('success', $flash);
    }

    public function paymentReturn(Request $request, $booking, PaymentService $payments): RedirectResponse
    {
        $booking = $this->bookings->findOrFail($booking, ['payments']);
        abort_unless($booking->customer_id === $request->user()->id, 403);

        $status  = $request->string('status')->toString();
        $payment = $booking->payments()->where('status', 'pending')->latest()->first();

        // On success redirect, ask the gateway directly so the booking confirms
        // instantly — independent of whether the webhook has already fired.
        if ($status === 'success' && $payment && !$payment->isPaid() && !$payment->isFailed()) {
            $payment = $payments->reconcile($payment);
        }

        if ($payment?->isPaid()) {
            $this->service->confirm($booking);
            return redirect()->route('customer.bookings.show', $booking)
                ->with('success', 'Payment successful! Booking #' . $booking->booking_number . ' is confirmed.');
        }

        if ($status === 'cancel') {
            if ($payment) {
                $payment->update(['status' => 'failed']);
            }
            $this->service->cancel($booking, 'Payment cancelled by customer.');
            return redirect()->route('customer.bookings.index')
                ->with('warning', 'Booking cancelled — payment was not completed.');
        }

        return redirect()->route('customer.bookings.show', $booking)
            ->with('info', 'Payment is being processed. Your booking will be confirmed shortly.');
    }

    public function show(Request $request, $booking): View
    {
        $booking = $this->bookings->findOrFail($booking, ['court', 'timer', 'payments', 'latestRefundRequest']);
        abort_unless($booking->customer_id === $request->user()->id, 403);
        return view('customer.bookings.show', compact('booking'));
    }

    public function cancel(Request $request, $booking): RedirectResponse
    {
        $booking = $this->bookings->findOrFail($booking);
        abort_unless($booking->customer_id === $request->user()->id, 403);

        $reason = $request->input('reason', 'Cancelled by customer');

        // Lead-time window + refund policy live in the service so web and API
        // behave identically (H-1). Surface the window message as a friendly
        // flash instead of a raw validation error.
        try {
            $this->service->customerCancel($booking, $reason);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $flash = match ($booking->payment_method) {
            'wallet'       => 'Booking cancelled. Any eligible refund has been credited back to your wallet.',
            'court_credit' => 'Booking cancelled. The court-credit minutes you used have been restored to your membership.',
            'cash'         => 'Booking cancelled. A cash refund request has been opened — please claim it at the venue.',
            default        => 'Booking cancelled.',
        };

        return back()->with('success', $flash);
    }

    /* ─────────────────────────────────────────────────────────────────────
     | JSON endpoints used by the create form
     ───────────────────────────────────────────────────────────────────── */

    /** Available time slots for a court on a given date — mirrors the admin endpoint. */
    public function availability(Request $request, Court $court): JsonResponse
    {
        // Cross-tenant safety: a customer can only check courts within their tenant.
        abort_unless($court->tenant_id === $request->user()->tenant_id, 404);

        $request->validate([
            'date'     => 'required|date|after_or_equal:today',
            'duration' => 'required|integer|min:30|max:240',
        ]);

        $slots = $this->pricingService->getAvailableSlots($court, $request->date, (int) $request->duration);

        return response()->json(['slots' => $slots]);
    }

    /** Day timeline (operating window + busy segments) for the visual schedule. */
    public function timeline(Request $request, Court $court): JsonResponse
    {
        abort_unless($court->tenant_id === $request->user()->tenant_id, 404);

        $request->validate(['date' => 'required|date|after_or_equal:today']);

        // Customers see generic "Booked" labels (no other customers' identities).
        return response()->json($this->availability->timeline($court, $request->date, detailed: false));
    }

    /** Verdict for an exact start + duration: available?, conflict, suggestions, pricing. */
    public function check(Request $request, Court $court): JsonResponse
    {
        abort_unless($court->tenant_id === $request->user()->tenant_id, 404);

        $data = $request->validate([
            'date'     => 'required|date|after_or_equal:today',
            'start'    => 'required|date_format:H:i',
            'duration' => 'required|integer|min:15|max:480',
        ]);

        $end = \Carbon\Carbon::createFromFormat('H:i', $data['start'])
            ->addMinutes((int) $data['duration'])->format('H:i');

        return response()->json(
            $this->availability->evaluate($court, $data['date'], $data['start'], $end)
        );
    }

    /** Validate a promo code against an amount — mirrors the admin endpoint. */
    public function validatePromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'   => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $promo = Promotion::where('tenant_id', $request->user()->tenant_id)
            ->where('code', strtoupper($data['code']))
            ->first();

        if (! $promo || ! $promo->isValid($request->user())) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired promo code.'], 422);
        }

        $discount = $promo->calculateDiscount($data['amount']);

        return response()->json([
            'valid'    => true,
            'discount' => $discount,
            'final'    => max(0, $data['amount'] - $discount),
        ]);
    }
}
