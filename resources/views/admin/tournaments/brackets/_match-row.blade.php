{{-- Compact one-line match row for group/round-robin listings. --}}
<div class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom small">
    <span class="text-muted flex-shrink-0">#{{ $match->match_number }}</span>
    <span class="text-truncate {{ $match->winner_team_id && $match->winner_team_id === $match->team1_id ? 'fw-bold' : '' }}">{{ $match->team1?->name ?? 'TBD' }}</span>
    <span class="text-muted flex-shrink-0">
        @if(in_array($match->status, ['finished', 'walkover'], true))
            {{ $match->scoreSummary() }}
        @else
            vs
        @endif
    </span>
    <span class="text-truncate text-end {{ $match->winner_team_id && $match->winner_team_id === $match->team2_id ? 'fw-bold' : '' }}">{{ $match->team2?->name ?? 'TBD' }}</span>
    <x-badge :status="match($match->status) { 'finished' => 'completed', 'walkover' => 'info', 'playing' => 'active', 'called' => 'pending', 'cancelled' => 'cancelled', default => 'neutral' }" class="flex-shrink-0">
        {{ ucfirst($match->status) }}
    </x-badge>
</div>
