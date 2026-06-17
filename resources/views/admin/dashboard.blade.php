@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    /* ── Dashboard — premium hero + clean KPI grid over the admin theme ── */

    /* Greeting hero band */
    .dash-hero {
        position: relative; overflow: hidden;
        border-radius: 1.1rem;
        padding: clamp(1.25rem, 3vw, 1.85rem);
        color: #fff;
        background:
            radial-gradient(130% 150% at 0% 0%,   rgba(16,185,129,.32), transparent 55%),
            radial-gradient(120% 160% at 100% 100%, rgba(56,189,248,.16), transparent 52%),
            linear-gradient(135deg, #0b1220 0%, #0f2027 100%);
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

    /* Hero inline mini-stats */
    .hero-stats { display: flex; flex-wrap: wrap; gap: 0; margin-top: 1.35rem; }
    .hero-stat { padding: .15rem 1.25rem .15rem 0; margin-right: 1.25rem; border-right: 1px solid rgba(255,255,255,.12); }
    .hero-stat:last-child { border-right: 0; margin-right: 0; padding-right: 0; }
    .hero-stat .l { display:flex; align-items:center; gap:.4rem; font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:rgba(226,232,240,.62); }
    .hero-stat .v { font-size: clamp(1.25rem, 2.4vw, 1.55rem); font-weight: 800; color:#fff; letter-spacing:-.02em; font-variant-numeric: tabular-nums; line-height: 1.2; }
    @media (max-width: 575.98px) {
        .hero-stat { flex: 1 1 40%; border-right: 0; padding: .5rem 0; margin-right: 0; }
    }
    /* Consistent VISUAL rhythm in the hero on phones (date → buttons → stats).
       Rect-gaps differ on purpose: the date carries ~5px line-leading below it and
       the stats row ~8px top padding, so equal box-gaps would look uneven. These
       compensate to ~12px even visual gaps (measured via headless render). */
    @media (max-width: 767.98px) {
        .dash-hero > .d-flex   { gap: .45rem !important; }
        .dash-hero .d-grid     { gap: .75rem !important; }
        .dash-hero .hero-stats { margin-top: .2rem; }
        .dash-hero .hero-stat  { padding-top: .35rem; padding-bottom: .35rem; }
    }

    /* Auto-flowing KPI grid — always fills the row cleanly, no orphan cards */
    .kpi-grid { display: grid; gap: .75rem; grid-template-columns: repeat(2, minmax(0,1fr)); }
    @media (min-width: 768px) { .kpi-grid { gap: 1rem; grid-template-columns: repeat(4, minmax(0,1fr)); } }

    /* Section rhythm */
    .dash-section { display:flex; align-items:center; gap:.75rem; margin: 1.9rem 0 .85rem; font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--bs-secondary-color); }
    .dash-section::after { content:''; flex:1; height:1px; background:var(--bs-border-color); }

    /* Court status list polish */
    .court-status-item { transition: background .12s ease; }
    .court-status-item:hover { background: var(--bs-body-bg-alt); }
    .court-status-item:last-child { border-bottom: 0 !important; }

    /* Quick action tiles */
    .qa-tile { transition: transform .16s ease, border-color .16s ease, background .16s ease; }
    .qa-tile:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.4) !important; background: var(--bs-body-bg-alt); }

    /* Recent bookings now stack via the shared .table-stack utility (app.scss) */
</style>
@endpush

@section('content')

{{-- Chart data (hidden) --}}
<div id="revenue-chart-data"
     data-daily="{{ json_encode($revenue['daily_breakdown'] ?? []) }}"
     class="d-none"></div>

{{-- Greeting hero --}}
@php
    $branchLabel = isset($activeBranch) && $activeBranch
        ? $activeBranch->name
        : 'All branches';
    $hour      = now()->hour;
    $greeting  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim(auth()->user()->name ?? 'there'))[0];
@endphp
<div class="dash-hero mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="min-w-0">
            <h1 class="dash-greet">{{ $greeting }}, {{ $firstName }} <span class="wave">👋</span></h1>
            <p class="dash-sub mb-0">
                {{ now()->format('l, F j, Y') }}
                <span class="sep">·</span>
                <i class="bi bi-geo-alt-fill me-1"></i>{{ $branchLabel }}
            </p>
        </div>
        <div class="d-grid gap-2 d-md-flex align-items-md-center flex-shrink-0">
            <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Booking
            </a>
            <a href="{{ route('admin.courts.status-board') }}" class="btn btn-hero btn-sm">
                <i class="bi bi-grid-3x3-gap me-1"></i>Status Board
            </a>
        </div>
    </div>

    <div class="hero-stats">
        <div class="hero-stat">
            <div class="l"><i class="bi bi-calendar-check"></i>Today's Bookings</div>
            <div class="v">{{ $stats['todays_bookings'] }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-cash-coin"></i>Today's Revenue</div>
            <div class="v">₱{{ number_format($todayRevenue['total_revenue'] ?? 0) }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-lightning-charge"></i>Active Courts</div>
            <div class="v">{{ $stats['active_courts'] }}</div>
        </div>
        <div class="hero-stat">
            <div class="l"><i class="bi bi-bar-chart-line"></i>Utilization</div>
            <div class="v">{{ $avgUtilization }}%</div>
        </div>
    </div>
</div>

{{-- Alerts banner --}}
@php $dismissedBanners = session('plan_banner_dismissed', []); @endphp
@if(!empty($alerts))
<div class="mb-3">
    @foreach($alerts as $a)
    @php $key = $a['key'] ?? md5($a['text']); @endphp
    @continue(!empty($dismissedBanners[$key]))
    <div class="alert alert-{{ $a['level'] }} d-flex align-items-center gap-2 py-2 mb-2 js-dismissible-banner" data-key="{{ $key }}">
        <i class="bi {{ $a['icon'] }}"></i>
        <span class="flex-grow-1 small">{{ $a['text'] }}</span>
        @if($a['href'])
        <a href="{{ $a['href'] }}" class="btn btn-sm btn-{{ $a['level'] }} btn-outline-{{ $a['level'] }}">View</a>
        @endif
        <button type="button" class="btn-close" aria-label="Close"></button>
    </div>
    @endforeach
</div>
@include('admin._partials.dismissible-banner-script')
@endif

{{-- KPI Cards --}}
@php
    $growth     = $revenue['growth_pct'] ?? null;
    $trendLabel = $growth !== null ? (($growth >= 0 ? '+' : '') . $growth . '%') : null;
    $customersLabel = ($stats['customers_scoped'] ?? false) ? 'Active Customers' : 'Total Customers';
@endphp
<div class="dash-section">Performance overview</div>
<div class="kpi-grid mb-4">
    <x-stat-card label="Monthly Revenue"   :value="'₱'.number_format($stats['monthly_revenue'])"
                 icon="bi-currency-dollar" color="emerald"
                 :trend="$trendLabel"
                 :trendUp="$growth !== null ? $growth >= 0 : null"/>

    <x-stat-card :label="$customersLabel" :value="$stats['customers']"
                 icon="bi-people" color="purple"
                 :trend="($stats['customers_scoped'] ?? false) ? 'last 90 days' : null"/>

    <x-stat-card label="Pending Bookings"  :value="$stats['pending_bookings']"
                 icon="bi-clock" color="amber"
                 :href="route('admin.bookings.index', ['status'=>'pending'])"/>

    <x-stat-card label="Court Utilization" :value="$avgUtilization.'%'"
                 icon="bi-bar-chart-line" color="emerald"/>
</div>

{{-- Charts row --}}
<div class="dash-section">Revenue &amp; courts</div>
<div class="row g-4 mb-4">

    {{-- Revenue chart --}}
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="mb-0 fw-semibold">Revenue This Month</h6>
                    <small class="text-muted">{{ number_format($revenue['transaction_count'] ?? 0) }} transactions</small>
                </div>
                <div class="text-end">
                    <p class="mb-0 fw-bold text-primary fs-5">₱{{ number_format($revenue['total_revenue'] ?? 0, 2) }}</p>
                    <small class="text-muted">{{ now()->format('F Y') }}</small>
                </div>
            </div>
            <div class="card-body">
                <div id="revenueChart" style="height:210px"></div>
            </div>
        </div>
    </div>

    {{-- Court status --}}
    <div class="col-12 col-xl-4">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header d-flex align-items-center justify-content-between flex-shrink-0">
                <h6 class="mb-0 fw-semibold">Court Status</h6>
                <span class="badge text-bg-success">
                    {{ $courtStatuses->where('status', 'available')->count() }} free
                </span>
            </div>
            <div class="flex-grow-1 overflow-auto" style="max-height:280px">
                @forelse($courtStatuses as $court)
                @php
                $dot = match($court->status) {
                    'available' => 'bg-success',
                    'occupied'  => 'bg-danger',
                    'reserved'  => 'bg-warning',
                    default     => 'bg-secondary'
                };
                @endphp
                <div class="d-flex align-items-center justify-content-between px-3 py-3 border-bottom court-status-item">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="rounded-circle flex-shrink-0 {{ $dot }}"
                              style="width:8px;height:8px;display:inline-block"></span>
                        <div class="min-w-0">
                            <p class="mb-0 small fw-medium text-truncate">{{ $court->name }}</p>
                            @if($court->branch)
                            <small class="text-muted text-truncate d-block">{{ $court->branch->name }}</small>
                            @endif
                        </div>
                    </div>
                    <div class="text-end flex-shrink-0 ms-2">
                        <x-badge :status="$court->status === 'available' ? 'active' : ($court->status === 'occupied' ? 'cancelled' : ($court->status === 'reserved' ? 'pending' : 'neutral'))">{{ ucfirst($court->status) }}</x-badge>
                        @if($court->activeTimer)
                        <small class="d-block text-primary fw-semibold font-monospace mt-1">
                            {{ gmdate('H:i', $court->activeTimer->remaining_seconds) }} left
                        </small>
                        @endif
                    </div>
                </div>
                @empty
                <x-empty-state title="No courts configured" icon="bi-grid"/>
                @endforelse
            </div>
            <div class="card-footer flex-shrink-0">
                <a href="{{ route('admin.courts.status-board') }}" class="small text-primary text-decoration-none">
                    View full status board <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Recent Bookings --}}
<div class="dash-section">Recent activity</div>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-0 fw-semibold">Recent Bookings</h6>
            <small class="text-muted">Latest activity across all courts</small>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="small text-primary text-decoration-none">
            View all <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-stack">
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th class="d-none d-md-table-cell">Court</th>
                    <th class="d-none d-lg-table-cell">Date & Time</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentBookings as $booking)
                <tr>
                    <td data-label="Booking">
                        <a href="{{ route('admin.bookings.show', $booking) }}"
                           class="font-monospace small fw-semibold text-primary text-decoration-none">
                            {{ $booking->booking_number }}
                        </a>
                    </td>
                    <td data-label="Customer">
                        <div class="d-flex align-items-center gap-2">
                            <img src="{{ $booking->customer->avatar_url }}" alt=""
                                 class="rounded-circle flex-shrink-0" style="width:28px;height:28px;object-fit:cover">
                            <span class="small fw-medium">{{ $booking->customer->name }}</span>
                        </div>
                    </td>
                    <td data-label="Court" class="d-none d-md-table-cell small text-muted">{{ $booking->court->name }}</td>
                    <td data-label="Date & Time" class="d-none d-lg-table-cell">
                        <p class="mb-0 small">{{ $booking->booking_date->format('M j, Y') }}</p>
                        <small class="text-muted">{{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}</small>
                    </td>
                    <td data-label="Amount" class="fw-semibold small recent-amt">₱{{ number_format($booking->total_amount, 2) }}</td>
                    <td data-label="Status">
                        <x-badge :status="$booking->status">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</x-badge>
                    </td>
                    <td data-label="" class="cell-actions text-end recent-action">
                        <a href="{{ route('admin.bookings.show', $booking) }}"
                           class="btn btn-outline-primary btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="cell-plain">
                        <x-empty-state title="No bookings yet"
                            description="Bookings will appear here as customers make reservations."
                            icon="bi-calendar"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Analytics row (lazy-fetched from /admin/analytics/overview) --}}
<div class="dash-collapsible" :class="{ open: open }" x-data="{ open: window.matchMedia('(min-width: 768px)').matches }">
    <button type="button" class="dash-section dash-collapsible-toggle w-100 border-0 bg-transparent text-start" @click="open = !open" :aria-expanded="open.toString()" :tabindex="window.matchMedia('(min-width: 768px)').matches ? -1 : 0">
        Customer analytics
        <i class="bi bi-chevron-down ms-auto"></i>
    </button>
    <div class="dash-collapsible-body">
        <div class="row g-4 mb-4" x-data="dashboardAnalytics()" x-init="load()">

    <div class="col-12">
        <div class="kpi-grid">
            <x-stat-card label="Retention rate" value="—"
                         icon="bi-arrow-repeat" color="emerald" x-ref="retention"/>
            <x-stat-card label="Churn rate" value="—"
                         icon="bi-graph-down" color="amber" x-ref="churn"/>
            <x-stat-card label="Bookings (30d)" value="—"
                         icon="bi-calendar3" color="emerald" x-ref="bookings30"/>
            <x-stat-card label="Peak hour" value="—"
                         icon="bi-clock-history" color="purple" x-ref="peakHour"/>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Bookings per day</h6>
                <small class="text-muted">Last 30 days</small>
            </div>
            <div class="card-body">
                <div id="bookingsPerDayChart" style="height:240px"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Hourly distribution</h6>
                <small class="text-muted">Bookings by hour-of-day</small>
            </div>
            <div class="card-body">
                <div id="bookingsHourlyChart" style="height:240px"></div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Top customers by lifetime value</h6>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th class="text-end">Lifetime spend</th>
                        </tr>
                    </thead>
                    <tbody id="topCustomersBody">
                        <tr><td colspan="3" class="text-muted text-center small py-4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
    </div>
</div>

{{-- Bottom row --}}
<div class="dash-collapsible" :class="{ open: open }" x-data="{ open: window.matchMedia('(min-width: 768px)').matches }">
    <button type="button" class="dash-section dash-collapsible-toggle w-100 border-0 bg-transparent text-start" @click="open = !open" :aria-expanded="open.toString()" :tabindex="window.matchMedia('(min-width: 768px)').matches ? -1 : 0">
        Shortcuts
        <i class="bi bi-chevron-down ms-auto"></i>
    </button>
    <div class="dash-collapsible-body">
        <div class="row g-4">

    {{-- Quick actions --}}
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @php
                    $quickActions = [
                        ['href' => route('admin.bookings.create'),  'label' => 'New Booking', 'icon' => 'bi-calendar-plus',   'bg' => 'bg-success bg-opacity-10', 'text' => 'text-success'],
                        ['href' => route('admin.pos.index'),        'label' => 'Open POS',    'icon' => 'bi-receipt',          'bg' => 'bg-success bg-opacity-10', 'text' => 'text-primary'],
                        ['href' => route('admin.customers.index'),  'label' => 'Customers',   'icon' => 'bi-people',           'bg' => 'bg-info bg-opacity-10',    'text' => 'text-info'],
                        ['href' => route('admin.reports.index'),    'label' => 'Reports',     'icon' => 'bi-bar-chart-line',   'bg' => 'bg-warning bg-opacity-10', 'text' => 'text-warning'],
                    ];
                    @endphp
                    @foreach($quickActions as $qa)
                    <div class="col-6">
                        <a href="{{ $qa['href'] }}"
                           class="qa-tile d-flex flex-column align-items-center gap-2 p-3 rounded-3 text-center text-decoration-none
                                  border text-reset">
                            <div class="rounded-3 d-flex align-items-center justify-content-center {{ $qa['bg'] }}"
                                 style="width:44px;height:44px">
                                <i class="bi {{ $qa['icon'] }} {{ $qa['text'] }} fs-5"></i>
                            </div>
                            <span class="small fw-semibold">{{ $qa['label'] }}</span>
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Revenue by method --}}
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Revenue by Method</h6>
                <small class="text-muted">{{ now()->format('F Y') }}</small>
            </div>
            <div class="card-body">
                @if(($revenue['by_method'] ?? collect())->isNotEmpty())
                @php $total = max($revenue['total_revenue'] ?? 1, 1); @endphp
                <div class="d-flex flex-column gap-3">
                    @foreach(($revenue['by_method'] ?? collect()) as $method => $amount)
                    @php
                    $pct      = round(($amount / $total) * 100, 1);
                    $barHex   = ['cash'=>'#22c55e','gcash'=>'#3b82f6','card'=>'#8b5cf6','maya'=>'#06b6d4','paymongo'=>'#f59e0b'];
                    $labelHex = ['cash'=>'#16a34a','gcash'=>'#2563eb','card'=>'#7c3aed','maya'=>'#0891b2','paymongo'=>'#ca8a04'];
                    $bar      = $barHex[$method]  ?? '#9ca3af';
                    $label    = $labelHex[$method] ?? '#6b7280';
                    @endphp
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small fw-medium text-capitalize" @style(['color: ' . $label])>{{ $method }}</span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-muted">{{ $pct }}%</span>
                                <span class="small fw-semibold">₱{{ number_format($amount, 2) }}</span>
                            </div>
                        </div>
                        <div class="progress" style="height:6px;border-radius:3px">
                            <div class="progress-bar" role="progressbar"
                                 style="width:{{ $pct }}%;background-color:{{ $bar }}"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <x-empty-state title="No revenue data"
                    description="Payment breakdowns appear once transactions are processed."
                    icon="bi-bar-chart"/>
                @endif
            </div>
        </div>
    </div>
</div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isDark  = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const gridC   = isDark ? '#334155' : '#f1f5f9';
    const labelC  = isDark ? '#64748b' : '#94a3b8';

    const el = document.getElementById('revenue-chart-data');
    const dailyData  = JSON.parse(el ? el.dataset.daily : '{}');
    const categories = Object.keys(dailyData).map(d =>
        new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    );
    const values = Object.values(dailyData).map(Number);

    const chart = new ApexCharts(document.querySelector('#revenueChart'), {
        chart: {
            type: 'area', height: 210, toolbar: { show: false },
            fontFamily: 'Inter, sans-serif', background: 'transparent',
            animations: { enabled: true, easing: 'easeinout', speed: 500 },
        },
        series: [{ name: 'Revenue', data: values }],
        xaxis: {
            categories,
            labels: { show: categories.length <= 31, style: { colors: labelC, fontSize: '11px' } },
            axisBorder: { show: false }, axisTicks: { show: false },
        },
        yaxis: {
            labels: {
                formatter: v => '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 0 }),
                style: { colors: labelC, fontSize: '11px' },
            },
        },
        colors: ['#10b981'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.2, opacityTo: 0.02, stops: [0, 100] } },
        stroke: { curve: 'smooth', width: 2 },
        dataLabels: { enabled: false },
        grid: { borderColor: gridC, strokeDashArray: 3, xaxis: { lines: { show: false } }, padding: { left: 4, right: 4 } },
        tooltip: {
            theme: isDark ? 'dark' : 'light',
            y: { formatter: v => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 }) },
        },
        markers: { size: 0, hover: { size: 4 } },
    });
    chart.render();

    window.addEventListener('theme-changed', e => {
        const dark = e.detail.dark;
        const gc   = dark ? '#334155' : '#f1f5f9';
        const lc   = dark ? '#64748b' : '#94a3b8';
        chart.updateOptions({
            tooltip: { theme: dark ? 'dark' : 'light' },
            grid: { borderColor: gc },
            xaxis: { labels: { style: { colors: lc } } },
            yaxis: { labels: { style: { colors: lc } } },
        });
    });
});

// Analytics row — fetches /admin/analytics/overview JSON once and renders.
function dashboardAnalytics() {
    return {
        bookingsChart: null,
        hourlyChart: null,
        async load() {
            try {
                const r = await fetch(@json(route('admin.analytics.overview')), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!r.ok) return;
                const data = await r.json();

                this.populateKpis(data);
                this.renderBookingsChart(data.bookings_by_day || []);
                this.renderHourlyChart(data.bookings_by_hour || []);
                this.renderTopCustomers(data.top_customers || []);
            } catch (e) { /* silent */ }
        },

        populateKpis(d) {
            const setVal = (refName, txt) => {
                const card = this.$refs[refName];
                if (!card) return;
                const valEl = card.querySelector('.stat-value, .display-6, h3, .h3');
                if (valEl) valEl.textContent = txt;
            };
            const totalBookings = (d.bookings_by_day || []).reduce((s, r) => s + (r.total || 0), 0);
            const peak = (d.bookings_by_hour || []).reduce((a, b) => (b.total > (a?.total || 0) ? b : a), null);
            setVal('retention', (d.retention_rate ?? 0) + '%');
            setVal('churn',     (d.churn_rate ?? 0) + '%');
            setVal('bookings30', totalBookings);
            setVal('peakHour', peak ? String(peak.hour).padStart(2, '0') + ':00' : '—');
        },

        renderBookingsChart(series) {
            const labels = series.map(r => new Date(r.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            const values = series.map(r => r.total);
            this.bookingsChart = new ApexCharts(document.querySelector('#bookingsPerDayChart'), {
                chart: { type: 'bar', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
                series: [{ name: 'Bookings', data: values }],
                xaxis: { categories: labels, labels: { show: labels.length <= 31, style: { fontSize: '11px' } } },
                colors: ['#3b82f6'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
                dataLabels: { enabled: false },
                grid: { strokeDashArray: 3 },
            });
            this.bookingsChart.render();
        },

        renderHourlyChart(series) {
            const labels = series.map(r => String(r.hour).padStart(2, '0'));
            const values = series.map(r => r.total);
            this.hourlyChart = new ApexCharts(document.querySelector('#bookingsHourlyChart'), {
                chart: { type: 'area', height: 240, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
                series: [{ name: 'Bookings', data: values }],
                xaxis: { categories: labels, labels: { style: { fontSize: '11px' } }, tickAmount: 8 },
                colors: ['#8b5cf6'],
                stroke: { curve: 'smooth', width: 2 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.03 } },
                dataLabels: { enabled: false },
                grid: { strokeDashArray: 3 },
            });
            this.hourlyChart.render();
        },

        renderTopCustomers(list) {
            const tbody = document.getElementById('topCustomersBody');
            if (!tbody) return;
            if (!list.length) {
                tbody.innerHTML = `<tr><td colspan="3" class="text-muted text-center small py-4">No paid customers yet.</td></tr>`;
                return;
            }
            tbody.innerHTML = list.map((c, i) => `
                <tr>
                    <td class="text-muted small">${i + 1}</td>
                    <td class="small fw-medium">${c.name ?? '—'}</td>
                    <td class="text-end small fw-semibold">₱${Number(c.ltv || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
                </tr>
            `).join('');
        },
    };
}
</script>
@endpush
