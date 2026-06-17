<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionUsage extends Model
{
    protected $fillable = ['promotion_id', 'customer_id', 'usable_type', 'usable_id', 'discount_applied'];
    protected $casts = ['discount_applied' => 'decimal:2'];

    public function promotion(): BelongsTo { return $this->belongsTo(Promotion::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function usable() { return $this->morphTo(); }
}
