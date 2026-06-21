<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PromotionController extends Controller
{
    public function index()
    {
        $tenant = $this->authTenant();

        $promotions = Promotion::where('tenant_id', $tenant->id)
            ->withCount('usages')
            ->when(request('status') === 'active',   fn($q) => $q->where('is_active', true))
            ->when(request('status') === 'inactive', fn($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(20);

        return view('admin.promotions.index', compact('promotions'));
    }

    public function create()
    {
        return view('admin.promotions.create');
    }

    public function store(Request $request)
    {
        $tenant = $this->authTenant();

        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'code'              => 'required|string|max:50|unique:promotions,code',
            'type'              => 'required|in:percentage,fixed',
            'value'             => 'required|numeric|min:0',
            'min_booking_amount'=> 'nullable|numeric|min:0',
            'max_discount_amount'=> 'nullable|numeric|min:0',
            'max_uses'          => 'nullable|integer|min:1',
            'max_uses_per_user' => 'nullable|integer|min:1',
            'starts_at'         => 'nullable|date',
            'expires_at'        => 'nullable|date|after_or_equal:starts_at',
            'is_active'         => 'boolean',
            'applies_to'        => 'nullable|in:all,courts,memberships,pos',
            'description'       => 'nullable|string|max:500',
        ]);

        Promotion::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'code'      => strtoupper($data['code']),
        ]));

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion created.');
    }

    public function edit(Promotion $promotion)
    {
        $tenant = $this->authTenant();
        abort_if($promotion->tenant_id !== $tenant->id, 403);

        return view('admin.promotions.edit', compact('promotion'));
    }

    public function update(Request $request, Promotion $promotion)
    {
        $tenant = $this->authTenant();
        abort_if($promotion->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'type'               => 'required|in:percentage,fixed',
            'value'              => 'required|numeric|min:0',
            'min_booking_amount' => 'nullable|numeric|min:0',
            'max_discount_amount'=> 'nullable|numeric|min:0',
            'max_uses'           => 'nullable|integer|min:1',
            'max_uses_per_user'  => 'nullable|integer|min:1',
            'starts_at'          => 'nullable|date',
            'expires_at'         => 'nullable|date|after_or_equal:starts_at',
            'is_active'          => 'boolean',
            'applies_to'         => 'nullable|in:all,courts,memberships,pos',
            'description'        => 'nullable|string|max:500',
        ]);

        $promotion->update($data);

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion updated.');
    }

    public function destroy(Promotion $promotion)
    {
        $tenant = $this->authTenant();
        abort_if($promotion->tenant_id !== $tenant->id, 403);

        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion deleted.');
    }

    public function validate(Request $request)
    {
        $data = $request->validate([
            'code'   => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $tenant = $this->authTenant();
        $promo = Promotion::where('tenant_id', $tenant->id)
            ->where('code', strtoupper($data['code']))
            ->first();

        if (! $promo || ! $promo->isValid($this->authUser())) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired promo code.'], 422);
        }

        $discount = $promo->calculateDiscount($data['amount']);

        return response()->json([
            'valid'    => true,
            'discount' => $discount,
            'final'    => max(0, $data['amount'] - $discount),
            'promo'    => ['code' => $promo->code, 'type' => $promo->type, 'value' => $promo->value],
        ]);
    }
}
