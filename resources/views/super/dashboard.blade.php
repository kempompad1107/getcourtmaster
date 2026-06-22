@extends('layouts.super')
@section('title', 'Super Admin')

@push('styles')
<style>
    /* ── Hero ── */
    .dash-hero {
        position: relative; overflow: hidden; border-radius: 1.1rem;
        padding: clamp(1.25rem, 3vw, 1.85rem); color: #fff;
        background:
            radial-gradient(130% 150% at 0% 0%,   rgba(16,185,129,.30), transparent 55%),
            radial-gradient(120% 160% at 100% 100%, rgba(99,102,241,.20), transparent 52%),
            linear-gradient(135deg, #0b1220 0%, #111827 100%);
        border: 1px solid rgba(255,255,255,.08);
        box-shadow: 0 26px 52px -30px rgba(0,0,0,.75);
    }
    .dash-hero::before {
        content: ''; position: absolute; inset: 0; pointer-events: none; opacity: .55;
        background-image:
            linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
        background-size: 34px 34px;
        -webkit-mask-image: radial-gradient(circle at 82% 12%, #000, transparent 72%);
                mask-image: radial-gradient(circle at 82% 12%, #000, transparent 72%);
    }
    .dash-hero > * { position: relative; z-index: 1; }
    .dash-greet { font-size: clamp(1.25rem, 2.6vw, 1.65rem); font-weight: 800; letter-spacing: -.025em; color: #fff; line-height: 1.15; margin: 0; }
    .dash-greet .wave { display: inline-block; transform-origin: 70% 70%; animation: dashWave 2.4s ease-in-out 1; }
    @keyframes dashWave { 0%,60%,100%{transform:rotate(0)} 15%{transform:rotate(16deg)} 30%{transform:rotate(-8deg)} 45%{transform:rotate(12deg)} }
    .dash-sub { color: rgba(226,232,240,.75); font-size: .82rem; margin-top: .3rem; }
    .dash-hero .btn-hero {
        --bs-btn-color:#fff; --bs-btn-border-color:rgba(255,255,255,.22);
        --bs-btn-hover-bg:rgba(255,255,255,.12); --bs-btn-hover-border-color:rgba(255,255,255,.4); --bs-btn-hover-color:#fff;
        backdrop-filter: blur(4px); background: rgba(255,255,255,.08);
    }

    /* Hero stats row */
    .hero-stats { display: flex; flex-wrap: wrap; gap: 0; margin-top: 1.35rem; padding-top: 1.1rem; border-top: 1px solid rgba(255,255,255,.1); }
    .hero-stat { padding: .1rem 1.35rem .1rem 0; margin-right: 1.35rem; border-right: 1px solid rgba(255,255,255,.1); }
    .hero-stat:last-child { border-right: 0; margin-right: 0; padding-right: 0; }
    .hero-stat .l { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: rgba(226,232,240,.6); display: flex; align-items: center; gap: .35rem; }
    .hero-stat .v { font-size: clamp(1.2rem, 2.2vw, 1.5rem); font-weight: 800; color: #fff; letter-spacing: -.02em; font-variant-numeric: tabular-nums; line-height: 1.25; }
    .hero-stat .v .dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: .35rem; vertical-align: middle; }
    @media (max-width: 575.98px) {
        .hero-stat { flex: 1 1 45%; border-right: 0; padding: .55rem 0; margin-right: 0; border-bottom: 1px solid rgba(255,255,255,.07); }
        .hero-stat:nth-last-child(-n+1) { border-bottom: 0; }
    }

    /* Section label */
    .dash-section {
        font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
        color: var(--bs-secondary-color); margin: 1.75rem 0 .85rem;
        display: flex; align-items: center; gap: .75rem;
    }
    .dash-section::after { content: ''; flex: 1; height: 1px; background: var(--bs-border-color); }

    /* Plan breakdown bar */
    .plan-bar { height: 4px; border-radius: 99px; background: rgba(16,185,129,.18); overflow: hidden; margin-top: .35rem; }
    .plan-bar-fill { height: 100%; border-radius: 99px; background: #10b981; }
</style>
@endpush

@section('content')

@php
    $hour      = now()->hour;
    $greeting  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim(auth()->user()->name ?? 'Admin'))[0];
    $totalPlans = $planBreakdown->sum('tenants_count') ?: 1;
@endphp

{{-- Hero --}}
<div class="dash-hero mb-4">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
        <div class="min-w-0">
            <h1 class="dash-greet">{{ $greeting }}, {{ $firstName }} <span class="wave">👋</span></h1>
            <p class="dash-sub mb-0">
                {{ now()->format('l, F j, Y') }}
                <span class="opacity-50 mx-1">·</span>
                Platform control center
            </p>
        </div>
        <div class="flex-shrink-0">
            <a href="{{ route('super.tenants.index') }}" class="btn btn-hero">
                <i class="bi bi-buildings me-1"></i>Manage Tenants
            </a>
        </div>
    </div>

    <div class="hero-stats">
        <div class="hero-stat">
            <div class="l"><i class="bi bi-buildings"></i>Tenants</div>
            <div class="v">{{ number_format($stats['total_tenants']) }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-cash-stack"></i>MRR (est.)</div>
            <div class="v">₱{{ number_format($stats['mrr']) }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-check-circle"></i>Active</div>
            <div class="v"><span class="dot" style="background:#34d399"></span>{{ number_format($stats['active_tenants']) }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-hourglass-split"></i>Trial</div>
            <div class="v"><span class="dot" style="background:#fbbf24"></span>{{ number_format($stats['on_trial']) }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-slash-circle"></i>Suspended</div>
            <div class="v"><span class="dot" style="background:#fb7185"></span>{{ number_format($stats['suspended']) }}</div>
        </div>
    </div>
</div>

{{-- Revenue & plans --}}
<div class="dash-section">Revenue &amp; Plans</div>
<div class="row g-4 mb-4">

    {{-- MRR trend chart --}}
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Monthly Recurring Revenue</h6>
                <span class="small text-muted">Last 6 months</span>
            </div>
            <div class="card-body">
                <div id="mrr-chart"></div>
                <p class="text-muted small mb-0 mt-2">Based on active subscriptions at current billing cycle prices.</p>
            </div>
        </div>
    </div>

    {{-- Plan breakdown --}}
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Tenants by Plan</h6>
            </div>
            @if($planBreakdown->isEmpty())
                <x-empty-state title="No plans yet" icon="bi-grid" description="Plans will appear here once tenants subscribe."/>
            @else
            <div class="card-body d-flex flex-column gap-3">
                @foreach($planBreakdown as $row)
                @php $pct = round($row->tenants_count / $totalPlans * 100); @endphp
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="small fw-medium">{{ $row->name }}</span>
                        <span class="small text-muted">{{ $row->tenants_count }} <span class="opacity-60">tenant{{ $row->tenants_count !== 1 ? 's' : '' }}</span></span>
                    </div>
                    <div class="plan-bar">
                        <div class="plan-bar-fill" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Recent tenants --}}
<div class="dash-section">Recent Activity</div>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">Recently Joined Tenants</h6>
        <a href="{{ route('super.tenants.index') }}" class="btn btn-primary btn-sm">View all</a>
    </div>

    @if($recentTenants->isEmpty())
        <x-empty-state title="No tenants yet" icon="bi-buildings" description="New tenant sign-ups will appear here."/>
    @else
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>Club</th>
                    <th class="d-none d-sm-table-cell">Plan</th>
                    <th>Status</th>
                    <th class="d-none d-md-table-cell">Joined</th>
                    <th class="cell-actions"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentTenants as $tenant)
                @php
                    [$sBadge, $sLabel] = match($tenant->status) {
                        'active'    => ['success', 'Active'],
                        'trial'     => ['warning', 'Trial'],
                        'suspended' => ['danger',  'Suspended'],
                        default     => ['secondary', ucfirst($tenant->status)],
                    };
                @endphp
                <tr>
                    <td data-label="Club" class="cell-plain">
                        <div class="fw-semibold small">{{ $tenant->name }}</div>
                        <div class="small text-muted">{{ $tenant->email }}</div>
                        {{-- Show plan + date inline on mobile --}}
                        <div class="d-sm-none small text-muted mt-1">
                            {{ ucfirst($tenant->plan ?? 'N/A') }}
                            · {{ $tenant->created_at->format('M j, Y') }}
                        </div>
                    </td>
                    <td data-label="Plan" class="d-none d-sm-table-cell">
                        <span class="badge bg-primary-subtle text-primary-emphasis">{{ ucfirst($tenant->plan ?? 'N/A') }}</span>
                    </td>
                    <td data-label="Status">
                        <span class="badge bg-{{ $sBadge }}-subtle text-{{ $sBadge }}-emphasis">{{ $sLabel }}</span>
                    </td>
                    <td data-label="Joined" class="small text-muted d-none d-md-table-cell text-nowrap">
                        {{ $tenant->created_at->format('M j, Y') }}
                    </td>
                    <td class="cell-actions">
                        <a href="{{ route('super.tenants.show', $tenant) }}" class="btn btn-primary btn-sm">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mrrData = @json($mrrTrend ?? []);
    const isDark  = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    if (mrrData.length && document.getElementById('mrr-chart')) {
        new ApexCharts(document.getElementById('mrr-chart'), {
            chart: {
                type: 'area', height: 180,
                toolbar: { show: false },
                background: 'transparent',
                fontFamily: 'inherit',
            },
            series: [{ name: 'MRR', data: mrrData.map(r => r.mrr) }],
            xaxis: { categories: mrrData.map(r => r.month), labels: { style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: v => '₱' + v.toLocaleString() } },
            colors: ['#10b981'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.03 } },
            stroke: { curve: 'smooth', width: 2 },
            grid: { borderColor: isDark ? '#2d3748' : '#e9ecef', strokeDashArray: 4 },
            theme: { mode: isDark ? 'dark' : 'light' },
            tooltip: { y: { formatter: v => '₱' + v.toLocaleString() } },
        }).render();
    }
});
</script>
@endpush

@endsection
