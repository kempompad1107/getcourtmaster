{{-- One elimination-bracket match cell. Expects $match (with team1/team2/winner loaded). --}}
@php
$labels = $match->slot_labels ?? [];
$sets = is_array($match->scores) ? $match->scores : [];
$cellClass = match (true) {
    in_array($match->status, ['finished', 'walkover', 'bye'], true) => 'is-decided',
    $match->status === 'cancelled' => 'is-cancelled',
    default => '',
};
@endphp
<div class="bracket-match {{ $cellClass }}">
    <div class="bracket-match-head">
        <span>#{{ $match->match_number }}</span>
        <span>
            @if($match->court)<i class="bi bi-geo-alt"></i> {{ $match->court->name }} ·@endif
            @if($match->scheduled_at){{ $match->scheduled_at->format('M j g:i A') }} ·@endif
            {{ ucfirst($match->status) }}
        </span>
    </div>
    @foreach([1, 2] as $slot)
    @php
    $team = $slot === 1 ? $match->team1 : $match->team2;
    $isWinner = $team && $match->winner_team_id === $team->id;
    $label = $labels[(string) $slot] ?? $labels[$slot] ?? 'TBD';
    @endphp
    <div class="bracket-team {{ $isWinner ? 'is-winner' : '' }} {{ !$team ? 'is-tbd' : '' }}">
        <span class="bt-name">
            @if($team)
                @if($team->seed)<span class="text-muted">{{ $team->seed }}.</span>@endif
                {{ $team->name }}
            @else
                {{ $label }}
            @endif
        </span>
        <span class="bt-score">
            @if($match->status === 'walkover')
                {{ $isWinner ? 'W/O' : '' }}
            @elseif($match->status === 'bye')
                {{ $isWinner ? 'BYE' : '' }}
            @else
                {{ collect($sets)->map(fn ($s) => $s[$slot === 1 ? 'team1' : 'team2'] ?? 0)->implode(' ') }}
            @endif
        </span>
    </div>
    @endforeach
</div>
