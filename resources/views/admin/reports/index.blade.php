@extends('layouts.app')
@section('title', 'Reports & Analytics')

@section('content')

<x-page-header title="Reports & Analytics" subtitle="Filterable analytics across revenue, bookings, courts, members, payments and staff activity"/>

<div x-data="reports()" x-init="init()" x-cloak>

    {{-- ── Filters ──────────────────────────────────────────────── --}}
    <div class="card mb-3">
        <div class="card-body py-3">

            {{-- Row 1: quick ranges (scrollable on mobile) --}}
            <div class="d-flex gap-1 mb-2 overflow-x-auto pb-1" style="white-space:nowrap">
                <button type="button" class="settings-tab-btn flex-shrink-0" @click="setRange('today')" :class="activeRange === 'today' && 'active'">Today</button>
                <button type="button" class="settings-tab-btn flex-shrink-0" @click="setRange('week')"  :class="activeRange === 'week'  && 'active'">This Week</button>
                <button type="button" class="settings-tab-btn flex-shrink-0" @click="setRange('month')" :class="activeRange === 'month' && 'active'">This Month</button>
                <button type="button" class="settings-tab-btn flex-shrink-0" @click="setRange('year')"  :class="activeRange === 'year'  && 'active'">This Year</button>
            </div>

            {{-- Row 2: date pickers + branch + actions --}}
            <div class="d-flex flex-wrap align-items-center gap-2">

                {{-- Date range --}}
                <div class="d-flex align-items-center gap-1 flex-grow-1" style="min-width:220px;max-width:340px">
                    <input x-model="dateFrom" type="date" class="form-control form-control-sm w-50" aria-label="From date">
                    <span class="text-muted flex-shrink-0">–</span>
                    <input x-model="dateTo"   type="date" class="form-control form-control-sm w-50" aria-label="To date">
                </div>

                @isset($availableBranches)
                    @if($availableBranches->count() > 1 || ($canSeeAllBranches ?? false))
                    <select x-model="branchId" class="form-select form-select-sm" style="max-width:180px" aria-label="Branch">
                        @if($canSeeAllBranches ?? false)
                            <option value="all">All branches</option>
                        @endif
                        @foreach($availableBranches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}@if($b->is_main) (Main){{-- --}}@endif</option>
                        @endforeach
                    </select>
                    @endif
                @endisset

                {{-- Actions --}}
                <div class="d-flex align-items-center gap-2 ms-lg-auto flex-wrap">
                    <button type="button" @click="loadActive(true)" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel"></i>Apply
                    </button>

                    {{-- Saved presets --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-bookmark-star"></i><span class="d-none d-sm-inline ms-1">Presets</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width:260px">
                            <li class="px-3 py-2">
                                <input x-model="presetName" type="text" class="form-control form-control-sm" placeholder="Preset name">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" x-model="presetShared" id="presetShared">
                                    <label class="form-check-label small" for="presetShared">Shared with team</label>
                                </div>
                                <button @click="savePreset()" class="btn btn-sm btn-primary w-100 mt-2">Save current</button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <template x-for="p in presets" :key="p.id">
                                <li class="d-flex align-items-center justify-content-between px-3">
                                    <button @click="applyPreset(p)" class="btn btn-link btn-sm text-decoration-none p-1 text-start flex-grow-1">
                                        <span x-text="p.name"></span>
                                        <small class="text-muted ms-1" x-text="'(' + p.report_type + ')'"></small>
                                    </button>
                                    <button @click="deletePreset(p)" class="btn btn-sm btn-link text-danger p-1">
                                        <i class="bi bi-x-lg small"></i>
                                    </button>
                                </li>
                            </template>
                            <template x-if="presets.length === 0">
                                <li class="px-3 py-2 small text-muted">No saved presets yet</li>
                            </template>
                        </ul>
                    </div>

                    {{-- Exports --}}
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download"></i><span class="d-none d-sm-inline ms-1">Export</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" @click.prevent="exportFile('pdf')"><i class="bi bi-file-pdf me-2"></i>PDF (combined)</a></li>
                            <li><a class="dropdown-item" href="#" @click.prevent="exportFile('excel')"><i class="bi bi-file-excel me-2"></i>Excel (current tab)</a></li>
                            <li><a class="dropdown-item" href="#" @click.prevent="exportFile('csv')"><i class="bi bi-file-text me-2"></i>CSV (current tab)</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" @click.prevent="window.print()"><i class="bi bi-printer me-2"></i>Print</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Tabs ────────────────────────────────────────────────── --}}
    <div class="overflow-x-auto pb-1 mb-3">
        <ul class="nav nav-pills flex-nowrap gap-2" style="white-space:nowrap">
            <template x-for="t in tabs" :key="t.key">
                <li class="nav-item flex-shrink-0">
                    <button class="nav-link"
                            :class="active === t.key ? 'active' : ''"
                            @click="switchTab(t.key)">
                        <i :class="'bi me-1 ' + t.icon"></i>
                        <span x-text="t.label"></span>
                    </button>
                </li>
            </template>
        </ul>
    </div>

    {{-- ── Loading bar ─────────────────────────────────────────── --}}
    <div class="progress mb-3" style="height:3px" x-show="loading" x-transition>
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:100%"></div>
    </div>

    {{-- ── OVERVIEW ────────────────────────────────────────────── --}}
    <div x-show="active === 'overview'">
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <x-stat-card label="Gross Revenue" color="green" icon="bi-cash-coin">
                    <x-slot name="value"><span x-text="peso(financial.gross_revenue)"></span></x-slot>
                </x-stat-card>
            </div>
            <div class="col-6 col-xl-3">
                <x-stat-card label="Net Revenue" color="emerald" icon="bi-graph-up-arrow">
                    <x-slot name="value"><span x-text="peso(financial.net_revenue)"></span></x-slot>
                </x-stat-card>
            </div>
            <div class="col-6 col-xl-3">
                <x-stat-card label="Refunds" color="red" icon="bi-arrow-counterclockwise">
                    <x-slot name="value"><span x-text="peso(financial.refunds)"></span></x-slot>
                </x-stat-card>
            </div>
            <div class="col-6 col-xl-3">
                <x-stat-card label="Transactions" color="amber" icon="bi-receipt">
                    <x-slot name="value"><span x-text="revenue.transaction_count || 0"></span></x-slot>
                </x-stat-card>
            </div>
        </div>

        <div class="alert alert-info d-flex align-items-start gap-2" x-show="revenue.growth_pct !== null && revenue.growth_pct !== undefined">
            <i class="bi bi-info-circle mt-1"></i>
            <div>
                Revenue is
                <strong :class="(revenue.growth_pct||0) >= 0 ? 'text-success' : 'text-danger'"
                        x-text="(revenue.growth_pct >= 0 ? '+' : '') + (revenue.growth_pct || 0) + '%'"></strong>
                vs the previous period (<span x-text="peso(revenue.previous_total)"></span>).
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 fw-semibold">Daily Revenue</h6></div>
                    <div class="card-body"><div id="overviewRevenueChart" style="height:240px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 fw-semibold">Revenue by Payment Method</h6></div>
                    <div class="card-body"><div id="overviewMethodChart" style="height:240px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 fw-semibold">Court Occupancy</h6></div>
                    <div class="card-body"><div id="overviewOccupancyChart" style="height:240px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card">
                    <div class="card-header"><h6 class="mb-0 fw-semibold">Customer Retention</h6></div>
                    <div class="card-body d-flex align-items-center justify-content-center" style="min-height:240px">
                        <div class="text-center">
                            <div class="display-4 fw-bold text-success" x-text="(customers.retention_rate || 0) + '%'">0%</div>
                            <p class="text-muted small mt-2">Retention Rate</p>
                            <p class="small mt-3">
                                <span class="fw-medium" x-text="customers.active_customers || 0"></span>
                                <span class="text-muted"> active / </span>
                                <span class="fw-medium" x-text="customers.total_customers || 0"></span>
                                <span class="text-muted"> total</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── REVENUE ─────────────────────────────────────────────── --}}
    <div x-show="active === 'revenue'">
        <div class="d-flex gap-2 mb-3">
            <select x-model="revPeriod" @change="loadActive(true)" class="form-select form-select-sm" style="width:auto">
                <option value="day">Daily</option>
                <option value="week">Weekly</option>
                <option value="month">Monthly</option>
                <option value="year">Yearly</option>
            </select>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Revenue Trend</h6></div>
                    <div class="card-body"><div id="revPeriodChart" style="height:260px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">By Booking Type</h6></div>
                    <div class="card-body"><div id="revTypeChart" style="height:260px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">By Branch</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Branch</th><th class="text-end">Revenue</th></tr></thead>
                            <tbody>
                                <template x-for="b in revByBranch" :key="b.branch_id">
                                    <tr><td x-text="b.branch_name"></td><td class="text-end fw-medium" x-text="peso(b.total)"></td></tr>
                                </template>
                                <tr x-show="revByBranch.length === 0"><td colspan="2" class="text-center text-muted small py-3">No revenue in this period</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">By Court</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Court</th><th class="text-end">Bookings</th><th class="text-end">Revenue</th></tr></thead>
                            <tbody>
                                <template x-for="c in revByCourt" :key="c.court_id">
                                    <tr>
                                        <td x-text="c.court_name"></td>
                                        <td class="text-end" x-text="c.bookings"></td>
                                        <td class="text-end fw-medium" x-text="peso(c.total)"></td>
                                    </tr>
                                </template>
                                <tr x-show="revByCourt.length === 0"><td colspan="3" class="text-center text-muted small py-3">No revenue in this period</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Subscription Revenue (Memberships)</h6></div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-3">
                                <p class="stat-label">Total</p>
                                <p class="fs-5 fw-bold mb-0" x-text="peso(subRev.total)"></p>
                            </div>
                            <div class="col-6 col-md-3">
                                <p class="stat-label">Count</p>
                                <p class="fs-5 fw-bold mb-0" x-text="subRev.count || 0"></p>
                            </div>
                        </div>
                        <div id="subRevChart" style="height:200px"></div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card"><div class="card-header d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-semibold">Refunds &amp; Cancellations</h6>
                    <span class="badge bg-danger-subtle text-danger" x-text="peso(refunds.total_refunded) + ' refunded'"></span>
                </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Payment #</th><th>Customer</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">Refunded</th><th>Date</th></tr></thead>
                            <tbody>
                                <template x-for="r in refunds.rows || []" :key="r.payment_number">
                                    <tr>
                                        <td><code x-text="r.payment_number"></code></td>
                                        <td x-text="r.customer || '—'"></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary" x-text="r.method"></span></td>
                                        <td class="text-end" x-text="peso(r.amount)"></td>
                                        <td class="text-end text-danger fw-medium" x-text="peso(r.refund_amount)"></td>
                                        <td class="small text-muted" x-text="r.refunded_at"></td>
                                    </tr>
                                </template>
                                <tr x-show="(refunds.rows || []).length === 0"><td colspan="6" class="text-center text-muted small py-3">No refunds in this period</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── BOOKINGS ────────────────────────────────────────────── --}}
    <div x-show="active === 'bookings'">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3 col-xl-2"><x-stat-card label="Total" color="green" icon="bi-calendar-event"><x-slot name="value"><span x-text="bookings.total || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3 col-xl-2"><x-stat-card label="Completed" color="green" icon="bi-check-circle"><x-slot name="value"><span x-text="bookings.completed || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3 col-xl-2"><x-stat-card label="Cancelled" color="red" icon="bi-x-circle"><x-slot name="value"><span x-text="bookings.cancelled || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3 col-xl-2"><x-stat-card label="No-show" color="amber" icon="bi-person-x"><x-slot name="value"><span x-text="bookings.no_show || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3 col-xl-2"><x-stat-card label="Active" color="green" icon="bi-play-circle"><x-slot name="value"><span x-text="bookings.active || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3 col-xl-2"><x-stat-card label="Pending" color="slate" icon="bi-hourglass"><x-slot name="value"><span x-text="bookings.pending || 0"></span></x-slot></x-stat-card></div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Hourly Heatmap (Peak Hours)</h6></div>
                    <div class="card-body"><div id="bkHeatmap" style="height:260px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">By Source</h6></div>
                    <div class="card-body"><div id="bkSourceChart" style="height:260px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Most-Booked Days</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Day</th><th class="text-end">Bookings</th></tr></thead>
                            <tbody>
                                <template x-for="d in bookings.busiest_days || []" :key="d.day">
                                    <tr><td x-text="d.day"></td><td class="text-end fw-medium" x-text="d.count"></td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Peak Hours (Top 5)</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Hour</th><th class="text-end">Bookings</th></tr></thead>
                            <tbody>
                                <template x-for="h in bookings.peak_hours || []" :key="h.hour">
                                    <tr><td x-text="(h.hour + ':00').padStart(5,'0')"></td><td class="text-end fw-medium" x-text="h.count"></td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── COURTS ──────────────────────────────────────────────── --}}
    <div x-show="active === 'courts'">
        <div class="row g-3 mb-4" x-show="courts.best_performer">
            <div class="col-12 col-md-6">
                <div class="card" style="border-color:rgba(34,197,94,.4);background:rgba(34,197,94,.05)">
                    <div class="card-body">
                        <p class="stat-label text-success"><i class="bi bi-trophy me-1"></i>Best Performer</p>
                        <p class="fs-5 fw-bold mb-1" x-text="courts.best_performer?.court_name"></p>
                        <p class="small text-muted mb-0">
                            <span x-text="peso(courts.best_performer?.revenue)"></span> ·
                            <span x-text="courts.best_performer?.bookings"></span> bookings ·
                            <span x-text="courts.best_performer?.utilization_pct + '%'"></span> utilized
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card" style="border-color:rgba(244,63,94,.4);background:rgba(244,63,94,.05)">
                    <div class="card-body">
                        <p class="stat-label text-danger"><i class="bi bi-graph-down-arrow me-1"></i>Lowest Performer</p>
                        <p class="fs-5 fw-bold mb-1" x-text="courts.worst_performer?.court_name"></p>
                        <p class="small text-muted mb-0">
                            <span x-text="peso(courts.worst_performer?.revenue)"></span> ·
                            <span x-text="courts.worst_performer?.bookings"></span> bookings ·
                            <span x-text="courts.worst_performer?.utilization_pct + '%'"></span> utilized
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Court Performance Ranking</h6></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Court</th><th>Branch</th>
                            <th class="text-end">Bookings</th>
                            <th class="text-end">Hours</th>
                            <th class="text-end">Revenue</th>
                            <th class="text-end">Utilization</th>
                            <th class="text-end">Avg Session</th>
                            <th class="text-end">Downtime</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(c, i) in courts.rows || []" :key="c.court_id">
                            <tr>
                                <td class="text-muted small" x-text="i + 1"></td>
                                <td class="fw-medium" x-text="c.court_name"></td>
                                <td class="small text-muted" x-text="c.branch || '—'"></td>
                                <td class="text-end" x-text="c.bookings"></td>
                                <td class="text-end" x-text="c.hours_used + 'h'"></td>
                                <td class="text-end fw-medium" x-text="peso(c.revenue)"></td>
                                <td class="text-end">
                                    <span class="badge"
                                          :class="c.utilization_pct >= 60 ? 'bg-success-subtle text-success' : (c.utilization_pct >= 30 ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary')"
                                          x-text="c.utilization_pct + '%'"></span>
                                </td>
                                <td class="text-end small" x-text="c.avg_session_mins + 'm'"></td>
                                <td class="text-end small text-danger" x-text="c.downtime_mins + 'm'"></td>
                            </tr>
                        </template>
                        <tr x-show="(courts.rows || []).length === 0"><td colspan="9" class="text-center text-muted small py-4">No data in this period</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── MEMBERS ─────────────────────────────────────────────── --}}
    <div x-show="active === 'members'">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4 col-xl-2"><x-stat-card label="Total" color="green" icon="bi-people"><x-slot name="value"><span x-text="members.total_customers || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat-card label="Active" color="green" icon="bi-person-check"><x-slot name="value"><span x-text="members.active_customers || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat-card label="Inactive" color="slate" icon="bi-person-dash"><x-slot name="value"><span x-text="members.inactive_customers || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat-card label="New" color="green" icon="bi-person-plus"><x-slot name="value"><span x-text="members.new_signups || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat-card label="Expiring (30d)" color="amber" icon="bi-hourglass-bottom"><x-slot name="value"><span x-text="members.expiring_soon || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-4 col-xl-2"><x-stat-card label="Expired" color="red" icon="bi-calendar-x"><x-slot name="value"><span x-text="members.expired || 0"></span></x-slot></x-stat-card></div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Plan Distribution</h6></div>
                    <div class="card-body"><div id="memPlanChart" style="height:240px"></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Top Spenders (Lifetime)</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Member</th><th class="text-end">LTV</th></tr></thead>
                            <tbody>
                                <template x-for="m in members.top_spenders || []" :key="m.id">
                                    <tr>
                                        <td><div class="fw-medium" x-text="m.name"></div><div class="small text-muted" x-text="m.email"></div></td>
                                        <td class="text-end fw-medium" x-text="peso(m.ltv)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Most Frequent Players (in period)</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Member</th><th class="text-end">Bookings</th></tr></thead>
                            <tbody>
                                <template x-for="m in members.most_frequent || []" :key="m.customer_id">
                                    <tr>
                                        <td><div class="fw-medium" x-text="m.name"></div><div class="small text-muted" x-text="m.email"></div></td>
                                        <td class="text-end fw-medium" x-text="m.bookings"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── PAYMENTS ────────────────────────────────────────────── --}}
    <div x-show="active === 'payments'">
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><x-stat-card label="Gross" color="green" icon="bi-cash-coin"><x-slot name="value"><span x-text="peso(payments.gross)"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-xl-3"><x-stat-card label="Fees" color="amber" icon="bi-percent"><x-slot name="value"><span x-text="peso(payments.fees)"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-xl-3"><x-stat-card label="Net" color="primary" icon="bi-bank"><x-slot name="value"><span x-text="peso(payments.net)"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-xl-3"><x-stat-card label="Failed" color="red" icon="bi-exclamation-triangle"><x-slot name="value"><span x-text="payments.by_status?.failed?.count || 0"></span></x-slot></x-stat-card></div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Payments by Status</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Status</th><th class="text-end">Count</th><th class="text-end">Total</th></tr></thead>
                            <tbody>
                                <template x-for="(v, k) in payments.by_status || {}" :key="k">
                                    <tr>
                                        <td><span class="badge text-capitalize"
                                            :class="{'bg-success-subtle text-success': k === 'paid', 'bg-warning-subtle text-warning': k === 'pending' || k === 'partial' || k === 'overdue', 'bg-danger-subtle text-danger': k === 'failed' || k === 'refunded'}"
                                            x-text="k"></span></td>
                                        <td class="text-end" x-text="v.count"></td>
                                        <td class="text-end fw-medium" x-text="peso(v.total)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Payment Method Breakdown</h6></div>
                    <div class="card-body"><div id="payMethodChart" style="height:240px"></div></div>
                </div>
            </div>
            <div class="col-12">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Settlement (Reconciliation)</h6></div>
                    <div class="card-body">
                        <div class="row g-3 text-center">
                            <div class="col-md-4"><p class="stat-label">Instant (cash + wallet)</p><p class="fs-5 fw-bold mb-0 text-success" x-text="peso(payments.settlement?.instant)"></p></div>
                            <div class="col-md-4"><p class="stat-label">Bank Transfer</p><p class="fs-5 fw-bold mb-0 text-primary" x-text="peso(payments.settlement?.bank)"></p></div>
                            <div class="col-md-4"><p class="stat-label">Gateway (T+1/T+2)</p><p class="fs-5 fw-bold mb-0" x-text="peso(payments.settlement?.gateway)"></p></div>
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            Net of gateway fees. Cash and wallet settle at the desk; bank transfers usually clear same day; gateway funds (GCash, Maya, PayMongo, Stripe, card) clear T+1 or T+2.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── AUDIT ───────────────────────────────────────────────── --}}
    <div x-show="active === 'audit'">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-4"><x-stat-card label="Total Actions" color="green" icon="bi-list-check"><x-slot name="value"><span x-text="audit.total_actions || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-4"><x-stat-card label="Booking Changes" color="green" icon="bi-pencil-square"><x-slot name="value"><span x-text="audit.booking_modifications || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-4"><x-stat-card label="Payment Changes" color="green" icon="bi-credit-card-2-back"><x-slot name="value"><span x-text="audit.payment_modifications || 0"></span></x-slot></x-stat-card></div>
        </div>
        <div class="row g-4">
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Actions by Area</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>Area</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                                <template x-for="r in audit.by_log_name || []" :key="r.log_name">
                                    <tr><td><span class="badge bg-secondary-subtle text-secondary text-capitalize" x-text="r.log_name"></span></td><td class="text-end fw-medium" x-text="r.count"></td></tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card"><div class="card-header"><h6 class="mb-0 fw-semibold">Most Active Staff</h6></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th>User</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                                <template x-for="u in audit.top_users || []" :key="u.user_id">
                                    <tr><td class="fw-medium" x-text="u.name"></td><td class="text-end fw-medium" x-text="u.actions"></td></tr>
                                </template>
                            </tbody>
                        </table>
                        <p class="small text-muted mt-2 mb-0">Full audit trail available at <a href="{{ route('admin.audit.index') }}">Audit Log</a>.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── BEHAVIOR ────────────────────────────────────────────── --}}
    <div x-show="active === 'behavior'">
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3"><x-stat-card label="Repeat Rate" color="green" icon="bi-arrow-repeat"><x-slot name="value"><span x-text="(behavior.repeat_rate_pct || 0) + '%'"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3"><x-stat-card label="Avg Frequency" color="green" icon="bi-calendar2-check"><x-slot name="value"><span x-text="behavior.avg_frequency || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3"><x-stat-card label="First-Timers" color="green" icon="bi-stars"><x-slot name="value"><span x-text="behavior.first_timers || 0"></span></x-slot></x-stat-card></div>
            <div class="col-6 col-md-3"><x-stat-card label="Returning" color="amber" icon="bi-arrow-return-left"><x-slot name="value"><span x-text="behavior.returning || 0"></span></x-slot></x-stat-card></div>
        </div>
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Preferred Booking Times</h6></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Hour</th><th class="text-end">Bookings</th></tr></thead>
                    <tbody>
                        <template x-for="h in behavior.preferred_hours || []" :key="h.hour">
                            <tr><td x-text="(h.hour + ':00').padStart(5,'0')"></td><td class="text-end fw-medium" x-text="h.count"></td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
function reports() {
    const today = new Date();
    const y = today.getFullYear(), m = today.getMonth();
    const pad = n => String(n).padStart(2, '0');
    const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
    const isDark = () => document.documentElement.getAttribute('data-bs-theme') === 'dark';

    return {
        // --- shared state ---
        dateFrom: fmt(new Date(y, m, 1)),
        dateTo: fmt(today),
        branchId: @json(($canSeeAllBranches ?? false) ? 'all' : ($activeBranchId ?? 'all')),
        loading: false,
        active: 'overview',
        activeRange: 'month', // tracks which quick-range button is currently selected for highlight
        loaded: {},
        charts: {},

        // --- presets ---
        presets: @json($presets ?? []),
        presetName: '',
        presetShared: false,

        // --- per-tab data buckets ---
        revenue: {}, financial: {}, occupancy: [], customers: {},
        revPeriod: 'day', revPeriodData: [], revByBranch: [], revByCourt: [], revByType: [], subRev: {}, refunds: {rows:[]},
        bookings: {}, courts: {}, members: {}, payments: {},
        audit: {}, behavior: {},

        tabs: [
            { key: 'overview', label: 'Overview',  icon: 'bi-speedometer2' },
            { key: 'revenue',  label: 'Revenue',   icon: 'bi-cash-coin' },
            { key: 'bookings', label: 'Bookings',  icon: 'bi-calendar-event' },
            { key: 'courts',   label: 'Courts',    icon: 'bi-grid-3x3' },
            { key: 'members',  label: 'Members',   icon: 'bi-people' },
            { key: 'payments', label: 'Payments',  icon: 'bi-credit-card' },
            { key: 'audit',    label: 'Audit',     icon: 'bi-shield-check' },
            { key: 'behavior', label: 'Behavior',  icon: 'bi-bar-chart-steps' },
        ],

        peso(v) { return '₱' + Number(v || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },

        setRange(range) {
            // Recompute "now" each click so a tab left open overnight doesn't
            // keep using the original page-load date for "today".
            const now = new Date();
            const ny = now.getFullYear(), nm = now.getMonth();
            if (range === 'today') {
                this.dateFrom = this.dateTo = fmt(now);
            }
            else if (range === 'week') {
                // ISO week: Monday → Sunday. JS getDay() returns 0 for Sunday,
                // so map that to 6 days from the prior Monday rather than 0.
                const dow = (now.getDay() + 6) % 7; // Mon=0, Tue=1, ..., Sun=6
                const mon = new Date(now); mon.setDate(now.getDate() - dow);
                const sun = new Date(mon); sun.setDate(mon.getDate() + 6);
                this.dateFrom = fmt(mon); this.dateTo = fmt(sun);
            }
            else if (range === 'month') {
                this.dateFrom = fmt(new Date(ny, nm, 1));
                this.dateTo   = fmt(new Date(ny, nm + 1, 0)); // last day of this month
            }
            else if (range === 'year') {
                this.dateFrom = `${ny}-01-01`;
                this.dateTo   = `${ny}-12-31`;
            }
            this.activeRange = range;
            this.loadActive(true);
        },

        async init() {
            await this.loadActive(true);
            window.addEventListener('theme-changed', () => this.renderActiveCharts());
        },

        async switchTab(key) {
            this.active = key;
            if (!this.loaded[key]) await this.loadActive(true);
            else this.$nextTick(() => this.renderActiveCharts());
        },

        async loadActive(force = false) {
            this.loading = true;
            try {
                const qs = `from=${this.dateFrom}&to=${this.dateTo}&branch_id=${encodeURIComponent(this.branchId)}`;
                // Wrap fetch so an HTTP 500 (or non-JSON response) doesn't kill
                // the whole Promise.all and leave the UI stuck on a stale tab.
                const j = async url => {
                    try {
                        const res = await fetch(`${window.APP_BASE}${url}?${qs}`);
                        if (!res.ok) { console.warn(`Report fetch ${url} → HTTP ${res.status}`); return null; }
                        return await res.json();
                    } catch (e) {
                        console.warn(`Report fetch ${url} failed`, e);
                        return null;
                    }
                };

                switch (this.active) {
                    case 'overview': {
                        const [r, f, o, c] = await Promise.all([
                            j('/admin/reports/revenue'), j('/admin/reports/financial'),
                            j('/admin/reports/occupancy'), j('/admin/reports/customers'),
                        ]);
                        this.revenue = r || {}; this.financial = f || {};
                        this.occupancy = o || []; this.customers = c || {};
                        break;
                    }
                    case 'revenue': {
                        const periodUrl = `/admin/reports/revenue-by-period?${qs}&period=${this.revPeriod}`;
                        const periodFetch = async () => {
                            try {
                                const res = await fetch(`${window.APP_BASE}${periodUrl}`);
                                return res.ok ? await res.json() : [];
                            } catch { return []; }
                        };
                        const [p, b, c, t, s, rf, sum] = await Promise.all([
                            periodFetch(),
                            j('/admin/reports/revenue-by-branch'), j('/admin/reports/revenue-by-court'),
                            j('/admin/reports/revenue-by-booking-type'), j('/admin/reports/subscription-revenue'),
                            j('/admin/reports/refunds'), j('/admin/reports/revenue'),
                        ]);
                        this.revPeriodData = p || []; this.revByBranch = b || []; this.revByCourt = c || [];
                        this.revByType = t || []; this.subRev = s || {}; this.refunds = rf || {rows:[]}; this.revenue = sum || {};
                        break;
                    }
                    case 'bookings': this.bookings = (await j('/admin/reports/bookings')) || {}; break;
                    case 'courts':   this.courts   = (await j('/admin/reports/courts')) || {}; break;
                    case 'members':  this.members  = (await j('/admin/reports/members')) || {}; break;
                    case 'payments': this.payments = (await j('/admin/reports/payments')) || {}; break;
                    case 'audit':    this.audit    = (await j('/admin/reports/audit')) || {}; break;
                    case 'behavior': this.behavior = (await j('/admin/reports/behavior')) || {}; break;
                }
                this.loaded[this.active] = true;
                this.$nextTick(() => this.renderActiveCharts());
            } catch (e) {
                console.error('loadActive failed', e);
            } finally {
                this.loading = false;
            }
        },

        chartOpts() {
            const dark = isDark();
            return {
                chart: { fontFamily: 'inherit', toolbar: { show: false }, foreColor: dark ? '#adb5bd' : '#6c757d' },
                grid: { borderColor: dark ? '#2a2d31' : '#e9ecef' },
            };
        },

        destroyChart(key) { if (this.charts[key]) { this.charts[key].destroy(); delete this.charts[key]; } },

        renderActiveCharts() {
            const o = this.chartOpts();
            const palette = ['#198754','#0d6efd','#ffc107','#6f42c1','#d63384','#fd7e14','#20c997','#0dcaf0'];

            if (this.active === 'overview') {
                this.destroyChart('overviewRevenueChart');
                const daily = this.revenue.daily_breakdown || {};
                this.charts.overviewRevenueChart = new ApexCharts(document.querySelector('#overviewRevenueChart'), {
                    ...o, chart: {...o.chart, type: 'bar', height: 220},
                    series: [{ name: 'Revenue', data: Object.values(daily).map(Number) }],
                    xaxis: { categories: Object.keys(daily) },
                    yaxis: { labels: { formatter: v => '₱' + Number(v).toLocaleString() } },
                    dataLabels: { enabled: false }, colors: [palette[0]],
                });
                this.charts.overviewRevenueChart.render();

                this.destroyChart('overviewOccupancyChart');
                this.charts.overviewOccupancyChart = new ApexCharts(document.querySelector('#overviewOccupancyChart'), {
                    ...o, chart: {...o.chart, type: 'bar', height: 220},
                    series: [{ name: 'Occupancy %', data: (this.occupancy || []).map(c => c.occupancy_rate) }],
                    xaxis: { categories: (this.occupancy || []).map(c => c.court_name) },
                    yaxis: { max: 100, labels: { formatter: v => v + '%' } },
                    dataLabels: { enabled: false }, colors: [palette[1]],
                });
                this.charts.overviewOccupancyChart.render();

                this.destroyChart('overviewMethodChart');
                const byMethod = this.revenue.by_method || {};
                this.charts.overviewMethodChart = new ApexCharts(document.querySelector('#overviewMethodChart'), {
                    ...o, chart: {...o.chart, type: 'donut', height: 220},
                    series: Object.values(byMethod).map(Number),
                    labels: Object.keys(byMethod).map(k => k.replace('_', ' ').toUpperCase()),
                    colors: palette,
                    legend: { position: 'bottom' },
                    dataLabels: { enabled: true, formatter: (v, opts) => opts.w.globals.labels[opts.seriesIndex] },
                });
                this.charts.overviewMethodChart.render();
            }

            if (this.active === 'revenue') {
                this.destroyChart('revPeriodChart');
                this.charts.revPeriodChart = new ApexCharts(document.querySelector('#revPeriodChart'), {
                    ...o, chart: {...o.chart, type: 'line', height: 260},
                    series: [{ name: 'Revenue', data: (this.revPeriodData || []).map(r => r.total) }],
                    xaxis: { categories: (this.revPeriodData || []).map(r => r.bucket) },
                    yaxis: { labels: { formatter: v => '₱' + Number(v).toLocaleString() } },
                    stroke: { curve: 'smooth', width: 3 }, colors: [palette[0]],
                });
                this.charts.revPeriodChart.render();

                this.destroyChart('revTypeChart');
                this.charts.revTypeChart = new ApexCharts(document.querySelector('#revTypeChart'), {
                    ...o, chart: {...o.chart, type: 'pie', height: 260},
                    series: (this.revByType || []).map(r => r.total),
                    labels: (this.revByType || []).map(r => r.type),
                    colors: palette, legend: { position: 'bottom' },
                });
                this.charts.revTypeChart.render();

                this.destroyChart('subRevChart');
                const daily = this.subRev.daily || [];
                this.charts.subRevChart = new ApexCharts(document.querySelector('#subRevChart'), {
                    ...o, chart: {...o.chart, type: 'area', height: 200},
                    series: [{ name: 'Memberships', data: daily.map(d => d.total) }],
                    xaxis: { categories: daily.map(d => d.date) },
                    yaxis: { labels: { formatter: v => '₱' + Number(v).toLocaleString() } },
                    stroke: { curve: 'smooth' }, colors: [palette[3]], fill: { type: 'gradient' },
                });
                this.charts.subRevChart.render();
            }

            if (this.active === 'bookings') {
                this.destroyChart('bkHeatmap');
                this.charts.bkHeatmap = new ApexCharts(document.querySelector('#bkHeatmap'), {
                    ...o, chart: {...o.chart, type: 'bar', height: 260},
                    series: [{ name: 'Bookings', data: (this.bookings.heatmap || []).map(h => h.count) }],
                    xaxis: { categories: (this.bookings.heatmap || []).map(h => h.hour + 'h') },
                    colors: [palette[1]], dataLabels: { enabled: false },
                });
                this.charts.bkHeatmap.render();

                this.destroyChart('bkSourceChart');
                const src = this.bookings.by_source || {};
                this.charts.bkSourceChart = new ApexCharts(document.querySelector('#bkSourceChart'), {
                    ...o, chart: {...o.chart, type: 'donut', height: 260},
                    series: Object.values(src), labels: Object.keys(src),
                    colors: palette, legend: { position: 'bottom' },
                });
                this.charts.bkSourceChart.render();
            }

            if (this.active === 'members') {
                this.destroyChart('memPlanChart');
                const pd = this.members.plan_distribution || [];
                this.charts.memPlanChart = new ApexCharts(document.querySelector('#memPlanChart'), {
                    ...o, chart: {...o.chart, type: 'pie', height: 240},
                    series: pd.map(p => p.count), labels: pd.map(p => p.plan),
                    colors: palette, legend: { position: 'bottom' },
                });
                this.charts.memPlanChart.render();
            }

            if (this.active === 'payments') {
                this.destroyChart('payMethodChart');
                const m = this.payments.by_method || [];
                this.charts.payMethodChart = new ApexCharts(document.querySelector('#payMethodChart'), {
                    ...o, chart: {...o.chart, type: 'bar', height: 240},
                    series: [{ name: 'Net', data: m.map(r => r.total - r.fees) }],
                    xaxis: { categories: m.map(r => r.method) },
                    yaxis: { labels: { formatter: v => '₱' + Number(v).toLocaleString() } },
                    colors: [palette[0]], dataLabels: { enabled: false },
                });
                this.charts.payMethodChart.render();
            }
        },

        // --- exports ---
        exportFile(format) {
            const qs = `from=${this.dateFrom}&to=${this.dateTo}&branch_id=${encodeURIComponent(this.branchId)}`;
            if (format === 'pdf') {
                window.location.href = `${window.APP_BASE}/admin/reports/pdf?${qs}`;
                return;
            }
            // Excel / CSV — type is derived from the active tab.
            const type = this.active === 'overview' ? 'revenue' : this.active;
            const allowed = ['revenue','bookings','courts','members','payments','refunds','audit'];
            const finalType = allowed.includes(type) ? type : 'revenue';
            window.location.href = `${window.APP_BASE}/admin/reports/spreadsheet?${qs}&type=${finalType}&format=${format}`;
        },

        // --- presets ---
        async savePreset() {
            if (!this.presetName.trim()) { alert('Please enter a preset name'); return; }
            const res = await fetch(`${window.APP_BASE}/admin/reports/presets`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({
                    name: this.presetName,
                    report_type: this.active,
                    is_shared: this.presetShared,
                    filters: { from: this.dateFrom, to: this.dateTo, branch_id: this.branchId, period: this.revPeriod },
                }),
            });
            if (res.ok) {
                const p = await res.json();
                this.presets.push(p);
                this.presetName = ''; this.presetShared = false;
            }
        },
        async deletePreset(p) {
            if (!confirm(`Delete preset "${p.name}"?`)) return;
            const res = await fetch(`${window.APP_BASE}/admin/reports/presets/${p.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            });
            if (res.status === 204) this.presets = this.presets.filter(x => x.id !== p.id);
        },
        applyPreset(p) {
            const f = p.filters || {};
            if (f.from) this.dateFrom = f.from;
            if (f.to) this.dateTo = f.to;
            if (f.branch_id !== undefined && f.branch_id !== null) this.branchId = f.branch_id;
            if (f.period) this.revPeriod = f.period;
            if (p.report_type && p.report_type !== 'overview') this.active = p.report_type;
            this.loadActive(true);
        },
    };
}
</script>
@endpush
