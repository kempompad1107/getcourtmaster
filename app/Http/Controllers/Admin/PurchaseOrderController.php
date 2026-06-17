<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $orders = PurchaseOrder::where('tenant_id', $this->authTenant()->id)
            ->with('supplier')
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->paginate(25);
        return view('admin.inventory.purchase-orders.index', compact('orders'));
    }

    public function create(): View
    {
        $tenantId = $this->authTenant()->id;
        $suppliers = Supplier::where('tenant_id', $tenantId)->where('is_active', true)->get();
        $products  = Product::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name')->get();
        return view('admin.inventory.purchase-orders.create', compact('suppliers', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id'        => ['required', 'exists:suppliers,id'],
            'expected_at'        => ['nullable', 'date'],
            'notes'              => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
            'items.*.unit_cost'  => ['required', 'numeric', 'min:0'],
        ]);

        $tenantId = $this->authTenant()->id;

        $po = DB::transaction(function () use ($data, $tenantId) {
            $subtotal = 0.0;
            $po = PurchaseOrder::create([
                'tenant_id'   => $tenantId,
                'supplier_id' => $data['supplier_id'],
                'created_by'  => $this->authUser()->id,
                'status'      => 'draft',
                'expected_at' => $data['expected_at'] ?? null,
                'notes'       => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $line = round($row['quantity'] * $row['unit_cost'], 2);
                $subtotal += $line;
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $row['product_id'],
                    'quantity_ordered'  => $row['quantity'],
                    'unit_cost'         => $row['unit_cost'],
                    'line_total'        => $line,
                ]);
            }

            $po->update([
                'subtotal' => $subtotal,
                'total'    => $subtotal,
            ]);

            return $po;
        });

        return redirect()->route('admin.purchase-orders.show', $po)
            ->with('success', 'PO created.');
    }

    public function show(PurchaseOrder $purchase_order): View
    {
        abort_unless($purchase_order->tenant_id === $this->authTenant()->id, 403);
        $purchase_order->load(['supplier', 'items.product']);
        return view('admin.inventory.purchase-orders.show', ['po' => $purchase_order]);
    }

    public function receive(Request $request, PurchaseOrder $purchase_order): RedirectResponse
    {
        abort_unless($purchase_order->tenant_id === $this->authTenant()->id, 403);

        $data = $request->validate([
            'received' => ['nullable', 'array'],
        ]);

        $this->inventory->receivePurchaseOrder($purchase_order, $data['received'] ?? [], $this->authUser());

        return back()->with('success', 'Stock received and recorded.');
    }
}
