@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    /* ── Dashboard — premium hero + clean KPI grid over the admin theme ── */

    /* Greeting hero band */
    .dash-hero {
        position: relative; overflow: hidden;
        border-radius: 1.1rem;
        padding: 1.5rem;
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

    /* Hero two-column layout */
    .hero-inner {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }
    @media (min-width: 768px) {
        .hero-inner {
            flex-direction: row;
            align-items: center;
            gap: 2rem;
        }
        .hero-left { flex: 0 0 auto; }
        .hero-stats { flex: 1; }
    }
    .hero-left { display: flex; flex-direction: column; gap: .6rem; }
    .hero-actions { display: grid; gap: .5rem; margin-top: .25rem; }
    @media (min-width: 768px) { .hero-actions { display: flex; } }

    /* Hero stat tiles */
    .hero-stats {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: .6rem;
    }
    @media (min-width: 992px) {
        .hero-stats { grid-template-columns: repeat(4, 1fr); gap: .75rem; }
    }
    .hero-stat {
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.1);
        border-radius: .75rem;
        padding: .75rem 1rem;
        backdrop-filter: blur(4px);
    }
    .hero-stat .l { display:flex; align-items:center; gap:.4rem; font-size:.65rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:rgba(226,232,240,.6); margin-bottom:.25rem; }
    .hero-stat .v { font-size: 1.5rem; font-weight: 800; color:#fff; letter-spacing:-.02em; font-variant-numeric: tabular-nums; line-height: 1.15; }

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
    <div class="hero-inner">
        {{-- Left: greeting + actions --}}
        <div class="hero-left">
            <h1 class="dash-greet">{{ $greeting }}, {{ $firstName }} <span class="wave">👋</span></h1>
            <p class="dash-sub mb-0">
                {{ now()->format('l, F j, Y') }}
                <span class="sep">·</span>
                <i class="bi bi-geo-alt-fill me-1"></i>{{ $branchLabel }}
            </p>
            <div class="hero-actions">
                <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>New Booking
                </a>
                <a href="{{ route('admin.courts.status-board') }}" class="btn btn-hero btn-sm">
                    <i class="bi bi-grid-3x3-gap me-1"></i>Status Board
                </a>
            </div>
        </div>

        {{-- Right: stat tiles --}}
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
                 :trendUp="$growth !== null ? $growth >= 0 : null">
        <p class="mb-0 text-muted mt-2" style="font-size:.72rem">{{ now()->format('F Y') }}</p>
    </x-stat-card>

    <x-stat-card :label="$customersLabel" :value="$stats['customers']"
                 icon="bi-people" color="purple"
                 :trend="($stats['customers_scoped'] ?? false) ? 'last 90 days' : null">
        <p class="mb-0 text-muted mt-2" style="font-size:.72rem">Registered accounts</p>
    </x-stat-card>

    <x-stat-card label="Pending Bookings"  :value="$stats['pending_bookings']"
                 icon="bi-clock" color="amber"
                 :href="route('admin.bookings.index', ['status'=>'pending'])">
        <p class="mb-0 text-muted mt-2" style="font-size:.72rem">Awaiting confirmation</p>
    </x-stat-card>

    <x-stat-card label="Court Utilization" :value="$avgUtilization.'%'"
                 icon="bi-bar-chart-line" color="emerald">
        <p class="mb-0 text-muted mt-2" style="font-size:.72rem">Avg across all courts</p>
    </x-stat-card>
</div>

{{-- Charts row --}}
@php
    $cAvailable = $courtStatuses->where('status', 'available')->count();
    $cOccupied  = $courtStatuses->where('status', 'occupied')->count();
    $cReserved  = $courtStatuses->where('status', 'reserved')->count();
    $cTotal     = $courtStatuses->count();
@endphp
<div class="dash-section">Revenue &amp; courts</div>
<div class="row g-4 mb-4">

    {{-- Revenue chart --}}
    <div class="col-12 col-xl-6">
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
    <div class="col-12 col-xl-3">
        <div class="card h-100 d-flex flex-column">
            <div class="card-header d-flex align-items-center justify-content-between flex-shrink-0">
                <h6 class="mb-0 fw-semibold">Court Status</h6>
                <a href="{{ route('admin.courts.status-board') }}" class="small text-primary text-decoration-none">
                    Full board <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            {{-- Availability summary --}}
            <div class="px-3 pt-3 pb-2 border-bottom flex-shrink-0">
                <div class="d-flex gap-3 justify-content-between">
                    <div class="text-center">
                        <div class="fw-bold fs-5 text-success lh-1">{{ $cAvailable }}</div>
                        <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-top:2px">Available</div>
                    </div>
                    <div class="text-center">
                        <div class="fw-bold fs-5 text-danger lh-1">{{ $cOccupied }}</div>
                        <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-top:2px">Occupied</div>
                    </div>
                    <div class="text-center">
                        <div class="fw-bold fs-5 text-warning lh-1">{{ $cReserved }}</div>
                        <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-top:2px">Reserved</div>
                    </div>
                    <div class="text-center">
                        <div class="fw-bold fs-5 lh-1">{{ $cTotal }}</div>
                        <div class="text-muted" style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-top:2px">Total</div>
                    </div>
                </div>
                @if($cTotal > 0)
                <div class="mt-2" style="height:6px;border-radius:3px;overflow:hidden;display:flex;gap:2px">
                    @if($cAvailable > 0)<div style="flex:{{ $cAvailable }};background:#22c55e;border-radius:3px"></div>@endif
                    @if($cOccupied > 0)<div style="flex:{{ $cOccupied }};background:#ef4444;border-radius:3px"></div>@endif
                    @if($cReserved > 0)<div style="flex:{{ $cReserved }};background:#f59e0b;border-radius:3px"></div>@endif
                </div>
                @endif
            </div>
            <div class="flex-grow-1 overflow-auto" style="max-height:230px">
                @forelse($courtStatuses as $court)
                @php
                $dot = match($court->status) {
                    'available' => 'bg-success',
                    'occupied'  => 'bg-danger',
                    'reserved'  => 'bg-warning',
                    default     => 'bg-secondary'
                };
                @endphp
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom court-status-item">
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
        </div>
    </div>

    {{-- Revenue by payment method (donut) --}}
    <div class="col-12 col-xl-3">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Revenue by Method</h6>
                <small class="text-muted">{{ now()->format('M Y') }}</small>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                @if(($revenue['by_method'] ?? collect())->isNotEmpty())
                <div id="revenueByMethodChart" style="width:100%;max-width:220px"></div>
                <div class="w-100 mt-2">
                    @php
                    $methodColors = ['cash'=>'#22c55e','gcash'=>'#3b82f6','card'=>'#8b5cf6','maya'=>'#06b6d4','paymongo'=>'#f59e0b'];
                    $totalRev = max($revenue['total_revenue'] ?? 1, 1);
                    @endphp
                    @foreach(($revenue['by_method'] ?? collect()) as $method => $amount)
                    <div class="d-flex align-items-center justify-content-between py-1" style="font-size:.78rem">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle flex-shrink-0"
                                  style="width:8px;height:8px;display:inline-block;background:{{ $methodColors[$method] ?? '#9ca3af' }}"></span>
                            <span class="text-capitalize fw-medium">{{ $method }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2 text-muted">
                            <span>{{ round(($amount / $totalRev) * 100, 1) }}%</span>
                            <span class="fw-semibold text-body">₱{{ number_format($amount) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <x-empty-state title="No revenue data"
                    description="Payment breakdowns appear once transactions are processed."
                    icon="bi-pie-chart"/>
                @endif
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

{{-- Bottom row: Quick Actions --}}
<div class="dash-section">Quick actions</div>
<div class="row g-3 mb-4">
    @php
    $quickActions = [
        ['href' => route('admin.bookings.create'),  'label' => 'New Booking',   'icon' => 'bi-calendar-plus',  'bg' => 'bg-success bg-opacity-10', 'text' => 'text-success',  'desc' => 'Reserve a court'],
        ['href' => route('admin.pos.index'),        'label' => 'Open POS',      'icon' => 'bi-receipt',         'bg' => 'bg-primary bg-opacity-10', 'text' => 'text-primary',  'desc' => 'Point of sale'],
        ['href' => route('admin.customers.index'),  'label' => 'Customers',     'icon' => 'bi-people',          'bg' => 'bg-info bg-opacity-10',    'text' => 'text-info',     'desc' => 'Manage members'],
        ['href' => route('admin.reports.index'),    'label' => 'Reports',       'icon' => 'bi-bar-chart-line',  'bg' => 'bg-warning bg-opacity-10', 'text' => 'text-warning',  'desc' => 'View analytics'],
    ];
    @endphp
    @foreach($quickActions as $qa)
    <div class="col-6 col-md-3">
        <a href="{{ $qa['href'] }}"
           class="qa-tile card text-decoration-none text-reset d-flex flex-row align-items-center gap-3 p-3">
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 {{ $qa['bg'] }}"
                 style="width:44px;height:44px">
                <i class="bi {{ $qa['icon'] }} {{ $qa['text'] }} fs-5"></i>
            </div>
            <div class="min-w-0">
                <p class="mb-0 fw-semibold small">{{ $qa['label'] }}</p>
                <p class="mb-0 text-muted" style="font-size:.72rem">{{ $qa['desc'] }}</p>
            </div>
        </a>
    </div>
    @endforeach
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
let _isDark  = document.documentElement.getAttribute('data-bs-theme') === 'dark';
let _gridC   = _isDark ? '#334155' : '#f1f5f9';
let _labelC  = _isDark ? '#64748b' : '#94a3b8';

document.addEventListener('DOMContentLoaded', function () {
    const isDark  = _isDark;
    const gridC   = _gridC;
    const labelC  = _labelC;

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

    // Revenue by method donut chart
    @if(($revenue['by_method'] ?? collect())->isNotEmpty())
    const methodEl = document.querySelector('#revenueByMethodChart');
    if (methodEl) {
        const methodColors = { cash:'#22c55e', gcash:'#3b82f6', card:'#8b5cf6', maya:'#06b6d4', paymongo:'#f59e0b' };
        const byMethod = @json($revenue['by_method'] ?? []);
        const mLabels  = Object.keys(byMethod).map(k => k.charAt(0).toUpperCase() + k.slice(1));
        const mValues  = Object.values(byMethod).map(Number);
        const mColors  = Object.keys(byMethod).map(k => methodColors[k] ?? '#9ca3af');
        new ApexCharts(methodEl, {
            chart: { type: 'donut', height: 200, toolbar: { show: false }, background: 'transparent', fontFamily: 'Inter, sans-serif' },
            series: mValues,
            labels: mLabels,
            colors: mColors,
            dataLabels: { enabled: false },
            legend: { show: false },
            plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total', formatter: () => '₱' + mValues.reduce((a,b)=>a+b,0).toLocaleString('en-PH',{maximumFractionDigits:0}) } } } } },
            tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: v => '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 }) } },
            stroke: { width: 0 },
        }).render();
    }
    @endif

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
                xaxis: {
                    categories: labels,
                    tickAmount: 6,
                    labels: {
                        rotate: -40,
                        rotateAlways: true,
                        style: { fontSize: '10px', colors: _labelC },
                        offsetY: 2,
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                yaxis: {
                    labels: {
                        formatter: v => Math.round(v),
                        style: { fontSize: '11px', colors: _labelC },
                    },
                    tickAmount: 4,
                },
                colors: ['#3b82f6'],
                plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
                dataLabels: { enabled: false },
                grid: { borderColor: _gridC, strokeDashArray: 3, xaxis: { lines: { show: false } } },
                tooltip: { theme: _isDark ? 'dark' : 'light' },
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
