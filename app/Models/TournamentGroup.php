<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentGroup extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = ['tenant_id', 'division_id', 'name', 'sort_order'];

    public function division(): BelongsTo
    {
        return $this->belongsTo(TournamentDivision::class, 'division_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class, 'group_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'group_id');
    }
}
