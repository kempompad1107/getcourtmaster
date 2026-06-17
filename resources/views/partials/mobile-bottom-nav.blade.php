{{--
    Mobile bottom tab bar (admin). Shown below `lg` only; desktop uses the sidebar.
    Operations-first: Home · [middle] · ＋New Booking · [middle] · More.
    Permission-aware — middle slots fill from an ordered candidate list of
    screens the user can access; "More" always opens the full drawer.
--}}
@php
    $u = auth()->user();
    $isOwner = $u && ($u->hasRole('business_owner') || $u->isBusinessOwner());

    // Center FAB → New Booking (gated like the sidebar's Bookings item)
    $showFab = $isOwner || $u->can('bookings.view');

    // Ordered candidates for the two middle slots: [label, icon, route, activePattern]
    $candidates = [];
    if ($isOwner || $u->can('bookings.view'))  $candidates[] = ['Bookings', 'bi-calendar-check', 'admin.bookings.index',       'admin.bookings.index'];
    if ($isOwner || $u->can('courts.view'))    $candidates[] = ['Courts',   'bi-grid',           'admin.courts.index',         'admin.courts.index'];
    if ($isOwner || $u->can('courts.view'))    $candidates[] = ['Status',   'bi-grid-3x3-gap',   'admin.courts.status-board',  'admin.courts.status-board'];
    if ($isOwner || $u->can('pos.access'))     $candidates[] = ['POS',      'bi-receipt',        'admin.pos.index',            'admin.pos.*'];
    if ($isOwner || $u->can('customers.view')) $candidates[] = ['Customers','bi-people',         'admin.customers.index',      'admin.customers.*'];
    if ($isOwner || $u->can('tournaments.view')) $candidates[] = ['Tourneys', 'bi-trophy',       'admin.tournaments.index',    'admin.tournaments.*'];

    $middle = array_slice($candidates, 0, 2);
    $leftItem  = $middle[0] ?? null;
    $rightItem = $middle[1] ?? null;
@endphp

<nav class="mobile-bottom-nav d-lg-none" aria-label="Primary mobile navigation">

    {{-- Home (always) --}}
    <a href="{{ route('admin.dashboard') }}"
       class="mbn-tab {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <i class="bi bi-house-door mbn-ic"></i>
        <span class="mbn-label">Home</span>
    </a>

    {{-- Left middle slot --}}
    @if($leftItem)
    <a href="{{ route($leftItem[2]) }}"
       class="mbn-tab {{ request()->routeIs($leftItem[3]) ? 'active' : '' }}">
        <i class="bi {{ $leftItem[1] }} mbn-ic"></i>
        <span class="mbn-label">{{ $leftItem[0] }}</span>
    </a>
    @endif

    {{-- Center FAB → New Booking --}}
    @if($showFab)
    <a href="{{ route('admin.bookings.create') }}" class="mbn-fab-wrap" aria-label="New booking">
        <span class="mbn-fab"><i class="bi bi-plus-lg"></i></span>
        <span class="mbn-label">New</span>
    </a>
    @endif

    {{-- Right middle slot --}}
    @if($rightItem)
    <a href="{{ route($rightItem[2]) }}"
       class="mbn-tab {{ request()->routeIs($rightItem[3]) ? 'active' : '' }}">
        <i class="bi {{ $rightItem[1] }} mbn-ic"></i>
        <span class="mbn-label">{{ $rightItem[0] }}</span>
    </a>
    @endif

    {{-- More → opens the existing drawer --}}
    <button type="button" class="mbn-tab" @click="$store.sidebar.openMobile()" aria-label="More menu">
        <i class="bi bi-list mbn-ic"></i>
        <span class="mbn-label">More</span>
    </button>

</nav>
