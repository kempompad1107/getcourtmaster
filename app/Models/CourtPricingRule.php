<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtPricingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'court_id', 'name', 'type', 'applicable_days', 'start_time',
        'end_time', 'date_from', 'date_to', 'rate', 'rate_type', 'priority', 'is_active',
    ];

    protected $casts = [
        'applicable_days' => 'array',
        'date_from' => 'date',
        'date_to' => 'date',
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function appliesTo(\DateTime $start, \DateTime $end): bool
    {
        $dayOfWeek = (int) $start->format('N') % 7;

        if ($this->applicable_days && !in_array($dayOfWeek, $this->applicable_days)) {
            return false;
        }

        $time = $start->format('H:i');

        if ($this->start_time && $this->end_time) {
            if ($time < $this->start_time || $time > $this->end_time) {
                return false;
            }
        }

        if ($this->date_from && $start < $this->date_from) {
            return false;
        }

        if ($this->date_to && $start > $this->date_to) {
            return false;
        }

        return true;
    }
}
