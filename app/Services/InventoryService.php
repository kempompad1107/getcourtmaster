<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function adjust(Product $product, int $delta, string $reason, ?User $by = null): Product
    {
        return DB::transaction(function () use ($product, $delta, $reason, $by) {
            $product->adjustStock(
                quantity: $delta,
                type: $delta > 0 ? 'in' : 'out',
                notes: $reason,
                userId: $by?->id,
            );

            return $product->fresh();
        });
    }

    public function receivePurchaseOrder(PurchaseOrder $po, array $receivedQuantities, ?User $by = null): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $receivedQuantities, $by) {
            foreach ($po->items as $item) {
                $received = (int) ($receivedQuantities[$item->id] ?? $item->quantity_ordered);
                if ($received <= 0) continue;

                $item->update(['quantity_received' => $item->quantity_received + $received]);

                if ($item->product) {
                    $this->adjust($item->product, $received, "PO {$po->po_number} received", $by);
                }
            }

            $po->update([
                'status'      => 'received',
                'received_at' => now()->toDateString(),
            ]);

            return $po->fresh(['items']);
        });
    }

    /** Products at or under threshold (and tracking inventory). */
    public function lowStockForTenant(int $tenantId)
    {
        return Product::where('tenant_id', $tenantId)
            ->where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->get();
    }
}
