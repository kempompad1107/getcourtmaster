<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDrawerLog extends Model
{
    use \App\Models\Concerns\BelongsToBranch;

    protected $fillable = [
        'tenant_id', 'branch_id', 'user_id', 'action',
        'amount', 'balance_after', 'reason',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
}
