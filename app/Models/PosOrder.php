<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PosOrder extends Model
{
    use HasFactory, LogsActivity, \App\Models\Concerns\BelongsToBranch, \App\Models\Concerns\BelongsToTenant;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'subtotal', 'discount_amount', 'tax_amount', 'total',
                'amount_tendered', 'change_amount', 'promo_code',
                'cashier_id', 'customer_id', 'voided_at', 'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('pos_order');
    }

    protected $table = 'pos_orders';

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'cashier_id',
        'order_number', 'status', 'subtotal', 'discount_amount',
        'tax_amount', 'total', 'amount_tendered', 'change_amount',
        'promo_code', 'notes', 'voided_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class, 'order_id');
    }

    public function posPayments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'order_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function scopeOfTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (PosOrder $order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . strtoupper(uniqid());
            }
        });
    }
}
