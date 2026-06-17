<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $courts = Court::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->with('branch')
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('sort_order')
            ->get();

        return response()->json(['courts' => $courts]);
    }

    public function show(Court $court): JsonResponse
    {
        $this->authorize('view', $court);

        return response()->json([
            'court' => $court->load('branch', 'pricingRules', 'activeTimer'),
        ]);
    }

    public function statusBoard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $courts = Court::where('tenant_id', $tenantId)
            ->with('activeTimer.booking.customer', 'branch')
            ->orderBy('name')
            ->get()
            ->map(fn (Court $court) => [
                'id' => $court->id,
                'name' => $court->name,
                'type' => $court->type,
                'status' => $court->status,
                'status_color' => $court->status_color,
                'branch' => $court->branch->name ?? null,
                'active_timer' => $court->activeTimer ? [
                    'elapsed' => $court->activeTimer->elapsed_seconds_live,
                    'remaining' => $court->activeTimer->remaining_seconds,
                    'is_overtime' => $court->activeTimer->isOvertime(),
                    'customer' => $court->activeTimer->booking->customer->name ?? null,
                ] : null,
            ]);

        return response()->json(['courts' => $courts]);
    }
}
