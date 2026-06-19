<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToBranch, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'branch_id', 'staff_id', 'shift_date',
        'scheduled_start', 'scheduled_end', 'clocked_in_at',
        'clocked_out_at', 'status', 'notes',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'clocked_in_at' => 'datetime',
        'clocked_out_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function getDurationMinutesAttribute(): int
    {
        if (!$this->clocked_in_at || !$this->clocked_out_at) {
            return 0;
        }
        return $this->clocked_in_at->diffInMinutes($this->clocked_out_at);
    }

    /**
     * The absolute instant this shift is scheduled to end, built from the
     * shift's date + scheduled_end time. Scheduled shifts validate end > start
     * (same day); ad-hoc clock-ins store scheduled_end as clock-in + 8h, which
     * can land past midnight — so if the computed end falls at/before the
     * clock-in, it belongs to the next day.
     */
    public function scheduledEndAt(): ?Carbon
    {
        if (!$this->shift_date || !$this->scheduled_end) {
            return null;
        }

        $end = Carbon::parse($this->shift_date->toDateString() . ' ' . $this->scheduled_end);

        if ($this->clocked_in_at && $end->lessThanOrEqualTo($this->clocked_in_at)) {
            $end->addDay();
        }

        return $end;
    }
}
