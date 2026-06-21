@extends('layouts.app')
@section('title', 'Bookings')

@push('styles')
<style>
    /* ── Bookings list — polish + mobile card stacking ── */
    .bk-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        display: grid; place-items: center; font-weight: 700; font-size: .8rem;
        color: #fff; background: linear-gradient(135deg, #10b981, #059669);
    }
    .bk-table tbody tr { transition: background-color .15s; }

    /* TailAdmin-style table: uppercase muted headers + airier rows */
    .bk-table thead th {
        text-transform: uppercase;
        font-size: .7rem;
        letter-spacing: .04em;
        font-weight: 600;
        color: var(--bs-secondary-color);
        padding-top: .85rem;
        padding-bottom: .85rem;
    }
    .bk-table tbody td { padding-top: .85rem; padding-bottom: .85rem; }

    /* Mobile stacked cells: customer name beside the avatar with the avatar on the
       far right; status badges aligned to the right (flex-end). Desktop untouched. */
    @media (max-width: 767.98px) {
        .bk-table td[data-label="Customer"] > .d-flex { flex-direction: row-reverse; }
        .bk-table td[data-label="Status"] > .d-flex { align-items: flex-end !important; }
    }

</style>
@endpush

@section('content')

<x-page-header title="Bookings">
    <x-slot name="actions">
        <div class="btn-group" role="group">
            <a href="{{ route('admin.bookings.index') }}"
               class="btn btn-sm {{ !request('view') || request('view') === 'list' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                <i class="bi bi-list-ul me-1"></i>List
            </a>
            <a href="{{ route('admin.bookings.calendar') }}"
               class="btn btn-sm {{ request('view') === 'calendar' ? 'btn-secondary' : 'btn-outline-secondary' }}">
                <i class="bi bi-calendar3 me-1"></i>Calendar
            </a>
        </div>
        <a href="{{ route('admin.bookings.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Booking
        </a>
    </x-slot>
</x-page-header>

{{-- Unified filter bar --}}
<x-filter-bar placeholder="Search booking #, customer…"
              :active-count="(int) request()->filled('status') + (int) request()->filled('date')"
              :clear="route('admin.bookings.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                <option value="pending"   @selected(request('status') === 'pending')>Pending</option>
                <option value="confirmed" @selected(request('status') === 'confirmed')>Confirmed</option>
                <option value="active"    @selected(request('status') === 'active')>Active</option>
                <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                <option value="no_show"   @selected(request('status') === 'no_show')>No Show</option>
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Date</label>
            <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm">
        </div>
    </x-slot>
</x-filter-bar>

{{-- Table --}}
<div class="card">
    <div class="table-responsive">
        <table class="table bk-table table-hover align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th>Court</th>
                    <th>Date &amp; Time</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $booking)
                @php
                $paymentBadge = match($booking->payment_method) {
                    'wallet'       => 'badge bg-primary-subtle text-primary-emphasis',
                    'court_credit' => 'badge bg-info-subtle text-info-emphasis',
                    'cash'         => 'badge bg-secondary-subtle text-secondary-emphasis',
                    default        => null,
                };
                @endphp
                <tr>
                    <td data-label="Booking">
                        <div>
                            <p class="mb-0 small fw-semibold">{{ $booking->booking_number }}</p>
                            <small class="text-muted">{{ ucfirst($booking->type) }}</small>
                        </div>
                    </td>
                    <td data-label="Customer">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bk-avatar">
                                {{ strtoupper(substr($booking->customer->name ?? '?', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="mb-0 small fw-medium text-truncate">{{ $booking->customer->name ?? 'Walk-in' }}</p>
                                <small class="text-muted d-block text-truncate">{{ $booking->customer->email ?? '' }}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Court">
                        <div>
                            <p class="mb-0 small">{{ $booking->court->name }}</p>
                            <small class="text-muted">{{ ucfirst($booking->court->type) }}</small>
                        </div>
                    </td>
                    <td data-label="Date & Time">
                        <div>
                            <p class="mb-0 small">{{ $booking->booking_date->format('M j, Y') }}</p>
                            <small class="text-muted">{{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}</small>
                        </div>
                    </td>
                    <td data-label="Amount" class="text-end">
                        <div>
                            <p class="mb-0 small fw-semibold">₱{{ number_format($booking->total_amount, 2) }}</p>
                            @if($booking->balance_due > 0)
                            <small class="text-danger">₱{{ number_format($booking->balance_due, 2) }} due</small>
                            @endif
                        </div>
                    </td>
                    <td data-label="Status">
                        <div class="d-flex flex-column align-items-start gap-1">
                            @if($paymentBadge)
                                <span class="{{ $paymentBadge }}">{{ $booking->paymentMethodLabel() }}</span>
                            @endif
                            <x-badge :status="$booking->status">{{ ucfirst(str_replace('_',' ',$booking->status)) }}</x-badge>
                            @php $cashBadge = $booking->cashPaymentBadge(); @endphp
                            @if($cashBadge)
                                <span class="badge bg-{{ $cashBadge['color'] }}-subtle text-{{ $cashBadge['color'] }}-emphasis">
                                    <i class="bi bi-cash-coin me-1"></i>{{ $cashBadge['label'] }}
                                </span>
                            @endif
                            @if($booking->approval_status === 'pending')
                                <span class="badge bg-warning-subtle text-warning-emphasis">Awaiting approval</span>
                            @endif
                        </div>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.bookings.show', $booking) }}"
                           class="btn btn-outline-primary btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="cell-plain">
                        <x-empty-state title="No bookings found"
                            description="Try adjusting your search or filter criteria."
                            icon="bi-calendar"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($bookings->hasPages())
    <div class="card-footer">
        {{ $bookings->withQueryString()->links('pagination.courtmaster') }}
    </div>
    @endif
</div>

@endsection
