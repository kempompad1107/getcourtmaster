@extends('layouts.customer')

@section('title', 'Booking #' . $booking->booking_number)

@section('content')
@php
    $statusKey = $booking->status;
    if ($booking->approval_status === 'pending') {
        $statusLabel = 'Pending Approval';
        $statusBadge = 'warning';
    } elseif ($booking->status === 'denied' || $booking->approval_status === 'denied') {
        $statusLabel = 'Denied';
        $statusBadge = 'danger';
    } else {
        $statusLabel = match($statusKey) {
            'confirmed' => 'Confirmed',
            'active'    => 'Active',
            'pending'   => 'Pending',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default     => ucfirst($statusKey),
        };
        $statusBadge = match($statusKey) {
            'confirmed' => 'success',
            'active'    => 'primary',
            'pending'   => 'warning',
            'completed' => 'secondary',
            'cancelled' => 'danger',
            default     => 'light',
        };
    }
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Booking #{{ $booking->booking_number }}</h4>
        <span class="text-muted">{{ $booking->court?->name }}</span>
    </div>
    <span class="badge rounded-pill bg-{{ $statusBadge }}-subtle text-{{ $statusBadge }}-emphasis fs-6">{{ $statusLabel }}</span>
</div>

{{-- Pending-approval banner --}}
@if($booking->approval_status === 'pending')
<div class="alert alert-warning d-flex align-items-start mb-4">
    <i class="bi bi-hourglass-split me-3 fs-4 flex-shrink-0"></i>
    <div>
        <strong>Your booking is pending approval.</strong>
        <div class="small mt-1">
            You chose to pay <strong>cash</strong>. Venue staff will review and confirm your slot shortly.
            You'll be notified as soon as a decision is made.
        </div>
    </div>
</div>
@endif

{{-- Denied banner --}}
@if($booking->approval_status === 'denied')
<div class="alert alert-danger d-flex align-items-start mb-4">
    <i class="bi bi-x-octagon me-3 fs-4 flex-shrink-0"></i>
    <div>
        <strong>Your booking has been denied.</strong>
        @if($booking->denial_note)
            <div class="small mt-1">
                <strong>Reason from venue:</strong> {{ $booking->denial_note }}
            </div>
        @endif
        <div class="small mt-1 text-muted">
            If you have questions, please contact the venue directly.
        </div>
    </div>
</div>
@endif

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted fw-normal">Date</dt>
                    <dd class="col-sm-8">{{ $booking->booking_date?->format('l, M d, Y') }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Time</dt>
                    <dd class="col-sm-8">{{ \Illuminate\Support\Carbon::parse($booking->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($booking->end_time)->format('h:i A') }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Duration</dt>
                    <dd class="col-sm-8">{{ $booking->duration_minutes }} min</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Payment method</dt>
                    <dd class="col-sm-8">
                        @php
                            $pmIcon = match($booking->payment_method) {
                                'wallet'       => 'bi-wallet2',
                                'court_credit' => 'bi-stopwatch',
                                'cash'         => 'bi-cash',
                                default        => 'bi-question-circle',
                            };
                        @endphp
                        <i class="bi {{ $pmIcon }} me-1"></i>{{ $booking->paymentMethodLabel() }}
                    </dd>
                    <dt class="col-sm-4 text-muted fw-normal">Base</dt>
                    <dd class="col-sm-8">₱{{ number_format($booking->base_amount, 2) }}</dd>
                    @if ($booking->discount_amount > 0)
                    <dt class="col-sm-4 text-muted fw-normal">Discount</dt>
                    <dd class="col-sm-8 text-success">– ₱{{ number_format($booking->discount_amount, 2) }}</dd>
                    @endif
                    <dt class="col-sm-4 text-muted fw-normal">Tax</dt>
                    <dd class="col-sm-8">₱{{ number_format($booking->tax_amount, 2) }}</dd>
                    <dt class="col-sm-4 fw-semibold">Total</dt>
                    <dd class="col-sm-8 fw-semibold">₱{{ number_format($booking->total_amount, 2) }}</dd>
                    <dt class="col-sm-4 text-muted fw-normal">Paid</dt>
                    <dd class="col-sm-8">₱{{ number_format($booking->paid_amount, 2) }}</dd>
                </dl>
            </div>
        </div>

        @if (in_array($booking->status, ['pending','confirmed']) && $booking->approval_status !== 'denied')
            @php
                $cancelConfirm = match($booking->payment_method) {
                    'wallet'       => 'Cancel this booking? Any eligible refund will go back to your wallet.',
                    'court_credit' => 'Cancel this booking? The court-credit minutes you used will be restored to your membership.',
                    'cash'         => 'Cancel this booking? A cash refund request will be opened — please claim it at the venue.',
                    default        => 'Cancel this booking?',
                };
            @endphp
            <form method="POST" action="{{ route('customer.bookings.cancel', $booking) }}" class="mt-3"
                  onsubmit="return confirm({{ \Illuminate\Support\Js::from($cancelConfirm) }});">
                @csrf
                <input type="hidden" name="reason" value="Cancelled by customer">
                <button class="btn btn-outline-danger">
                    <i class="bi bi-x-circle me-1"></i> Cancel booking
                </button>
            </form>
        @endif

        {{-- Refund status: shows up once a cash refund request exists --}}
        @if ($booking->latestRefundRequest)
            @php $rr = $booking->latestRefundRequest; @endphp
            <div class="card mt-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0">Refund</h6>
                        @php
                            $rrBadge = match($rr->status) {
                                'pending'   => 'warning',
                                'processed' => 'success',
                                'denied'    => 'secondary',
                            };
                        @endphp
                        <span class="badge rounded-pill bg-{{ $rrBadge }}-subtle text-{{ $rrBadge }}-emphasis">{{ ucfirst($rr->status) }}</span>
                    </div>
                    <p class="small text-muted mb-2">
                        Amount: <strong>₱{{ number_format($rr->amount, 2) }}</strong> (cash)
                    </p>
                    @if($rr->status === 'pending')
                        <p class="small mb-0 text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Please visit the venue to receive your cash refund.
                        </p>
                    @elseif($rr->status === 'processed')
                        <p class="small mb-0 text-success">
                            <i class="bi bi-check-circle me-1"></i>
                            Paid out {{ $rr->processed_at?->format('M j, Y g:i a') }}.
                        </p>
                    @elseif($rr->status === 'denied')
                        <p class="small mb-0">
                            <i class="bi bi-x-octagon me-1 text-danger"></i>
                            <strong>Reason:</strong> {{ $rr->denial_note }}
                        </p>
                    @endif
                </div>
            </div>
        @endif
    </div>
    <div class="col-lg-5">
        @if ($booking->qr_code && $booking->approval_status !== 'pending' && $booking->approval_status !== 'denied')
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="text-muted fw-normal">Show this at the venue</h6>
                    <img src="{{ $booking->qr_code_image }}" alt="Booking QR" class="img-fluid" style="max-width:240px;">
                    <p class="small text-muted mt-2 mb-0">{{ $booking->booking_number }}</p>
                </div>
            </div>
        @elseif($booking->approval_status === 'pending')
            <div class="card">
                <div class="card-body text-center text-muted">
                    <i class="bi bi-hourglass-split display-5 d-block mb-2 text-warning"></i>
                    <p class="mb-0 small">Your QR code will appear once the venue approves the booking.</p>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
