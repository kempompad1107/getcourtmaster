{{--
    Mobile bottom tab bar (customer). Shown below `lg` only; desktop uses the sidebar.
    Static tabs (no permission gating): Home · Bookings · ＋Book · Wallet · More.
--}}
<nav class="mobile-bottom-nav d-lg-none" aria-label="Primary mobile navigation">

    <a href="{{ route('customer.dashboard') }}"
       class="mbn-tab {{ request()->routeIs('customer.dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door mbn-ic"></i>
        <span class="mbn-label">Home</span>
    </a>

    <a href="{{ route('customer.bookings.index') }}"
       class="mbn-tab {{ request()->routeIs('customer.bookings.index') || request()->routeIs('customer.bookings.show') ? 'active' : '' }}">
        <i class="bi bi-calendar-check mbn-ic"></i>
        <span class="mbn-label">Bookings</span>
    </a>

    {{-- Center FAB → Book a Court --}}
    <a href="{{ route('customer.bookings.create') }}" class="mbn-fab-wrap" aria-label="Book a court">
        <span class="mbn-fab"><i class="bi bi-plus-lg"></i></span>
        <span class="mbn-label">Book</span>
    </a>

    <a href="{{ route('customer.wallet.index') }}"
       class="mbn-tab {{ request()->routeIs('customer.wallet.*') ? 'active' : '' }}">
        <i class="bi bi-wallet2 mbn-ic"></i>
        <span class="mbn-label">Wallet</span>
    </a>

    {{-- More → opens the existing drawer --}}
    <button type="button" class="mbn-tab" @click="$store.sidebar.openMobile()" aria-label="More menu">
        <i class="bi bi-list mbn-ic"></i>
        <span class="mbn-label">More</span>
    </button>

</nav>
