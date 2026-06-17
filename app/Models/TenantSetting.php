<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSetting extends Model
{
    protected $table = 'tenant_settings';
    protected $fillable = ['tenant_id', 'key', 'value', 'group'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
