@extends('layouts.super')
@section('title', 'System Reports')

@push('styles')
@include('super._partials.premium-ui')
<style>
    /* Usage table polish */
    .usage-table th { white-space: nowrap; }
    .usage-table .progress { height: 5px; border-radius: 999px; margin-top: .3rem; }
    .usage-table .usage-num { font-variant-numeric: tabular-nums; font-weight: 600; }
</style>
@endpush

@section('content')

<x-page-header title="System Reports" subtitle="Platform-wide SaaS metrics &amp; tenant usage"/>

{{-- ── Revenue & activity ──────────────────────────────────────── --}}
<div class="dash-section">Revenue &amp; activity</div>
<div class="kpi-grid mb-4">
    <x-stat-card label="MRR (est.)"     :value="'₱'.number_format($overview['mrr'], 2)" icon="bi-currency-dollar" color="emerald"/>
    <x-stat-card label="ARPU"           :value="'₱'.number_format($overview['arpu'], 2)" icon="bi-graph-up" color="primary"/>
    <x-stat-card label="Bookings (30d)" :value="number_format($overview['bookings_30d'])" icon="bi-calendar-event" color="blue"/>
    <x-stat-card label="Revenue (30d)"  :value="'₱'.number_format($overview['revenue_30d'], 2)" icon="bi-cash-coin" color="green"/>
</div>

{{-- ── Tenant health ───────────────────────────────────────────── --}}
<div class="dash-section">Tenant health</div>
<div class="kpi-grid mb-4">
    <x-stat-card label="Total Tenants" :value="$overview['total_tenants']" icon="bi-buildings" color="emerald"/>
    <x-stat-card label="Active"        :value="$overview['active_tenants']" icon="bi-check-circle" color="emerald"/>
    <x-stat-card label="On Trial"      :value="$overview['on_trial']" icon="bi-hourglass-split" color="amber"/>
    <x-stat-card label="Suspended"     :value="$overview['suspended']" icon="bi-slash-circle" color="red"/>
</div>

{{-- ── Tenant usage table ────────────────────────────────────────── --}}
<div class="dash-section">Usage vs plan limits</div>
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">Tenant Usage vs Plan Limits</h6>
        <span class="badge bg-warning-subtle text-warning">
            {{ $tenantUsage->where('at_limit', true)->count() }} near or at limit
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 usage-table sticky-first">
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Courts</th>
                    <th>Branches</th>
                    <th>Customers</th>
                    <th>Staff</th>
                    <th>Bookings (mo)</th>
                    <th class="text-end">Worst</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenantUsage as $t)
                <tr>
                    <td>
                        <a href="{{ route('super.tenants.show', $t['id']) }}" class="fw-medium text-decoration-none">{{ $t['name'] }}</a>
                        <div class="small text-muted">{{ $t['slug'] }}</div>
                    </td>
                    <td><span class="badge bg-primary-subtle text-primary">{{ $t['plan_name'] }}</span></td>
                    <td>
                        @php
                            $statusClass = ['active'=>'success', 'trial'=>'warning', 'suspended'=>'danger'][$t['status']] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $statusClass }}-subtle text-{{ $statusClass }} text-capitalize">{{ $t['status'] }}</span>
                    </td>
                    @foreach(['courts','branches','customers','staff','bookings_month'] as $k)
                        @php $u = $t['usage'][$k]; @endphp
                        <td style="min-width:96px">
                            @if($u['max'])
                                @php
                                    $pct = ($u['used'] / max($u['max'],1)) * 100;
                                    $barClass = $pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-success');
                                @endphp
                                <div class="small usage-num">{{ $u['used'] }} <span class="text-muted fw-normal">/ {{ $u['max'] }}</span></div>
                                <div class="progress">
                                    <div class="progress-bar {{ $barClass }}" style="width:{{ min($pct,100) }}%"></div>
                                </div>
                            @else
                                <div class="small text-muted usage-num">{{ $u['used'] }} / <i class="bi bi-infinity"></i></div>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-end">
                        @if($t['worst_pct'] >= 100)
                            <span class="badge bg-danger">{{ $t['worst_pct'] }}%</span>
                        @elseif($t['worst_pct'] >= 80)
                            <span class="badge bg-warning text-dark">{{ $t['worst_pct'] }}%</span>
                        @else
                            <span class="badge bg-success-subtle text-success">{{ $t['worst_pct'] }}%</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted small py-4">No tenants yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ── Distribution & system ─────────────────────────────────────── --}}
<div class="dash-section">Distribution &amp; system</div>
<div class="row g-4 mb-4">
    {{-- Plan distribution --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100 lift-card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Plan Distribution</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th class="ps-3">Plan</th><th class="text-end">Tenants</th><th class="text-end pe-3">MRR</th></tr></thead>
                    <tbody>
                        @forelse($planDistribution as $p)
                        <tr>
                            <td class="fw-medium ps-3">{{ $p['plan'] }}</td>
                            <td class="text-end">{{ $p['count'] }}</td>
                            <td class="text-end pe-3">₱{{ number_format($p['mrr'], 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted small py-3">No plans yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Branch activity --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100 lift-card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Branch Activity <span class="text-muted fw-normal small">· last 7d</span></h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th class="ps-3">Tenant</th><th>Branch</th><th class="text-end pe-3">Bookings</th></tr></thead>
                    <tbody>
                        @forelse(array_slice($branchActivity, 0, 15) as $r)
                        <tr>
                            <td class="small ps-3">{{ $r['tenant'] }}</td>
                            <td>{{ $r['branch'] }}</td>
                            <td class="text-end fw-medium pe-3">{{ $r['bookings'] }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted small py-3">No activity</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Storage --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100 lift-card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Storage by Model</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th class="ps-3">Model</th><th class="text-end pe-3">Storage</th></tr></thead>
                    <tbody>
                        @forelse($storage as $s)
                        <tr><td class="ps-3">{{ $s['model'] }}</td><td class="text-end fw-medium pe-3">{{ $s['mb'] }} MB</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted small py-3">No media uploaded yet</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="small text-muted px-3 py-2 mb-0 border-top">Aggregated from Spatie media library. Per-tenant attribution requires further wiring.</p>
            </div>
        </div>
    </div>

    {{-- Failed logins --}}
    <div class="col-12 col-xl-6">
        <div class="card h-100 lift-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Failed Logins <span class="text-muted fw-normal small">· last 24h</span></h6>
                <span class="badge bg-danger-subtle text-danger">{{ count($failedLogins) }}</span>
            </div>
            <div class="card-body p-0" style="max-height:280px;overflow-y:auto">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th class="ps-3">When</th><th class="pe-3">Detail</th></tr></thead>
                    <tbody>
                        @forelse($failedLogins as $l)
                        <tr>
                            <td class="small text-muted ps-3" style="white-space:nowrap">{{ \Carbon\Carbon::parse($l['created_at'])->diffForHumans() }}</td>
                            <td class="small pe-3">{{ $l['description'] }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted small py-3">No failed logins in the last 24h</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
