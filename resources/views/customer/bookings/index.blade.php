@extends('layouts.customer')

@section('title', 'My Bookings')

@push('styles')
<style>
    /* ── My bookings — polish (mobile stacking via shared .table-stack) ── */
    .cb-court-ico { width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0; display: grid; place-items: center; background: rgba(16,185,129,.1); color: #10b981; }
    .cb-table tbody tr { transition: background-color .15s; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">My Bookings</h4>
        <p class="text-muted mb-0">Your court reservations and history.</p>
    </div>
    <a href="{{ route('customer.bookings.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New booking
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table cb-table mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Court</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Payment</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $b)
                    @php
                        if ($b->approval_status === 'pending') {
                            $sLabel = 'Pending Approval'; $sBadge = 'warning';
                        } elseif ($b->status === 'denied' || $b->approval_status === 'denied') {
                            $sLabel = 'Denied'; $sBadge = 'danger';
                        } else {
                            $sLabel = ucfirst($b->status);
                            $sBadge = match($b->status){'confirmed'=>'success','active'=>'primary','pending'=>'warning','completed'=>'secondary','cancelled'=>'danger',default=>'secondary'};
                        }
                    @endphp
                    <tr>
                        <td data-label="#" class="text-muted small font-monospace">{{ $b->booking_number }}</td>
                        <td data-label="Court">
                            <div class="d-flex align-items-center gap-2 justify-content-end justify-content-md-start">
                                <span class="cb-court-ico"><i class="bi bi-trophy"></i></span>
                                <span class="fw-semibold">{{ $b->court?->name }}</span>
                            </div>
                        </td>
                        <td data-label="Date" class="small">{{ $b->booking_date?->format('M d, Y') }}</td>
                        <td data-label="Time" class="small">{{ \Illuminate\Support\Carbon::parse($b->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($b->end_time)->format('h:i A') }}</td>
                        <td data-label="Payment"><span class="small text-muted">{{ $b->paymentMethodLabel() }}</span></td>
                        <td data-label="Total" class="text-end fw-semibold">₱{{ number_format($b->total_amount, 2) }}</td>
                        <td data-label="Status">
                            <span class="badge rounded-pill bg-{{ $sBadge }}-subtle text-{{ $sBadge }}-emphasis">{{ $sLabel }}</span>
                        </td>
                        <td class="cell-actions text-end">
                            <a href="{{ route('customer.bookings.show', $b) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="cell-plain text-center text-muted py-5">
                        <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
                        No bookings yet.
                        <div class="mt-3"><a href="{{ route('customer.bookings.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Book a court</a></div>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
