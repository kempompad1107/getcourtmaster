<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderItem extends Model
{
    protected $table = 'pos_order_items';
    protected $fillable = ['order_id', 'product_id', 'name', 'sku', 'quantity', 'unit_price', 'discount', 'tax', 'subtotal'];

    protected $casts = ['unit_price' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'subtotal' => 'decimal:2'];

    public function order(): BelongsTo { return $this->belongsTo(PosOrder::class, 'order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
