<!DOCTYPE html>
<html lang="en" x-data>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#059669">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
    @include('partials.favicon')
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>
    <script>window.APP_BASE = @json(url(''));</script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('styles')
</head>
<body class="has-mobile-nav">

@php
    $u = auth()->user();
    $isOwner = $u && ($u->hasRole('business_owner') || $u->isBusinessOwner());
@endphp

{{-- ── Mobile sidebar overlay ──────────────────────────────────────────── --}}
<div id="sidebar-overlay" class="sidebar-overlay" @click="$store.sidebar.closeMobile()"></div>

{{-- ── Sidebar ──────────────────────────────────────────────────────────── --}}
<aside id="sidebar" class="sidebar">

    {{-- Brand --}}
    <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
            </svg>
        </div>
        <div class="sidebar-brand-text">
            <span class="brand-name">{{ config('app.name') }}</span>
            @if(isset($currentTenant))
                <span class="brand-tenant">{{ $currentTenant->name }}</span>
            @endif
        </div>
    </a>

    {{-- Navigation (role/permission filtered) --}}
    <nav class="sidebar-nav">

        <x-nav-item href="{{ route('admin.dashboard') }}" icon="bi-house-door"
            :active="request()->routeIs('admin.dashboard')">Dashboard</x-nav-item>

        <x-nav-item href="{{ route('admin.staff.my-shift') }}" icon="bi-stopwatch"
            :active="request()->routeIs('admin.staff.my-shift')">My Shift</x-nav-item>

        @if ($isOwner || $u->can('courts.view') || $u->can('bookings.view') || $u->can('pos.access'))
            <div class="nav-section-label">Operations</div>
            <div class="nav-divider"></div>
        @endif

        @if ($isOwner || $u->can('branches.view'))
            <x-nav-item href="{{ route('admin.branches.index') }}" icon="bi-shop"
                :active="request()->routeIs('admin.branches.*')">Branches</x-nav-item>
        @endif

        @if ($isOwner || $u->can('courts.view'))
            <x-nav-item href="{{ route('admin.courts.index') }}" icon="bi-grid"
                :active="request()->routeIs('admin.courts.index') || request()->routeIs('admin.courts.show') || request()->routeIs('admin.courts.create') || request()->routeIs('admin.courts.edit')">Courts</x-nav-item>

            <x-nav-item href="{{ route('admin.courts.status-board') }}" icon="bi-grid-3x3-gap"
                :active="request()->routeIs('admin.courts.status-board')">Court Status Board</x-nav-item>
        @endif

        @if ($isOwner || $u->can('bookings.view'))
            <x-nav-item href="{{ route('admin.bookings.index') }}" icon="bi-calendar-check"
                :active="request()->routeIs('admin.bookings.index') || request()->routeIs('admin.bookings.show') || request()->routeIs('admin.bookings.create')">Bookings</x-nav-item>

            <x-nav-item href="{{ route('admin.bookings.calendar') }}" icon="bi-calendar3"
                :active="request()->routeIs('admin.bookings.calendar')">Booking Calendar</x-nav-item>
        @endif

        @if ($isOwner || $u->can('pos.access'))
            <x-nav-item href="{{ route('admin.pos.index') }}" icon="bi-receipt"
                :active="request()->routeIs('admin.pos.*')">Point of Sale</x-nav-item>
        @endif

        @if ($isOwner || $u->can('courts.view') || $u->can('bookings.view'))
            <x-nav-item href="{{ route('admin.display.index') }}" icon="bi-tv" target="_blank"
                :active="request()->routeIs('admin.display.*')">Smart Display</x-nav-item>
        @endif

        @if ($isOwner || $u->can('memberships.view') || $u->can('customers.view'))
            <div class="nav-section-label">Members</div>
            <div class="nav-divider"></div>
        @endif

        @if ($isOwner || $u->can('memberships.view'))
            <x-nav-item href="{{ route('admin.memberships.index') }}" icon="bi-credit-card"
                :active="request()->routeIs('admin.memberships.*')">Memberships</x-nav-item>
        @endif

        @if ($isOwner || $u->can('customers.view'))
            <x-nav-item href="{{ route('admin.customers.index') }}" icon="bi-people"
                :active="request()->routeIs('admin.customers.*')">Customers</x-nav-item>

            <x-nav-item href="{{ route('admin.wallet.index') }}" icon="bi-wallet2"
                :active="request()->routeIs('admin.wallet.*')">Wallet Management</x-nav-item>

            <x-nav-item href="{{ route('admin.refund-requests.index') }}" icon="bi-arrow-counterclockwise"
                :active="request()->routeIs('admin.refund-requests.*')">Refund Requests</x-nav-item>
        @endif

        @if ($isOwner || $u->can('tournaments.view'))
            @php($tournamentActive = request()->routeIs('admin.tournaments.*'))
            <div class="nav-group {{ $tournamentActive ? 'open' : '' }}"
                x-data="{ open: {{ $tournamentActive ? 'true' : 'false' }} }"
                :class="{ 'open': open }">
                <button type="button" class="nav-link nav-group-toggle {{ $tournamentActive ? 'active-parent' : '' }}"
                    @click="open = !open" :aria-expanded="open">
                    <span class="nav-icon"><i class="bi bi-trophy"></i></span>
                    <span class="nav-label">Tournament</span>
                    <i class="bi bi-chevron-down nav-group-caret"></i>
                </button>

                <div class="nav-group-items">
                    <div class="nav-group-inner">
                        <x-nav-item href="{{ route('admin.tournaments.dashboard') }}" icon="bi-speedometer2"
                            :active="request()->routeIs('admin.tournaments.dashboard')">Dashboard</x-nav-item>

                        <x-nav-item href="{{ route('admin.tournaments.index') }}" icon="bi-trophy"
                            :active="request()->routeIs('admin.tournaments.index') || request()->routeIs('admin.tournaments.show') || request()->routeIs('admin.tournaments.create') || request()->routeIs('admin.tournaments.edit')">Tournaments</x-nav-item>

                        <x-nav-item href="{{ route('admin.tournaments.divisions.index') }}" icon="bi-diagram-3"
                            :active="request()->routeIs('admin.tournaments.divisions.*')">Divisions</x-nav-item>

                        <x-nav-item href="{{ route('admin.tournaments.brackets.index') }}" icon="bi-bezier2"
                            :active="request()->routeIs('admin.tournaments.brackets.*')">Brackets</x-nav-item>

                        <x-nav-item href="{{ route('admin.tournaments.matches.index') }}" icon="bi-controller"
                            :active="request()->routeIs('admin.tournaments.matches.*')">Matches</x-nav-item>

                        <x-nav-item href="{{ route('admin.tournaments.teams.index') }}" icon="bi-people-fill"
                            :active="request()->routeIs('admin.tournaments.teams.*')">Teams</x-nav-item>

                        <x-nav-item href="{{ route('admin.tournaments.rankings.index') }}" icon="bi-list-ol"
                            :active="request()->routeIs('admin.tournaments.rankings.*')">Rankings</x-nav-item>

                        @if ($isOwner || $u->can('tournaments.reports'))
                        <x-nav-item href="{{ route('admin.tournaments.reports.index') }}" icon="bi-clipboard-data"
                            :active="request()->routeIs('admin.tournaments.reports.*')">Reports</x-nav-item>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($isOwner || $u->can('staff.view') || $u->can('inventory.view') || $u->can('promotions.view') || $u->can('reports.view'))
            <div class="nav-section-label">Business</div>
            <div class="nav-divider"></div>
        @endif

        @if ($isOwner || $u->can('staff.view'))
            <x-nav-item href="{{ route('admin.staff.index') }}" icon="bi-person-badge"
                :active="request()->routeIs('admin.staff.index') || request()->routeIs('admin.staff.show') || request()->routeIs('admin.staff.create') || request()->routeIs('admin.staff.edit')">Staff</x-nav-item>

            <x-nav-item href="{{ route('admin.staff.shifts') }}" icon="bi-clock-history"
                :active="request()->routeIs('admin.staff.shifts')">Shifts</x-nav-item>
        @endif

        @if ($isOwner || $u->can('inventory.view'))
            <x-nav-item href="{{ route('admin.products.index') }}" icon="bi-box-seam"
                :active="request()->routeIs('admin.products.*') || request()->routeIs('admin.categories.*')">Inventory</x-nav-item>

            <x-nav-item href="{{ route('admin.suppliers.index') }}" icon="bi-truck"
                :active="request()->routeIs('admin.suppliers.*')">Suppliers</x-nav-item>

            <x-nav-item href="{{ route('admin.purchase-orders.index') }}" icon="bi-clipboard-check"
                :active="request()->routeIs('admin.purchase-orders.*')">Purchase Orders</x-nav-item>
        @endif

        @if ($isOwner || $u->can('promotions.view'))
            <x-nav-item href="{{ route('admin.promotions.index') }}" icon="bi-tag"
                :active="request()->routeIs('admin.promotions.*')">Promotions</x-nav-item>
        @endif

        @if ($isOwner || $u->can('reports.view'))
            <x-nav-item href="{{ route('admin.reports.index') }}" icon="bi-bar-chart-line"
                :active="request()->routeIs('admin.reports.*')">Reports</x-nav-item>
        @endif

        @if ($isOwner)
            <div class="nav-section-label">System</div>
            <div class="nav-divider"></div>

            <x-nav-item href="{{ route('admin.audit.index') }}" icon="bi-shield-check"
                :active="request()->routeIs('admin.audit.*')">Audit Log</x-nav-item>

            <x-nav-item href="{{ route('admin.subscription') }}" icon="bi-stars"
                :active="request()->routeIs('admin.subscription')">My Subscription</x-nav-item>

            <x-nav-item href="{{ route('admin.subscription-invoices.index') }}" icon="bi-receipt-cutoff"
                :active="request()->routeIs('admin.subscription-invoices.*')">Subscription Invoices</x-nav-item>

            <x-nav-item href="{{ route('admin.roles.index') }}" icon="bi-shield-lock"
                :active="request()->routeIs('admin.roles.*')">Roles & Permissions</x-nav-item>

            <x-nav-item href="{{ route('admin.settings.index') }}" icon="bi-gear"
                :active="request()->routeIs('admin.settings.*')">Settings</x-nav-item>
        @endif

    </nav>

    @include('partials.sidebar-footer')
</aside>

{{-- ── Topbar ────────────────────────────────────────────────────────────── --}}
<header id="topbar" class="topbar">

    <button class="topbar-btn d-lg-none" @click="$store.sidebar.openMobile()" aria-label="Menu">
        <i class="bi bi-list fs-5"></i>
    </button>

    <h1 class="topbar-title d-none d-lg-block">@yield('title', 'Dashboard')</h1>
    <span class="topbar-title d-lg-none">{{ config('app.name') }}</span>

    @include('partials.topbar-actions', [
        'profileRoute'   => 'admin.account',
        'settingsRoute'  => $isOwner ? 'admin.settings.index' : null,
        'twoFactorRoute' => '2fa.show',
        'devicesRoute'   => 'devices.index',
    ])
</header>

{{-- ── Main content ─────────────────────────────────────────────────────── --}}
<main id="main-content" class="main-content">
    <div class="content-wrapper">
        @if(session('impersonator_id'))
            <div style="display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;
                        background:#92400e;color:#fffbeb;border:1px solid #b45309;border-radius:.6rem;
                        padding:.6rem .9rem;margin-bottom:1rem;font-size:.85rem;">
                <span style="display:inline-flex;align-items:center;gap:.5rem;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    You are impersonating <strong>{{ auth()->user()->name }}</strong>.
                </span>
                <form method="POST" action="{{ route('impersonate.stop') }}" style="margin:0;">
                    @csrf
                    <button type="submit"
                            style="background:#fffbeb;color:#92400e;border:0;border-radius:.45rem;
                                   padding:.35rem .8rem;font-weight:600;font-size:.8rem;cursor:pointer;">
                        Exit impersonation
                    </button>
                </form>
            </div>
        @endif
        @include('partials.flash-messages')

        <div class="page-enter">
            @yield('content')
        </div>
    </div>
</main>

{{-- ── Mobile bottom navigation (below lg only) ─────────────────────────── --}}
@include('partials.mobile-bottom-nav')

{{-- Modals stack (rendered outside .page-enter to avoid transform stacking-context issues) --}}
@stack('modals')
@stack('scripts')

{{-- PWA service worker registration --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(@json(asset('sw.js'))).catch(err => console.warn('SW register failed', err));
        });
    }
</script>
</body>
</html>
