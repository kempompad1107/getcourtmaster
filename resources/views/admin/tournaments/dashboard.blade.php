@extends('layouts.app')
@section('title', 'Tournament Dashboard')

@section('content')

<x-page-header title="Tournament Dashboard" subtitle="Today's matches, registrations and entry-fee collection at a glance.">
    <x-slot name="actions">
        @can('create', App\Models\Tournament::class)
        <a href="{{ route('admin.tournaments.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Tournament
        </a>
        @endcan
    </x-slot>
</x-page-header>

<div class="kpi-grid mb-4" style="--kpi-cols:5">
    <x-stat-card label="Active Tournaments" :value="$stats['active']" icon="bi-trophy" color="emerald"
                 :href="route('admin.tournaments.index')"/>
    <x-stat-card label="Open for Registration" :value="$stats['registration_open']" icon="bi-megaphone" color="blue"
                 :href="route('admin.tournaments.index', ['status' => 'registration_open'])"/>
    <x-stat-card label="Teams Registered" :value="$stats['teams']" icon="bi-people" color="purple"
                 :href="route('admin.tournaments.teams.index')"/>
    <x-stat-card label="Matches Today" :value="$stats['matches_today']" icon="bi-controller" color="amber"
                 :href="route('admin.tournaments.matches.index', ['date' => today()->format('Y-m-d')])"/>
    <x-stat-card label="Fees This Month" :value="number_format($stats['fees_month'], 2)" icon="bi-cash-stack" color="emerald"
                 :href="route('admin.tournaments.reports.index')"/>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <x-card title="Today's Matches" class="mb-4" flush>
            @if($todayMatches->isEmpty())
            <div class="p-3">
                <p class="small text-muted mb-0">No matches scheduled today.</p>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-stack">
                    <thead class="table-light">
                        <tr>
                            <th>Time</th><th>Match</th><th>Court</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todayMatches as $match)
                        <tr>
                            <td data-label="Time"><span class="small fw-medium">{{ $match->scheduled_at?->format('g:i A') ?? '—' }}</span></td>
                            <td data-label="Match">
                                <span class="small">{{ $match->team1?->name ?? 'TBD' }} vs {{ $match->team2?->name ?? 'TBD' }}</span>
                                <small class="text-muted d-block">{{ $match->tournament->name }} · {{ $match->division->name }}</small>
                            </td>
                            <td data-label="Court"><span class="small">{{ $match->court?->name ?? '—' }}</span></td>
                            <td data-label="Status">
                                <x-badge :status="match($match->status) { 'finished' => 'completed', 'walkover' => 'info', 'playing' => 'active', 'called' => 'pending', 'scheduled' => 'info', default => 'neutral' }">
                                    {{ ucfirst($match->status) }}
                                </x-badge>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('admin.tournaments.matches.index', ['date' => today()->format('Y-m-d')]) }}" class="small">All matches <i class="bi bi-arrow-right-short"></i></a>
            </div>
            @endif
        </x-card>

        <x-card title="Registrations — Last 14 Days">
            <div id="registrationTrendChart" style="height:240px"></div>
        </x-card>
    </div>

    <div class="col-12 col-xl-5">
        <x-card title="Active & Upcoming Tournaments" class="mb-4" flush>
            @if($upcoming->isEmpty())
            <div class="p-3">
                <p class="small text-muted mb-0">Nothing scheduled. <a href="{{ route('admin.tournaments.create') }}">Create a tournament</a>.</p>
            </div>
            @else
            <div class="list-group list-group-flush">
                @foreach($upcoming as $tournament)
                <a href="{{ route('admin.tournaments.show', $tournament) }}" class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="min-w-0">
                            <p class="mb-0 small fw-semibold text-truncate">{{ $tournament->name }}</p>
                            <small class="text-muted">
                                {{ $tournament->starts_at?->format('M j, Y') ?? 'No date' }} · {{ $tournament->teams_count }} teams
                            </small>
                        </div>
                        @include('admin.tournaments._status-badge', ['status' => $tournament->status])
                    </div>
                </a>
                @endforeach
            </div>
            @endif
        </x-card>

        <x-card title="Recent Activity" flush>
            @if($recentActivity->isEmpty())
            <div class="p-3"><p class="small text-muted mb-0">No tournament activity yet.</p></div>
            @else
            <div class="list-group list-group-flush">
                @foreach($recentActivity as $entry)
                <div class="list-group-item">
                    <p class="mb-0 small">{{ $entry->description }}</p>
                    <small class="text-muted">{{ \Carbon\Carbon::parse($entry->created_at)->diffForHumans() }} · {{ str_replace('_', ' ', $entry->log_name) }}</small>
                </div>
                @endforeach
            </div>
            @endif
        </x-card>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.querySelector('#registrationTrendChart');
    if (!el || typeof ApexCharts === 'undefined') return;
    new ApexCharts(el, {
        chart: { type: 'area', height: 240, toolbar: { show: false }, fontFamily: 'inherit' },
        series: [{ name: 'Registrations', data: @json($trend['data']) }],
        xaxis: { categories: @json($trend['labels']), labels: { style: { fontSize: '10px' } } },
        yaxis: { labels: { formatter: v => Math.round(v) } },
        colors: ['#10b981'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .02 } },
        dataLabels: { enabled: false },
        grid: { borderColor: 'rgba(148,163,184,.15)' },
    }).render();
});
</script>
@endpush
