@extends('layouts.app')
@section('title', 'Tournament Dashboard')

@push('styles')
<style>
    /* 3 cols at md-lg, 5 at xl+ */
    @media (min-width: 768px)  { .kpi-grid { --kpi-cols: 3 !important; } }
    @media (min-width: 1200px) { .kpi-grid { --kpi-cols: 5 !important; } }

    /* Activity dot */
    .act-dot {
        width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: .35rem;
        background: var(--bs-secondary-color);
    }

    /* Tournament list icon */
    .trn-ico {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center; font-size: .95rem;
        background: rgba(16,185,129,.1); color: #10b981;
        border: 1px solid rgba(16,185,129,.2);
    }
</style>
@endpush

@section('content')

<x-page-header title="Tournament Dashboard" subtitle="Today's matches, registrations and entry-fee collection at a glance.">
    <x-slot name="actions">
        @can('create', App\Models\Tournament::class)
        <a href="{{ route('admin.tournaments.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Tournament
        </a>
        @endcan
    </x-slot>
</x-page-header>

<div class="kpi-grid mb-4">
    <x-stat-card label="Active Tournaments"    :value="$stats['active']"                         icon="bi-trophy"      color="emerald" :href="route('admin.tournaments.index')"/>
    <x-stat-card label="Open for Registration" :value="$stats['registration_open']"              icon="bi-megaphone"   color="blue"    :href="route('admin.tournaments.index', ['status' => 'registration_open'])"/>
    <x-stat-card label="Teams Registered"      :value="$stats['teams']"                          icon="bi-people"      color="purple"  :href="route('admin.tournaments.teams.index')"/>
    <x-stat-card label="Matches Today"         :value="$stats['matches_today']"                  icon="bi-controller"  color="amber"   :href="route('admin.tournaments.matches.index', ['date' => today()->format('Y-m-d')])"/>
    <x-stat-card label="Fees This Month"       :value="'₱ ' . number_format($stats['fees_month'], 2)" icon="bi-cash-stack" color="emerald" :small="true" :href="route('admin.tournaments.reports.index')"/>
</div>

<div class="row g-4">
    {{-- Left column --}}
    <div class="col-12 col-xl-7">

        {{-- Today's Matches --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Today's Matches</h6>
                <a href="{{ route('admin.tournaments.matches.index', ['date' => today()->format('Y-m-d')]) }}"
                   class="small text-muted">View all <i class="bi bi-arrow-right-short"></i></a>
            </div>

            @if($todayMatches->isEmpty())
                <x-empty-state
                    title="No matches today"
                    description="No matches are scheduled for today."
                    icon="bi-controller"/>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-stack">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Match</th>
                                <th>Court</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($todayMatches as $match)
                            <tr>
                                <td data-label="Time" class="text-nowrap">
                                    <span class="fw-medium small">{{ $match->scheduled_at?->format('g:i A') ?? '—' }}</span>
                                </td>
                                <td data-label="Match">
                                    <span class="small fw-medium">{{ $match->team1?->name ?? 'TBD' }} vs {{ $match->team2?->name ?? 'TBD' }}</span>
                                    <div class="small text-muted">{{ $match->tournament->name }} · {{ $match->division->name }}</div>
                                </td>
                                <td data-label="Court">
                                    <span class="small">{{ $match->court?->name ?? '—' }}</span>
                                </td>
                                <td data-label="Status">
                                    <x-badge :status="match($match->status) {
                                        'finished'  => 'completed',
                                        'walkover'  => 'info',
                                        'playing'   => 'active',
                                        'called'    => 'pending',
                                        'scheduled' => 'info',
                                        default     => 'neutral'
                                    }">{{ ucfirst($match->status) }}</x-badge>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Registration trend --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Registrations — Last 14 Days</h6>
            </div>
            <div class="card-body">
                <div id="registrationTrendChart" style="height:240px"></div>
            </div>
        </div>

    </div>

    {{-- Right column --}}
    <div class="col-12 col-xl-5">

        {{-- Active & Upcoming Tournaments --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Active &amp; Upcoming</h6>
                <a href="{{ route('admin.tournaments.index') }}" class="small text-muted">
                    View all <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>

            @if($upcoming->isEmpty())
                <x-empty-state
                    title="No tournaments scheduled"
                    description='Nothing active or upcoming. <a href="{{ route(\'admin.tournaments.create\') }}">Create one</a>.'
                    icon="bi-trophy"/>
            @else
                <div class="list-group list-group-flush">
                    @foreach($upcoming as $tournament)
                    <a href="{{ route('admin.tournaments.show', $tournament) }}"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                        <div class="trn-ico flex-shrink-0">
                            <i class="bi bi-trophy"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold small text-truncate">{{ $tournament->name }}</div>
                            <div class="small text-muted">
                                {{ $tournament->starts_at?->format('M j, Y') ?? 'No date' }}
                                · {{ $tournament->teams_count }} {{ Str::plural('team', $tournament->teams_count) }}
                            </div>
                        </div>
                        @include('admin.tournaments._status-badge', ['status' => $tournament->status])
                    </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Activity --}}
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Recent Activity</h6>
            </div>

            @if($recentActivity->isEmpty())
                <x-empty-state
                    title="No activity yet"
                    description="Tournament events will appear here."
                    icon="bi-activity"/>
            @else
                <div class="list-group list-group-flush">
                    @foreach($recentActivity as $entry)
                    @php
                        $logName = str_replace('_', ' ', $entry->log_name);
                        $dotColor = match(true) {
                            str_contains($entry->log_name, 'match')      => '#10b981',
                            str_contains($entry->log_name, 'team')       => '#6366f1',
                            str_contains($entry->log_name, 'tournament') => '#f59e0b',
                            str_contains($entry->log_name, 'cancel')     => '#ef4444',
                            default                                       => null,
                        };
                    @endphp
                    <div class="list-group-item d-flex gap-3 py-3">
                        <div class="act-dot mt-1" @if($dotColor) style="background:{{ $dotColor }}" @endif></div>
                        <div class="flex-grow-1 min-w-0">
                            <p class="mb-0 small">{{ $entry->description }}</p>
                            <div class="small text-muted mt-1">
                                <span>{{ \Carbon\Carbon::parse($entry->created_at)->diffForHumans() }}</span>
                                <span class="mx-1">·</span>
                                <span class="text-capitalize">{{ $logName }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.querySelector('#registrationTrendChart');
    if (!el || typeof ApexCharts === 'undefined') return;

    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const textColor  = isDark ? '#94a3b8' : '#6b7280';
    const gridColor  = isDark ? 'rgba(148,163,184,.1)' : 'rgba(148,163,184,.15)';

    new ApexCharts(el, {
        chart: {
            type: 'area', height: 240,
            toolbar: { show: false },
            fontFamily: 'inherit',
            background: 'transparent',
            foreColor: textColor,
        },
        theme: { mode: isDark ? 'dark' : 'light' },
        series: [{ name: 'Registrations', data: @json($trend['data']) }],
        xaxis: {
            categories: @json($trend['labels']),
            labels: { style: { fontSize: '10px', colors: textColor } },
            axisBorder: { show: false },
            axisTicks:  { show: false },
        },
        yaxis: { labels: { formatter: v => Math.round(v), style: { colors: textColor } } },
        colors: ['#10b981'],
        stroke: { curve: 'smooth', width: 2 },
        fill:   { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .02 } },
        dataLabels: { enabled: false },
        grid: { borderColor: gridColor, strokeDashArray: 4 },
        tooltip: { theme: isDark ? 'dark' : 'light' },
    }).render();
});
</script>
@endpush
