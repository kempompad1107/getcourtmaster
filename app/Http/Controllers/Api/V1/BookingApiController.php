<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Court;
use App\Services\BookingService;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingApiController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
        private readonly PricingService $pricingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $bookings = Booking::where('customer_id', $user->id)
            ->with('court', 'branch', 'addons', 'timer')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(20);

        return response()->json($bookings);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            // Tenant-scope the court so a cross-tenant id fails cleanly as a 422
            // here rather than as an opaque 404 deeper in the service (L-3).
            'court_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('courts', 'id')
                    ->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            // Let mobile clients choose how to pay, matching the web flow (L-1).
            // Falls back to use_credit/cash inside the service when omitted.
            'payment_method' => 'nullable|in:wallet,court_credit,cash',
            'promo_code' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
            'use_credit' => 'nullable|boolean',
        ]);

        $booking = $this->bookingService->create($data, $request->user());

        return response()->json([
            'booking' => $booking->load('court', 'addons'),
            'message' => 'Booking created successfully.',
        ], 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->authorize('view', $booking);
        return response()->json($booking->load('court', 'addons', 'timer', 'payments'));
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);
        $request->validate(['reason' => 'required|string']);

        // Mirror the web flow exactly (H-1): enforce the cancellation lead-time
        // window AND apply the venue's refund policy. Previously this called
        // cancel() with refund defaulting to false, so mobile cancellations
        // silently forfeited the refund and bypassed the window.
        $this->bookingService->customerCancel($booking, $request->reason);

        return response()->json(['message' => 'Booking cancelled.', 'booking' => $booking->fresh()]);
    }

    public function availability(Request $request): JsonResponse
    {
        $request->validate([
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date',
            'duration' => 'required|integer|min:30',
        ]);

        $court = Court::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($request->court_id);
        $slots = $this->pricingService->getAvailableSlots($court, $request->date, $request->duration);

        return response()->json(['slots' => $slots]);
    }

    public function checkConflict(Request $request): JsonResponse
    {
        $request->validate([
            'court_id' => 'required|exists:courts,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        // Verify the court belongs to the authenticated user's tenant.
        abort_unless(
            Court::where('id', $request->court_id)
                ->where('tenant_id', $request->user()->tenant_id)
                ->exists(),
            404
        );

        $available = $this->bookingService->checkAvailability(
            $request->court_id, $request->date,
            $request->start_time, $request->end_time
        );

        return response()->json(['available' => $available]);
    }
}
