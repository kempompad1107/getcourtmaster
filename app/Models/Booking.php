<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, \App\Models\Concerns\BelongsToBranch, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'court_id', 'customer_id', 'created_by',
        'booking_number', 'type', 'status', 'booking_date', 'start_time',
        'end_time', 'duration_minutes', 'base_amount', 'addon_amount',
        'discount_amount', 'tax_amount', 'total_amount', 'paid_amount',
        'payment_method',
        'approval_status', 'approved_by', 'approved_at',
        'denied_by', 'denied_at', 'denial_note',
        'promo_code', 'notes', 'qr_code', 'reminder_sent', 'start_reminder_sent',
        'checked_in_at', 'checked_out_at', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'base_amount' => 'decimal:2',
        'addon_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'reminder_sent' => 'boolean',
        'start_reminder_sent' => 'boolean',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'approved_at' => 'datetime',
        'denied_at' => 'datetime',
    ];

    public const PAYMENT_METHODS = ['wallet', 'court_credit', 'cash', 'online'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'status', 'approval_status',
                'approved_by', 'approved_at',
                'denied_by', 'denied_at', 'denial_note',
                'paid_amount', 'total_amount', 'discount_amount', 'payment_method',
                'booking_date', 'start_time', 'end_time',
                'court_id', 'customer_id', 'promo_code',
                'checked_in_at', 'checked_out_at',
                'cancelled_at', 'cancellation_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('booking');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function court(): BelongsTo
    {
        // Include soft-deleted courts: a booking always references a court that
        // was valid at creation, and removing a court must not orphan the
        // display of its historical bookings (index, show, calendar, reports).
        return $this->belongsTo(Court::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deniedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'denied_by');
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isDenied(): bool
    {
        return $this->status === 'denied' || $this->approval_status === 'denied';
    }

    public function getQrCodeImageAttribute(): ?string
    {
        if (empty($this->booking_number)) {
            return null;
        }

        return app(\App\Services\QrCodeService::class)->generate($this->booking_number);
    }

    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'wallet'       => 'Wallet Balance',
            'court_credit' => 'Court Credit',
            'cash'         => 'Cash',
            default        => '—',
        };
    }

    /**
     * Quick cash-payment status used by the booking show + list views.
     * Returns null for non-cash bookings so the caller can skip rendering.
     *   ['label' => 'Cash paid',    'color' => 'success']
     *   ['label' => 'Cash partial', 'color' => 'warning']
     *   ['label' => 'Cash unpaid',  'color' => 'danger']
     */
    public function cashPaymentBadge(): ?array
    {
        if ($this->payment_method !== 'cash') {
            return null;
        }

        $paid  = (float) $this->paid_amount;
        $total = (float) $this->total_amount;

        if ($paid <= 0) {
            return ['label' => 'Cash unpaid', 'color' => 'danger'];
        }
        if ($paid + 0.005 < $total) {
            return ['label' => 'Cash partial', 'color' => 'warning'];
        }
        return ['label' => 'Cash paid', 'color' => 'success'];
    }

    public function addons(): HasMany
    {
        return $this->hasMany(BookingAddon::class);
    }

    public function timer(): HasOne
    {
        return $this->hasOne(BookingTimer::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function latestRefundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class)->latestOfMany();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getRemainingBalance(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function scopeOfTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('booking_date', '>=', today())
            ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('booking_date', $date);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Booking $booking) {
            if (empty($booking->booking_number)) {
                // ULID, not uniqid(): uniqid() is microsecond-based (can collide
                // across concurrent processes, violating the unique index) and
                // sequential (so the number — which is also the QR payload — is
                // enumerable). ULIDs are collision-safe and carry 80 bits of
                // randomness, so they're not guessable. (M-4)
                $booking->booking_number = 'BK-' . strtoupper((string) \Illuminate\Support\Str::ulid());
            }
        });
    }
}
