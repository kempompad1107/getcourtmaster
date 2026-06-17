<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'customer_id', 'payable_type', 'payable_id',
        'payment_number', 'amount', 'fee', 'currency', 'method', 'status',
        'gateway', 'gateway_reference', 'gateway_checkout_url', 'gateway_response',
        'receipt_number', 'notes', 'paid_at', 'refunded_at',
        'refund_amount', 'refund_reference', 'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'method', 'amount', 'fee', 'currency',
                'gateway_reference', 'receipt_number',
                'paid_at', 'refunded_at', 'refund_amount', 'refund_reference',
                'processed_by', 'notes',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('payment');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function payable()
    {
        return $this->morphTo();
    }

    /**
     * Narrow a Payment query to a single branch.
     *
     *  - Payments whose payable (Booking, PosOrder) has branch_id = $branchId
     *    are included.
     *  - Tenant-wide payments (Memberships, wallet top-ups, anything whose
     *    payable is NOT branch-bound) are rolled up to the *main* branch,
     *    so per-branch totals still reconcile with the tenant-wide total
     *    when summed across every branch.
     *  - Pass null to skip the filter (whole tenant).
     */
    public function scopeForBranch($query, ?int $branchId)
    {
        if ($branchId === null) {
            return $query;
        }

        $branchBound  = [Booking::class, PosOrder::class];
        $isMainBranch = \App\Models\Branch::where('id', $branchId)
            ->where('is_main', true)
            ->exists();

        return $query->where(function ($q) use ($branchId, $branchBound, $isMainBranch) {
            $q->whereHasMorph(
                'payable',
                $branchBound,
                fn ($qq) => $qq->withoutGlobalScope(\App\Models\Scopes\BranchScope::class)
                               ->where('branch_id', $branchId)
            );

            if ($isMainBranch) {
                $q->orWhere(function ($qq) use ($branchBound) {
                    $qq->whereNotIn('payable_type', $branchBound)
                       ->orWhereNull('payable_type');
                });
            }
        });
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function scopeOfTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Payment $payment) {
            if (empty($payment->payment_number)) {
                // ULID over uniqid(): collision-safe under concurrency (the column
                // is uniquely indexed) and non-sequential. (M-4)
                $payment->payment_number = 'PAY-' . strtoupper((string) \Illuminate\Support\Str::ulid());
            }
        });
    }
}
