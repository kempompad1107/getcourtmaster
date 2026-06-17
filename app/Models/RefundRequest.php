<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RefundRequest extends Model
{
    use LogsActivity, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'booking_id', 'customer_id',
        'method', 'amount', 'status',
        'reason', 'denial_note', 'reference',
        'processed_by', 'processed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'reference', 'denial_note', 'processed_by', 'processed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('refund_request');
    }

    public function tenant(): BelongsTo   { return $this->belongsTo(Tenant::class); }
    public function booking(): BelongsTo  { return $this->belongsTo(Booking::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function processedBy(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }
}
