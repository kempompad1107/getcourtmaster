@extends('layouts.customer')

@section('title', 'Membership')

@push('styles')
<style>
    /* ── Customer memberships — pricing cards (mobile tables via shared .table-stack) ── */
    .cust-plan {
        height: 100%; overflow: hidden; --accent: #10b981; --accent-rgb: 16,185,129;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .cust-plan.is-vip { --accent: #f59e0b; --accent-rgb: 245,158,11; border-color: rgba(245,158,11,.4); }
    .cust-plan:hover { transform: translateY(-4px); border-color: rgba(var(--accent-rgb),.45); box-shadow: 0 18px 36px -24px rgba(0,0,0,.6); }
    .cust-plan .plan-accent { height: 5px; background: linear-gradient(90deg, var(--accent), rgba(var(--accent-rgb),.25)); }
    .cust-plan-price { font-size: 2rem; font-weight: 800; letter-spacing: -.02em; line-height: 1; }
</style>
@endpush

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Membership</h4>
        <p class="text-muted small mb-0">Unlock perks, discounts, and priority bookings.</p>
    </div>
    @if($active)
        <x-badge :status="$active->status">{{ ucfirst($active->status) }}</x-badge>
    @endif
</div>

{{-- ── Active membership card ───────────────────────────────── --}}
@if($active)
@php
    $plan       = $active->plan;
    $daysLeft   = max(0, (int) now()->diffInDays($active->expires_at, false));
    $expiresSoon = $daysLeft <= 14;
    $totalCredits = (int) ($plan?->court_credits ?? 0);
    $usedCredits  = max(0, $totalCredits - (int) $active->remaining_credits);
    $usedPct      = $totalCredits > 0 ? min(100, round(($usedCredits / $totalCredits) * 100)) : 0;
@endphp
<div class="card mb-4 overflow-hidden">
    <div class="card-body p-4" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="small opacity-75 mb-1">Current Plan</div>
                <h3 class="fw-bold mb-2">
                    {{ $plan?->name ?? 'Active Member' }}
                    @if($plan?->is_vip)
                        <span class="badge bg-warning text-dark ms-2"><i class="bi bi-star-fill"></i> VIP</span>
                    @endif
                </h3>
                <div class="small opacity-75">
                    <i class="bi bi-calendar-event me-1"></i>
                    Expires <strong>{{ $active->expires_at?->format('M j, Y') }}</strong>
                    ({{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} left)
                </div>
                @if($active->status === 'frozen')
                    <div class="small opacity-75 mt-1">
                        <i class="bi bi-snow me-1"></i>
                        Frozen until {{ $active->frozen_until?->format('M j, Y') }}
                    </div>
                @endif
            </div>
            <div class="text-end">
                <div class="small opacity-75 mb-1">Court Credits Remaining</div>
                <div class="display-6 fw-bold mb-0">{{ $active->credits_label ?? '0h 0m' }}</div>
            </div>
        </div>

        @if($totalCredits > 0)
        <div class="mt-3">
            <div class="d-flex justify-content-between small mb-1">
                <span class="opacity-75">Credits used this cycle</span>
                <span><strong>{{ $usedPct }}%</strong></span>
            </div>
            <div class="progress" style="height:6px; background: rgba(255,255,255,.25);">
                <div class="progress-bar bg-light" style="width: {{ $usedPct }}%"></div>
            </div>
        </div>
        @endif
    </div>

    <div class="card-body">
        @if($expiresSoon)
            <div class="alert alert-warning small mb-3">
                <i class="bi bi-clock-history me-1"></i>
                Your membership expires in {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}. Renew now to keep your credits and perks.
            </div>
        @endif

        {{-- Perks list --}}
        @if(!empty($plan?->perks))
            <div class="mb-3">
                <div class="small text-muted fw-medium mb-2">Your perks</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($plan->perks as $perk)
                        <span class="badge bg-success-subtle text-success-emphasis">
                            <i class="bi bi-check-circle me-1"></i>{{ $perk }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        @if($plan?->discount_percent > 0)
            <p class="small text-muted mb-3">
                <i class="bi bi-tag me-1 text-success"></i>
                <strong>{{ rtrim(rtrim(number_format($plan->discount_percent, 2), '0'), '.') }}%</strong>
                discount on all bookings.
            </p>
        @endif

        {{-- Actions --}}
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#renewModal">
                <i class="bi bi-arrow-clockwise me-1"></i>Renew
            </button>
            @if($active->status !== 'frozen' && $plan?->max_freeze_days)
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#freezeModal">
                    <i class="bi bi-snow me-1"></i>Freeze
                </button>
            @endif
            <form method="POST" action="{{ route('customer.memberships.cancel', $active) }}"
                  onsubmit="return confirm('Cancel your membership? You can still book until the end of your current cycle.');">
                @csrf
                <button class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Cancel</button>
            </form>
        </div>
    </div>
</div>

{{-- Recent membership transactions --}}
@if($active->transactions->count() > 0)
<div class="card mb-4">
    <div class="card-header ">
        <h6 class="mb-0 fw-semibold">Recent Activity</h6>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th class="text-end">Credits</th>
                </tr>
            </thead>
            <tbody>
                @foreach($active->transactions->take(8) as $tx)
                <tr>
                    <td data-label="Date" class="small text-muted">{{ $tx->created_at->format('M j, Y') }}</td>
                    <td data-label="Type"><span class="badge rounded-pill bg-secondary-subtle text-secondary text-capitalize">{{ str_replace('_', ' ', $tx->type) }}</span></td>
                    <td data-label="Description" class="small">{{ $tx->description }}</td>
                    <td data-label="Credits" class="text-end small fw-medium {{ ($tx->credits_change ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        @if($tx->credits_change)
                            {{ $tx->credits_change > 0 ? '+' : '' }}{{ $tx->credits_change }}m
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endif

{{-- ── Plans grid (signup) ─────────────────────────────────── --}}
@if(!$active)
<div class="mb-3">
    <h5 class="fw-semibold mb-1">Choose a plan</h5>
    <p class="text-muted small">Pay from your wallet (₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }} available) or at the desk.</p>
</div>
@endif

<div class="row g-3">
    @forelse ($plans as $plan)
        @php
            $hours = intdiv((int) $plan->court_credits, 60);
            $mins  = (int) $plan->court_credits % 60;
        @endphp
        <div class="col-md-6 col-lg-4">
            <div class="card cust-plan {{ $plan->is_vip ? 'is-vip' : '' }}">
                <div class="plan-accent"></div>
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="fw-bold mb-0">{{ $plan->name }}</h5>
                        @if($plan->is_vip)
                            <span class="badge rounded-pill bg-warning-subtle text-warning"><i class="bi bi-star-fill"></i> VIP</span>
                        @endif
                    </div>

                    <div class="cust-plan-price text-success my-2">
                        ₱{{ number_format($plan->price, 2) }}
                        <small class="text-muted fs-6 fw-normal">/ {{ ucfirst($plan->billing_cycle) }}</small>
                    </div>

                    @if ($plan->description)
                        <p class="text-muted small mb-3">{{ $plan->description }}</p>
                    @endif

                    <ul class="list-unstyled small mb-3">
                        @if($plan->court_credits)
                            <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i> {{ $hours }}h {{ $mins }}m court credits</li>
                        @endif
                        @if($plan->discount_percent > 0)
                            <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i> {{ rtrim(rtrim(number_format($plan->discount_percent, 2), '0'), '.') }}% off bookings</li>
                        @endif
                        @if($plan->max_freeze_days)
                            <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i> Freeze up to {{ $plan->max_freeze_days }} days
                                @if($plan->freeze_count_per_year) ({{ $plan->freeze_count_per_year }}/yr) @endif
                            </li>
                        @endif
                        @foreach(($plan->perks ?? []) as $perk)
                            <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i> {{ $perk }}</li>
                        @endforeach
                    </ul>

                    <div class="mt-auto">
                        @if($active)
                            <button class="btn btn-outline-secondary w-100" disabled title="You already have an active membership">
                                Already subscribed
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
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-inbox display-6 d-block mb-2"></i>
                    No membership plans available right now.
                </div>
            </div>
        </div>
    @endforelse
</div>

{{-- ── History ─────────────────────────────────────────────── --}}
@if($history->count() > 0)
<div class="card mt-4">
    <div class="card-header ">
        <h6 class="mb-0 fw-semibold">Past Memberships</h6>
    </div>
    <div class="table-responsive">
        <table class="table mb-0 align-middle table-stack">
            <thead class="table-light">
                <tr>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Ended</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $h)
                    <tr>
                        <td data-label="Plan" class="fw-medium">{{ $h->plan?->name ?? '—' }}</td>
                        <td data-label="Status">
                            <x-badge :status="match($h->status) { 'expired' => 'expired', 'cancelled' => 'cancelled', 'frozen' => 'pending', default => 'neutral' }">{{ ucfirst($h->status) }}</x-badge>
                        </td>
                        <td data-label="Started" class="small text-muted">{{ $h->starts_at?->format('M j, Y') }}</td>
                        <td data-label="Ended" class="small text-muted">{{ $h->expires_at?->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@endsection

@push('modals')
{{-- Subscribe modals (one per plan, pushed outside the gradient hero) --}}
@foreach($plans as $plan)
    @continue($active)
    <div class="modal fade" id="subscribeModal-{{ $plan->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('customer.memberships.subscribe', $plan) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Subscribe to {{ $plan->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        <strong>{{ $plan->name }}</strong> · {{ ucfirst($plan->billing_cycle) }}<br>
                        Amount: <strong>₱{{ number_format($plan->price, 2) }}</strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Payment method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="wallet">
                                Wallet (₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }} available)
                            </option>
                            <option value="cash">Cash at the desk</option>
                            <option value="gcash">GCash</option>
                            <option value="maya">Maya</option>
                            <option value="card">Credit / Debit Card</option>
                        </select>
                    </div>
                    @if(auth()->user()->wallet_balance < $plan->price)
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Your wallet has ₱{{ number_format(auth()->user()->wallet_balance, 2) }} — pick another method or
                            <a href="{{ route('customer.wallet.index') }}" class="alert-link">top up</a> first.
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Subscribe
                    </button>
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
                <h6 class="modal-title">Renew {{ $active->plan?->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Extends your membership by another {{ $active->plan?->billing_cycle }} cycle and adds
                    <strong>{{ intdiv((int)($active->plan?->court_credits ?? 0), 60) }}h {{ (int)($active->plan?->court_credits ?? 0) % 60 }}m</strong>
                    of court credits on top of your current balance.
                </p>
                <p class="small text-muted mb-3">
                    Amount: <strong>₱{{ number_format($active->plan?->price ?? 0, 2) }}</strong>
                </p>
                <label class="form-label small fw-medium">Payment method</label>
                <select name="payment_method" class="form-select" required>
                    <option value="wallet">Wallet (₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }} available)</option>
                    <option value="cash">Cash at the desk</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="card">Credit / Debit Card</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
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
                <h6 class="modal-title">Freeze Membership</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Pause your membership for a set number of days. Credits don't expire while frozen.
                </p>
                <label class="form-label small fw-medium">Freeze for (days)</label>
                <input type="number" name="days" min="1" max="{{ $active->plan?->max_freeze_days ?? 90 }}" value="7" required class="form-control">
                @if($active->plan?->max_freeze_days)
                    <div class="form-text small">Max {{ $active->plan->max_freeze_days }} days per freeze
                        @if($active->plan->freeze_count_per_year) · {{ $active->plan->freeze_count_per_year }} freeze(s) allowed per year @endif
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-snow me-1"></i>Freeze</button>
            </div>
        </form>
    </div>
</div>
@endif
@endpush
