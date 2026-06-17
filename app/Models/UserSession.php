<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'device_label', 'ip',
        'user_agent', 'last_active_at', 'revoked_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
        'revoked_at'     => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
