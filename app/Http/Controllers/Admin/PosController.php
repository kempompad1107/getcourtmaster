<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\CashDrawerService;
use App\Services\PosService;
use App\Services\Payments\GatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PosController extends Controller
{
    public function __construct(
        private readonly PosService $posService,
        private readonly CashDrawerService $drawer,
        private readonly BranchContext $branchContext,
    ) {}

    public function barcode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $product = $this->posService->lookupByBarcode($this->authTenant()->id, $data['code']);
        return response()->json([
            'product' => $product ? [
                'id'              => $product->id,
                'name'            => $product->name,
                'sku'             => $product->sku,
                'price'           => (float) $product->selling_price,
                'tax'             => (float) $product->tax_rate,
                'stock'           => (int) $product->stock_quantity,
                'track_inventory' => (bool) $product->track_inventory,
            ] : null,
        ]);
    }

    public function drawerSummary(): JsonResponse
    {
        $tenantId = $this->authTenant()->id;
        return response()->json([
            'balance' => $this->drawer->currentBalance($tenantId),
        ]);
    }

    public function drawerAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:open,close,in,out,adjust'],
            'amount' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);
        $this->drawer->record(
            $this->authUser(),
            $data['action'],
            (float) $data['amount'],
            $data['reason'] ?? null
        );
        return back()->with('success', "Cash drawer: {$data['action']}.");
    }

    public function thermalReceipt(PosOrder $order)
    {
        abort_unless($order->tenant_id === $this->authTenant()->id, 403);
        $order->load('items.product', 'posPayments', 'cashier', 'tenant');
        return view('admin.pos.thermal-receipt', compact('order'));
    }

    public function addPayment(Request $request, PosOrder $order)
    {
        abort_unless($order->tenant_id === $this->authTenant()->id, 403);
        $data = $request->validate([
            'payments'              => ['required', 'array', 'min:1'],
            'payments.*.method'     => ['required', 'in:cash,card,gcash,paymaya,maya,qrph,wallet,bank,stripe_card'],
            'payments.*.amount'     => ['required', 'numeric', 'min:0'],
            'payments.*.reference'  => ['nullable', 'string', 'max:120'],
            'amount_tendered'       => ['required', 'numeric', 'min:0'],
        ]);

        $order = $this->posService->processPayment(
            $order,
            $data['payments'],
            (float) $data['amount_tendered'],
            $this->authUser()
        );

        return redirect()->route('admin.pos.receipt', $order)
            ->with('success', $order->status === 'completed' ? 'Order fully paid.' : 'Partial payment recorded.');
    }


    public function index(GatewayManager $gateways)
    {
        $this->authorize('pos.access');
        $tenant = $this->authTenant();
        $tenantId = $tenant->id;

        $activeBranchId = $this->branchContext->current()
            ?? $tenant->branches()->where('is_main', true)->value('id')
            ?? $tenant->branches()->value('id');

        $categories = ProductCategory::where('tenant_id', $tenantId)->where('is_active', true)
            ->with('products')->orderBy('sort_order')->get();

        $recentOrders = PosOrder::where('tenant_id', $tenantId)->latest()->limit(5)->with('cashier')->get();

        $availableGateways = $gateways->availableForTenant($tenant);
        $paymongoMethods   = [];
        if (in_array('paymongo', $availableGateways)) {
            $paymongoMethods = $tenant->payment_credentials['paymongo']['methods'] ?? [];
        }
        $hasStripe = in_array('stripe', $availableGateways);

        return view('admin.pos.index', compact('categories', 'recentOrders', 'activeBranchId', 'paymongoMethods', 'hasStripe'));
    }

    public function store(Request $request)
    {
        $this->authorize('pos.access');
        $tenantId = $this->authTenant()->id;

        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.name'       => 'required|string',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'branch_id'          => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
            'payments'           => 'required|array|min:1',
            'amount_tendered'    => 'required|numeric|min:0',
            'promo_code'         => 'nullable|string|max:50',
            // A wallet tender resolves the customer through the global TenantScope,
            // but pin it down here too so a foreign / bogus id fails as a clean 422
            // rather than silently attaching to the order (L-2).
            'customer_id'        => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
            ],
        ]);

        // Re-validate the promo code server-side. The discount sent by the
        // client is never trusted — we recompute it from the authoritative
        // subtotal using the same Promotion::isValid()/calculateDiscount()
        // path the /promotions/validate preview endpoint uses, so the price
        // the cashier saw and the price we charge always agree.
        $payload = $request->all();
        $payload['discount_amount'] = 0;
        $appliedPromotion = null;

        if ($request->filled('promo_code')) {
            $subtotal = collect($request->input('items'))
                ->sum(fn ($i) => (float) ($i['unit_price'] ?? 0) * (int) ($i['quantity'] ?? 0));

            $promo = Promotion::where('tenant_id', $tenantId)
                ->where('code', strtoupper($request->promo_code))
                ->first();

            if (! $promo || ! $promo->isValid()) {
                return response()->json([
                    'message' => 'Invalid or expired promo code.',
                    'errors'  => ['promo_code' => ['Invalid or expired promo code.']],
                ], 422);
            }

            $payload['discount_amount'] = $promo->calculateDiscount($subtotal);
            $appliedPromotion = $promo;
        }

        try {
            $order = $this->posService->createOrder($payload, $this->authUser());
            $order = $this->posService->processPayment($order, $request->payments, (float) $request->amount_tendered);

            // Record the redemption only after the order + payment succeed, so
            // an abandoned/failed sale never burns a usage slot.
            if ($appliedPromotion && $payload['discount_amount'] > 0) {
                PromotionUsage::create([
                    'promotion_id'     => $appliedPromotion->id,
                    'customer_id'      => $order->customer_id ?? $this->authUser()->id,
                    'usable_type'      => $order->getMorphClass(),
                    'usable_id'        => $order->id,
                    'discount_applied' => $payload['discount_amount'],
                ]);
                $appliedPromotion->increment('used_count');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Expected business rejections (e.g. wallet tender without a customer
            // or with insufficient balance) are 422s, not server errors.
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Payment was rejected.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            // Log with full context so the failure shows up in laravel.log
            // instead of silently returning an HTML 500 to the JSON fetch.
            \Illuminate\Support\Facades\Log::error('POS order failed', [
                'tenant_id' => $tenantId,
                'cashier'   => $this->authUser()->id,
                'payload'   => $request->except('payments'),
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'POS order failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'order'       => $order->load('items'),
            'change'      => $order->change_amount,
            'receipt_url' => route('admin.pos.receipt', $order),
        ]);
    }

    public function receipt(PosOrder $order)
    {
        $order->load('items', 'cashier', 'customer', 'posPayments');
        return view('admin.pos.receipt', compact('order'));
    }

    public function void(Request $request, PosOrder $order)
    {
        $this->authorize('pos.void');
        $request->validate(['reason' => 'required|string']);
        $order = $this->posService->voidOrder($order, $request->reason);
        return response()->json(['order' => $order]);
    }

    public function history(Request $request)
    {
        $this->authorize('pos.access');
        $tenantId = $this->authTenant()->id;

        $orders = PosOrder::where('tenant_id', $tenantId)
            ->with('cashier', 'items')
            ->when($request->date, fn ($q, $v) => $q->whereDate('created_at', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()->paginate(25);

        return view('admin.pos.history', compact('orders'));
    }
}
