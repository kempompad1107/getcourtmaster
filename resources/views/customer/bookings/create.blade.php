@extends('layouts.customer')

@section('title', 'Book a Court')

@push('styles')
<style>
[x-cloak]{display:none!important}
/* Wizard stepper — numbered circles + connectors */
.wz-dot{width:30px;height:30px;border-radius:50%;flex:0 0 auto;
    display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;
    background:var(--bs-secondary-bg,#e2e8f0);color:var(--bs-secondary-color,#94a3b8);
    transition:background .2s ease,color .2s ease,box-shadow .2s ease}
.wz-dot-on{background:#10b981;color:#fff;box-shadow:0 4px 10px -2px rgba(16,185,129,.5)}
.wz-line{height:3px;border-radius:3px;margin:0 6px;background:var(--bs-secondary-bg,#e2e8f0);transition:background .2s ease}
.wz-line-on{background:#10b981}
</style>
@endpush

@section('content')

@php
    // The customer's active membership credits (in minutes) — used to gate
    // the Court Credit payment option to only those who can actually use it.
    $activeMembership = auth()->user()->activeMembership;
    $availableCredits = (int) ($activeMembership?->remaining_credits ?? 0);
    $walletBalance    = (float) (auth()->user()->wallet_balance ?? 0);
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Book a Court</h4>
        <p class="text-muted mb-0">Pick a court, time and how you'd like to pay.</p>
    </div>
    <a href="{{ route('customer.bookings.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>My bookings
    </a>
</div>

<div class="card" x-data="bookingForm()">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('customer.bookings.store') }}" @submit.prevent="submitForm($el)">
            @csrf

            {{-- Server-side validation errors --}}
            @if($errors->any())
            <div class="alert alert-danger alert-dismissible mb-4">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                    <li class="small">{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            {{-- Wizard progress — numbered steps --}}
            <div class="mb-4">
                <div class="d-flex align-items-center">
                    <div class="wz-dot" :class="step >= 1 && 'wz-dot-on'">
                        <span x-show="step <= 1">1</span><i class="bi bi-check-lg" x-show="step > 1" x-cloak></i>
                    </div>
                    <div class="wz-line flex-fill" :class="step >= 2 && 'wz-line-on'"></div>
                    <div class="wz-dot" :class="step >= 2 && 'wz-dot-on'">
                        <span x-show="step <= 2">2</span><i class="bi bi-check-lg" x-show="step > 2" x-cloak></i>
                    </div>
                    <div class="wz-line flex-fill" :class="step >= 3 && 'wz-line-on'"></div>
                    <div class="wz-dot" :class="step >= 3 && 'wz-dot-on'">3</div>
                </div>
                <div class="d-flex justify-content-between small fw-semibold mt-2">
                    <span :class="step >= 1 ? 'fw-semibold' : 'text-muted'" :style="step >= 1 ? 'color:#10b981' : ''">Court &amp; time</span>
                    <span :class="step >= 2 ? 'fw-semibold' : 'text-muted'" :style="step >= 2 ? 'color:#10b981' : ''">Payment</span>
                    <span :class="step >= 3 ? 'fw-semibold' : 'text-muted'" :style="step >= 3 ? 'color:#10b981' : ''">Confirm</span>
                </div>
            </div>

            {{-- Step 1: Court & time --}}
            <div x-show="step === 1">
                {{-- Court & Date --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label fw-medium">Court <span class="text-danger">*</span></label>
                        <select name="court_id" required x-model="courtId" @change="onCourtChange()"
                                class="form-select">
                            <option value="">Select a court…</option>
                            @foreach($courts as $court)
                                <option value="{{ $court->id }}"
                                        data-rate="{{ $court->base_hourly_rate }}"
                                        data-min="{{ $court->min_booking_minutes }}"
                                        data-max="{{ $court->max_booking_minutes }}">
                                    {{ $court->name }} ({{ ucfirst($court->type ?? 'court') }}) — ₱{{ number_format($court->base_hourly_rate, 0) }}/hr
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-medium">Date <span class="text-danger">*</span></label>
                        <input type="date" name="booking_date" required
                               min="{{ now()->toDateString() }}" max="{{ now()->addDays(30)->toDateString() }}"
                               x-model="bookingDate" @change="onDateChange()"
                               class="form-control">
                    </div>
                </div>

                {{-- Time-first picker: start time + duration + timeline + verdict --}}
                @include('partials.booking-time-picker', ['staff' => false])
            </div>

            {{-- Step 2: Payment --}}
            <div x-show="step === 2" x-cloak>
                {{-- Hidden fields for online gateway selection --}}
                <input type="hidden" name="gateway" x-model="gatewayName">
                <input type="hidden" name="gateway_method" x-model="gatewayMethod">

                {{-- Payment method picker --}}
                <div class="mb-3" x-show="selectedSlot" x-cloak>
                    <label class="form-label fw-medium">Payment method <span class="text-danger">*</span></label>

                    @if($requirePayment && empty($availableGateways))
                        <div class="alert alert-warning small mb-3">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            This venue requires payment before a booking is confirmed. Cash bookings will remain <strong>pending</strong> until you pay at the venue.
                        </div>
                    @endif

                    <div class="row g-2">
                        {{-- Online payment via gateway (shown first when available) --}}
                        @php
                            $pmLabels = ['gcash' => 'GCash', 'paymaya' => 'Maya / PayMaya', 'card' => 'Credit / Debit Card', 'qrph' => 'QR Ph'];
                        @endphp
                        @if(!empty($availableGateways))
                            @if(in_array('paymongo', $availableGateways))
                                @foreach($paymongoMethods as $pm)
                                <div class="col-6 col-md-4">
                                    <label class="card border-2 p-3 h-100 mb-0"
                                           :class="paymentMethod === 'online' && gatewayName === 'paymongo' && gatewayMethod === '{{ $pm }}' ? 'border-success' : 'border'"
                                           style="cursor:pointer"
                                           @click="paymentMethod = 'online'; gatewayName = 'paymongo'; gatewayMethod = '{{ $pm }}'">
                                        <input type="radio" name="payment_method" value="online"
                                               class="form-check-input mb-2"
                                               :checked="paymentMethod === 'online' && gatewayName === 'paymongo' && gatewayMethod === '{{ $pm }}'">
                                        <div class="fw-semibold small">
                                            <i class="bi bi-phone me-1"></i>{{ $pmLabels[$pm] ?? $pm }}
                                            @if($requirePayment)<span class="badge bg-success ms-1 fw-normal" style="font-size:10px">Recommended</span>@endif
                                        </div>
                                        <div class="small text-muted">Pay online — booking confirmed instantly.</div>
                                        <div x-show="paymentMethod === 'online' && gatewayName === 'paymongo' && gatewayMethod === '{{ $pm }}'"
                                             x-cloak class="small text-success mt-1">
                                            You'll be redirected to complete payment.
                                        </div>
                                    </label>
                                </div>
                                @endforeach
                            @endif
                            @if(in_array('stripe', $availableGateways))
                            <div class="col-6 col-md-4">
                                <label class="card border-2 p-3 h-100 mb-0"
                                       :class="paymentMethod === 'online' && gatewayName === 'stripe' ? 'border-success' : 'border'"
                                       style="cursor:pointer"
                                       @click="paymentMethod = 'online'; gatewayName = 'stripe'; gatewayMethod = ''">
                                    <input type="radio" name="payment_method" value="online"
                                           class="form-check-input mb-2"
                                           :checked="paymentMethod === 'online' && gatewayName === 'stripe'">
                                    <div class="fw-semibold small">
                                        <i class="bi bi-credit-card me-1"></i>International Card (Stripe)
                                        @if($requirePayment)<span class="badge bg-success ms-1 fw-normal" style="font-size:10px">Recommended</span>@endif
                                    </div>
                                    <div class="small text-muted">Pay online — booking confirmed instantly.</div>
                                    <div x-show="paymentMethod === 'online' && gatewayName === 'stripe'"
                                         x-cloak class="small text-success mt-1">
                                        You'll be redirected to complete payment.
                                    </div>
                                </label>
                            </div>
                            @endif
                        @endif

                        {{-- Wallet --}}
                        <div class="col-6 col-md-4">
                            <label class="card border-2 p-3 h-100 mb-0"
                                   :class="paymentMethod === 'wallet' ? 'border-success' : 'border'"
                                   style="cursor:pointer"
                                   @click="paymentMethod = 'wallet'; gatewayName = ''; gatewayMethod = ''">
                                <input type="radio" name="payment_method" value="wallet"
                                       x-model="paymentMethod" class="form-check-input mb-2" required>
                                <div class="fw-semibold small"><i class="bi bi-wallet2 me-1"></i>Wallet Balance</div>
                                <div class="small text-muted">Available: <strong>₱{{ number_format($walletBalance, 2) }}</strong></div>
                                <div x-show="paymentMethod === 'wallet' && !walletCovers" x-cloak
                                     class="small text-danger mt-1">
                                    Insufficient balance. <a href="{{ route('customer.wallet.index') }}">Top up your wallet</a> or pick another method.
                                </div>
                                <div x-show="paymentMethod === 'wallet' && walletCovers" x-cloak
                                     class="small text-success mt-1">
                                    Booking is confirmed instantly.
                                </div>
                            </label>
                        </div>

                        {{-- Court Credit --}}
                        <div class="col-6 col-md-4">
                            <label class="card border-2 p-3 h-100 mb-0"
                                   :class="paymentMethod === 'court_credit' ? 'border-success' : 'border'"
                                   :style="creditAvailable ? 'cursor:pointer' : 'cursor:not-allowed;opacity:.6'">
                                <input type="radio" name="payment_method" value="court_credit"
                                       x-model="paymentMethod" class="form-check-input mb-2"
                                       :disabled="!creditAvailable">
                                <div class="fw-semibold small"><i class="bi bi-stopwatch me-1"></i>Court Credit</div>
                                <div class="small text-muted">
                                    @if($availableCredits > 0)
                                        Available: <strong>{{ floor($availableCredits / 60) }}h {{ $availableCredits % 60 }}m</strong>
                                    @else
                                        No active membership credits.
                                    @endif
                                </div>
                                <div x-show="paymentMethod === 'court_credit'" x-cloak class="small mt-1"
                                     :class="creditFullyCovers ? 'text-success' : 'text-danger'"
                                     x-text="creditFullyCovers ? 'Fully covered. Booking is confirmed instantly.' : 'Your credit does not fully cover this slot.'"></div>
                            </label>
                        </div>

                        {{-- Cash (hidden when require_payment is on and gateways are available) --}}
                        @if(!($requirePayment && !empty($availableGateways)))
                        <div class="col-6 col-md-4">
                            <label class="card border-2 p-3 h-100 mb-0"
                                   :class="paymentMethod === 'cash' ? 'border-success' : 'border'"
                                   style="cursor:pointer"
                                   @click="paymentMethod = 'cash'; gatewayName = ''; gatewayMethod = ''">
                                <input type="radio" name="payment_method" value="cash"
                                       x-model="paymentMethod" class="form-check-input mb-2">
                                <div class="fw-semibold small"><i class="bi bi-cash me-1"></i>Cash</div>
                                <div class="small text-muted">Pay at the venue on arrival.</div>
                                <div x-show="paymentMethod === 'cash'" x-cloak class="small text-warning mt-1">
                                    Booking will be <strong>pending approval</strong> by venue staff.
                                </div>
                            </label>
                        </div>
                        @endif
                    </div>

                    @if(empty($availableGateways))
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Wallet top-up is handled by venue staff. Please contact staff or owner to add balance.
                    </div>
                    @endif
                </div>

                {{-- Promo code (hidden when using credit) --}}
                <div class="mb-3" x-show="selectedSlot && paymentMethod !== 'court_credit'" x-cloak>
                    <label class="form-label fw-medium">Promo code <span class="text-muted small">(optional)</span></label>
                    <div class="input-group">
                        <input type="text" name="promo_code" x-model="promoCode"
                               class="form-control text-uppercase" placeholder="Enter code">
                        <button type="button" @click="validatePromo()"
                                :disabled="!promoCode || !selectedSlot"
                                class="btn btn-outline-secondary">Apply</button>
                    </div>
                    <p x-show="promoMessage" x-cloak x-text="promoMessage"
                       :class="discount > 0 ? 'text-success' : 'text-danger'"
                       class="small mt-1 mb-0"></p>
                </div>
            </div>

            {{-- Step 3: Confirm --}}
            <div x-show="step === 3" x-cloak>
                {{-- Notes --}}
                <div class="mb-3">
                    <label class="form-label fw-medium">Notes <span class="text-muted small">(optional)</span></label>
                    <textarea name="notes" rows="2" class="form-control" placeholder="Any special requests?">{{ old('notes') }}</textarea>
                </div>

                {{-- Price summary --}}
                <div x-show="selectedSlot" x-cloak class="bg-body-tertiary rounded-3 p-3 mb-4 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Court rental</span>
                        <span x-text="'₱' + bookingBaseAmount.toLocaleString()"></span>
                    </div>
                    <div x-show="paymentMethod === 'court_credit' && creditFreeAmount > 0" x-cloak class="d-flex justify-content-between mb-1 text-success">
                        <span>Court credit (<span x-text="creditMinutesUsed"></span> min)</span>
                        <span x-text="'-₱' + creditFreeAmount.toLocaleString()"></span>
                    </div>
                    <div x-show="paymentMethod !== 'court_credit' && discount > 0" x-cloak class="d-flex justify-content-between mb-1 text-success">
                        <span>Promo discount</span>
                        <span x-text="'-₱' + discount.toLocaleString()"></span>
                    </div>
                    <div class="d-flex justify-content-between fw-semibold border-top pt-2 mt-1 fs-6">
                        <span>Total</span>
                        <span class="text-success" x-text="'₱' + computedTotal.toLocaleString()"></span>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="button" class="btn btn-outline-secondary" x-show="step > 1" @click="back()">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </button>
                <a href="{{ route('customer.bookings.index') }}" class="btn btn-outline-secondary" x-show="step === 1">Cancel</a>
                <button type="button" class="btn btn-primary ms-auto" x-show="step < 3"
                        :disabled="(step === 1 && !step1Valid) || (step === 2 && !step2Valid)"
                        @click="next()">
                    Continue<i class="bi bi-arrow-right ms-1"></i>
                </button>
                <button type="submit" class="btn btn-primary ms-auto" x-show="step === 3" :disabled="submitDisabled">
                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-1"></span>
                    <i class="bi bi-calendar-check me-1" x-show="!submitting && paymentMethod !== 'online'"></i>
                    <i class="bi bi-arrow-right-circle me-1" x-show="!submitting && paymentMethod === 'online'" x-cloak></i>
                    <span x-text="paymentMethod === 'online' ? 'Continue to Payment' : 'Confirm Booking'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function bookingForm() {
    return {
        courtId:     '',
        bookingDate: '{{ now()->toDateString() }}',
        duration:    '60',
        selectedSlot: null,

        // Customer's own membership credits and wallet balance (Blade-injected).
        availableCredits: {{ $availableCredits }},
        walletBalance:    {{ $walletBalance }},
        paymentMethod:    '',
        gatewayName:      '',
        gatewayMethod:    '',

        promoCode: '', promoMessage: '', discount: 0,
        submitting: false,
        step: 1,
        get step1Valid() { return !!this.selectedSlot; },
        get step2Valid() {
            if (!this.paymentMethod) return false;
            if (this.paymentMethod === 'wallet'       && !this.walletCovers)      return false;
            if (this.paymentMethod === 'court_credit' && !this.creditFullyCovers) return false;
            if (this.paymentMethod === 'online'       && !this.gatewayName)       return false;
            return true;
        },
        next() {
            if (this.step === 1 && !this.step1Valid) return;
            if (this.step === 2 && !this.step2Valid) return;
            if (this.step < 3) this.step++;
        },
        back() { if (this.step > 1) this.step--; },

@include('partials.booking-time-picker-js', ['timelinePath' => '/app/courts', 'staff' => false, 'defaultStart' => '18:00'])

        get creditAvailable() {
            return this.availableCredits > 0;
        },
        get walletCovers() {
            return this.walletBalance >= this.computedTotal;
        },

        get bookingMinutes() {
            if (this.selectedSlot && this.selectedSlot.duration) {
                return parseInt(this.selectedSlot.duration, 10);
            }
            return parseInt(this.duration, 10) || 0;
        },
        get bookingBaseAmount() {
            return Number(this.selectedSlot?.total) || 0;
        },
        get creditMinutesUsed() {
            if (this.paymentMethod !== 'court_credit' || this.availableCredits <= 0 || this.bookingMinutes <= 0) return 0;
            return Math.min(this.availableCredits, this.bookingMinutes);
        },
        get creditFullyCovers() {
            return this.bookingMinutes > 0 && this.availableCredits >= this.bookingMinutes;
        },
        get creditFreeAmount() {
            if (this.creditMinutesUsed === 0 || this.bookingMinutes === 0) return 0;
            return Math.round(this.bookingBaseAmount * (this.creditMinutesUsed / this.bookingMinutes));
        },
        get computedTotal() {
            if (this.paymentMethod === 'court_credit') return Math.max(0, this.bookingBaseAmount - this.creditFreeAmount);
            return Math.max(0, this.bookingBaseAmount - this.discount);
        },
        get creditAvailableLabel() {
            const m = this.availableCredits;
            return `${Math.floor(m / 60)}h ${m % 60}m available`;
        },
        get creditCoverageLabel() {
            if (this.bookingMinutes === 0) return '';
            if (this.creditFullyCovers) return `${this.bookingMinutes} min covered — free`;
            const uncovered = this.bookingMinutes - this.creditMinutesUsed;
            return `${this.creditMinutesUsed} min covered, ${uncovered} min charged`;
        },
        get submitDisabled() {
            if (this.submitting || !this.selectedSlot || !this.paymentMethod) return true;
            if (this.paymentMethod === 'wallet'       && !this.walletCovers)      return true;
            if (this.paymentMethod === 'court_credit' && !this.creditFullyCovers) return true;
            if (this.paymentMethod === 'online'       && !this.gatewayName)       return true;
            return false;
        },

        async validatePromo() {
            if (!this.promoCode || !this.selectedSlot) return;
            try {
                const r = await fetch(`${window.APP_BASE}/app/promotions/validate`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.head.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ code: this.promoCode, amount: this.selectedSlot.total }),
                });
                const d = await r.json();
                if (d.valid) {
                    this.discount = d.discount;
                    this.promoMessage = `Promo applied! You save ₱${d.discount.toLocaleString()}`;
                } else {
                    this.discount = 0;
                    this.promoMessage = d.message ?? 'Invalid promo code.';
                }
            } catch (e) {
                this.promoMessage = 'Could not validate promo code.';
            }
        },

        submitForm(form) {
            if (this.submitDisabled) return;
            this.submitting = true;
            form.submit();
        },
    };
}
</script>
@endpush
