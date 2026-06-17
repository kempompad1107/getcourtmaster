<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TournamentTeamMember extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'team_id', 'tournament_id', 'division_id', 'user_id',
        'is_captain', 'skill_level', 'rating',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
        'rating' => 'decimal:2',
        'team_id' => 'integer',
        'tournament_id' => 'integer',
        'division_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(TournamentTeam::class, 'team_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(TournamentDivision::class, 'division_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function paidPayment(): ?Payment
    {
        return $this->payments()
            ->whereIn('status', ['paid', 'partial', 'refunded'])
            ->latest('id')
            ->first();
    }

    public function hasPaid(): bool
    {
        return $this->payments()->whereIn('status', ['paid', 'partial'])->exists();
    }
}
