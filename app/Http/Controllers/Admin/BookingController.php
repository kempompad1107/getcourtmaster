<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\BookingTimer;
use App\Models\Court;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookingService) {}

    public function index(Request $request)
    {
        $tenantId = $this->authTenant()->id;

        $bookings = Booking::where('tenant_id', $tenantId)
            ->with('customer', 'court', 'branch')
            ->when($request->date, fn ($q, $v) => $q->where('booking_date', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->court_id, fn ($q, $v) => $q->where('court_id', $v))
            ->when($request->search, function ($q, $v) {
                $q->whereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$v}%"))
                  ->orWhere('booking_number', 'like', "%{$v}%");
            })
            ->latest()->paginate(25);

        $courts = Court::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('admin.bookings.index', compact('bookings', 'courts'));
    }

    public function create()
    {
        $tenantId = $this->authTenant()->id;
        $courts = Court::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $customers = User::where('tenant_id', $tenantId)->where('user_type', 'customer')->get();
        return view('admin.bookings.create', compact('courts', 'customers'));
    }

    public function store(BookingRequest $request)
    {
        $tenantId = $this->authTenant()->id;
        $customer = $request->customer_id
            ? User::where('tenant_id', $tenantId)->where('user_type', 'customer')->findOrFail($request->customer_id)
            : null;
        $booking = $this->bookingService->create(
            $request->validated(),
            $customer,
            $this->authUser()
        );

        // Wallet/court_credit bookings are already settled and confirmed by
        // the service; only cash (and anything still pending) needs an
        // explicit confirm step here — unless the venue's require_payment
        // policy says an unpaid booking must wait until cash is collected.
        if ($booking->status === 'pending' && !$this->bookingService->paymentRequiredAndUnpaid($booking)) {
            $this->bookingService->confirm($booking);
        }

        $cashFlash = $booking->status === 'pending'
            ? "Booking #{$booking->booking_number} created. It stays pending until payment is collected."
            : "Booking #{$booking->booking_number} created and confirmed. Cash to be collected at the venue.";

        $flash = match ($booking->payment_method) {
            'wallet'       => "Booking #{$booking->booking_number} created. Paid ₱" . number_format($booking->total_amount, 2) . " from wallet.",
            'court_credit' => "Booking #{$booking->booking_number} created. Paid with court credit.",
            'cash'         => $cashFlash,
            default        => "Booking #{$booking->booking_number} created and confirmed!",
        };

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', $flash);
    }

    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);
        $booking->load('customer', 'court', 'addons', 'timer', 'payments');
        return view('admin.bookings.show', compact('booking'));
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorize('cancel', $booking);
        $request->validate(['reason' => 'required|string|max:255']);

        $refund        = $request->boolean('refund');
        $paidBefore    = (float) $booking->paid_amount;
        $paymentMethod = $booking->payment_method;

        // Admin cancellations bypass the refund-window policy — staff are
        // making an informed decision, so refund the full paid_amount.
        $this->bookingService->cancel($booking, $request->reason, $refund, bypassWindow: true);

        // Build a per-payment-method flash so staff know what just happened
        // (especially the cash "go to Refund Requests" follow-up).
        $msg = 'Booking cancelled.';
        if ($refund && $paidBefore > 0) {
            $msg = match ($paymentMethod) {
                'wallet'       => 'Booking cancelled. ₱' . number_format($paidBefore, 2) . ' credited back to the customer\'s wallet.',
                'court_credit' => 'Booking cancelled. Court-credit minutes restored to the customer\'s membership.',
                'cash'         => 'Booking cancelled. A pending cash refund request was created — settle it from Refund Requests.',
                default        => 'Booking cancelled.',
            };
        }

        return redirect()->back()->with('success', $msg);
    }

    public function collectCash(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);
        $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'reference' => 'nullable|string|max:100',
        ]);

        $payment = $this->bookingService->recordCashPayment(
            $booking,
            $this->authUser(),
            (float) $request->input('amount'),
            $request->input('reference')
        );

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Collected ₱' . number_format($payment->amount, 2) . ' cash. Receipt ' . $payment->payment_number . '.');
    }

    public function startTimer(Request $request, Booking $booking)
    {
        $this->authorize('manageTimer', $booking);
        $timer = $this->bookingService->startTimer($booking);

        if ($request->expectsJson()) {
            return response()->json([
                'timer' => $timer,
                'message' => 'Timer started.',
            ]);
        }

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', 'Timer started.');
    }

    public function pauseTimer(BookingTimer $timer)
    {
        $this->authorize('manageTimer', $timer->booking);
        $timer = $this->bookingService->pauseTimer($timer);
        return response()->json(['timer' => $timer]);
    }

    public function resumeTimer(BookingTimer $timer)
    {
        $this->authorize('manageTimer', $timer->booking);
        $timer = $this->bookingService->resumeTimer($timer);
        return response()->json(['timer' => $timer]);
    }

    public function extendTimer(Request $request, BookingTimer $timer)
    {
        $this->authorize('manageTimer', $timer->booking);
        $request->validate(['minutes' => 'required|integer|min:15|max:120']);
        $timer = $this->bookingService->extendTimer($timer, $request->minutes);
        return response()->json(['timer' => $timer]);
    }

    public function stopTimer(Request $request, BookingTimer $timer)
    {
        $this->authorize('manageTimer', $timer->booking);
        return $this->handleStopWithSettlement($request, $timer);
    }

    /**
     * Booking-bound timer endpoints (called from the booking show page where only
     * the booking id is in the URL). Resolves the booking's current timer and
     * delegates to the timer-bound handlers above.
     */
    public function extendBookingTimer(Request $request, Booking $booking)
    {
        $this->authorize('manageTimer', $booking);
        // The default validation enforces 15-120, but the UI's +30 button needs to
        // pass. Accepting min:1 so admins can extend by any reasonable amount.
        $data = $request->validate(['minutes' => 'required|integer|min:1|max:240']);

        $timer = $booking->timer()->whereIn('status', ['running', 'paused'])->latest('id')->first();
        if (!$timer) {
            return response()->json(['message' => 'No active timer for this booking.'], 422);
        }

        $timer = $this->bookingService->extendTimer($timer, $data['minutes']);

        return response()->json([
            'message' => "Timer extended by {$data['minutes']} min.",
            'timer'   => $timer,
        ]);
    }

    /**
     * Authoritative live state for a single booking's timer, polled by the
     * booking detail page. Running the auto-stop check here means an open page
     * stops its own session at grace expiry — no cron, queue worker, or
     * websocket required. Returns whether the session is still active so the
     * page can freeze the timer and refresh once it has been stopped.
     */
    public function bookingTimerState(Booking $booking)
    {
        $this->authorize('manageTimer', $booking);

        $timer = $booking->timer()->whereIn('status', ['running', 'paused', 'overtime'])->latest('id')->first();

        if ($timer) {
            // Enforce auto-stop-after-grace for this session, then re-read.
            if ($this->bookingService->maybeAutoStop($timer)) {
                $timer = $timer->fresh();
            }
        }

        $active = $timer && in_array($timer->status, ['running', 'paused', 'overtime'], true);

        return response()->json([
            'active'            => $active,
            'status'            => $timer?->status ?? 'stopped',
            'remaining_seconds' => $active ? $timer->remaining_seconds : 0,
            'elapsed_seconds'   => $timer ? $timer->elapsed_seconds_live : 0,
            'scheduled_end_ms'  => $timer?->scheduled_end_at?->getTimestamp() * 1000,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function stopBookingTimer(Request $request, Booking $booking)
    {
        $this->authorize('manageTimer', $booking);

        $timer = $booking->timer()->whereIn('status', ['running', 'paused', 'overtime'])->latest('id')->first();
        if (!$timer) {
            return response()->json(['message' => 'No active timer for this booking.'], 422);
        }

        return $this->handleStopWithSettlement($request, $timer);
    }

    /**
     * Two-phase stop:
     *   - First call (no `settlement`): if overtime is owed past the grace
     *     period, return 200 with `requires_settlement: true` and the per-tier
     *     breakdown so the frontend can render the Pay / Void modal. Nothing
     *     is mutated yet.
     *   - Second call (`settlement=collect|void`): finalize the stop with the
     *     chosen outcome. The settlement is recorded on the timer for audit.
     *
     * When there's no overtime owed, the first call just stops normally with
     * `settlement=auto` — no modal needed.
     */
    private function handleStopWithSettlement(Request $request, BookingTimer $timer)
    {
        $settlement = $request->input('settlement');

        if ($settlement && !in_array($settlement, ['collect', 'void'], true)) {
            return response()->json(['message' => 'Invalid settlement.'], 422);
        }

        if (!$settlement) {
            $preview = $this->bookingService->previewOvertimeAtStop($timer);

            // Overtime owed — bounce back so the frontend can show the modal.
            if (($preview['charge'] ?? 0) > 0) {
                return response()->json([
                    'requires_settlement' => true,
                    'overtime'            => $preview,
                    'timer_id'            => $timer->id,
                    'booking_number'      => $timer->booking?->booking_number,
                    'court_name'          => $timer->booking?->court?->name,
                ]);
            }

            // No overtime — stop normally.
            $timer = $this->bookingService->stopTimer($timer, 'auto');

            return response()->json([
                'message'         => 'Session stopped.',
                'timer'           => $timer,
                'overtime_charge' => $timer->overtime_charge,
            ]);
        }

        $timer = $this->bookingService->stopTimer($timer, $settlement, $request->user());

        return response()->json([
            'message'         => $settlement === 'collect'
                ? 'Overtime collected. Session closed.'
                : 'Overtime voided. Session closed.',
            'timer'           => $timer,
            'overtime_charge' => $timer->overtime_charge,
            'settlement'      => $timer->overtime_settlement,
        ]);
    }

    public function confirm(Booking $booking)
    {
        $this->authorize('update', $booking);
        $this->bookingService->confirm($booking);

        return redirect()->back()->with('success', 'Booking confirmed.');
    }

    /**
     * Approve a customer's pending cash booking. Available only to owner/staff
     * and only when the booking is awaiting manual cash approval.
     */
    public function approve(Booking $booking)
    {
        $this->authorize('update', $booking);
        $this->bookingService->approveCashBooking($booking, $this->authUser());

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Booking approved. The customer has been notified.');
    }

    /**
     * Deny a customer's pending cash booking. A denial note is required and
     * forwarded to the customer.
     */
    public function deny(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'denial_note' => 'required|string|max:500',
        ]);

        $this->bookingService->denyCashBooking($booking, $this->authUser(), $data['denial_note']);

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Booking denied. The customer has been notified with your note.');
    }

    public function reschedule(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $data = $request->validate([
            'booking_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time'   => ['required', 'date_format:H:i'],
            'end_time'     => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $this->bookingService->reschedule($booking, $data['booking_date'], $data['start_time'], $data['end_time']);

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Booking rescheduled.');
    }

    public function walkIn(Request $request)
    {
        $tenantId = $this->authTenant()->id;

        $data = $request->validate([
            'court_id'         => ['required', 'exists:courts,id'],
            'customer_id'      => ['nullable', 'exists:users,id'],
            'duration_minutes' => ['nullable', 'integer', 'in:30,60,90,120'],
            'mode'             => ['nullable', 'in:auto,cap,bump'],
            'notes'            => ['nullable', 'string', 'max:500'],
            'use_credit'       => ['nullable', 'boolean'],
            'payment_method'   => ['nullable', 'in:wallet,court_credit,cash'],
            'promo_code'       => ['nullable', 'string', 'max:50'],
        ]);

        $customer = !empty($data['customer_id'])
            ? User::where('tenant_id', $tenantId)->find($data['customer_id'])
            : null;

        $booking = $this->bookingService->walkIn(
            $data,
            $customer,
            $this->authUser(),
            $data['mode'] ?? 'auto'
        );

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', 'Walk-in started. Timer running.');
    }

    public function walkInPreview(Request $request)
    {
        $tenantId = $this->authTenant()->id;
        $data = $request->validate([
            'court_id'         => ['required', 'exists:courts,id'],
            'duration_minutes' => ['required', 'integer', 'in:30,60,90,120'],
        ]);

        $court = Court::where('tenant_id', $tenantId)->findOrFail($data['court_id']);

        return response()->json(
            $this->bookingService->previewWalkIn($court, (int) $data['duration_minutes'])
        );
    }

    public function receipt(Booking $booking)
    {
        $this->authorize('view', $booking);
        $booking->load('customer', 'court.tenant', 'addons', 'payments');

        return view('admin.bookings.receipt', compact('booking'));
    }

    public function calendar(Request $request)
    {
        $tenantId = $this->authTenant()->id;
        $courts = Court::where('tenant_id', $tenantId)->with('branch')->get();

        return view('admin.bookings.calendar', compact('courts'));
    }

    public function calendarData(Request $request)
    {
        $tenantId = $this->authTenant()->id;
        $request->validate(['start' => 'required|date', 'end' => 'required|date']);

        $bookings = Booking::where('tenant_id', $tenantId)
            ->whereBetween('booking_date', [$request->start, $request->end])
            ->with('customer', 'court')
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'title' => $b->customer->name . ' — ' . $b->court->name,
                'start' => $b->booking_date->format('Y-m-d') . 'T' . $b->start_time->format('H:i:s'),
                'end' => $b->booking_date->format('Y-m-d') . 'T' . $b->end_time->format('H:i:s'),
                'color' => match ($b->status) {
                    'confirmed' => '#10b981',
                    'active' => '#3b82f6',
                    'pending' => '#f59e0b',
                    'cancelled' => '#ef4444',
                    default => '#6b7280',
                },
                'status' => $b->status,
            ]);

        return response()->json($bookings);
    }
}
