<!DOCTYPE html>
<html lang="en" x-data>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#10b981">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">
    @include('partials.favicon')
    <title>@yield('title', 'My Account') — {{ config('app.name') }}</title>
    <script>window.APP_BASE = @json(url(''));</script>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('styles')
</head>
<body class="has-mobile-nav">

{{-- ── Mobile sidebar overlay ──────────────────────────────────────────── --}}
<div id="sidebar-overlay" class="sidebar-overlay" @click="$store.sidebar.closeMobile()"></div>

{{-- ── Sidebar ──────────────────────────────────────────────────────────── --}}
<aside id="sidebar" class="sidebar">

    <a href="{{ route('customer.dashboard') }}" class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <svg width="18" height="18" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
            </svg>
        </div>
        <div class="sidebar-brand-text">
            <span class="brand-name">{{ config('app.name') }}</span>
            <span class="brand-tenant">Player Account</span>
        </div>
    </a>

    <nav class="sidebar-nav">

        <x-nav-item href="{{ route('customer.dashboard') }}" icon="bi-house-door"
            :active="request()->routeIs('customer.dashboard')">Dashboard</x-nav-item>

        <div class="nav-section-label">Play</div>
        <div class="nav-divider"></div>

        <x-nav-item href="{{ route('customer.bookings.create') }}" icon="bi-plus-circle"
            :active="request()->routeIs('customer.bookings.create')">Book a Court</x-nav-item>

        <x-nav-item href="{{ route('customer.bookings.index') }}" icon="bi-calendar-check"
            :active="request()->routeIs('customer.bookings.index') || request()->routeIs('customer.bookings.show')">My Bookings</x-nav-item>

        <x-nav-item href="{{ route('customer.tournaments.index') }}" icon="bi-trophy"
            :active="request()->routeIs('customer.tournaments.*')">Tournaments</x-nav-item>

        <div class="nav-section-label">Account</div>
        <div class="nav-divider"></div>

        <x-nav-item href="{{ route('customer.memberships.index') }}" icon="bi-credit-card"
            :active="request()->routeIs('customer.memberships.*')">Membership</x-nav-item>

        <x-nav-item href="{{ route('customer.wallet.index') }}" icon="bi-wallet2"
            :active="request()->routeIs('customer.wallet.*')">Wallet</x-nav-item>

        <x-nav-item href="{{ route('notifications.index') }}" icon="bi-bell"
            :active="request()->routeIs('notifications.*')">Notifications</x-nav-item>

        <div class="nav-section-label">Settings</div>
        <div class="nav-divider"></div>

        <x-nav-item href="{{ route('customer.profile.edit') }}" icon="bi-person"
            :active="request()->routeIs('customer.profile.*')">Profile &amp; Security</x-nav-item>

    </nav>

    @include('partials.sidebar-footer')
</aside>

{{-- ── Topbar ────────────────────────────────────────────────────────────── --}}
<header id="topbar" class="topbar">

    <button class="topbar-btn d-lg-none" @click="$store.sidebar.openMobile()" aria-label="Menu">
        <i class="bi bi-list fs-5"></i>
    </button>

    <h1 class="topbar-title d-none d-lg-block">@yield('title', 'My Account')</h1>
    <span class="topbar-title d-lg-none">{{ config('app.name') }}</span>

    @include('partials.topbar-actions', [
        'profileRoute'   => 'customer.profile.edit',
        'twoFactorRoute' => '2fa.show',
        'devicesRoute'   => 'devices.index',
    ])
</header>

<main id="main-content" class="main-content">
    <div class="content-wrapper">
        @include('partials.flash-messages')

        <div class="page-enter">
            @yield('content')
        </div>
    </div>
</main>

{{-- ── Mobile bottom navigation (below lg only) ─────────────────────────── --}}
@include('partials.customer-mobile-bottom-nav')

@stack('modals')
@stack('scripts')

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register(@json(asset('sw.js'))).catch(err => console.warn('SW register failed', err));
        });
    }
</script>
</body>
</html>
