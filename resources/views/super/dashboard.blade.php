@extends('layouts.super')
@section('title', 'Super Admin')

@push('styles')
<style>
    /* ── Super dashboard — platform control-center hero ── */
    .dash-hero {
        position: relative; overflow: hidden;
        border-radius: 1.1rem;
        padding: clamp(1.25rem, 3vw, 1.85rem);
        color: #fff;
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
    .dash-greet { font-size: clamp(1.35rem, 2.6vw, 1.7rem); font-weight: 800; letter-spacing: -.025em; color: #fff; line-height: 1.15; margin: 0; }
    .dash-greet .wave { display: inline-block; transform-origin: 70% 70%; animation: dashWave 2.4s ease-in-out 1; }
    @keyframes dashWave { 0%,60%,100%{transform:rotate(0)} 15%{transform:rotate(16deg)} 30%{transform:rotate(-8deg)} 45%{transform:rotate(12deg)} }
    .dash-sub { color: rgba(226,232,240,.78); font-size: .875rem; margin-top: .35rem; }
    .dash-sub .sep { opacity: .4; margin: 0 .5rem; }
    .dash-hero .btn-hero {
        --bs-btn-color:#fff; --bs-btn-border-color:rgba(255,255,255,.22);
        --bs-btn-hover-bg:rgba(255,255,255,.12); --bs-btn-hover-border-color:rgba(255,255,255,.4); --bs-btn-hover-color:#fff;
        backdrop-filter: blur(4px); background: rgba(255,255,255,.06);
    }
    .hero-stats { display: flex; flex-wrap: wrap; gap: 0; margin-top: 1.35rem; }
    .hero-stat { padding: .15rem 1.5rem .15rem 0; margin-right: 1.5rem; border-right: 1px solid rgba(255,255,255,.12); }
    .hero-stat:last-child { border-right: 0; margin-right: 0; padding-right: 0; }
    .hero-stat .l { display:flex; align-items:center; gap:.4rem; font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:rgba(226,232,240,.62); }
    .hero-stat .v { font-size: clamp(1.25rem, 2.4vw, 1.55rem); font-weight: 800; color:#fff; letter-spacing:-.02em; font-variant-numeric: tabular-nums; line-height: 1.2; }
    .hero-stat .v .dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:.4rem; vertical-align:middle; }
    @media (max-width: 575.98px) {
        .hero-stat { flex: 1 1 40%; border-right: 0; padding: .5rem 0; margin-right: 0; }
    }

    .dash-section { display:flex; align-items:center; gap:.75rem; margin: 1.9rem 0 .85rem; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--bs-secondary-color); }
    .dash-section::after { content:''; flex:1; height:1px; background:var(--bs-border-color); }

    /* Recent tenants — stacked cards on phones */
    @media (max-width: 575.98px) {
        .tenant-table thead { display: none; }
        .tenant-table, .tenant-table tbody, .tenant-table tr, .tenant-table td { display: block; width: 100%; }
        .tenant-table tr { padding: .85rem 1rem; border-bottom: 1px solid var(--bs-border-color); }
        .tenant-table td { padding: .15rem 0 !important; border: 0 !important; }
        .tenant-table td.tenant-meta { margin-top: .35rem; }
        .tenant-table td.tenant-action { display: none !important; }
    }
</style>
@endpush

@section('content')

{{-- Platform hero --}}
@php
    $hour      = now()->hour;
    $greeting  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim(auth()->user()->name ?? 'Admin'))[0];
@endphp
<div class="dash-hero mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="min-w-0">
            <h1 class="dash-greet">{{ $greeting }}, {{ $firstName }} <span class="wave">👋</span></h1>
            <p class="dash-sub mb-0">
                {{ now()->format('l, F j, Y') }}
                <span class="sep">·</span>
                <i class="bi bi-hdd-network me-1"></i>Platform control center
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <a href="{{ route('super.tenants.index') }}" class="btn btn-hero btn-sm">
                <i class="bi bi-buildings me-1"></i>Manage Tenants
            </a>
        </div>
    </div>

    <div class="hero-stats">
        <div class="hero-stat">
            <div class="l"><i class="bi bi-buildings"></i>Total Tenants</div>
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
            <div class="l"><i class="bi bi-hourglass-split"></i>On Trial</div>
            <div class="v"><span class="dot" style="background:#fbbf24"></span>{{ number_format($stats['on_trial']) }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-slash-circle"></i>Suspended</div>
            <div class="v"><span class="dot" style="background:#fb7185"></span>{{ number_format($stats['suspended']) }}</div>
        </div>
    </div>
</div>

{{-- MRR + Plan breakdown --}}
<div class="dash-section">Revenue &amp; plans</div>
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-body p-4">
                <p class="text-muted small fw-medium mb-1">Monthly Recurring Revenue (est.)</p>
                <h2 class="fw-bold mb-0">
                    ₱{{ number_format($stats['mrr']) }}
                    <span class="fs-6 fw-normal text-muted">/mo</span>
                </h2>
                <p class="text-muted small mt-1 mb-3">Based on active subscriptions at current billing cycle prices.</p>
                <div id="mrr-chart"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Tenants by Plan</h6></div>
            <div class="list-group list-group-flush">
                @forelse($planBreakdown as $row)
                <div class="list-group-item d-flex align-items-center justify-content-between py-3">
                    <span class="small">{{ $row->name }}</span>
                    <span class="badge text-bg-secondary rounded-pill">{{ $row->tenants_count }}</span>
                </div>
                @empty
                <div class="list-group-item text-center text-muted small py-4">No plans yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Recent tenants --}}
<div class="dash-section">Recent activity</div>
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">Recently Joined Tenants</h6>
        <a href="{{ route('super.tenants.index') }}" class="btn btn-link btn-sm text-success p-0">View all</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 tenant-table">
            <thead>
                <tr>
                    <th>Club</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentTenants as $tenant)
                <tr>
                    <td>
                        <p class="mb-0 small fw-medium">{{ $tenant->name }}</p>
                        <small class="text-muted">{{ $tenant->email }}</small>
                    </td>
                    <td>
                        <span class="badge text-bg-primary">{{ ucfirst($tenant->plan ?? 'N/A') }}</span>
                    </td>
                    <td>
                        @if($tenant->status === 'active')
                            <span class="badge text-bg-success">Active</span>
                        @elseif($tenant->status === 'trial')
                            <span class="badge text-bg-warning">Trial</span>
                        @elseif($tenant->status === 'suspended')
                            <span class="badge text-bg-danger">Suspended</span>
                        @else
                            <span class="badge text-bg-secondary">{{ ucfirst($tenant->status) }}</span>
                        @endif
                    </td>
                    <td class="small text-muted tenant-meta">{{ $tenant->created_at->format('M j, Y') }}</td>
                    <td class="text-end tenant-action">
                        <a href="{{ route('super.tenants.show', $tenant) }}"
                           class="btn btn-link btn-sm p-0 text-success">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-5 small">No tenants yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mrrData = @json($mrrTrend ?? []);
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    if (mrrData.length && document.getElementById('mrr-chart')) {
        new ApexCharts(document.getElementById('mrr-chart'), {
            chart: {
                type: 'area', height: 130,
                sparkline: { enabled: false },
                toolbar: { show: false },
                background: 'transparent',
            },
            series: [{ name: 'MRR', data: mrrData.map(r => r.mrr) }],
            xaxis: { categories: mrrData.map(r => r.month), labels: { style: { fontSize: '11px' } } },
            yaxis: { labels: { formatter: v => '₱' + v.toLocaleString() } },
            colors: ['#10b981'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
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
