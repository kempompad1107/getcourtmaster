<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Promotion;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $list = $this->bookings->forCustomer($request->user()->id);
        return view('customer.bookings.index', ['bookings' => $list]);
    }

    public function create(Request $request): View
    {
        $courts = Court::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->where('status', '!=', 'closed')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'status', 'base_hourly_rate', 'branch_id', 'min_booking_minutes', 'max_booking_minutes']);

        return view('customer.bookings.create', compact('courts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'court_id'       => ['required', 'exists:courts,id'],
            'booking_date'   => ['required', 'date', 'after_or_equal:today'],
            'start_time'     => ['required', 'date_format:H:i'],
            'end_time'       => ['required', 'date_format:H:i', 'after:start_time'],
            'payment_method' => ['required', 'in:wallet,court_credit,cash'],
            'promo_code'     => ['nullable', 'string', 'max:50'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        // Defence: the court must belong to this customer's tenant.
        $court = Court::where('id', $data['court_id'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->firstOrFail();

        $booking = $this->service->create(
            $data + ['type' => 'online'],
            $request->user()
        );

        $flash = match ($booking->payment_method) {
            'wallet'       => 'Paid from wallet. Booking #' . $booking->booking_number . ' is confirmed.',
            'court_credit' => 'Paid with court credit. Booking #' . $booking->booking_number . ' is confirmed.',
            'cash'         => 'Booking #' . $booking->booking_number . ' submitted. It is pending venue approval.',
            default        => 'Booking created. Booking #' . $booking->booking_number,
        };

        return redirect()->route('customer.bookings.show', $booking)->with('success', $flash);
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
