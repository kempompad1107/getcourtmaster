@extends('layouts.customer')

@section('title', 'My Dashboard')

@push('styles')
<style>
    /* ── Customer dashboard — polish over the portal theme ── */
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

    .cust-booking-ico { width: 38px; height: 38px; border-radius: 11px; flex-shrink: 0; display: grid; place-items: center; background: rgba(16,185,129,.1); color: #10b981; }
    .cust-feed-item { transition: background-color .15s; }
    .cust-feed-item:hover { background: rgba(148,163,184,.06); }
</style>
@endpush

@section('content')

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Welcome back, {{ explode(' ', trim($user->name))[0] ?? 'Player' }} 👋</h4>
        <p class="text-muted mb-0">Here's what's happening with your court time.</p>
    </div>
    <a href="{{ route('customer.bookings.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Book a Court
    </a>
</div>

<div class="row g-4 mb-4">
    {{-- Wallet --}}
    <div class="col-12 col-md-4">
        <div class="card cust-stat h-100">
            <div class="card-body d-flex align-items-start justify-content-between gap-3">
                <div class="min-w-0">
                    <p class="cust-stat-label">Wallet balance</p>
                    <p class="cust-stat-value text-success">₱{{ number_format($user->wallet_balance ?? 0, 2) }}</p>
                    <a href="{{ route('customer.wallet.index') }}" class="cust-link">View transactions →</a>
                </div>
                <div class="cust-stat-ico bg-success bg-opacity-10 text-success"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>

    {{-- Membership --}}
    <div class="col-12 col-md-4">
        <div class="card cust-stat h-100">
            <div class="card-body d-flex align-items-start justify-content-between gap-3">
                <div class="min-w-0">
                    <p class="cust-stat-label">Active membership</p>
                    @if ($membership)
                        <p class="cust-stat-value" style="font-size:1.35rem">{{ $membership->plan?->name ?? 'Member' }}</p>
                        <div class="small text-muted"><i class="bi bi-calendar-check me-1"></i>Expires {{ $membership->expires_at?->format('M d, Y') }}</div>
                    @else
                        <p class="cust-stat-value text-muted" style="font-size:1.35rem">None</p>
                        <a href="{{ route('customer.memberships.index') }}" class="cust-link">Browse plans →</a>
                    @endif
                </div>
                <div class="cust-stat-ico bg-primary bg-opacity-10 text-primary"><i class="bi bi-patch-check"></i></div>
            </div>
        </div>
    </div>

    {{-- Upcoming --}}
    <div class="col-12 col-md-4">
        <div class="card cust-stat h-100">
            <div class="card-body d-flex align-items-start justify-content-between gap-3">
                <div class="min-w-0">
                    <p class="cust-stat-label">Upcoming bookings</p>
                    <p class="cust-stat-value">{{ $upcoming->count() }}</p>
                    <a href="{{ route('customer.bookings.create') }}" class="cust-link">Book a court →</a>
                </div>
                <div class="cust-stat-ico bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-event"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    {{-- Upcoming bookings --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-calendar-event me-1 text-muted"></i>Upcoming bookings</h6>
                <a href="{{ route('customer.bookings.index') }}" class="cust-link">All →</a>
            </div>
            <div class="list-group list-group-flush">
                @forelse ($upcoming as $b)
                    <a href="{{ route('customer.bookings.show', $b) }}" class="list-group-item list-group-item-action cust-feed-item py-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="cust-booking-ico"><i class="bi bi-trophy"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $b->court?->name }}</div>
                                <div class="small text-muted">
                                    <i class="bi bi-clock me-1"></i>{{ $b->booking_date?->format('M d, Y') }} · {{ \Illuminate\Support\Carbon::parse($b->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($b->end_time)->format('h:i A') }}
                                </div>
                            </div>
                            <x-badge :status="$b->status">{{ ucfirst($b->status) }}</x-badge>
                        </div>
                    </a>
                @empty
                    <div class="list-group-item text-center text-muted py-5">
                        <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
                        No upcoming bookings.
                        <div class="mt-3"><a href="{{ route('customer.bookings.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Book a court</a></div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent activity --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-1 text-muted"></i>Recent activity</h6>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($past as $b)
                    <li class="list-group-item cust-feed-item d-flex justify-content-between align-items-center py-3">
                        <span class="small text-truncate"><i class="bi bi-dot text-muted"></i>{{ $b->court?->name }} · {{ $b->booking_date?->format('M d') }}</span>
                        <x-badge :status="$b->status">{{ ucfirst($b->status) }}</x-badge>
                    </li>
                @empty
                    <li class="list-group-item text-muted text-center py-5">
                        <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>No history yet.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
