<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipTransaction extends Model
{
    protected $fillable = ['membership_id', 'type', 'credits_change', 'amount', 'description'];
    protected $casts = ['amount' => 'decimal:2'];

    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
}
