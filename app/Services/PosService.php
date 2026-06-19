<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    public function __construct(private readonly WalletService $wallet) {}

    public function createOrder(array $data, User $cashier): PosOrder
    {
        return DB::transaction(function () use ($data, $cashier) {
            $items = $data['items'];
            $subtotal = 0;
            $taxTotal = 0;
            $lockedProducts = [];
            $reservedQty    = [];

            foreach ($items as $idx => &$item) {
                $product = ! empty($item['product_id'])
                    ? Product::where('id', $item['product_id'])->lockForUpdate()->first()
                    : null;

                // Block overselling of inventory-tracked products (defense in depth —
                // the POS UI also guards this, but two cashiers racing the last unit, or
                // any client bypass, must still fail cleanly here).
                // Accumulate reserved qty per product so duplicate cart lines can't
                // collectively exceed stock.
                if ($product && $product->track_inventory) {
                    $reserved = ($reservedQty[$product->id] ?? 0) + $item['quantity'];
                    if ($reserved > $product->stock_quantity) {
                        throw ValidationException::withMessages([
                            'items' => "Only {$product->stock_quantity} of {$product->name} in stock.",
                        ]);
                    }
                    $reservedQty[$product->id] = $reserved;
                }

                $lockedProducts[$idx] = $product;

                $unitPrice = $item['unit_price'] ?? ($product?->selling_price ?? 0);
                $quantity  = $item['quantity'];
                $taxRate   = $product?->tax_rate ?? 0;
                $itemTax   = $unitPrice * $quantity * ($taxRate / 100);
                $item['unit_price'] = $unitPrice;
                $item['tax']        = $itemTax;
                $item['subtotal']   = ($unitPrice * $quantity) + $itemTax;
                $subtotal += $unitPrice * $quantity;
                $taxTotal += $itemTax;
            }
            unset($item);

            $discountAmount = (float) ($data['discount_amount'] ?? 0);
            $total = $subtotal + $taxTotal - $discountAmount;

            $order = PosOrder::create([
                'tenant_id' => $cashier->tenant_id,
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'cashier_id' => $cashier->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxTotal,
                'total' => $total,
                'promo_code' => $data['promo_code'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($items as $idx => $item) {
                PosOrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'name'       => $item['name'],
                    'sku'        => $item['sku'] ?? null,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount'   => 0,
                    'tax'        => $item['tax'],
                    'subtotal'   => $item['subtotal'],
                ]);

                // Deduct inventory using the already-locked product from the pricing loop.
                // Product::adjustStock signature is (quantity, type, notes, reference, userId)
                // — pass cashier id as userId, not as notes.
                $product = $lockedProducts[$idx] ?? null;
                if ($product && $product->track_inventory) {
                    $product->adjustStock(
                        -$item['quantity'],
                        'sale',
                        null,
                        $order->order_number,
                        $cashier->id
                    );
                }
            }

            return $order;
        });
    }

    /**
     * Apply multiple payment lines to an order. Supports split + partial:
     *  - `cash` lines roll into the cash drawer via CashDrawerService.
     *  - `wallet` lines actually DEBIT the customer's wallet (balance-checked,
     *    row-locked, ledgered) — never just recorded. A wallet tender without a
     *    selected customer, or with insufficient balance, aborts the whole sale.
     *  - `card`, `gcash`, `maya`, `bank` lines persist as PosPayment only.
     *  - If totalPaid < order->total → status remains `pending` (partial).
     *  - If totalPaid >= order->total → status becomes `completed`.
     */
    public function processPayment(PosOrder $order, array $payments, float $amountTendered, ?User $cashier = null): PosOrder
    {
        return DB::transaction(function () use ($order, $payments, $amountTendered, $cashier) {
            $cashier = $cashier ?? $order->cashier;
            $cashIn  = 0.0;

            foreach ($payments as $payment) {
                $method = $payment['method'] ?? 'cash';
                $amount = (float) ($payment['amount'] ?? 0);
                if ($amount <= 0) continue;

                // Wallet tenders must move real balance, not just be recorded —
                // otherwise a "paid by wallet" sale dispenses inventory and books
                // revenue while the customer is never charged (FIN). debit() locks
                // the wallet row, enforces sufficient balance, and writes the
                // ledger entry; a shortfall throws and rolls back the sale.
                if ($method === 'wallet') {
                    $customer = $order->customer;
                    if (! $customer) {
                        throw ValidationException::withMessages([
                            'payments' => 'Wallet payment requires a selected customer.',
                        ]);
                    }
                    try {
                        $this->wallet->debit(
                            $customer,
                            $amount,
                            "POS sale {$order->order_number}",
                            $order,
                            $cashier,
                        );
                    } catch (\RuntimeException $e) {
                        throw ValidationException::withMessages([
                            'payments' => 'Insufficient wallet balance for this sale.',
                        ]);
                    }
                }

                PosPayment::create([
                    'order_id'  => $order->id,
                    'method'    => $method,
                    'amount'    => $amount,
                    'reference' => $payment['reference'] ?? null,
                ]);

                if ($method === 'cash') $cashIn += $amount;
            }

            $alreadyPaid = (float) $order->posPayments()->sum('amount');
            $balance = max(0, $order->total - $alreadyPaid);
            $change = max(0, $amountTendered - $balance);

            $order->update([
                'status'          => $alreadyPaid >= $order->total ? 'completed' : 'pending',
                'amount_tendered' => ($order->amount_tendered ?? 0) + $amountTendered,
                'change_amount'   => $change,
            ]);

            if ($cashIn > 0 && $cashier) {
                app(CashDrawerService::class)->record(
                    $cashier,
                    'in',
                    $cashIn,
                    "POS sale {$order->order_number}",
                    $order->branch_id
                );
            }

            return $order->fresh(['posPayments']);
        });
    }

    /**
     * Look up a product by barcode for the current tenant (USB-HID handheld scanners
     * just type the scanned digits and an Enter — frontend POSTs here).
     */
    public function lookupByBarcode(int $tenantId, string $barcode): ?Product
    {
        return Product::where('tenant_id', $tenantId)
            ->where(function ($q) use ($barcode) {
                $q->where('barcode', $barcode)->orWhere('sku', $barcode);
            })
            ->where('is_active', true)
            ->first();
    }

    public function voidOrder(PosOrder $order, string $reason): PosOrder
    {
        return DB::transaction(function () use ($order, $reason) {
            // Idempotency: never void (and re-refund / re-restock) an order twice.
            if ($order->status === 'voided') {
                return $order;
            }

            $order->update(['status' => 'voided', 'voided_at' => now(), 'notes' => $reason]);

            // Refund any wallet tenders back to the customer — they were real
            // debits (see processPayment), so a void must return that balance or
            // the customer is left short. Other methods settle out-of-band.
            $walletPaid = (float) $order->posPayments()->where('method', 'wallet')->sum('amount');
            if ($walletPaid > 0 && $order->customer) {
                $this->wallet->credit(
                    $order->customer,
                    $walletPaid,
                    "Void refund — POS sale {$order->order_number}",
                    $order,
                );
            }

            // Restore inventory
            foreach ($order->items as $item) {
                if ($item->product_id) {
                    $item->product?->adjustStock($item->quantity, 'return', auth()->id(), $order->order_number);
                }
            }

            return $order;
        });
    }
}
