<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\BelongsToBranch, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'category_id', 'name', 'sku', 'barcode',
        'description', 'cost_price', 'selling_price', 'tax_rate',
        'stock_quantity', 'low_stock_threshold', 'track_inventory', 'is_active', 'image',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'track_inventory' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= 0;
    }

    public function adjustStock(int $quantity, string $type, ?string $notes = null, ?string $reference = null, ?int $userId = null): void
    {
        $before = $this->stock_quantity;
        $after = $before + $quantity;

        $this->increment('stock_quantity', $quantity);

        InventoryMovement::create([
            'product_id'      => $this->id,
            'tenant_id'       => $this->tenant_id,
            'branch_id'       => $this->branch_id,
            'type'            => $type,
            'quantity_before' => $before,
            'quantity_change' => $quantity,
            'quantity_after'  => $after,
            'notes'           => $notes,
            'reference'       => $reference,
            'created_by'      => $userId ?? auth()->id(),
        ]);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->where('track_inventory', true)
            ->whereRaw('stock_quantity <= low_stock_threshold');
    }
}
