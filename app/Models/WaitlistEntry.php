<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitlistEntry extends Model
{
    protected $fillable = [
        'tenant_id', 'court_id', 'customer_id', 'preferred_date',
        'preferred_start_time', 'preferred_end_time', 'duration_minutes',
        'status', 'notified_at', 'expires_at',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'notified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function court(): BelongsTo { return $this->belongsTo(Court::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
}
