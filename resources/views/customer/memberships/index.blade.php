@extends('layouts.customer')

@section('title', 'Membership')

@push('styles')
<style>
    .cust-plan {
        height: 100%; overflow: hidden;
        --accent: #10b981; --accent-rgb: 16,185,129;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .cust-plan.is-vip { --accent: #f59e0b; --accent-rgb: 245,158,11; border-color: rgba(245,158,11,.35); }
    .cust-plan:hover  { transform: translateY(-4px); border-color: rgba(var(--accent-rgb),.45); box-shadow: 0 18px 36px -24px rgba(0,0,0,.6); }
    .cust-plan .plan-accent { height: 5px; background: linear-gradient(90deg, var(--accent), rgba(var(--accent-rgb),.2)); }
    .cust-plan-price { font-size: 2rem; font-weight: 800; letter-spacing: -.02em; line-height: 1; }

    .mem-tx-ico {
        width: 36px; height: 36px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center; font-size: .9rem;
        background: rgba(16,185,129,.1); color: #10b981;
        border: 1px solid rgba(16,185,129,.2);
    }
</style>
@endpush

@section('content')

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        {{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
        {{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

{{-- Page header --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Membership</h4>
        <p class="text-muted small mb-0">Unlock perks, discounts, and priority bookings.</p>
    </div>
</div>

{{-- ── Active membership card ─────────────────────────────── --}}
@if($active)
@php
    $plan        = $active->plan;
    $daysLeft    = max(0, (int) now()->diffInDays($active->expires_at, false));
    $expiresSoon = $daysLeft <= 14;
    $totalCredits = (int) ($plan?->court_credits ?? 0);
    $usedCredits  = max(0, $totalCredits - (int) $active->remaining_credits);
    $usedPct      = $totalCredits > 0 ? min(100, round(($usedCredits / $totalCredits) * 100)) : 0;
@endphp
<div class="card mb-4 overflow-hidden">
    {{-- Gradient hero --}}
    <div class="p-4" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color:#fff;">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <span class="small fw-semibold opacity-75">Current Plan</span>
                    @if($active->cancelled_at && $active->status === 'active')
                        <span class="badge" style="background:rgba(255,255,255,.2);color:#fff">Cancels {{ $active->expires_at->format('M j') }}</span>
                    @else
                        <span class="badge" style="background:rgba(255,255,255,.2);color:#fff">{{ ucfirst($active->status) }}</span>
                    @endif
                    @if($plan?->is_vip)
                        <span class="badge bg-warning text-dark"><i class="bi bi-star-fill me-1"></i>VIP</span>
                    @endif
                </div>
                <h3 class="fw-bold mb-2">{{ $plan?->name ?? 'Active Member' }}</h3>
                <div class="small" style="opacity:.8">
                    <i class="bi bi-calendar-event me-1"></i>
                    Expires <strong>{{ $active->expires_at?->format('M j, Y') }}</strong>
                    · {{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }} left
                </div>
                @if($active->status === 'frozen')
                    <div class="small mt-1" style="opacity:.8">
                        <i class="bi bi-snow me-1"></i>Frozen until {{ $active->frozen_until?->format('M j, Y') }}
                    </div>
                @endif
            </div>
            <div class="text-end flex-shrink-0">
                <div class="small mb-1" style="opacity:.75">Court Credits</div>
                <div class="fw-bold" style="font-size:2rem;line-height:1;letter-spacing:-.02em">{{ $active->credits_label ?? '0h 0m' }}</div>
                <div class="small mt-1" style="opacity:.6">remaining</div>
            </div>
        </div>

        @if($totalCredits > 0)
        <div class="mt-3">
            <div class="d-flex justify-content-between small mb-1" style="opacity:.8">
                <span>Credits used this cycle</span>
                <span><strong>{{ $usedPct }}%</strong></span>
            </div>
            <div class="progress" style="height:5px;background:rgba(255,255,255,.2);">
                <div class="progress-bar bg-white" style="width:{{ $usedPct }}%"></div>
            </div>
        </div>
        @endif
    </div>

    {{-- Body: alerts + perks + actions --}}
    <div class="card-body">
        @if($active->cancelled_at)
            <div class="alert alert-warning small py-2 mb-3">
                <i class="bi bi-calendar-x me-1"></i>
                Scheduled for cancellation — full access until <strong>{{ $active->expires_at->format('M j, Y') }}</strong>.
            </div>
        @elseif($expiresSoon)
            <div class="alert alert-warning small py-2 mb-3">
                <i class="bi bi-clock-history me-1"></i>
                Expires in <strong>{{ $daysLeft }} day{{ $daysLeft === 1 ? '' : 's' }}</strong>. Renew now to keep your credits and perks.
            </div>
        @endif

        @if(!empty($plan?->perks))
            <div class="mb-3">
                <div class="small text-muted fw-semibold mb-2" style="text-transform:uppercase;letter-spacing:.05em;font-size:.68rem">Your perks</div>
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
                <strong>{{ rtrim(rtrim(number_format($plan->discount_percent, 2), '0'), '.') }}%</strong> discount on all bookings.
            </p>
        @endif

        {{-- Actions --}}
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#renewModal">
                <i class="bi bi-arrow-clockwise me-1"></i>Renew
            </button>
            @if($active->status === 'frozen')
                <form method="POST" action="{{ route('customer.memberships.unfreeze', $active) }}"
                      onsubmit="return confirm('Unfreeze now? Your expiry will be extended for the remaining frozen days.');">
                    @csrf
                    <button class="btn btn-outline-secondary"><i class="bi bi-sun me-1"></i>Unfreeze</button>
                </form>
            @elseif($plan?->max_freeze_days)
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#freezeModal">
                    <i class="bi bi-snow me-1"></i>Freeze
                </button>
            @endif
            @if(!$active->cancelled_at)
                @php $cancelConfirm = "Cancel your membership? You will keep full access until {$active->expires_at->format('M j, Y')}."; @endphp
                <form method="POST" action="{{ route('customer.memberships.cancel', $active) }}"
                      onsubmit="return confirm('{{ $cancelConfirm }}');">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel</button>
                </form>
            @endif
        </div>
    </div>
</div>

{{-- Recent membership transactions --}}
@if($active->transactions->count() > 0)
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">Recent Activity</h6>
    </div>
    <div class="list-group list-group-flush">
        @foreach($active->transactions->take(8) as $tx)
        @php
            $change = $tx->credits_change ?? 0;
            $ico = match(true) {
                str_contains($tx->type ?? '', 'book')   => 'bi-calendar3',
                str_contains($tx->type ?? '', 'refund') => 'bi-arrow-counterclockwise',
                str_contains($tx->type ?? '', 'top')    => 'bi-plus-circle',
                str_contains($tx->type ?? '', 'freeze') => 'bi-snow',
                default                                  => 'bi-clock-history',
            };
        @endphp
        <div class="list-group-item d-flex align-items-center gap-3 py-3">
            <div class="mem-tx-ico"><i class="bi {{ $ico }}"></i></div>
            <div class="flex-grow-1 min-w-0">
                <div class="small fw-semibold">{{ $tx->description ?: ucwords(str_replace('_', ' ', $tx->type)) }}</div>
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
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h5 class="fw-bold mb-0">{{ $active ? 'Available Plans' : 'Choose a Plan' }}</h5>
        @if(!$active)
        <p class="text-muted small mb-0">
            Wallet balance: <strong>₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }}</strong> — or pay at the desk.
        </p>
        @endif
    </div>
</div>

<div class="row g-3 mb-4">
    @forelse ($plans as $plan)
    @php
        $hours = intdiv((int) $plan->court_credits, 60);
        $mins  = (int) $plan->court_credits % 60;
    @endphp
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card cust-plan {{ $plan->is_vip ? 'is-vip' : '' }}">
            <div class="plan-accent"></div>
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="fw-bold mb-0">{{ $plan->name }}</h5>
                    @if($plan->is_vip)
                        <span class="badge rounded-pill bg-warning-subtle text-warning"><i class="bi bi-star-fill me-1"></i>VIP</span>
                    @endif
                </div>

                <div class="cust-plan-price text-success my-2">
                    ₱{{ number_format($plan->price, 2) }}
                    <small class="text-muted fs-6 fw-normal">/ {{ ucfirst($plan->billing_cycle) }}</small>
                </div>

                @if($plan->description)
                    <p class="text-muted small mb-3">{{ $plan->description }}</p>
                @endif

                <ul class="list-unstyled small mb-3">
                    @if($plan->court_credits)
                        <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i>{{ $hours }}h{{ $mins ? " {$mins}m" : '' }} court credits</li>
                    @endif
                    @if($plan->discount_percent > 0)
                        <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i>{{ rtrim(rtrim(number_format($plan->discount_percent, 2), '0'), '.') }}% off bookings</li>
                    @endif
                    @if($plan->max_freeze_days)
                        <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i>Freeze up to {{ $plan->max_freeze_days }} days{{ $plan->freeze_count_per_year ? " ({$plan->freeze_count_per_year}/yr)" : '' }}</li>
                    @endif
                    @foreach(($plan->perks ?? []) as $perk)
                        <li class="mb-1"><i class="bi bi-check-circle text-success me-1"></i>{{ $perk }}</li>
                    @endforeach
                </ul>

                <div class="mt-auto">
                    @if($active)
                        <button class="btn btn-outline-secondary w-100" disabled>
                            <i class="bi bi-check2 me-1"></i>Already subscribed
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
                    <p class="small text-muted mb-3">
                        <strong>{{ $plan->name }}</strong> · {{ ucfirst($plan->billing_cycle) }}<br>
                        Amount: <strong>₱{{ number_format($plan->price, 2) }}</strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="wallet">Wallet (₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }} available)</option>
                            <option value="cash">Cash at the desk</option>
                            <option value="gcash">GCash</option>
                            <option value="maya">Maya</option>
                            <option value="card">Credit / Debit Card</option>
                        </select>
                    </div>
                    @if(auth()->user()->wallet_balance < $plan->price)
                        <div class="alert alert-warning small mb-0 py-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            Wallet has ₱{{ number_format(auth()->user()->wallet_balance, 2) }} — pick another method or
                            <a href="{{ route('customer.wallet.index') }}" class="alert-link">top up</a> first.
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
                <p class="small text-muted mb-1">
                    Extends your membership by another <strong>{{ $active->plan?->billing_cycle }}</strong> cycle and adds
                    <strong>{{ intdiv((int)($active->plan?->court_credits ?? 0), 60) }}h {{ (int)($active->plan?->court_credits ?? 0) % 60 }}m</strong>
                    court credits on top of your current balance.
                </p>
                <p class="small text-muted mb-3">Amount: <strong>₱{{ number_format($active->plan?->price ?? 0, 2) }}</strong></p>
                <label class="form-label small fw-medium">Payment Method</label>
                <select name="payment_method" class="form-select" required>
                    <option value="wallet">Wallet (₱{{ number_format(auth()->user()->wallet_balance ?? 0, 2) }} available)</option>
                    <option value="cash">Cash at the desk</option>
                    <option value="gcash">GCash</option>
                    <option value="maya">Maya</option>
                    <option value="card">Credit / Debit Card</option>
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
                <p class="small text-muted mb-3">Pause your membership for a set number of days. Credits don't expire while frozen.</p>
                <label class="form-label small fw-medium">Freeze for (days)</label>
                <input type="number" name="days" min="1" max="{{ $active->plan?->max_freeze_days ?? 90 }}" value="7" required class="form-control">
                @if($active->plan?->max_freeze_days)
                    <div class="form-text">Max {{ $active->plan->max_freeze_days }} days per freeze{{ $active->plan->freeze_count_per_year ? " · {$active->plan->freeze_count_per_year} freeze(s) per year" : '' }}</div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-snow me-1"></i>Freeze</button>
            </div>
        </form>
    </div>
</div>
@endif
@endpush
