<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourtRequest;
use App\Models\Branch;
use App\Models\Court;
use App\Services\AvailabilityService;
use App\Services\ImageOptimizer;
use App\Services\PlanLimitGuard;
use App\Services\PricingService;
use Illuminate\Http\Request;

class CourtController extends Controller
{
    public function __construct(
        private readonly PricingService $pricingService,
        private readonly PlanLimitGuard $planLimit,
        private readonly ImageOptimizer $optimizer,
        private readonly AvailabilityService $availability,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Court::class);
        $tenantId = $this->authTenant()->id;

        $courts = Court::where('tenant_id', $tenantId)
            ->with('branch', 'activeTimer')
            ->when($request->branch_id, fn ($q, $v) => $q->where('branch_id', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate(20);

        $branches = Branch::where('tenant_id', $tenantId)->active()->get();

        return view('admin.courts.index', compact('courts', 'branches'));
    }

    public function create()
    {
        $this->authorize('create', Court::class);
        $this->planLimit->assertCanAdd($this->authTenant(), 'courts');
        $branches = Branch::where('tenant_id', $this->authTenant()->id)->active()->get();
        return view('admin.courts.create', compact('branches'));
    }

    public function store(CourtRequest $request)
    {
        $this->authorize('create', Court::class);
        $this->planLimit->assertCanAdd($this->authTenant(), 'courts');
        $branchId = $this->requireActiveBranch('court');

        $data = collect($request->validated())->except('photos')->all();

        $court = Court::create(array_merge(
            $data,
            [
                'tenant_id' => $this->authTenant()->id,
                'branch_id' => $branchId,
            ]
        ));

        $this->syncPhotos($request, $court);

        activity()->on($court)->log('Court created');

        return redirect()->route('admin.courts.index')
            ->with('success', "Court '{$court->name}' created successfully.");
    }

    public function edit(Court $court)
    {
        $this->authorize('update', $court);
        $branches = Branch::where('tenant_id', $this->authTenant()->id)->active()->get();
        return view('admin.courts.edit', compact('court', 'branches'));
    }

    public function update(CourtRequest $request, Court $court)
    {
        $this->authorize('update', $court);
        $data = collect($request->validated())->except('photos')->all();
        $court->update($data);
        $this->syncPhotos($request, $court);
        activity()->on($court)->log('Court updated');

        return redirect()->route('admin.courts.index')
            ->with('success', "Court '{$court->name}' updated successfully.");
    }

    public function destroy(Court $court)
    {
        $this->authorize('delete', $court);
        $court->delete();
        return redirect()->route('admin.courts.index')
            ->with('success', 'Court removed.');
    }

    public function destroyMedia(Court $court, int $mediaId)
    {
        $this->authorize('update', $court);
        $media = $court->media()->where('collection_name', 'photos')->findOrFail($mediaId);
        $media->delete();

        return redirect()->route('admin.courts.edit', $court)
            ->with('success', 'Photo removed.');
    }

    private function syncPhotos(CourtRequest $request, Court $court): void
    {
        if (! $request->hasFile('photos')) {
            return;
        }

        // Run gym photos through the same central optimiser before Media
        // Library takes ownership of the file (resize + compress + strip EXIF).
        foreach ($request->file('photos') as $file) {
            $court->addMedia($this->optimizer->optimizedUpload($file))
                ->toMediaCollection('photos');
        }
    }

    public function statusBoard()
    {
        $tenantId = $this->authTenant()->id;
        $courts = Court::where('tenant_id', $tenantId)
            ->with('branch', 'activeTimer.booking.customer', 'nextBookingToday.customer')
            ->orderBy('name')->get();

        return view('admin.courts.status-board', compact('courts'));
    }

    /**
     * Lightweight JSON poll for the status board. Returns the current timer
     * state per court so cards can self-correct when BroadcastChannel messages
     * are missed (e.g., cross-browser, originating tab navigated away).
     */
    public function timerState(\App\Services\BookingService $bookingService)
    {
        $tenantId = $this->authTenant()->id;

        // Enforce auto-stop-after-grace on every poll. This makes the policy fire
        // within the 3s poll window while the live court board is open — no cron
        // or queue worker required — and guarantees the payload below already
        // reflects any session that just hit its grace boundary.
        $bookingService->autoStopExpiredTimers($tenantId);

        $courts = Court::where('tenant_id', $tenantId)
            ->with(['activeTimer'])
            ->get();

        $payload = [];
        foreach ($courts as $court) {
            $t = $court->activeTimer;
            $payload[$court->id] = [
                'status'             => $court->status,
                'has_timer'          => (bool) $t,
                'timer_id'           => $t?->id,
                'remaining_seconds'  => $t?->remaining_seconds ?? 0,
                'elapsed_seconds'    => $t?->elapsed_seconds_live ?? 0,
                'scheduled_end_ms'   => $t?->scheduled_end_at?->getTimestamp() * 1000,
            ];
        }

        return response()->json(['courts' => $payload, 'updated_at' => now()->toIso8601String()])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function updateStatus(Request $request, Court $court)
    {
        $this->authorize('updateStatus', $court);
        $request->validate(['status' => 'required|in:available,maintenance,closed']);
        $court->update(['status' => $request->status]);

        event(new \App\Events\CourtStatusChanged($court));

        if ($request->expectsJson()) {
            return response()->json(['status' => $court->status]);
        }

        return redirect()->back()
            ->with('success', "{$court->name} set to " . ucfirst($court->status) . '.');
    }

    public function availability(Request $request, Court $court)
    {
        // Cross-tenant guard (CROSS-TENANT-01): route-model binding resolves any
        // tenant's court (there is no global tenant scope), so confirm ownership
        // before exposing pricing + occupancy.
        abort_unless($court->tenant_id === $this->authTenant()->id, 404);

        $request->validate(['date' => 'required|date', 'duration' => 'required|integer|min:30']);
        $slots = $this->pricingService->getAvailableSlots($court, $request->date, $request->duration);

        return response()->json(['slots' => $slots]);
    }

    /** Day timeline for the staff schedule — detailed (customer/block identity). */
    public function timeline(Request $request, Court $court)
    {
        abort_unless($court->tenant_id === $this->authTenant()->id, 404);

        $request->validate(['date' => 'required|date']);

        return response()->json($this->availability->timeline($court, $request->date, detailed: true));
    }

    /** Availability verdict for an exact start + duration (staff scheduled booking). */
    public function check(Request $request, Court $court)
    {
        abort_unless($court->tenant_id === $this->authTenant()->id, 404);

        $data = $request->validate([
            'date'     => 'required|date',
            'start'    => 'required|date_format:H:i',
            'duration' => 'required|integer|min:15|max:480',
        ]);

        $end = \Carbon\Carbon::createFromFormat('H:i', $data['start'])
            ->addMinutes((int) $data['duration'])->format('H:i');

        return response()->json(
            $this->availability->evaluate($court, $data['date'], $data['start'], $end)
        );
    }
}
