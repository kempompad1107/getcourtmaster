{{-- Leaderboard table. Expects $standings (array from TournamentRankingService). --}}
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 table-stack">
        <thead class="table-light">
            <tr>
                <th style="width:3rem">#</th>
                <th>Team</th>
                <th class="text-center">P</th>
                <th class="text-center">W</th>
                <th class="text-center">L</th>
                <th class="text-center">PF</th>
                <th class="text-center">PA</th>
                <th class="text-center">+/-</th>
            </tr>
        </thead>
        <tbody>
            @forelse($standings as $i => $row)
            @php
                $medal = match($i) {
                    0 => ['icon' => 'bi-trophy-fill', 'color' => '#f59e0b'],
                    1 => ['icon' => 'bi-award-fill',  'color' => '#94a3b8'],
                    2 => ['icon' => 'bi-award-fill',  'color' => '#cd7c2f'],
                    default => null,
                };
            @endphp
            <tr>
                <td data-label="#" class="cell-plain">
                    <div class="d-flex align-items-center gap-1">
                        @if($medal)
                            <i class="bi {{ $medal['icon'] }}" style="color:{{ $medal['color'] }}; font-size:.95rem"></i>
                        @endif
                        <span class="small fw-semibold {{ $i < 3 ? 'fw-bold' : '' }}">{{ $i + 1 }}</span>
                    </div>
                </td>
                <td data-label="Team">
                    <p class="mb-0 small fw-semibold">{{ $row['team']?->name ?? '—' }}</p>
                    <small class="text-muted">{{ $row['team']?->members->map(fn ($m) => $m->user->name)->implode(' / ') }}</small>
                </td>
                <td data-label="Played" class="text-center"><span class="small">{{ $row['played'] }}</span></td>
                <td data-label="Wins"   class="text-center"><span class="small fw-bold text-success">{{ $row['wins'] }}</span></td>
                <td data-label="Losses" class="text-center"><span class="small">{{ $row['losses'] }}</span></td>
                <td data-label="PF"     class="text-center"><span class="small">{{ $row['points_for'] }}</span></td>
                <td data-label="PA"     class="text-center"><span class="small">{{ $row['points_against'] }}</span></td>
                <td data-label="+/-"    class="text-center">
                    <span class="small fw-medium {{ $row['point_diff'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}
                    </span>
                </td>
            </tr>
            @empty
            <tr class="stack-skip">
                <td colspan="8" class="cell-plain">
                    <x-empty-state title="No standings yet" description="Standings appear once matches are recorded." icon="bi-list-ol"/>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
