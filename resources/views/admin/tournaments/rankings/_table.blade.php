{{-- Leaderboard table. Expects $standings (array from TournamentRankingService). --}}
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0 table-stack">
        <thead class="table-light">
            <tr>
                <th>Rank</th>
                <th>Team</th>
                <th class="text-center">Played</th>
                <th class="text-center">Wins</th>
                <th class="text-center">Losses</th>
                <th class="text-center">Points For</th>
                <th class="text-center">Points Against</th>
                <th class="text-center">Diff</th>
            </tr>
        </thead>
        <tbody>
            @forelse($standings as $i => $row)
            <tr>
                <td data-label="Rank">
                    @if($i === 0)<i class="bi bi-trophy-fill text-warning me-1"></i>@endif
                    <span class="small fw-semibold">{{ $i + 1 }}</span>
                </td>
                <td data-label="Team">
                    <p class="mb-0 small fw-semibold">{{ $row['team']?->name ?? '—' }}</p>
                    <small class="text-muted">{{ $row['team']?->members->map(fn ($m) => $m->user->name)->implode(' / ') }}</small>
                </td>
                <td data-label="Played" class="text-center"><span class="small">{{ $row['played'] }}</span></td>
                <td data-label="Wins" class="text-center"><span class="small fw-bold text-success">{{ $row['wins'] }}</span></td>
                <td data-label="Losses" class="text-center"><span class="small">{{ $row['losses'] }}</span></td>
                <td data-label="PF" class="text-center"><span class="small">{{ $row['points_for'] }}</span></td>
                <td data-label="PA" class="text-center"><span class="small">{{ $row['points_against'] }}</span></td>
                <td data-label="Diff" class="text-center">
                    <span class="small fw-medium {{ $row['point_diff'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="cell-plain">
                    <x-empty-state title="No standings yet" description="Standings appear once matches are recorded." icon="bi-list-ol"/>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
