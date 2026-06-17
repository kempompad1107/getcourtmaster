<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    protected $fillable = [
        'tenant_id', 'subscription_id', 'invoice_number', 'amount', 'tax', 'total',
        'status', 'payment_gateway', 'payment_reference', 'paid_at', 'due_at',
        'failed_attempts', 'next_retry_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2',
        'paid_at' => 'datetime', 'due_at' => 'datetime', 'next_retry_at' => 'datetime',
    ];

    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isOverdue(): bool { return $this->status !== 'paid' && $this->due_at && $this->due_at->isPast(); }

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function subscription(): BelongsTo { return $this->belongsTo(TenantSubscription::class); }
}
