@extends('layouts.app')
@section('title', 'Refund Requests')

@push('styles')
<style>
    /* Emerald active tab */
    .refund-tabs .nav-link { color: var(--bs-secondary-color); border-radius: .5rem; }
    .refund-tabs .nav-link.active { background: #10b981; color: #fff; }
    .refund-tabs .nav-link:not(.active):hover { background: var(--bs-body-bg-alt, rgba(148,163,184,.08)); }

    /* TailAdmin table header */
    .refund-table thead th {
        text-transform: uppercase; font-size: .7rem; letter-spacing: .04em;
        font-weight: 600; color: var(--bs-secondary-color);
        padding-top: .85rem; padding-bottom: .85rem;
    }
    .refund-table tbody td { padding-top: .8rem; padding-bottom: .8rem; }
    .refund-table tbody tr { transition: background-color .15s; }
</style>
@endpush

@section('content')

<x-page-header title="Cash Refund Requests"
    subtitle="Cash-paid bookings that were cancelled — hand over cash then mark as paid out."/>

<div class="alert alert-info border-0 small d-flex gap-2 align-items-start mb-4">
    <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
    <div>
        Refunds for <strong>wallet</strong> and <strong>court credit</strong> bookings are issued automatically.
        Only <strong>cash</strong> refunds appear here because they require a physical payout.
    </div>
</div>

{{-- Status tabs --}}
@php
    $tabs = [
        'pending'   => ['Pending',   $counts['pending'],   'warning'],
        'processed' => ['Processed', $counts['processed'], 'success'],
        'denied'    => ['Denied',    $counts['denied'],    'secondary'],
        'all'       => ['All',       null,                 'secondary'],
    ];
@endphp
<ul class="nav nav-pills refund-tabs gap-1 mb-4">
    @foreach($tabs as $key => [$label, $count, $color])
    <li class="nav-item">
        <a href="{{ route('admin.refund-requests.index', ['status' => $key]) }}"
           class="nav-link px-3 py-2 {{ $status === $key ? 'active' : '' }}">
            {{ $label }}
            @if($count !== null)
            <span class="badge rounded-pill ms-1
                {{ $status === $key ? 'bg-white bg-opacity-25 text-white' : 'bg-'.$color.'-subtle text-'.$color }}">
                {{ $count }}
            </span>
            @endif
        </a>
    </li>
    @endforeach
</ul>

<div class="card">
    @if($refunds->isEmpty())
        <x-empty-state title="No refund requests" icon="bi-inbox"
            description="Nothing to show for this status."/>
    @else
    <div class="table-responsive">
        <table class="table refund-table table-stack table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th class="d-none d-md-table-cell">Processed</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($refunds as $r)
                @php
                    $badge = match($r->status) {
                        'pending'   => 'warning',
                        'processed' => 'success',
                        'denied'    => 'secondary',
                        default     => 'secondary',
                    };
                @endphp
                <tr>
                    <td data-label="Booking">
                        <a href="{{ route('admin.bookings.show', $r->booking_id) }}"
                           class="fw-semibold small text-decoration-none">
                            #{{ $r->booking?->booking_number ?? $r->booking_id }}
                        </a>
                        <p class="mb-0 text-muted" style="font-size:.72rem">
                            {{ optional($r->booking?->booking_date)->format('M j, Y') }}
                        </p>
                    </td>
                    <td data-label="Customer">
                        <p class="mb-0 small fw-medium">{{ $r->customer?->name ?? '—' }}</p>
                        <p class="mb-0 text-muted" style="font-size:.72rem">{{ $r->customer?->email }}</p>
                    </td>
                    <td data-label="Amount" class="text-end fw-semibold small">
                        ₱{{ number_format($r->amount, 2) }}
                    </td>
                    <td data-label="Status">
                        <span class="badge rounded-pill bg-{{ $badge }}-subtle text-{{ $badge }}">
                            {{ ucfirst($r->status) }}
                        </span>
                    </td>
                    <td data-label="Requested" class="small text-muted">
                        {{ $r->created_at->diffForHumans() }}
                    </td>
                    <td data-label="Processed" class="small text-muted d-none d-md-table-cell">
                        @if($r->processed_at)
                            <p class="mb-0">{{ $r->processed_at->format('M j, g:i a') }}</p>
                            <p class="mb-0" style="font-size:.72rem">by {{ $r->processedBy?->name }}</p>
                        @else
                            —
                        @endif
                    </td>
                    <td class="cell-actions text-end">
                        @if($r->status === 'pending')
                            <button type="button" class="btn btn-success btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#processModal-{{ $r->id }}">
                                <i class="bi bi-cash-coin"></i>Pay out
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#denyModal-{{ $r->id }}">
                                <i class="bi bi-x-circle"></i>Deny
                            </button>
                        @else
                            <span class="text-muted small">
                                @if($r->reference) Ref: {{ $r->reference }} @endif
                                @if($r->denial_note) <em>{{ Str::limit($r->denial_note, 40) }}</em> @endif
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($refunds->hasPages())
    <div class="card-footer">{{ $refunds->withQueryString()->links() }}</div>
    @endif
    @endif
</div>

@endsection

@push('modals')
@foreach($refunds as $r)
@continue($r->status !== 'pending')

<x-modal name="processModal-{{ $r->id }}" title="Pay Out Refund">
    <form method="POST" action="{{ route('admin.refund-requests.process', $r) }}"
          id="process-form-{{ $r->id }}">
        @csrf
        <p class="small text-muted mb-3">
            Hand over <strong>₱{{ number_format($r->amount, 2) }}</strong> cash to
            <strong>{{ $r->customer?->name }}</strong> for booking
            <strong>#{{ $r->booking?->booking_number }}</strong>.
        </p>
        <div>
            <label class="form-label">Reference / Receipt # <span class="text-muted fw-normal">(optional)</span></label>
            <input type="text" name="reference" maxlength="100" class="form-control"
                   placeholder="e.g. cash-drawer slip number">
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="process-form-{{ $r->id }}" class="btn btn-success">
            <i class="bi bi-check2-circle"></i>Mark as paid out
        </button>
    </x-slot>
</x-modal>

<x-modal name="denyModal-{{ $r->id }}" title="Deny Refund">
    <form method="POST" action="{{ route('admin.refund-requests.deny', $r) }}"
          id="deny-form-{{ $r->id }}">
        @csrf
        <p class="small text-muted mb-3">
            Denying the refund for <strong>{{ $r->customer?->name }}</strong>
            (₱{{ number_format($r->amount, 2) }}). Please provide a reason.
        </p>
        <div>
            <label class="form-label">Reason <span class="text-danger">*</span></label>
            <textarea name="denial_note" rows="3" required maxlength="255"
                      class="form-control" placeholder="e.g. Customer no-show, past refund window"></textarea>
        </div>
    </form>
    <x-slot name="footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="deny-form-{{ $r->id }}" class="btn btn-danger">
            <i class="bi bi-x-octagon"></i>Deny refund
        </button>
    </x-slot>
</x-modal>

@endforeach
@endpush
