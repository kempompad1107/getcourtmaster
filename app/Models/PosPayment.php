<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosPayment extends Model
{
    protected $table = 'pos_payments';
    protected $fillable = ['order_id', 'method', 'amount', 'reference'];
    protected $casts = ['amount' => 'decimal:2'];

    public function order(): BelongsTo { return $this->belongsTo(PosOrder::class, 'order_id'); }
}
