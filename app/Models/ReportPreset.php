<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPreset extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'name', 'report_type', 'filters', 'is_shared',
    ];

    protected $casts = [
        'filters'   => 'array',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
