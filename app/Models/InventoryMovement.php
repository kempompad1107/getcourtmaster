<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'product_id', 'tenant_id', 'branch_id', 'type',
        'quantity_before', 'quantity_change', 'quantity_after',
        'unit_cost', 'reference', 'notes', 'created_by',
    ];

    protected $casts = ['unit_cost' => 'decimal:2'];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
