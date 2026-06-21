@extends('layouts.customer')

@section('title', 'My Bookings')

@push('styles')
<style>
    .cb-court-ico {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center;
        background: rgba(16,185,129,.1); color: #10b981;
        border: 1px solid rgba(16,185,129,.2);
    }
    .bk-tab-bar { display: flex; gap: .25rem; flex-wrap: wrap; }
    .bk-tab {
        padding: .35rem .9rem; border-radius: .5rem; font-size: .82rem; font-weight: 600;
        border: 1px solid var(--bs-border-color); background: transparent;
        color: var(--bs-secondary-color); text-decoration: none; transition: all .15s;
    }
    .bk-tab:hover { background: var(--bs-secondary-bg); color: var(--bs-body-color); }
    .bk-tab.active { background: #10b981; border-color: #10b981; color: #fff; }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0">My Bookings</h4>
        <p class="text-muted small mb-0">Your court reservations and history.</p>
    </div>
    <a href="{{ route('customer.bookings.create') }}" class="btn btn-primary flex-shrink-0">
        <i class="bi bi-plus-lg me-1"></i>New Booking
    </a>
</div>

{{-- Status tabs --}}
<div class="bk-tab-bar mb-3">
    @foreach([
        'upcoming'  => 'Upcoming',
        'past'      => 'Completed',
        'cancelled' => 'Cancelled',
        'all'       => 'All',
    ] as $key => $label)
        <a href="{{ route('customer.bookings.index', ['tab' => $key]) }}"
           class="bk-tab {{ $tab === $key ? 'active' : '' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Table --}}
<div class="card">
    @if($bookings->isEmpty())
        <x-empty-state
            :title="$tab === 'upcoming' ? 'No upcoming bookings' : ($tab === 'past' ? 'No completed bookings' : ($tab === 'cancelled' ? 'No cancelled bookings' : 'No bookings yet'))"
            :description="$tab === 'upcoming' ? 'Book a court to get started.' : 'Nothing here yet.'"
            icon="bi-calendar-x"
            :action="$tab === 'upcoming' ? route('customer.bookings.create') : null"
            :actionLabel="$tab === 'upcoming' ? 'Book a Court' : null"/>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-stack">
                <thead class="table-light">
                    <tr>
                        <th>Court</th>
                        <th>Date &amp; Time</th>
                        <th>Payment</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="cell-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $b)
                    @php
                        if ($b->approval_status === 'pending') {
                            $sLabel = 'Pending Approval'; $sBadge = 'warning';
                        } elseif ($b->status === 'denied' || $b->approval_status === 'denied') {
                            $sLabel = 'Denied'; $sBadge = 'danger';
                        } else {
                            $sLabel = ucfirst($b->status);
                            $sBadge = match($b->status) {
                                'confirmed' => 'success',
                                'active'    => 'primary',
                                'pending'   => 'warning',
                                'completed' => 'secondary',
                                'cancelled' => 'danger',
                                default     => 'secondary',
                            };
                        }
                    @endphp
                    <tr>
                        <td data-label="Court" class="cell-plain">
                            <div class="d-flex align-items-center gap-3">
                                <div class="cb-court-ico">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                                <div class="min-w-0">
                                    <div class="fw-semibold small text-truncate">{{ $b->court?->name }}</div>
                                    <div class="small text-muted font-monospace">{{ $b->booking_number }}</div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Date &amp; Time">
                            <div class="small fw-medium">{{ $b->booking_date?->format('M d, Y') }}</div>
                            <div class="small text-muted">
                                {{ \Illuminate\Support\Carbon::parse($b->start_time)->format('h:i A') }}
                                – {{ \Illuminate\Support\Carbon::parse($b->end_time)->format('h:i A') }}
                            </div>
                        </td>
                        <td data-label="Payment">
                            <span class="small text-muted">{{ $b->paymentMethodLabel() }}</span>
                        </td>
                        <td data-label="Total" class="text-end fw-semibold small text-nowrap">
                            ₱{{ number_format($b->total_amount, 2) }}
                        </td>
                        <td data-label="Status">
                            <span class="badge rounded-pill bg-{{ $sBadge }}-subtle text-{{ $sBadge }}-emphasis">
                                {{ $sLabel }}
                            </span>
                        </td>
                        <td class="cell-actions">
                            <a href="{{ route('customer.bookings.show', $b) }}" class="btn btn-primary btn-sm">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
