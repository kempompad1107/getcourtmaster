<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Booking;
use Illuminate\Http\Request;

class DisplayController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $this->resolveTenant($request);

        // Customer names are PII. Only expose them to an authenticated staff session;
        // public slug access (an in-venue TV opened without login) sees court status
        // only, so a remote visitor can't harvest who is playing (M-2).
        $showNames = auth()->check();

        $courts = Court::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['activeBooking.timer', 'activeBooking.customer', 'nextBookingToday.customer'])
            ->orderBy('sort_order')
            ->get();

        return view('admin.display.index', compact('courts', 'tenant', 'showNames'));
    }

    public function data(Request $request)
    {
        $tenant = $this->resolveTenant($request);
        $showNames = auth()->check();

        $courts = Court::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['activeBooking.timer', 'activeBooking.customer'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn($court) => [
                'id'            => $court->id,
                'name'          => $court->name,
                'type'          => $court->type,
                'status'        => $court->status,
                'status_color'  => $court->status_color,
                'customer_name' => $showNames ? $court->activeBooking?->customer?->name : null,
                'elapsed'       => $court->activeBooking?->timer?->elapsed_seconds_live,
                'remaining'     => $court->activeBooking?->timer?->remaining_seconds,
                'is_overtime'   => $court->activeBooking?->timer?->isOvertime(),
            ]);

        return response()->json(['courts' => $courts, 'updated_at' => now()->toIso8601String()]);
    }

    private function resolveTenant(Request $request)
    {
        if (auth()->check()) {
            return $this->authTenant();
        }

        $slug = $request->query('tenant');
        if ($slug) {
            return \App\Models\Tenant::where('slug', $slug)->where('status', 'active')->firstOrFail();
        }

        abort(403);
    }
}
