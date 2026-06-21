@extends('layouts.customer')

@section('title', 'Membership')

@push('styles')
<style>
/* ── membership page ── */
.mem-hero {
    padding: 1.5rem;
    border-radius: 0;
    color: #fff;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    transition: background .3s;
}
.mem-hero.is-frozen    { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
.mem-hero.is-cancelled { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }

.mem-stat {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.2);
    border-radius: 12px;
    padding: .75rem 1rem;
    min-width: 0;
    flex: 1 1 0;
}
.mem-stat-value { font-size: 1.5rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
.mem-stat-label { font-size: .7rem; opacity: .75; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .2rem; }

.days-ring { position: relative; width: 52px; height: 52px; flex-shrink: 0; }
.days-ring svg { transform: rotate(-90deg); }
.days-ring-text {
    position: absolute; inset: 0;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    font-weight: 800; font-size: .75rem; line-height: 1; color: #fff;
}
.days-ring-text span { font-size: .55rem; opacity: .8; }

/* plan cards */
.cust-plan {
    height: 100%; overflow: hidden;
    --accent: #10b981; --accent-rgb: 16,185,129;
    border: 1.5px solid var(--bs-border-color);
    transition: transform .18s, border-color .18s, box-shadow .18s;
    position: relative;
}
.cust-plan.is-vip      { --accent: #f59e0b; --accent-rgb: 245,158,11; border-color: rgba(245,158,11,.35); }
.cust-plan.is-current  { border-color: rgba(var(--accent-rgb),.6); box-shadow: 0 0 0 3px rgba(var(--accent-rgb),.12); }
.cust-plan:not(.is-current):hover { transform: translateY(-3px); border-color: rgba(var(--accent-rgb),.45); box-shadow: 0 14px 28px -16px rgba(0,0,0,.5); }
.cust-plan-bar { height: 4px; background: linear-gradient(90deg, var(--accent), rgba(var(--accent-rgb),.2)); }
.cust-plan-price { font-size: 1.9rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
.cust-plan-check {
    position: absolute; top: .75rem; right: .75rem;
    width: 26px; height: 26px; border-radius: 50%;
    background: rgba(var(--accent-rgb),.15);
    border: 1.5px solid rgba(var(--accent-rgb),.4);
    display: grid; place-items: center; font-size: .75rem;
    color: rgb(var(--accent-rgb));
}

/* activity feed */
.mem-tx-ico {
    width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
    display: grid; place-items: center; font-size: .85rem;
    background: rgba(16,185,129,.1); color: #10b981;
    border: 1px solid rgba(16,185,129,.18);
}
.mem-tx-ico.ico-danger  { background: rgba(239,68,68,.1);  color: #ef4444; border-color: rgba(239,68,68,.18); }
.mem-tx-ico.ico-info    { background: rgba(59,130,246,.1); color: #3b82f6; border-color: rgba(59,130,246,.18); }
.mem-tx-ico.ico-warning { background: rgba(245,158,11,.1); color: #f59e0b; border-color: rgba(245,158,11,.18); }
.mem-tx-ico.ico-neutral { background: rgba(107,114,128,.1);color: #6b7280; border-color: rgba(107,114,128,.18); }

/* action strip */
.mem-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
@media(max-width:480px){
    .mem-actions .btn { flex: 1 1 auto; }
    .mem-actions form { flex: 1 1 auto; }
    .mem-actions form .btn { width: 100%; }
}
</style>
@endpush

@section('content')

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Page header --}}
<div class="mb-4">
    <h4 class="fw-bold mb-0">Membership</h4>
    <p class="text-muted small mb-0">Manage your plan, credits, and billing.</p>
</div>

{{-- ── Active membership card ─────────────────────────────── --}}
@if($active)
@php
    $plan         = $active->plan;
    $daysLeft     = max(0, (int) now()->diffInDays($active->expires_at, false));
    $totalDays    = max(1, (int) $active->starts_at->diffInDays($active->expires_at));
    $daysPct      = min(100, round(($daysLeft / $totalDays) * 100));
    $expiresSoon  = $daysLeft <= 14 && !$active->cancelled_at;
    $totalCredits = (int) ($plan?->court_credits ?? 0);
    $usedCredits  = max(0, $totalCredits - (int) $active->remaining_credits);
    $usedPct      = $totalCredits > 0 ? min(100, round(($usedCredits / $totalCredits) * 100)) : 0;
    $isFrozen     = $active->status === 'frozen';
    $isPending    = $active->cancelled_at && $active->status === 'active';
    $heroClass    = $isFrozen ? 'is-frozen' : ($isPending ? 'is-cancelled' : '');
    $circumference = 2 * M_PI * 22; // radius 22
    $dashOffset   = $circumference * (1 - $daysPct / 100);
@endphp

<div class="card mb-4 overflow-hidden">
    <div class="mem-hero {{ $heroClass }}">

        {{-- Top row: plan name + status badge --}}
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3 flex-wrap">
            <div>
                <div class="small fw-semibold mb-1" style="opacity:.75;letter-spacing:.04em;text-transform:uppercase;font-size:.68rem">Current Plan</div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="fw-bold mb-0">{{ $plan?->name ?? 'Active Member' }}</h4>
                    @if($plan?->is_vip)
                        <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>VIP</span>
                    @endif
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                @if($isFrozen)
                    <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;font-size:.75rem"><i class="bi bi-snow me-1"></i>Frozen</span>
                @elseif($isPending)
                    <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;font-size:.75rem"><i class="bi bi-calendar-x me-1"></i>Cancels {{ $active->expires_at->format('M j') }}</span>
                @else
                    <span class="badge" style="background:rgba(255,255,255,.2);color:#fff;font-size:.75rem"><i class="bi bi-check2-circle me-1"></i>Active</span>
                @endif
            </div>
        </div>

        {{-- Stat row --}}
        <div class="d-flex gap-2 mb-3 flex-wrap">
            {{-- Days left ring + stat --}}
            <div class="mem-stat d-flex align-items-center gap-3">
                <div class="days-ring">
                    <svg width="52" height="52" viewBox="0 0 52 52">
                        <circle cx="26" cy="26" r="22" fill="none" stroke="rgba(255,255,255,.2)" stroke-width="4"/>
                        <circle cx="26" cy="26" r="22" fill="none" stroke="#fff" stroke-width="4"
                                stroke-dasharray="{{ number_format($circumference, 2) }}"
                                stroke-dashoffset="{{ number_format($dashOffset, 2) }}"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="days-ring-text">
                        {{ $daysLeft }}<span>days</span>
                    </div>
                </div>
                <div>
                    <div class="mem-stat-label">Expires</div>
                    <div class="fw-semibold" style="font-size:.9rem">{{ $active->expires_at?->format('M j, Y') }}</div>
                    @if($isFrozen && $active->frozen_until)
                        <div class="small mt-1" style="opacity:.75;font-size:.72rem"><i class="bi bi-snow me-1"></i>Until {{ $active->frozen_until->format('M j') }}</div>
                    @endif
                </div>
            </div>

            {{-- Court credits stat --}}
            <div class="mem-stat">
                <div class="mem-stat-label">Court Credits</div>
                <div class="mem-stat-value">{{ $active->credits_label ?? '0h 0m' }}</div>
                @if($totalCredits > 0)
                    <div class="mt-2">
                        <div class="progress" style="height:4px;background:rgba(255,255,255,.2);border-radius:4px;">
                            <div class="progress-bar bg-white" style="width:{{ 100 - $usedPct }}%;border-radius:4px;"></div>
                        </div>
                        <div class="small mt-1" style="opacity:.7;font-size:.7rem">{{ $usedPct }}% used</div>
                    </div>
                @endif
            </div>

            {{-- Discount stat (only if plan has one) --}}
            @if($plan?->discount_percent > 0)
            <div class="mem-stat d-none d-sm-block">
                <div class="mem-stat-label">Booking Discount</div>
                <div class="mem-stat-value">{{ rtrim(rtrim(number_format($plan->discount_percent, 2), '0'), '.') }}%</div>
                <div class="small mt-1" style="opacity:.7;font-size:.7rem">off every booking</div>
            </div>
            @endif
        </div>

        {{-- Action buttons --}}
        <div class="mem-actions">
            <button type="button" class="btn btn-sm fw-semibold" style="background:rgba(255,255,255,.95);color:#059669;border:none;" data-bs-toggle="modal" data-bs-target="#renewModal">
                <i class="bi bi-arrow-clockwise me-1"></i>Renew
            </button>
            @if($isFrozen)
                <form method="POST" action="{{ route('customer.memberships.unfreeze', $active) }}"
                      onsubmit="return confirm('Unfreeze now? Your expiry will be extended by the days you were actually frozen.');">
                    @csrf
                    <button class="btn btn-sm fw-semibold" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);">
                        <i class="bi bi-sun me-1"></i>Unfreeze
                    </button>
                </form>
            @elseif($plan?->max_freeze_days && !$isPending)
                <button type="button" class="btn btn-sm fw-semibold" style="background:rgba(255,255,255,.2);color:#fff;border:1px solid rgba(255,255,255,.3);" data-bs-toggle="modal" data-bs-target="#freezeModal">
                    <i class="bi bi-snow me-1"></i>Freeze
                </button>
            @endif
            @if(!$isPending)
                @php $cancelConfirm = "Cancel your membership? You keep full access until {$active->expires_at->format('M j, Y')}."; @endphp
                <form method="POST" action="{{ route('customer.memberships.cancel', $active) }}"
                      onsubmit="return confirm('{{ $cancelConfirm }}');">
                    @csrf
                    <button class="btn btn-sm" style="background:rgba(239,68,68,.2);color:#fff;border:1px solid rgba(255,255,255,.2);">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Alerts & perks body --}}
    @if($isPending || $expiresSoon || !empty($plan?->perks))
    <div class="card-body py-3">
        @if($isPending)
            <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);">
                <i class="bi bi-calendar-x text-warning mt-1"></i>
                <div class="small">
                    Cancellation scheduled — you have full access until <strong>{{ $active->expires_at->format('M j, Y') }}</strong>. Your membership will not renew.
                </div>
            </div>
        @elseif($expiresSoon)
            <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);">
                <i class="bi bi-clock-history text-warning mt-1"></i>
                <div class="small">
                    Expires in <strong>{{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}</strong>. Renew now to keep your credits and perks.
                </div>
            </div>
        @endif

        @if(!empty($plan?->perks))
            <div class="small text-muted fw-semibold mb-2" style="text-transform:uppercase;letter-spacing:.06em;font-size:.68rem">Your perks</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($plan->perks as $perk)
                    <span class="badge bg-success-subtle text-success-emphasis fw-normal">
                        <i class="bi bi-check-circle me-1"></i>{{ $perk }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>
    @endif
</div>

{{-- Recent Activity --}}
@if($active->transactions->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">Recent Activity</h6>
        <span class="badge bg-secondary-subtle text-secondary">{{ $active->transactions->count() }} events</span>
    </div>
    <div class="list-group list-group-flush">
        @foreach($active->transactions->take(8) as $tx)
        @php
            $change = $tx->credits_change ?? 0;
            [$ico, $icoClass] = match(true) {
                str_contains($tx->type, 'purchase'), str_contains($tx->type, 'renewal') => ['bi-bag-check', ''],
                str_contains($tx->type, 'credit_add')                                   => ['bi-plus-circle', ''],
                str_contains($tx->type, 'credit_use')                                   => ['bi-dash-circle', 'ico-danger'],
                str_contains($tx->type, 'unfreeze')                                     => ['bi-sun', 'ico-info'],
                str_contains($tx->type, 'freeze')                                       => ['bi-snow', 'ico-info'],
                str_contains($tx->type, 'cancel')                                       => ['bi-x-circle', 'ico-warning'],
                str_contains($tx->type, 'refund')                                       => ['bi-arrow-counterclockwise', 'ico-neutral'],
                default                                                                  => ['bi-clock-history', 'ico-neutral'],
            };
        @endphp
        <div class="list-group-item d-flex align-items-center gap-3 py-3">
            <div class="mem-tx-ico {{ $icoClass }}"><i class="bi {{ $ico }}"></i></div>
            <div class="flex-grow-1 min-w-0">
                <div class="small fw-semibold text-truncate">{{ $tx->description ?: ucwords(str_replace('_', ' ', $tx->type)) }}</div>
                <div class="small text-muted">{{ $tx->created_at->format('M j, Y · g:i A') }}</div>
            </div>
            @if($change)
            <div class="small fw-bold text-nowrap {{ $change >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $change > 0 ? '+' : '' }}{{ $change }}m
            </div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif
@endif {{-- end @if($active) --}}

{{-- ── Plans grid ───────────────────────────────────────── --}}
<div class="d-flex align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0">{{ $active ? 'Available Plans' : 'Choose a Plan' }}</h5>
        @if(!$active)
        <p class="text-muted small mb-0">Wallet: <strong>₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }}</strong> · or pay at the desk</p>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    @forelse ($plans as $plan)
    @php
        $isCurrent = $active && $active->plan_id === $plan->id;
        $hours = intdiv((int) $plan->court_credits, 60);
        $mins  = (int) $plan->court_credits % 60;
    @endphp
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card cust-plan {{ $plan->is_vip ? 'is-vip' : '' }} {{ $isCurrent ? 'is-current' : '' }}">
            <div class="cust-plan-bar"></div>
            @if($isCurrent)
                <div class="cust-plan-check"><i class="bi bi-check2"></i></div>
            @endif
            <div class="card-body d-flex flex-column p-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                    <div>
                        <h5 class="fw-bold mb-0">{{ $plan->name }}</h5>
                        @if($plan->is_vip)
                            <span class="badge rounded-pill bg-warning-subtle text-warning" style="font-size:.7rem"><i class="bi bi-star-fill me-1"></i>VIP</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex align-items-baseline gap-1 my-2">
                    <span class="cust-plan-price text-success">₱{{ number_format($plan->price, 2) }}</span>
                    <span class="text-muted small">/ {{ ucfirst($plan->billing_cycle) }}</span>
                </div>

                @if($plan->description)
                    <p class="text-muted small mb-2" style="line-height:1.4">{{ $plan->description }}</p>
                @endif

                <ul class="list-unstyled small mb-3 mt-1" style="line-height:1.8">
                    @if($plan->court_credits)
                        <li><i class="bi bi-clock text-success me-2"></i>{{ $hours }}h{{ $mins ? " {$mins}m" : '' }} court credits</li>
                    @endif
                    @if($plan->discount_percent > 0)
                        <li><i class="bi bi-tag text-success me-2"></i>{{ rtrim(rtrim(number_format($plan->discount_percent, 2), '0'), '.') }}% off every booking</li>
                    @endif
                    @if($plan->max_freeze_days)
                        <li><i class="bi bi-snow text-success me-2"></i>Freeze up to {{ $plan->max_freeze_days }} days{{ $plan->freeze_count_per_year ? " · {$plan->freeze_count_per_year}/yr" : '' }}</li>
                    @endif
                    @foreach(($plan->perks ?? []) as $perk)
                        <li><i class="bi bi-check2 text-success me-2"></i>{{ $perk }}</li>
                    @endforeach
                </ul>

                <div class="mt-auto">
                    @if($isCurrent)
                        <div class="d-flex align-items-center gap-2 p-2 rounded-3" style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span class="small fw-semibold text-success">Your current plan</span>
                        </div>
                    @elseif($active)
                        <button class="btn btn-outline-secondary w-100 btn-sm" disabled>
                            Already subscribed to another plan
                        </button>
                    @else
                        <button type="button" class="btn btn-primary w-100"
                                data-bs-toggle="modal" data-bs-target="#subscribeModal-{{ $plan->id }}">
                            <i class="bi bi-credit-card me-1"></i>Subscribe
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <x-empty-state
            title="No plans available"
            description="No membership plans are available right now. Check back soon or ask venue staff."
            icon="bi-patch-check"/>
    </div>
    @endforelse
</div>

{{-- ── Membership history ───────────────────────────────── --}}
@if($history->count() > 0)
<div class="card">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">Past Memberships</h6>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>Plan</th>
                    <th>Period</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $h)
                <tr>
                    <td data-label="Plan" class="fw-medium small">{{ $h->plan?->name ?? '—' }}</td>
                    <td data-label="Period" class="small text-muted text-nowrap">
                        {{ $h->starts_at?->format('M j, Y') }} – {{ $h->expires_at?->format('M j, Y') }}
                    </td>
                    <td data-label="Status">
                        <x-badge :status="match($h->status) { 'expired' => 'expired', 'cancelled' => 'cancelled', 'frozen' => 'pending', default => 'neutral' }">{{ ucfirst($h->status) }}</x-badge>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('modals')
{{-- Subscribe modals --}}
@foreach($plans as $plan)
    @continue($active)
    <div class="modal fade" id="subscribeModal-{{ $plan->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('customer.memberships.subscribe', $plan) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Subscribe to {{ $plan->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="p-3 rounded-3 mb-3" style="background:var(--bs-tertiary-bg)">
                        <div class="d-flex justify-content-between small">
                            <span class="text-muted">Plan</span><strong>{{ $plan->name }}</strong>
                        </div>
                        <div class="d-flex justify-content-between small mt-1">
                            <span class="text-muted">Cycle</span><strong>{{ ucfirst($plan->billing_cycle) }}</strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Total</span><strong>₱{{ number_format($plan->price, 2) }}</strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="wallet">Wallet (₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }} available)</option>
                            <option value="cash">Cash at the desk</option>
                            @php $pmLabels = ['gcash'=>'GCash','paymaya'=>'Maya','card'=>'Credit / Debit Card','qrph'=>'QR Ph']; @endphp
                            @foreach($paymongoMethods as $m)
                                <option value="{{ $m }}">{{ $pmLabels[$m] ?? ucfirst($m) }}</option>
                            @endforeach
                            @if($hasStripe)
                                <option value="stripe_card">International Card (Stripe)</option>
                            @endif
                        </select>
                    </div>
                    @if(auth()->user()->wallet_balance < $plan->price)
                        <div class="d-flex align-items-start gap-2 p-3 rounded-3" style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);">
                            <i class="bi bi-exclamation-triangle text-warning mt-1 flex-shrink-0"></i>
                            <span class="small">Wallet balance is low. Pick another method or <a href="{{ route('customer.wallet.index') }}" class="text-warning fw-semibold">top up</a> first.</span>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Subscribe</button>
                </div>
            </form>
        </div>
    </div>
@endforeach

{{-- Renew modal --}}
@if($active)
<div class="modal fade" id="renewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('customer.memberships.renew', $active) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Renew {{ $active->plan?->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="p-3 rounded-3 mb-3" style="background:var(--bs-tertiary-bg)">
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Adds credits</span>
                        <strong>{{ intdiv((int)($active->plan?->court_credits ?? 0), 60) }}h {{ (int)($active->plan?->court_credits ?? 0) % 60 }}m</strong>
                    </div>
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-muted">Extends expiry by</span>
                        <strong>1 {{ $active->plan?->billing_cycle }}</strong>
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Total</span>
                        <strong>₱{{ number_format($active->plan?->price ?? 0, 2) }}</strong>
                    </div>
                </div>
                <label class="form-label small fw-medium">Payment Method</label>
                <select name="payment_method" class="form-select" required>
                    <option value="wallet">Wallet (₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }} available)</option>
                    <option value="cash">Cash at the desk</option>
                    @php $pmLabels = ['gcash'=>'GCash','paymaya'=>'Maya','card'=>'Credit / Debit Card','qrph'=>'QR Ph']; @endphp
                    @foreach($paymongoMethods as $m)
                        <option value="{{ $m }}">{{ $pmLabels[$m] ?? ucfirst($m) }}</option>
                    @endforeach
                    @if($hasStripe)
                        <option value="stripe_card">International Card (Stripe)</option>
                    @endif
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-clockwise me-1"></i>Renew</button>
            </div>
        </form>
    </div>
</div>

{{-- Freeze modal --}}
<div class="modal fade" id="freezeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('customer.memberships.freeze', $active) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h6 class="modal-title fw-semibold">Freeze Membership</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);">
                    <i class="bi bi-info-circle text-primary mt-1 flex-shrink-0"></i>
                    <span class="small">Freezing pauses your membership. Credits don't expire while frozen, and your expiry will be extended by the number of days you were actually frozen.</span>
                </div>
                <label class="form-label small fw-medium">How many days?</label>
                <input type="number" name="days" min="1" max="{{ $active->plan?->max_freeze_days ?? 90 }}" value="7" required class="form-control form-control-lg text-center">
                @if($active->plan?->max_freeze_days)
                    <div class="form-text text-center">Max {{ $active->plan->max_freeze_days }} days{{ $active->plan->freeze_count_per_year ? " · {$active->plan->freeze_count_per_year} freeze(s) per year" : '' }}</div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-snow me-1"></i>Freeze Membership</button>
            </div>
        </form>
    </div>
</div>
@endif
@endpush
