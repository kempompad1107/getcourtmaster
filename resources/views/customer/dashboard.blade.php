@extends('layouts.customer')

@section('title', 'My Dashboard')

@push('styles')
<style>
    .cust-stat {
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .cust-stat:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.35); box-shadow: 0 16px 32px -22px rgba(0,0,0,.55); }
    .cust-stat-label { font-size: .72rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--bs-secondary-color); margin: 0; }
    .cust-stat-value { font-size: 1.85rem; font-weight: 800; letter-spacing: -.02em; line-height: 1; margin: .4rem 0 .1rem; }
    .cust-stat-ico { width: 48px; height: 48px; border-radius: 13px; flex-shrink: 0; display: grid; place-items: center; font-size: 1.35rem; }
    .cust-link { font-weight: 600; text-decoration: none; font-size: .85rem; }
    .cust-link:hover { text-decoration: underline; }

    .bk-row-ico { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: grid; place-items: center; background: rgba(16,185,129,.1); color: #10b981; border: 1px solid rgba(16,185,129,.2); }
    .act-row-ico { width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0; display: grid; place-items: center; font-size: .85rem; background: var(--bs-secondary-bg); color: var(--bs-secondary-color); }
    .cust-feed-item { transition: background-color .15s; }
    .cust-feed-item:hover { background: rgba(148,163,184,.06); }
</style>
@endpush

@section('content')

{{-- Welcome banner --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Welcome back, {{ explode(' ', trim($user->name))[0] ?? 'Player' }} 👋</h4>
        <p class="text-muted mb-0 small">Here's what's happening with your court time.</p>
    </div>
    <a href="{{ route('customer.bookings.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Book a Court
    </a>
</div>

{{-- Stat cards --}}
<div class="row g-3 mb-4">
    {{-- Wallet --}}
    <div class="col-12 col-sm-4">
        <div class="card cust-stat h-100">
            <div class="card-body d-flex align-items-start justify-content-between gap-3">
                <div class="min-w-0">
                    <p class="cust-stat-label">Wallet balance</p>
                    <p class="cust-stat-value text-success">₱{{ number_format($user->wallet_balance ?? 0, 2) }}</p>
                    <a href="{{ route('customer.wallet.index') }}" class="cust-link text-success">View transactions →</a>
                </div>
                <div class="cust-stat-ico bg-success bg-opacity-10 text-success"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>

    {{-- Membership --}}
    <div class="col-12 col-sm-4">
        <div class="card cust-stat h-100">
            <div class="card-body d-flex align-items-start justify-content-between gap-3">
                <div class="min-w-0">
                    <p class="cust-stat-label">Membership</p>
                    @if($membership)
                        <p class="cust-stat-value" style="font-size:1.25rem;letter-spacing:-.01em">{{ $membership->plan?->name ?? 'Member' }}</p>
                        <div class="small text-muted"><i class="bi bi-calendar-check me-1"></i>Expires {{ $membership->expires_at?->format('M d, Y') }}</div>
                    @else
                        <p class="cust-stat-value text-muted" style="font-size:1.35rem">None</p>
                        <a href="{{ route('customer.memberships.index') }}" class="cust-link" style="color:#a855f7">Browse plans →</a>
                    @endif
                </div>
                <div class="cust-stat-ico" style="background:rgba(168,85,247,.1);color:#a855f7;border:1px solid rgba(168,85,247,.2)">
                    <i class="bi bi-patch-check"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Upcoming --}}
    <div class="col-12 col-sm-4">
        <div class="card cust-stat h-100">
            <div class="card-body d-flex align-items-start justify-content-between gap-3">
                <div class="min-w-0">
                    <p class="cust-stat-label">Upcoming bookings</p>
                    <p class="cust-stat-value" style="color:#f59e0b">{{ $upcoming->count() }}</p>
                    <a href="{{ route('customer.bookings.create') }}" class="cust-link" style="color:#f59e0b">Book a court →</a>
                </div>
                <div class="cust-stat-ico" style="background:rgba(245,158,11,.1);color:#f59e0b;border:1px solid rgba(245,158,11,.2)">
                    <i class="bi bi-calendar-event"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Upcoming bookings list --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Upcoming Bookings</h6>
                <a href="{{ route('customer.bookings.index') }}" class="cust-link text-muted">All →</a>
            </div>
            @if($upcoming->isEmpty())
                <x-empty-state
                    title="No upcoming bookings"
                    description="You have no bookings scheduled. Book a court to get started."
                    icon="bi-calendar-x"
                    :action="route('customer.bookings.create')"
                    actionLabel="Book a Court"/>
            @else
                <div class="list-group list-group-flush">
                    @foreach($upcoming as $b)
                    <a href="{{ route('customer.bookings.show', $b) }}"
                       class="list-group-item list-group-item-action cust-feed-item py-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bk-row-ico">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small text-truncate">{{ $b->court?->name }}</div>
                                <div class="small text-muted">
                                    {{ $b->booking_date?->format('M d, Y') }}
                                    · {{ \Illuminate\Support\Carbon::parse($b->start_time)->format('h:i A') }}
                                    – {{ \Illuminate\Support\Carbon::parse($b->end_time)->format('h:i A') }}
                                </div>
                            </div>
                            <x-badge :status="$b->status">{{ ucfirst($b->status) }}</x-badge>
                        </div>
                    </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Recent Activity</h6>
            </div>
            @if($past->isEmpty())
                <x-empty-state
                    title="No history yet"
                    description="Your past bookings will appear here."
                    icon="bi-inbox"/>
            @else
                <div class="list-group list-group-flush">
                    @foreach($past as $b)
                    @php
                        $actIcon = match(true) {
                            in_array($b->status, ['completed', 'confirmed']) => 'bi-check-circle',
                            in_array($b->status, ['cancelled'])              => 'bi-x-circle',
                            in_array($b->status, ['no_show'])                => 'bi-dash-circle',
                            default                                          => 'bi-calendar',
                        };
                    @endphp
                    <div class="list-group-item cust-feed-item d-flex align-items-center gap-3 py-3">
                        <div class="act-row-ico flex-shrink-0">
                            <i class="bi {{ $actIcon }}"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="small fw-medium text-truncate">{{ $b->court?->name }}</div>
                            <div class="small text-muted">{{ $b->booking_date?->format('M d, Y') }}</div>
                        </div>
                        <x-badge :status="$b->status">{{ ucfirst($b->status) }}</x-badge>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@endsection
