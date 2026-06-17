@extends('layouts.app')
@section('title', 'Booking ' . $booking->booking_number)

@section('content')

<div x-data="timerCard()">

<x-page-header :title="$booking->booking_number" :back="route('admin.bookings.index')"
               subtitle="Booking details">
    <x-slot name="actions">
        <a href="{{ route('admin.bookings.receipt', $booking) }}" target="_blank"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-printer me-1"></i>Receipt
        </a>
    </x-slot>
</x-page-header>

{{-- Cash approval banner — visible whenever a customer-initiated cash booking is
     waiting for owner/staff action. Approve = confirm slot; Deny = with note. --}}
@if($booking->approval_status === 'pending')
<div class="alert alert-warning mb-4">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
        <div>
            <h6 class="alert-heading mb-1">
                <i class="bi bi-cash-coin me-1"></i>Cash payment approval required
            </h6>
            <p class="mb-0 small">
                Customer chose to pay <strong>cash</strong> on arrival. Approve to finalise the slot,
                or deny with a note explaining why.
            </p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}"
                  onsubmit="return confirm('Approve this booking? The customer will be notified.')">
                @csrf
                <button class="btn btn-success btn-sm">
                    <i class="bi bi-check2-circle me-1"></i>Approve
                </button>
            </form>
            <button type="button" class="btn btn-outline-danger btn-sm"
                    data-bs-toggle="modal" data-bs-target="#denyBookingModal">
                <i class="bi bi-x-octagon me-1"></i>Deny
            </button>
        </div>
    </div>
</div>
@endif

@if($booking->approval_status === 'denied' && $booking->denial_note)
<div class="alert alert-danger small mb-4">
    <strong><i class="bi bi-x-octagon me-1"></i>Booking denied</strong>
    @if($booking->deniedBy)
        by {{ $booking->deniedBy->name }}
    @endif
    @if($booking->denied_at)
        on {{ $booking->denied_at->format('M j, Y · g:i A') }}
    @endif
    <div class="mt-1">Reason: <em>{{ $booking->denial_note }}</em></div>
</div>
@endif

{{-- Active session banner --}}
@if($booking->status === 'active')
<div class="alert alert-success d-flex align-items-center justify-content-between gap-3 mb-4 flex-wrap">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center"
             style="width:40px;height:40px">
            <i class="bi bi-clock fs-5"></i>
        </div>
        <div>
            <p class="mb-0 fw-semibold">Session Active</p>
            <p class="mb-0 small">
                Time remaining:
                <span x-text="remaining"
                      :class="remainingSec <= 0 ? 'font-monospace fw-bold text-danger' : 'font-monospace fw-bold'">--:--</span>
                <span x-show="remainingSec <= 0" class="badge rounded-pill bg-danger ms-1">Overtime</span>
            </p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button @click="extend(30)" :disabled="busy"
                class="btn btn-outline-secondary btn-sm">
            <span x-show="!busy">+30 min</span>
            <span x-show="busy">…</span>
        </button>
        <button @click="stop()" :disabled="busy"
                class="btn btn-outline-danger btn-sm">Stop</button>
    </div>
</div>
@endif

<div class="row g-4">
    {{-- Main info --}}
    <div class="col-12 col-lg-8">

        {{-- Booking details --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Booking Details</h6>
                @php
                $badge = match($booking->status) {
                    'confirmed' => 'bg-primary-subtle text-primary',
                    'active'    => 'bg-success-subtle text-success',
                    'completed' => 'bg-secondary-subtle text-secondary',
                    'pending'   => 'bg-warning-subtle text-warning',
                    'cancelled' => 'bg-danger-subtle text-danger',
                    default     => 'bg-secondary-subtle text-secondary',
                };
                $cashBadge = $booking->cashPaymentBadge();
                @endphp
                <div class="d-flex align-items-center gap-2">
                    @if($cashBadge)
                        <span class="badge rounded-pill bg-{{ $cashBadge['color'] }}-subtle text-{{ $cashBadge['color'] }}">
                            <i class="bi bi-cash-coin me-1"></i>{{ $cashBadge['label'] }}
                        </span>
                    @endif
                    <span class="badge rounded-pill {{ $badge }}">{{ ucfirst(str_replace('_', ' ', $booking->status)) }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    <div class="col-sm-6">
                        <p class="text-muted mb-1">Court</p>
                        <p class="fw-medium mb-0">{{ $booking->court->name }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted mb-1">Date</p>
                        <p class="fw-medium mb-0">{{ $booking->booking_date->format('l, F j, Y') }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted mb-1">Time</p>
                        <p class="fw-medium mb-0">{{ \Carbon\Carbon::parse($booking->start_time)->format('g:i A') }} – {{ \Carbon\Carbon::parse($booking->end_time)->format('g:i A') }}</p>
                    </div>
                    <div class="col-sm-6">
                        <p class="text-muted mb-1">Duration</p>
                        <p class="fw-medium mb-0">{{ $booking->duration_minutes }} minutes</p>
                    </div>
                    @if($booking->notes)
                    <div class="col-12">
                        <p class="text-muted mb-1">Notes</p>
                        <p class="mb-0">{{ $booking->notes }}</p>
                    </div>
                    @endif
                </div>

                @php $graceMinutes = (int) ($booking->court->tenant->settings['grace_period_minutes'] ?? 5); @endphp
                @if($graceMinutes > 0)
                <div class="alert alert-info small mb-0 mt-3 py-2 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>{{ $graceMinutes }}-minute grace period</strong> after end time.
                    Play beyond that is billed at the court rate.
                </div>
                @endif
            </div>
        </div>

        {{-- Payment summary --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-semibold">Payment Summary</h6>
                @if($booking->payment_method)
                    @php
                        $pmBadge = match($booking->payment_method) {
                            'wallet'       => 'bg-primary-subtle text-primary',
                            'court_credit' => 'bg-info-subtle text-info',
                            'cash'         => 'bg-secondary-subtle text-secondary',
                            default        => 'bg-light text-muted',
                        };
                    @endphp
                    <span class="badge rounded-pill {{ $pmBadge }}">
                        <i class="bi bi-wallet2 me-1"></i>{{ $booking->paymentMethodLabel() }}
                        @if($booking->payment_method === 'cash')
                            @if($booking->approval_status === 'pending')
                                · Pending
                            @elseif($booking->approval_status === 'approved')
                                · Approved
                            @elseif($booking->approval_status === 'denied')
                                · Denied
                            @endif
                        @endif
                    </span>
                @endif
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2 small">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Court rental</span>
                        <span>₱{{ number_format($booking->base_amount, 2) }}</span>
                    </div>
                    @if($booking->discount_amount > 0)
                    <div class="d-flex justify-content-between text-success">
                        <span>Discount</span>
                        <span>-₱{{ number_format($booking->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    @if($booking->tax_amount > 0)
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Tax</span>
                        <span>₱{{ number_format($booking->tax_amount, 2) }}</span>
                    </div>
                    @endif
                    @if($booking->overtime_amount > 0)
                    <div class="d-flex justify-content-between text-warning">
                        <span>Overtime</span>
                        <span>+₱{{ number_format($booking->overtime_amount, 2) }}</span>
                    </div>
                    @endif
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fw-semibold">
                        <span>Total</span>
                        <span>₱{{ number_format($booking->total_amount, 2) }}</span>
                    </div>
                    <div class="d-flex justify-content-between text-success">
                        <span>Paid</span>
                        <span>₱{{ number_format($booking->paid_amount, 2) }}</span>
                    </div>
                    @if($booking->balance_due > 0)
                    <div class="d-flex justify-content-between text-danger fw-medium">
                        <span>Balance due</span>
                        <span>₱{{ number_format($booking->balance_due, 2) }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Add-ons --}}
        @if($booking->addons->isNotEmpty())
        <div class="card">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Add-ons</h6></div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2 small">
                    @foreach($booking->addons as $addon)
                    <div class="d-flex justify-content-between">
                        <span>{{ $addon->name }} &times; {{ $addon->quantity }}</span>
                        <span class="fw-medium">₱{{ number_format($addon->amount, 2) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Sidebar --}}
    <div class="col-12 col-lg-4">

        {{-- Customer --}}
        <div class="card mb-4">
            <div class="card-header">
                <p class="mb-0 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em">Customer</p>
            </div>
            <div class="card-body">
                @if($booking->customer)
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10 text-success fw-semibold"
                         style="width:40px;height:40px">
                        {{ strtoupper(substr($booking->customer->name, 0, 1)) }}
                    </div>
                    <div class="min-w-0">
                        <p class="mb-0 fw-medium small text-truncate">{{ $booking->customer->name }}</p>
                        <small class="text-muted d-block text-truncate">{{ $booking->customer->email }}</small>
                    </div>
                </div>
                <a href="{{ route('admin.customers.show', $booking->customer) }}"
                   class="btn btn-outline-secondary btn-sm w-100">View Profile</a>
                @else
                <p class="text-muted small mb-0">Walk-in customer</p>
                @if($booking->customer_name)
                <p class="fw-medium small mt-1 mb-0">{{ $booking->customer_name }}</p>
                @endif
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="card mb-4">
            <div class="card-header">
                <p class="mb-0 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em">Actions</p>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                @if($booking->isPending())
                <form method="POST" action="{{ route('admin.bookings.confirm', $booking) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-success w-100 btn-sm">
                        <i class="bi bi-check-circle me-1"></i>Confirm Booking
                    </button>
                </form>
                @endif

                @if($booking->isConfirmed())
                <form method="POST" action="{{ route('admin.bookings.timer.start', $booking) }}">
                    @csrf
                    <button class="btn btn-primary w-100 btn-sm">
                        <i class="bi bi-play-circle me-1"></i>Start Timer
                    </button>
                </form>
                @endif

                @php
                    $cashRemaining = round((float) $booking->total_amount - (float) $booking->paid_amount, 2);
                @endphp
                @if($booking->payment_method === 'cash'
                    && !in_array($booking->status, ['cancelled','denied'])
                    && $cashRemaining > 0)
                <button type="button" class="btn btn-success w-100 btn-sm"
                        data-bs-toggle="modal" data-bs-target="#collectCashModal">
                    <i class="bi bi-cash-coin me-1"></i>Collect Cash (₱{{ number_format($cashRemaining, 2) }})
                </button>
                @endif

                @if(!in_array($booking->status, ['completed','cancelled']))
                <button type="button" class="btn btn-outline-danger w-100 btn-sm"
                        data-bs-toggle="modal" data-bs-target="#cancelBookingModal">
                    <i class="bi bi-x-circle me-1"></i>Cancel Booking
                </button>
                @endif
            </div>
        </div>

        {{-- QR code --}}
        @if($booking->qr_code)
        <div class="card text-center">
            <div class="card-header">
                <p class="mb-0 text-muted small fw-semibold text-uppercase" style="letter-spacing:.05em">QR Code</p>
            </div>
            <div class="card-body">
                <img src="{{ $booking->qr_code_image }}" alt="QR Code" class="img-fluid mx-auto d-block" style="max-width:128px">
                <small class="text-muted d-block mt-2 font-monospace">{{ $booking->booking_number }}</small>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Overtime settlement modal — opens when Stop is clicked and the session has
     overtime past the grace period. Staff MUST pick Pay or Void to close. --}}
<div
    :class="overtimeOpen ? 'd-flex' : 'd-none'"
    style="position:fixed;inset:0;z-index:1080;align-items:center;justify-content:center;background:rgba(15,23,42,0.55);backdrop-filter:blur(2px)"
    role="alertdialog" aria-modal="true"
>
    <div class="card shadow-lg" style="width:100%;max-width:480px;margin:1rem">
        <div class="card-body">
            <div class="d-flex align-items-start gap-2 mb-3">
                <i class="bi bi-stopwatch text-danger fs-4"></i>
                <div class="flex-grow-1">
                    <h6 class="mb-1">Overtime Summary</h6>
                    <div class="small text-muted">{{ $booking->court->name }} · Booking #{{ $booking->booking_number }}</div>
                </div>
            </div>

            <div class="d-flex justify-content-between small mb-2">
                <span class="text-muted">Total overtime</span>
                <span class="fw-medium" x-text="(overtimePreview?.minutes || 0) + ' min'"></span>
            </div>

            <div class="border rounded mb-3" x-show="(overtimePreview?.segments || []).length">
                <div class="px-3 py-2 bg-body-tertiary small text-uppercase fw-semibold text-muted">Breakdown</div>
                <template x-for="seg in (overtimePreview?.segments || []).filter(s => s.seconds > 0)" :key="seg.tier + '-' + seg.seconds">
                    <div class="px-3 py-2 border-top d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-medium" x-text="seg.label"></div>
                            <div class="small text-muted">
                                <span x-text="seg.minutes"></span> min @ ₱<span x-text="Number(seg.rate).toFixed(2)"></span>/hr
                            </div>
                        </div>
                        <div class="fw-semibold">₱<span x-text="Number(seg.charge).toFixed(2)"></span></div>
                    </div>
                </template>
                <div class="px-3 py-2 border-top d-flex justify-content-between bg-danger-subtle">
                    <span class="fw-semibold">Final amount to pay</span>
                    <span class="fw-bold text-danger">₱<span x-text="Number(overtimePreview?.charge || 0).toFixed(2)"></span></span>
                </div>
            </div>

            <p class="small text-muted">
                Pay records a cash payment and adds it to today's revenue.
                Void waives the fee — the overtime is still logged for the audit trail.
                Pick one to close the session.
            </p>

            <div class="d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        :disabled="busy"
                        @click="settleOvertime('void')">Void Overtime</button>
                <button type="button" class="btn btn-success btn-sm"
                        :disabled="busy"
                        @click="settleOvertime('collect')">
                    Pay Overtime ₱<span x-text="Number(overtimePreview?.charge || 0).toFixed(2)"></span>
                </button>
            </div>
        </div>
    </div>
</div>

</div>

@push('modals')
{{-- Collect cash — record a cash payment at the desk against this booking --}}
@php
    $cashRemainingModal = round((float) $booking->total_amount - (float) $booking->paid_amount, 2);
@endphp
@if($booking->payment_method === 'cash'
    && !in_array($booking->status, ['cancelled','denied'])
    && $cashRemainingModal > 0)
<div class="modal fade" id="collectCashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.bookings.collect-cash', $booking) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="bi bi-cash-coin me-1"></i>Collect cash — {{ $booking->booking_number }}
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="border rounded bg-body-tertiary p-3 small mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total</span>
                        <strong>₱{{ number_format($booking->total_amount, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Already paid</span>
                        <strong>₱{{ number_format($booking->paid_amount, 2) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Remaining balance</span>
                        <strong class="text-danger">₱{{ number_format($cashRemainingModal, 2) }}</strong>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-medium">Amount collected (₱) <span class="text-danger">*</span></label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                           max="{{ $cashRemainingModal }}"
                           value="{{ $cashRemainingModal }}"
                           required class="form-control">
                    <small class="text-muted">For partial collection, lower this amount. Cannot exceed the remaining balance.</small>
                </div>

                <div class="mb-1">
                    <label class="form-label small fw-medium">Receipt / reference # (optional)</label>
                    <input type="text" name="reference" maxlength="100" class="form-control"
                           placeholder="e.g. cash-drawer slip number">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check2-circle me-1"></i>Record cash payment
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Cancel booking — optional refund delivered according to original payment method --}}
@if(!in_array($booking->status, ['completed','cancelled']))
@php
    $refundPreview = match ($booking->payment_method) {
        'wallet'       => 'Full paid amount will be credited back to the customer\'s wallet immediately.',
        'court_credit' => 'The exact court-credit minutes used will be restored to the customer\'s membership.',
        'cash'         => 'A pending cash refund request will be created for the full paid amount. Settle the payout at the desk via Refund Requests.',
        default        => 'No refundable payment recorded.',
    };
@endphp
<div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" class="modal-content">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h6 class="modal-title">Cancel booking — {{ $booking->booking_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-medium">Reason</label>
                    <input type="text" name="reason" maxlength="255" required value="Cancelled by staff"
                           class="form-control">
                </div>

                <div class="border rounded bg-body-tertiary p-3 small mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Payment method</span>
                        <strong>{{ $booking->paymentMethodLabel() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Paid</span>
                        <strong>₱{{ number_format($booking->paid_amount, 2) }}</strong>
                    </div>
                    <div class="text-muted" style="font-size:12px;">{{ $refundPreview }}</div>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="refund" value="1" id="refundCheckbox" checked>
                    <label class="form-check-label small" for="refundCheckbox">
                        Issue a refund (subject to the venue's refund window policy)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Keep booking</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Cancel booking</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Deny booking — requires a note that's forwarded to the customer --}}
<div class="modal fade" id="denyBookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.bookings.deny', $booking) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title">Deny booking — {{ $booking->booking_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    A denial note is required. It will be included in the notification sent to {{ $booking->customer?->name ?? 'the customer' }}.
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-medium">Reason <span class="text-danger">*</span></label>
                    <textarea name="denial_note" rows="3" maxlength="500" required class="form-control"
                              placeholder="e.g. Court unavailable due to maintenance, double-booking with league night, etc."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-octagon me-1"></i>Deny Booking</button>
            </div>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
@php
    $tm = $booking->timer;
    $scheduledEndMs = $tm?->scheduled_end_at ? $tm->scheduled_end_at->getTimestamp() * 1000 : 0;
    $startedAtMs    = $tm?->started_at       ? $tm->started_at->getTimestamp() * 1000       : 0;
    $pausedSeconds  = (int) ($tm?->paused_seconds ?? 0);
    $autoStopEnabled = (bool) ($booking->court->tenant->settings['auto_stop_after_grace'] ?? false);
    $graceEndMs      = $tm?->scheduled_end_at
        ? $tm->scheduled_end_at->copy()->addSeconds((int) $tm->grace_period_seconds)->getTimestamp() * 1000
        : 0;
@endphp

function timerCard() {
    return {
        elapsed: '--:--', remaining: '--:--', interval: null, poller: null, busy: false,
        timerId:        {{ (int) ($tm?->id ?? 0) }},
        courtId:        {{ (int) $booking->court_id }},
        scheduledEndMs: {{ $scheduledEndMs }},
        startedAtMs:    {{ $startedAtMs }},
        pausedSeconds:  {{ $pausedSeconds }},
        autoStopEnabled: {{ $autoStopEnabled ? 'true' : 'false' }},
        graceEndMs:     {{ $graceEndMs }},
        stopped:        false,
        elapsedSec: 0,
        remainingSec: 0,
        extendUrl: @json(route('admin.bookings.timer.extend', $booking)),
        stopUrl:   @json(route('admin.bookings.timer.stop',   $booking)),
        stateUrl:  @json(route('admin.bookings.timer.state',  $booking)),
        bus: null,

        // Overtime settlement modal state — populated when the Stop endpoint
        // reports overtime is owed. Closed only by Pay Overtime or Void Overtime.
        overtimeOpen: false,
        overtimePreview: null,
        overtimeBookingNumber: '',
        overtimeCourtName: '',

        init() {
            @if($booking->status === 'active' && $tm)
            this.recompute();
            // Tick from absolute timestamps — never drifts; pages with the same
            // scheduled_end_at will always display the same remaining time.
            // Run at 250ms (not 1s) so the displayed second updates right at the
            // boundary: setInterval jitter/GC can otherwise delay a 1s tick past
            // the boundary, making the countdown visibly skip/hold a second.
            this.interval = setInterval(() => this.recompute(), 250);

            // Server-state poll. Drives auto-stop-after-grace for THIS session even
            // when no cron/queue worker/websocket is running: as long as the page
            // is open, the server stops the session at grace expiry and we reload.
            this.poller = setInterval(() => this.pollState(), 5000);

            if ('BroadcastChannel' in window) {
                this.bus = new BroadcastChannel('courtmaster-timer');
                this.bus.onmessage = (ev) => this.onBus(ev.data);
            }

            if (window.Echo) {
                window.Echo.channel('court.' + this.courtId + '.timer')
                    .listen('.timer.updated', (e) => {
                        // Server can tell us the authoritative new scheduled end.
                        if (typeof e.scheduled_end_ms === 'number') this.scheduledEndMs = e.scheduled_end_ms;
                        this.recompute();
                    });
            }
            @endif
        },

        recompute() {
            const now = Date.now();
            this.elapsedSec   = Math.max(0, Math.floor((now - this.startedAtMs) / 1000) - this.pausedSeconds);
            this.remainingSec = Math.max(0, Math.floor((this.scheduledEndMs - now) / 1000));

            // Auto-stop policy: once the grace window closes, freeze the display so
            // the timer visibly stops counting immediately and never shows time
            // accruing past the boundary. The pollState() call confirms the
            // server-side stop and reloads into the completed view.
            if (this.autoStopEnabled && this.graceEndMs && now >= this.graceEndMs && !this.stopped) {
                this.stopped = true;
                const cappedElapsed = Math.max(0, Math.floor((this.graceEndMs - this.startedAtMs) / 1000) - this.pausedSeconds);
                this.elapsedSec   = cappedElapsed;
                this.remainingSec = 0;
                this.elapsed   = this.fmt(cappedElapsed);
                this.remaining = this.fmt(0);
                if (this.interval) { clearInterval(this.interval); this.interval = null; }
                this.pollState();
                return;
            }

            this.elapsed   = this.fmt(this.elapsedSec);
            this.remaining = this.fmt(this.remainingSec);
        },

        async pollState() {
            try {
                const url = this.stateUrl + (this.stateUrl.includes('?') ? '&' : '?') + '_=' + Date.now();
                const r = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!r.ok) return;
                const data = await r.json();

                // Keep the authoritative scheduled end in sync (e.g. extended elsewhere),
                // but only re-anchor when it genuinely moved (>1.5s). Re-seeding on every
                // poll with a near-identical value is what made the countdown visibly
                // jump; the local clock already ticks smoothly from this timestamp.
                if (typeof data.scheduled_end_ms === 'number'
                    && Math.abs(data.scheduled_end_ms - this.scheduledEndMs) > 1500) {
                    this.scheduledEndMs = data.scheduled_end_ms;
                    this.recompute();
                }

                // Session no longer active → it was auto-stopped (or stopped on
                // another device). Halt timers and reload into the final view.
                if (data.active === false) {
                    if (this.interval) clearInterval(this.interval);
                    if (this.poller)   clearInterval(this.poller);
                    window.location.reload();
                }
            } catch (e) {
                // Network blip — next tick retries.
            }
        },

        onBus(msg) {
            if (!msg || (msg.timerId && msg.timerId !== this.timerId && msg.courtId !== this.courtId)) return;
            if (msg.type === 'extend' && msg.minutes) {
                this.scheduledEndMs += msg.minutes * 60 * 1000;
                this.recompute();
            } else if (msg.type === 'stop') {
                if (this.interval) clearInterval(this.interval);
                window.location.reload();
            }
        },

        publish(msg) {
            if (this.bus) this.bus.postMessage(msg);
        },

        fmt(sec) {
            sec = Math.max(0, Math.floor(sec));
            const h = Math.floor(sec / 3600);
            const m = Math.floor((sec % 3600) / 60);
            const s = sec % 60;
            return h > 0
                ? `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`
                : `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        },

        async extend(minutes) {
            if (this.busy) return;
            this.busy = true;
            try {
                const r = await fetch(this.extendUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ minutes }),
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    alert(data.message || `Extend failed (${r.status})`);
                    return;
                }
                this.scheduledEndMs += minutes * 60 * 1000;
                this.recompute();
                this.publish({ type: 'extend', timerId: this.timerId, courtId: this.courtId, minutes });
            } catch (e) {
                alert('Network error: ' + e.message);
            } finally {
                this.busy = false;
            }
        },

        async stop() {
            return this.stopWithSettlement(null);
        },

        async stopWithSettlement(settlement) {
            if (this.busy) return;
            if (!settlement && !confirm('Stop the session and complete this booking?')) return;
            this.busy = true;
            try {
                const body = settlement ? JSON.stringify({ settlement }) : undefined;
                const r = await fetch(this.stopUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    alert(data.message || `Stop failed (${r.status})`);
                    this.busy = false;
                    return;
                }

                // Overtime owed — show the settlement modal and wait for staff
                // to pick Pay or Void.
                if (data.requires_settlement) {
                    this.overtimePreview = data.overtime;
                    this.overtimeBookingNumber = data.booking_number || '';
                    this.overtimeCourtName = data.court_name || '';
                    this.overtimeOpen = true;
                    this.busy = false;
                    return;
                }

                if (this.interval) clearInterval(this.interval);
                this.publish({ type: 'stop', timerId: this.timerId, courtId: this.courtId });
                window.location.reload();
            } catch (e) {
                alert('Network error: ' + e.message);
                this.busy = false;
            }
        },

        async settleOvertime(choice) {
            if (choice === 'void') {
                const amt = Number(this.overtimePreview?.charge || 0).toFixed(2);
                if (!confirm(`Waive ₱${amt} of overtime and close the session?`)) return;
            }
            await this.stopWithSettlement(choice);
            this.overtimeOpen = false;
        },
    };
}
</script>
@endpush

@endsection
