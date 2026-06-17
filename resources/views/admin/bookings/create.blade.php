@extends('layouts.app')
@section('title', 'New Booking')

@push('styles')
<style>
    [x-cloak]{display:none!important}
    .cust-avatar{
        width:30px;height:30px;flex-shrink:0;border-radius:50%;
        display:grid;place-items:center;font-weight:700;font-size:.75rem;
        color:#fff;background:linear-gradient(135deg,#10b981,#059669);
    }
</style>
@endpush

@section('content')

<x-page-header title="New Booking" :back="route('admin.bookings.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-8 col-xl-7">

<div class="card" x-data="bookingForm()">
    <div class="card-body p-4">
        <form method="POST"
              :action="isWalkIn
                       ? @js(route('admin.bookings.walk-in'))
                       : @js(route('admin.bookings.store'))"
              @submit.prevent="submitForm($el)">
            @csrf

            {{-- Validation errors from server --}}
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

            {{-- Booking type --}}
            <div class="mb-4">
                <label class="form-label fw-medium">Booking type</label>
                <input type="hidden" name="type" x-model="bookingType">
                <div class="settings-tabs">
                    @foreach(['walk_in' => 'Walk-in', 'online' => 'Online', 'phone' => 'Phone'] as $val => $label)
                    <button type="button" class="settings-tab-btn"
                            :class="bookingType === '{{ $val }}' && 'active'"
                            @click="bookingType = '{{ $val }}'; onTypeChange()">{{ $label }}</button>
                    @endforeach
                </div>
                <div x-show="isWalkIn" x-cloak class="form-text mt-2">
                    <i class="bi bi-lightning-charge me-1 text-warning"></i>
                    Walk-in starts immediately at the current time. No slot picker needed.
                </div>
            </div>

            {{-- Customer search --}}
            <div class="mb-3 position-relative">
                <label class="form-label fw-medium">Customer <span class="text-muted fw-normal">(optional)</span></label>

                {{-- Search box — shown until a customer is picked --}}
                <template x-if="!selectedCustomerId">
                    <div class="search-field">
                        <i class="bi bi-search"></i>
                        <input type="text" placeholder="Type name or email to search…"
                               x-model="customerSearch"
                               @input.debounce.350ms="searchCustomers()"
                               autocomplete="off">
                        <span x-show="searching" x-cloak
                              class="spinner-border spinner-border-sm text-secondary me-2"
                              style="width:1rem;height:1rem"></span>
                    </div>
                </template>

                {{-- Selected customer chip --}}
                <template x-if="selectedCustomerId">
                    <div class="d-flex align-items-center gap-2 p-2 rounded-3 border border-success bg-success-subtle">
                        <span class="cust-avatar" x-text="(customerSearch || '?').charAt(0).toUpperCase()"></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="small fw-semibold text-success-emphasis text-truncate" x-text="customerSearch"></div>
                            <div class="text-success d-flex align-items-center gap-1" style="font-size:.72rem">
                                <i class="bi bi-person-check-fill"></i>Selected
                            </div>
                        </div>
                        <button type="button" @click="clearCustomer()"
                                class="btn btn-sm btn-outline-danger border-0" title="Remove customer">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </template>

                <input type="hidden" name="customer_id" x-model="selectedCustomerId">

                {{-- Dropdown results --}}
                <div x-show="(customers.length > 0 || (customerSearched && customers.length === 0)) && !selectedCustomerId"
                     x-cloak
                     class="position-absolute w-100 bg-body border rounded-3 shadow z-3 mt-1 py-1" style="top:100%;max-height:240px;overflow-y:auto">
                    <template x-for="c in customers" :key="c.id">
                        <button type="button" @click="selectCustomer(c)"
                                class="dropdown-item d-flex align-items-center gap-2 px-3 py-2 text-start w-100">
                            <span class="cust-avatar" x-text="(c.name || '?').charAt(0).toUpperCase()"></span>
                            <span class="min-w-0">
                                <span class="d-block small fw-medium text-truncate" x-text="c.name"></span>
                                <span class="d-block text-muted text-truncate" style="font-size:.75rem" x-text="c.email"></span>
                            </span>
                        </button>
                    </template>
                    <div x-show="customerSearched && customers.length === 0" x-cloak
                         class="px-3 py-2 small text-muted">
                        No customers found for "<span x-text="customerSearch"></span>".
                    </div>
                </div>

                <div x-show="!selectedCustomerId" class="form-text">Leave empty for anonymous walk-in.</div>
            </div>

            {{-- Court, Date & Duration --}}
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Court <span class="text-danger">*</span></label>
                    <select name="court_id" required x-model="courtId" @change="onCourtPick()"
                            class="form-select form-select-sm">
                        <option value="">Select court</option>
                        @foreach($courts as $court)
                        <option value="{{ $court->id }}"
                                data-rate="{{ $court->base_hourly_rate }}"
                                data-min="{{ $court->min_booking_minutes }}"
                                data-max="{{ $court->max_booking_minutes }}">{{ $court->name }} ({{ ucfirst($court->type) }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Date — scheduled only --}}
                <div class="col-sm-6" x-show="!isWalkIn" x-cloak>
                    <label class="form-label fw-medium">Date <span class="text-danger">*</span></label>
                    <input type="date" :name="isWalkIn ? '' : 'booking_date'" :required="!isWalkIn"
                           min="{{ now()->toDateString() }}" max="{{ now()->addDays(30)->toDateString() }}"
                           x-model="bookingDate" @change="onDateChange()"
                           class="form-control form-control-sm">
                </div>

                {{-- Duration — walk-in only (scheduled uses the time picker below) --}}
                <div class="col-sm-3" x-show="isWalkIn" x-cloak>
                    <label class="form-label fw-medium">Duration <span class="text-danger">*</span></label>
                    <select name="duration_minutes"
                            x-model="duration" @change="onWalkinDuration()"
                            class="form-select form-select-sm">
                        <option value="30">30 minutes</option>
                        <option value="60">1 hour</option>
                        <option value="90">1.5 hours</option>
                        <option value="120">2 hours</option>
                    </select>
                </div>

                {{-- Walk-in start indicator --}}
                <div class="col-sm-3" x-show="isWalkIn" x-cloak>
                    <label class="form-label fw-medium">Will start at</label>
                    <div class="form-control form-control-sm bg-body-tertiary fw-medium" x-text="walkInStartLabel"></div>
                </div>
            </div>

            {{-- Time-first picker (scheduled bookings) --}}
            <div x-show="!isWalkIn" x-cloak>
                @include('partials.booking-time-picker', ['staff' => true])
            </div>

            {{-- Walk-in conflict preview --}}
            <div x-show="isWalkIn && walkInPreview" x-cloak class="mb-3">
                {{-- Court is currently occupied — no walk-in possible --}}
                <template x-if="walkInPreview && walkInPreview.current_booking">
                    <div class="alert alert-danger py-2 small mb-0">
                        <p class="mb-1 fw-semibold">
                            <i class="bi bi-x-octagon me-1"></i>
                            Court is currently in use.
                        </p>
                        <p class="mb-1">
                            <strong x-text="walkInPreview.current_booking.customer"></strong>
                            (<span x-text="walkInPreview.current_booking.booking_number"></span>)
                            is playing
                            <span x-text="formatTime(walkInPreview.current_booking.start) + ' – ' + formatTime(walkInPreview.current_booking.end)"></span>.
                        </p>
                        <p class="mb-0 text-muted" x-text="walkInPreview.current_booking.message"></p>
                    </div>
                </template>

                {{-- Free — no conflicts --}}
                <template x-if="walkInPreview && !walkInPreview.current_booking && walkInPreview.conflicts.length === 0">
                    <div class="alert alert-success py-2 small mb-0">
                        <i class="bi bi-check-circle me-1"></i>
                        No conflicts. Will run
                        <strong x-text="formatTime(walkInPreview.start) + ' – ' + formatTime(walkInPreview.requested_end)"></strong>.
                    </div>
                </template>

                {{-- Future bookings would be bumped or duration capped --}}
                <template x-if="walkInPreview && !walkInPreview.current_booking && walkInPreview.conflicts.length > 0">
                    <div class="alert alert-warning py-2 small mb-0">
                        <p class="mb-1 fw-semibold">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            This walk-in would conflict with
                            <span x-text="walkInPreview.conflicts.length"></span>
                            upcoming booking<span x-show="walkInPreview.conflicts.length > 1">s</span>.
                        </p>
                        <ul class="mb-2 ps-3" style="font-size:.8rem">
                            <template x-for="c in walkInPreview.conflicts" :key="c.booking_id">
                                <li>
                                    <strong x-text="c.customer"></strong>
                                    (<span x-text="c.booking_number"></span>):
                                    <span x-text="formatTime(c.old_start) + ' – ' + formatTime(c.old_end)"></span>
                                    →
                                    <span x-text="formatTime(c.new_start) + ' – ' + formatTime(c.new_end)" class="fw-semibold"></span>
                                </li>
                            </template>
                        </ul>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" @click="walkInMode = 'cap'"
                                    :class="walkInMode === 'cap' ? 'btn-warning' : 'btn-outline-warning'"
                                    class="btn btn-sm">
                                Cap to <span x-text="walkInPreview.capped_minutes"></span> min
                                (<span x-text="formatTime(walkInPreview.start) + ' – ' + formatTime(walkInPreview.capped_end)"></span>)
                            </button>
                            <button type="button" @click="walkInMode = 'bump'"
                                    :class="walkInMode === 'bump' ? 'btn-warning' : 'btn-outline-warning'"
                                    class="btn btn-sm">
                                Bump booking<span x-show="walkInPreview.conflicts.length > 1">s</span> & continue full
                                <span x-text="walkInPreview.duration_min"></span> min
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Hidden inputs used only by walk-in submit --}}
            <input type="hidden" name="mode" :value="walkInMode" x-show="false">

            {{-- Auto-updating indicator (walk-in only) — availability re-checks
                 itself on a timer, so no manual button is needed. --}}
            <div x-show="isWalkIn && courtId" x-cloak class="mb-3 small text-muted">
                <span x-show="walkInBusy" x-cloak class="spinner-border spinner-border-sm me-1"
                      style="width:.8rem;height:.8rem"></span>
                <i class="bi bi-broadcast me-1 text-success" x-show="!walkInBusy"></i>
                Availability updates automatically<span x-show="walkInLastChecked" x-cloak>
                    · last checked <span x-text="walkInLastChecked"></span></span>
            </div>

            {{-- Payment method picker — mirrors the customer side. Shown for
                 scheduled (after slot selection) and walk-ins (once court is
                 picked). Walk-in court_credit may partially cover; the unpaid
                 remainder is collected at the desk. Owner/staff cash bypasses
                 the customer approval queue and confirms immediately. --}}
            <div class="mb-3" x-show="(!isWalkIn && selectedSlot) || (isWalkIn && courtId)" x-cloak>
                <label class="form-label fw-medium">Payment method <span class="text-danger">*</span></label>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="card border-2 p-3 h-100 mb-0"
                               :class="paymentMethod === 'wallet' ? 'border-primary' : 'border-light-subtle'"
                               :style="selectedCustomerId ? 'cursor:pointer' : 'cursor:not-allowed;opacity:.6'">
                            <input type="radio" name="payment_method" value="wallet"
                                   x-model="paymentMethod" class="form-check-input mb-2"
                                   :disabled="!selectedCustomerId">
                            <div class="fw-semibold small"><i class="bi bi-wallet2 me-1"></i>Wallet</div>
                            <div class="small text-muted">
                                <template x-if="selectedCustomerId">
                                    <span>Available: <strong x-text="'₱' + selectedCustomerWallet.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})"></strong></span>
                                </template>
                                <template x-if="!selectedCustomerId">
                                    <span>Select a customer first.</span>
                                </template>
                            </div>
                            <div x-show="paymentMethod === 'wallet' && selectedCustomerId && !walletCovers" x-cloak
                                 class="small text-danger mt-1">
                                Insufficient balance for this slot.
                            </div>
                            <div x-show="paymentMethod === 'wallet' && walletCovers" x-cloak
                                 class="small text-success mt-1">
                                Booking is confirmed instantly.
                            </div>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="card border-2 p-3 h-100 mb-0"
                               :class="paymentMethod === 'court_credit' ? 'border-primary' : 'border-light-subtle'"
                               :style="(selectedCustomerId && selectedCustomerCredits > 0) ? 'cursor:pointer' : 'cursor:not-allowed;opacity:.6'">
                            <input type="radio" name="payment_method" value="court_credit"
                                   x-model="paymentMethod" class="form-check-input mb-2"
                                   :disabled="!selectedCustomerId || selectedCustomerCredits <= 0">
                            <div class="fw-semibold small"><i class="bi bi-stopwatch me-1"></i>Court Credit</div>
                            <div class="small text-muted">
                                <template x-if="selectedCustomerId && selectedCustomerCredits > 0">
                                    <span x-text="creditAvailableLabel"></span>
                                </template>
                                <template x-if="!selectedCustomerId">
                                    <span>Select a customer first.</span>
                                </template>
                                <template x-if="selectedCustomerId && selectedCustomerCredits <= 0">
                                    <span>No active membership credits.</span>
                                </template>
                            </div>
                            <div x-show="paymentMethod === 'court_credit' && selectedCustomerId" x-cloak class="small mt-1"
                                 :class="creditFullyCovers ? 'text-success' : (isWalkIn ? 'text-warning' : 'text-danger')"
                                 x-text="creditFullyCovers
                                     ? 'Fully covered. Booking is confirmed instantly.'
                                     : (isWalkIn
                                         ? creditCoverageLabel + ' — remainder collected at the desk.'
                                         : 'Credit does not fully cover this slot.')"></div>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="card border-2 p-3 h-100 mb-0"
                               :class="paymentMethod === 'cash' ? 'border-primary' : 'border-light-subtle'"
                               style="cursor:pointer">
                            <input type="radio" name="payment_method" value="cash"
                                   x-model="paymentMethod" class="form-check-input mb-2">
                            <div class="fw-semibold small"><i class="bi bi-cash me-1"></i>Cash</div>
                            <div class="small text-muted">Collect at the venue.</div>
                            <div x-show="paymentMethod === 'cash'" x-cloak class="small text-success mt-1">
                                <i class="bi bi-check2-circle me-1"></i>
                                <span x-text="isWalkIn ? 'Confirmed immediately. Settle cash at the desk.' : 'Direct approve — confirms immediately, no approval queue.'"></span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            {{-- Promo code — scheduled bookings (slot picked) and walk-ins (court + duration) --}}
            <div class="mb-3" x-show="!usingCreditMethod && ((!isWalkIn && selectedSlot) || (isWalkIn && courtId))" x-cloak>
                <label class="form-label fw-medium">Promo code</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="promo_code" x-model="promoCode"
                           class="form-control text-uppercase" placeholder="Optional">
                    <button type="button" @click="validatePromo()"
                            :disabled="!promoCode || bookingBaseAmount <= 0"
                            class="btn btn-outline-secondary">Apply</button>
                </div>
                <p x-show="promoMessage" x-cloak x-text="promoMessage"
                   :class="discount > 0 ? 'text-success' : 'text-danger'"
                   class="small mt-1 mb-0"></p>
            </div>

            {{-- Notes --}}
            <div class="mb-4">
                <label class="form-label fw-medium">Notes</label>
                <textarea name="notes" rows="2" class="form-control form-control-sm"
                          placeholder="Any special requests or notes...">{{ old('notes') }}</textarea>
            </div>

            {{-- Price summary --}}
            <div x-show="(!isWalkIn && selectedSlot) || (isWalkIn && courtId)" x-cloak class="bg-body-tertiary rounded-3 p-3 mb-4 small">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Court rental
                        <template x-if="isWalkIn">
                            <span class="text-muted">(<span x-text="bookingMinutes"></span> min)</span>
                        </template>
                    </span>
                    <span x-text="'₱' + bookingBaseAmount.toLocaleString()"></span>
                </div>
                <div x-show="usingCreditMethod && creditFreeAmount > 0" x-cloak class="d-flex justify-content-between mb-1 text-success">
                    <span>Court credit (<span x-text="creditMinutesUsed"></span> min)</span>
                    <span x-text="'-₱' + creditFreeAmount.toLocaleString()"></span>
                </div>
                <div x-show="!usingCreditMethod && discount > 0" x-cloak class="d-flex justify-content-between mb-1 text-success">
                    <span>Promo discount</span>
                    <span x-text="'-₱' + discount.toLocaleString()"></span>
                </div>
                <div class="d-flex justify-content-between fw-semibold border-top pt-2 mt-1">
                    <span>Total</span>
                    <span x-text="'₱' + computedTotal.toLocaleString()"></span>
                </div>
                <template x-if="isWalkIn">
                    <p class="small text-muted mb-0 mt-1">Walk-in rate is estimated; final amount reflects actual play duration.</p>
                </template>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm"
                        :disabled="submitDisabled">
                    <span x-show="submitting" x-cloak class="spinner-border spinner-border-sm me-1"></span>
                    <span x-show="!isWalkIn">Create Booking</span>
                    <span x-show="isWalkIn" x-cloak>
                        <i class="bi bi-lightning-charge me-1"></i>
                        Start Walk-in
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

</div>
</div>

@push('scripts')
<script>
function bookingForm() {
    return {
        bookingType: 'online',
        courtId:    @json((string) (request('court_id') ?? '')),
        bookingDate: '{{ now()->toDateString() }}',
        duration:   '60',
        selectedSlot: null,

        customerSearch: '', customers: [], selectedCustomerId: null,
        selectedCustomerCredits: 0,
        selectedCustomerWallet: 0,
        useCredit: false,
        paymentMethod: 'cash',
        searching: false, customerSearched: false,
        promoCode: '', promoMessage: '', discount: 0,
        submitting: false,

        // Walk-in state
        walkInPreview: null,
        walkInMode: 'auto',
        walkInBusy: false,
        walkInLastChecked: '',
        clockTick: 0,   // ticks the live "Will start at" clock + drives auto-refresh

        // Live clock + automatic walk-in availability refresh. Pure client-side
        // setInterval — no cron/queue needed. Walk-ins start "now", so both the
        // displayed start time and the conflict preview need to stay current.
        // SaaS hygiene: skip polling while the tab is backgrounded so an idle open
        // form doesn't hammer the endpoint; refresh immediately when it returns.
        init() {
            const tick = () => {
                if (document.hidden) return;
                this.clockTick++;   // re-renders walkInStartLabel (reads clockTick)
                if (this.isWalkIn && this.courtId && !this.walkInBusy) {
                    this.checkWalkIn(true);   // silent background recheck
                }
            };
            setInterval(tick, 15000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });

            // Court pre-selected via ?court_id= (e.g. from the status board "Book Now").
            // Trigger the same load that picking a court manually would.
            if (this.courtId) {
                this.$nextTick(() => this.onCourtPick());
            }
        },

@include('partials.booking-time-picker-js', ['timelinePath' => '/admin/courts', 'staff' => true, 'defaultStart' => '18:00'])

        get isWalkIn() { return this.bookingType === 'walk_in'; },

        // For the scheduled-booking picker. Walk-ins still use the legacy
        // `useCredit` toggle since their settlement happens at timer end.
        get walletCovers() {
            return this.selectedCustomerWallet >= this.computedTotal;
        },
        get usingCreditMethod() {
            return this.paymentMethod === 'court_credit';
        },

        // Credit math — selectedCustomerCredits is in MINUTES.
        get bookingMinutes() {
            // For scheduled bookings the slot carries its own duration; for walk-ins
            // we fall back to the duration dropdown.
            if (this.selectedSlot && this.selectedSlot.duration) {
                return parseInt(this.selectedSlot.duration, 10);
            }
            return parseInt(this.duration, 10) || 0;
        },
        get bookingBaseAmount() {
            // Scheduled: pricing comes from the selected slot.
            if (!this.isWalkIn && this.selectedSlot) {
                return Number(this.selectedSlot.total) || 0;
            }
            // Walk-in: estimate from court's hourly rate × duration.
            if (this.isWalkIn && this.courtId) {
                const opt = document.querySelector(`select[name="court_id"] option[value="${this.courtId}"]`);
                const rate = parseFloat(opt?.dataset?.rate ?? 0);
                if (!rate || !this.bookingMinutes) return 0;
                return Math.round(rate * (this.bookingMinutes / 60));
            }
            return 0;
        },
        get creditMinutesUsed() {
            if (!this.usingCreditMethod || this.selectedCustomerCredits <= 0 || this.bookingMinutes <= 0) return 0;
            return Math.min(this.selectedCustomerCredits, this.bookingMinutes);
        },
        get creditFullyCovers() {
            return this.bookingMinutes > 0 && this.selectedCustomerCredits >= this.bookingMinutes;
        },
        get creditFreeAmount() {
            const total = this.bookingBaseAmount;
            if (this.creditMinutesUsed === 0 || this.bookingMinutes === 0) return 0;
            return Math.round(total * (this.creditMinutesUsed / this.bookingMinutes));
        },
        get computedTotal() {
            const total = this.bookingBaseAmount;
            if (this.usingCreditMethod) return Math.max(0, total - this.creditFreeAmount);
            return Math.max(0, total - this.discount);
        },
        get creditAvailableLabel() {
            const m = this.selectedCustomerCredits;
            const h = Math.floor(m / 60);
            const r = m % 60;
            return `${h}h ${r}m available`;
        },
        get creditCoverageLabel() {
            if (this.bookingMinutes === 0) return '';
            if (this.creditFullyCovers) {
                return `${this.bookingMinutes} min covered — free`;
            }
            const uncovered = this.bookingMinutes - this.creditMinutesUsed;
            return `${this.creditMinutesUsed} min covered, ${uncovered} min charged`;
        },

        get walkInStartLabel() {
            // Live wall-clock time in 12-hour AM/PM. Reading clockTick makes Alpine
            // re-evaluate this every interval so the displayed time stays current.
            this.clockTick;
            const d = new Date();
            let h = d.getHours();
            const m = String(d.getMinutes()).padStart(2, '0');
            const ap = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ap}`;
        },

        get submitDisabled() {
            if (this.submitting) return true;
            if (this.isWalkIn) {
                if (!this.courtId) return true;
                // Court currently busy — can't start a walk-in at all.
                if (this.walkInPreview?.current_booking) return true;
                // Conflicts exist — staff must pick cap/bump.
                if (this.walkInPreview && this.walkInPreview.conflicts.length > 0
                    && this.walkInMode === 'auto') {
                    return true;
                }
                if (!this.paymentMethod) return true;
                // Wallet always needs full coverage (it auto-debits).
                if (this.paymentMethod === 'wallet'
                    && (!this.selectedCustomerId || !this.walletCovers)) return true;
                // Court credit on walk-in allows partial coverage — but still
                // needs a customer with at least some credit.
                if (this.paymentMethod === 'court_credit'
                    && (!this.selectedCustomerId || this.selectedCustomerCredits <= 0)) return true;
                return false;
            }
            if (!this.selectedSlot) return true;
            // Scheduled booking — payment method gates submit.
            if (!this.paymentMethod) return true;
            if (this.paymentMethod === 'wallet') {
                if (!this.selectedCustomerId) return true;
                if (!this.walletCovers) return true;
            }
            if (this.paymentMethod === 'court_credit') {
                if (!this.selectedCustomerId) return true;
                if (!this.creditFullyCovers) return true;
            }
            return false;
        },

        // Court picked: walk-in re-runs its conflict preview; scheduled loads the
        // day timeline + availability verdict (onCourtChange lives in the picker).
        onCourtPick() {
            if (this.isWalkIn) {
                this.walkInPreview = null;
                this.walkInMode = 'auto';
                if (this.courtId) this.checkWalkIn();
            } else {
                this.onCourtChange();
            }
        },
        // Walk-in duration dropdown changed.
        onWalkinDuration() {
            this.walkInPreview = null;
            this.walkInMode = 'auto';
            if (this.courtId) this.checkWalkIn();
        },
        // Switched between walk-in / online / phone.
        onTypeChange() {
            if (this.isWalkIn) {
                this.walkInPreview = null;
                if (this.courtId) this.checkWalkIn();
            } else if (this.courtId) {
                this.loadTimeline();
            }
        },

        clearCustomer() {
            this.selectedCustomerId = null;
            this.customerSearch = '';
            this.customers = [];
            this.customerSearched = false;
            this.selectedCustomerCredits = 0;
            this.selectedCustomerWallet = 0;
            this.useCredit = false;
            // Wallet/credit need a customer — drop back to cash.
            if (this.paymentMethod === 'wallet' || this.paymentMethod === 'court_credit') {
                this.paymentMethod = 'cash';
            }
        },

        async searchCustomers() {
            if (this.customerSearch.length < 1) {
                this.customers = [];
                this.customerSearched = false;
                return;
            }
            this.searching = true;
            this.customerSearched = false;
            try {
                const r = await fetch(`${window.APP_BASE}/admin/customers/search?q=${encodeURIComponent(this.customerSearch)}`,
                                      { headers: { 'Accept': 'application/json' } });
                const d = await r.json();
                this.customers = d.customers ?? [];
            } catch (e) {
                this.customers = [];
            } finally {
                this.searching = false;
                this.customerSearched = true;
            }
        },

        selectCustomer(c) {
            this.selectedCustomerId = c.id;
            this.customerSearch = c.name;
            this.selectedCustomerCredits = c.remaining_credits ?? 0;
            this.selectedCustomerWallet = Number(c.wallet_balance ?? 0);
            this.useCredit = this.selectedCustomerCredits > 0;
            this.customers = [];
        },

        async validatePromo() {
            // Works for scheduled (slot.total) and walk-ins (estimated base from
            // court rate × duration) — bookingBaseAmount covers both.
            if (!this.promoCode || this.bookingBaseAmount <= 0) return;
            try {
                const r = await fetch(`${window.APP_BASE}/admin/promotions/validate`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.head.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ code: this.promoCode, amount: this.bookingBaseAmount }),
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
            // Scheduled booking: needs a selected slot.
            if (!this.isWalkIn && !this.selectedSlot) return;
            this.submitting = true;
            form.submit();
        },

        async checkWalkIn(silent = false) {
            if (!this.courtId || this.walkInBusy) return;
            this.walkInBusy = true;
            // Keep the current preview on screen during a silent auto-refresh so the
            // conflict panel doesn't flicker every interval.
            if (!silent) this.walkInPreview = null;
            try {
                const r = await fetch(@js(route('admin.bookings.walk-in.preview')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.head.querySelector('meta[name=csrf-token]').content,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        court_id: this.courtId,
                        duration_minutes: parseInt(this.duration, 10),
                    }),
                });
                if (!r.ok) {
                    if (!silent) {
                        const e = await r.json().catch(() => ({}));
                        alert(e.message || `Could not check availability (${r.status}).`);
                    }
                    return;
                }
                this.walkInPreview = await r.json();
                this.walkInLastChecked = this.walkInStartLabel;
                // On an explicit (re)check, reset to auto; on silent refresh keep the
                // staff's chosen cap/bump so it isn't wiped every interval.
                if (!silent) this.walkInMode = 'auto';
            } catch (e) {
                if (!silent) alert('Network error: ' + e.message);
            } finally {
                this.walkInBusy = false;
            }
        },

        formatTime(hhmm) {
            if (!hhmm) return '';
            const [h, m] = hhmm.split(':').map(Number);
            const ap = h >= 12 ? 'PM' : 'AM';
            const h12 = h % 12 || 12;
            return `${h12}:${String(m).padStart(2,'0')} ${ap}`;
        },
    };
}
</script>
@endpush

@endsection
