<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
