<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingTimer extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'court_id', 'status', 'started_at', 'paused_at',
        'resumed_at', 'stopped_at', 'scheduled_end_at', 'elapsed_seconds',
        'paused_seconds', 'extension_seconds', 'overtime_seconds',
        'grace_period_seconds', 'overtime_rate', 'overtime_charge', 'notes',
        'ending_soon_alert_acknowledged_at', 'ending_soon_alert_acknowledged_by',
        'overtime_alert_acknowledged_at', 'overtime_alert_acknowledged_by',
        'overtime_settlement', 'overtime_settled_at', 'overtime_settled_by',
        'overtime_breakdown',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'stopped_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'ending_soon_alert_acknowledged_at' => 'datetime',
        'overtime_alert_acknowledged_at' => 'datetime',
        'overtime_settled_at' => 'datetime',
        'overtime_breakdown' => 'array',
        'overtime_rate' => 'decimal:2',
        'overtime_charge' => 'decimal:2',
    ];

    public const ENDING_SOON_THRESHOLD_SECONDS = 300;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isOvertime(): bool
    {
        return $this->status === 'overtime';
    }

    /**
     * Effective elapsed seconds = wall-clock time since started_at minus all paused durations.
     * While the timer is paused, the value is frozen at the moment of pause.
     * Carbon 3 note: $a->diffInSeconds($b) returns ($b - $a) signed; use started_at→now for
     * positive elapsed (Carbon 2's absolute-by-default no longer applies in v3).
     */
    public function getElapsedSecondsLiveAttribute(): int
    {
        if (!$this->started_at) {
            return (int) $this->elapsed_seconds;
        }

        $endRef = match (true) {
            $this->status === 'paused' && $this->paused_at !== null => $this->paused_at,
            in_array($this->status, ['stopped', 'expired'], true)   => $this->stopped_at ?? now(),
            default                                                 => now(),
        };

        $total  = (int) $this->started_at->diffInSeconds($endRef);
        $paused = (int) ($this->paused_seconds ?? 0);

        return max(0, $total - $paused);
    }

    /**
     * Remaining seconds until scheduled_end_at. 0 once we're at or past the end.
     * Overtime (post-end) is tracked separately via overtime_seconds.
     */
    public function getRemainingSecondsAttribute(): int
    {
        if (!$this->scheduled_end_at) {
            return 0;
        }
        // signed=false → positive when scheduled_end is in the future, negative when past.
        return (int) max(0, now()->diffInSeconds($this->scheduled_end_at, false));
    }

    public function computeOvertimeCharge(): float
    {
        if ($this->overtime_seconds <= 0 || $this->overtime_rate <= 0) {
            return 0.0;
        }
        $overtimeHours = $this->overtime_seconds / 3600;
        return round($overtimeHours * $this->overtime_rate, 2);
    }

    /**
     * Whether the timer is currently inside the free grace period
     * (past scheduled end but not yet billable).
     */
    public function isInGracePeriod(): bool
    {
        if (!$this->scheduled_end_at || $this->status !== 'running') {
            return false;
        }
        $secondsPastEnd = (int) $this->scheduled_end_at->diffInSeconds(now(), false);
        return $secondsPastEnd > 0 && $secondsPastEnd <= (int) $this->grace_period_seconds;
    }

    public function getGraceSecondsRemainingAttribute(): int
    {
        if (!$this->isInGracePeriod()) {
            return 0;
        }
        $secondsPastEnd = (int) $this->scheduled_end_at->diffInSeconds(now(), false);
        return max(0, (int) $this->grace_period_seconds - $secondsPastEnd);
    }

    public function needsEndingSoonAlert(): bool
    {
        if ($this->ending_soon_alert_acknowledged_at !== null) {
            return false;
        }
        if ($this->status !== 'running' || !$this->scheduled_end_at) {
            return false;
        }
        $remaining = $this->remaining_seconds;
        return $remaining > 0 && $remaining <= self::ENDING_SOON_THRESHOLD_SECONDS;
    }

    public function needsOvertimeAlert(): bool
    {
        if ($this->overtime_alert_acknowledged_at !== null) {
            return false;
        }
        if (!$this->scheduled_end_at) {
            return false;
        }
        return now()->greaterThanOrEqualTo($this->scheduled_end_at)
            && in_array($this->status, ['running', 'overtime'], true);
    }
}
