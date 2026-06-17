<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'supplier_id', 'created_by', 'po_number', 'status',
        'subtotal', 'tax_amount', 'total', 'expected_at', 'received_at', 'notes',
    ];

    protected $casts = [
        'subtotal'    => 'decimal:2',
        'tax_amount'  => 'decimal:2',
        'total'       => 'decimal:2',
        'expected_at' => 'date',
        'received_at' => 'date',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'supplier_id',
                'subtotal', 'tax_amount', 'total',
                'expected_at', 'received_at', 'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('purchase_order');
    }

    public function tenant(): BelongsTo   { return $this->belongsTo(Tenant::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany      { return $this->hasMany(PurchaseOrderItem::class); }

    protected static function boot()
    {
        parent::boot();
        static::creating(function (PurchaseOrder $po) {
            if (empty($po->po_number)) {
                $po->po_number = 'PO-' . strtoupper(uniqid());
            }
        });
    }
}
