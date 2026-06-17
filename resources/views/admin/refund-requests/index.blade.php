@extends('layouts.app')
@section('title', 'Refund Requests')

@section('content')

<x-page-header title="Cash Refund Requests"
    subtitle="Cash-paid bookings that were cancelled. Hand over the cash, then mark each one as paid out.">
    <x-slot name="actions">
        <span class="badge bg-info-subtle text-info">
            <i class="bi bi-shield-lock me-1"></i>Owner / Staff only
        </span>
    </x-slot>
</x-page-header>

<div class="alert alert-info border-0 small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    Refunds for <strong>wallet</strong> and <strong>court credit</strong> bookings are issued automatically — wallet balance
    is credited back, and court-credit minutes are restored to the customer's membership. Only <strong>cash</strong> refunds
    appear here because they require physical payout.
</div>

{{-- Status tabs --}}
<ul class="nav nav-pills gap-2 mb-3">
    @php
        $tabs = [
            'pending'   => ['Pending',   $counts['pending'],   'warning'],
            'processed' => ['Processed', $counts['processed'], 'success'],
            'denied'    => ['Denied',    $counts['denied'],    'secondary'],
            'all'       => ['All',       null,                 'secondary'],
        ];
    @endphp
    @foreach($tabs as $key => [$label, $count, $color])
        <li class="nav-item">
            <a href="{{ route('admin.refund-requests.index', ['status' => $key]) }}"
               class="nav-link {{ $status === $key ? 'active' : '' }}">
                {{ $label }}
                @if($count !== null)
                    <span class="badge bg-{{ $color }}-subtle text-{{ $color }} ms-1">{{ $count }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Booking</th>
                    <th>Customer</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Processed</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($refunds as $r)
                    <tr>
                        <td>
                            <a href="{{ route('admin.bookings.show', $r->booking_id) }}" class="text-decoration-none">
                                #{{ $r->booking?->booking_number ?? $r->booking_id }}
                            </a>
                            <div class="text-muted" style="font-size:11px;">
                                {{ optional($r->booking?->booking_date)->format('M j, Y') }}
                            </div>
                        </td>
                        <td>
                            <div class="fw-medium">{{ $r->customer?->name ?? '—' }}</div>
                            <div class="text-muted" style="font-size:11px;">{{ $r->customer?->email }}</div>
                        </td>
                        <td class="text-end fw-semibold">₱{{ number_format($r->amount, 2) }}</td>
                        <td>
                            @php
                                $badge = match($r->status) {
                                    'pending'   => 'warning',
                                    'processed' => 'success',
                                    'denied'    => 'secondary',
                                };
                            @endphp
                            <span class="badge text-bg-{{ $badge }}">{{ ucfirst($r->status) }}</span>
                        </td>
                        <td class="text-muted">{{ $r->created_at->diffForHumans() }}</td>
                        <td class="text-muted">
                            @if($r->processed_at)
                                {{ $r->processed_at->format('M j, g:i a') }}<br>
                                <span style="font-size:11px;">by {{ $r->processedBy?->name }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">
                            @if($r->status === 'pending')
                                <button type="button" class="btn btn-success btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#processModal-{{ $r->id }}">
                                    <i class="bi bi-cash-coin me-1"></i>Pay out
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#denyModal-{{ $r->id }}">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            @else
                                <span class="text-muted small">
                                    @if($r->reference) Ref: {{ $r->reference }} @endif
                                    @if($r->denial_note) <em>{{ $r->denial_note }}</em> @endif
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                            No refund requests in this view.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($refunds->hasPages())
        <div class="card-footer">{{ $refunds->links() }}</div>
    @endif
</div>

@endsection

{{--
    Modals are pushed to the layout-level @stack('modals') so they render at
    the end of <body>, outside the table/card stacking context. Otherwise the
    modal-dialog renders behind the backdrop and the whole thing looks blurred.
--}}
@push('modals')
@foreach($refunds as $r)
    @continue($r->status !== 'pending')

    <div class="modal fade" id="processModal-{{ $r->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST"
                  action="{{ route('admin.refund-requests.process', $r) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Pay out refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Hand over <strong>₱{{ number_format($r->amount, 2) }}</strong> cash to
                        <strong>{{ $r->customer?->name }}</strong> for booking
                        #{{ $r->booking?->booking_number }}.
                    </p>
                    <label class="form-label small">Reference / Receipt # (optional)</label>
                    <input type="text" name="reference" maxlength="100" class="form-control"
                           placeholder="e.g. cash-drawer slip number">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Mark as paid out
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="denyModal-{{ $r->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" method="POST"
                  action="{{ route('admin.refund-requests.deny', $r) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Deny refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small">Reason (required)</label>
                    <textarea name="denial_note" rows="3" required maxlength="255"
                              class="form-control"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-octagon me-1"></i>Deny refund
                    </button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endpush
