@extends('layouts.app')
@section('title', 'My Subscription')

@push('styles')
<style>
    .sub-plan {
        height: 100%; border: 2px solid var(--bs-border-color); border-radius: 1rem;
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .sub-plan:hover {
        transform: translateY(-3px);
        border-color: rgba(16,185,129,.4);
        box-shadow: 0 14px 32px -20px rgba(0,0,0,.55);
    }
    .sub-plan.is-current {
        border-color: #10b981;
        background: rgba(16,185,129,.04);
    }
    .sub-price { font-size: 2rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; }
    .sub-feature { font-size: .82rem; }
    .billing-row { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; }
    .billing-row + .billing-row { border-top: 1px solid var(--bs-border-color); }
</style>
@endpush

@section('content')

@php
    $currentPlan = $subscription?->plan;
    $cycle       = $subscription?->billing_cycle ?? 'monthly';
    $pendingPlan = $subscription?->pendingPlan;
@endphp

<x-page-header title="My Subscription" subtitle="Manage your plan, renew, or upgrade"/>

<div class="row g-4">

    {{-- ── Current Plan ── --}}
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">Current Plan</p>

                @if($subscription && $currentPlan)
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <h5 class="mb-0 fw-bold">{{ $currentPlan->name }}</h5>
                        <x-badge :status="match($subscription->status) {
                            'active'    => 'active',
                            'trialing'  => 'pending',
                            'cancelled' => 'cancelled',
                            default     => 'pending'
                        }">{{ ucfirst($subscription->status) }}</x-badge>
                    </div>
                    <p class="text-muted small mb-4">
                        ₱{{ number_format($subscription->amount, 2) }} / {{ $cycle === 'yearly' ? 'year' : 'month' }}
                    </p>

                    @if($pendingPlan)
                    <div class="alert alert-info d-flex gap-2 small mb-4">
                        <i class="bi bi-hourglass-split flex-shrink-0 mt-1"></i>
                        <span>
                            <strong>Pending plan change:</strong> switching to <strong>{{ $pendingPlan->name }}</strong>
                            ({{ $subscription->pending_billing_cycle }}).
                            Pay the invoice below to activate it.
                        </span>
                    </div>
                    @endif

                    <div class="mb-4">
                        <div class="billing-row">
                            <span class="small text-muted">Billing cycle</span>
                            <span class="small fw-medium">{{ ucfirst($cycle) }}</span>
                        </div>
                        <div class="billing-row">
                            <span class="small text-muted">Renews on</span>
                            <span class="small fw-medium">{{ $subscription->renews_at?->format('M j, Y') ?? '—' }}</span>
                        </div>
                        <div class="billing-row">
                            <span class="small text-muted">Account status</span>
                            <span class="small fw-medium">{{ ucfirst($tenant->status) }}</span>
                        </div>
                    </div>

                    @if($outstandingInvoice)
                        <div class="alert alert-warning d-flex gap-2 small mb-0">
                            <i class="bi bi-exclamation-circle flex-shrink-0 mt-1"></i>
                            <span>You have an invoice due — settle it below to keep your plan active.</span>
                        </div>
                    @elseif($canRenew)
                        <form method="POST" action="{{ route('admin.subscription.renew') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-arrow-repeat me-1"></i>Renew now
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary w-100" disabled>
                            <i class="bi bi-arrow-repeat me-1"></i>Renew now
                        </button>
                        <p class="small text-muted mt-2 mb-0">
                            <i class="bi bi-clock-history me-1"></i>Renewal opens {{ $renewOpensAt?->format('M j, Y') ?? 'closer to your renewal date' }}.
                            You can pay within {{ \App\Models\TenantSubscription::RENEWAL_WINDOW_DAYS }} days of expiry.
                        </p>
                    @endif
                @else
                    <x-empty-state title="No active plan"
                                   description="Choose a plan below to get started."
                                   icon="bi-stars"/>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Outstanding Invoice ── --}}
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">Outstanding Invoice</p>

                @if($outstandingInvoice)
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                        <div>
                            <div class="font-monospace fw-semibold">{{ $outstandingInvoice->invoice_number }}</div>
                            <div class="small text-muted mt-1">
                                Due {{ $outstandingInvoice->due_at?->format('M j, Y') ?? '—' }}
                                @if($outstandingInvoice->isOverdue())
                                    <span class="text-danger fw-medium ms-1">· Overdue</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="h5 mb-1">₱{{ number_format($outstandingInvoice->total, 2) }}</div>
                            <x-badge :status="$outstandingInvoice->status">{{ ucfirst($outstandingInvoice->status) }}</x-badge>
                        </div>
                    </div>

                    @if(count($onlineGateways))
                        <p class="small text-muted mb-2">Pay online now:</p>
                        <div class="d-flex gap-2 flex-wrap mb-4">
                            @foreach($onlineGateways as $gw)
                                <form method="POST" action="{{ route('admin.subscription.checkout', $outstandingInvoice) }}">
                                    @csrf
                                    <input type="hidden" name="gateway" value="{{ $gw }}">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-credit-card me-1"></i>Pay with {{ ucfirst($gw) }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @else
                        <div class="alert alert-info d-flex gap-2 small mb-4">
                            <i class="bi bi-info-circle flex-shrink-0 mt-1"></i>
                            <span>Online payment isn't enabled. Settle this invoice via bank transfer or cash — we'll mark it paid.</span>
                        </div>
                    @endif

                    <a href="{{ route('admin.subscription-invoices.pdf', $outstandingInvoice) }}"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Download invoice PDF
                    </a>
                @else
                    <x-empty-state title="Nothing due"
                                   description="You have no outstanding invoices."
                                   icon="bi-check2-circle"/>
                @endif
            </div>

            <div class="card-footer d-flex align-items-center gap-2">
                <i class="bi bi-receipt-cutoff text-muted"></i>
                <a href="{{ route('admin.subscription-invoices.index') }}" class="small">View full billing history</a>
            </div>
        </div>
    </div>
</div>

{{-- ── Plan Picker ── --}}
<div class="card mt-4" x-data="{ cycle: '{{ $cycle }}' }">
    <div class="card-body">
        @if($outstandingInvoice)
        <div class="alert alert-warning d-flex gap-2 small mb-4">
            <i class="bi bi-lock flex-shrink-0 mt-1"></i>
            <span>Plan changes are locked until your outstanding invoice is paid.</span>
        </div>
        @endif
        <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
            <p class="mb-0" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">Available Plans</p>
            <div class="settings-tabs">
                <button type="button" class="settings-tab-btn" @click="cycle='monthly'"
                        :class="cycle==='monthly' && 'active'">Monthly</button>
                <button type="button" class="settings-tab-btn" @click="cycle='yearly'"
                        :class="cycle==='yearly' && 'active'">Yearly</button>
            </div>
        </div>

        <div class="row g-3">
            @forelse($plans as $plan)
                @php
                    $isCurrent = $currentPlan && $currentPlan->id === $plan->id && !$pendingPlan;
                    $isPending = $pendingPlan && $pendingPlan->id === $plan->id;
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="sub-plan p-4 d-flex flex-column {{ $isCurrent ? 'is-current' : ($isPending ? 'is-current' : '') }}">

                        {{-- Plan name + badge --}}
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <span class="fw-bold fs-6">{{ $plan->name }}</span>
                            @if($isCurrent)
                                <x-badge status="active">Current</x-badge>
                            @elseif($isPending)
                                <x-badge status="pending">Pending payment</x-badge>
                            @endif
                        </div>

                        @if($plan->description)
                            <p class="small text-muted mb-3">{{ $plan->description }}</p>
                        @else
                            <div class="mb-3"></div>
                        @endif

                        {{-- Price --}}
                        <div class="mb-4">
                            <span x-show="cycle==='monthly'">
                                <span class="sub-price">₱{{ number_format($plan->price_monthly, 0) }}</span>
                                <span class="text-muted small ms-1">/mo</span>
                            </span>
                            <span x-show="cycle==='yearly'" x-cloak>
                                <span class="sub-price">₱{{ number_format($plan->price_yearly, 0) }}</span>
                                <span class="text-muted small ms-1">/yr</span>
                            </span>
                        </div>

                        {{-- Features --}}
                        <ul class="list-unstyled sub-feature text-muted mb-4 d-flex flex-column gap-2 flex-grow-1">
                            @if($plan->max_courts)
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                                    <span>{{ $plan->max_courts }} courts</span>
                                </li>
                            @endif
                            @if($plan->max_staff)
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                                    <span>{{ $plan->max_staff }} staff members</span>
                                </li>
                            @endif
                            @if($plan->max_branches)
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                                    <span>{{ $plan->max_branches }} branches</span>
                                </li>
                            @endif
                        </ul>

                        {{-- CTA --}}
                        @if($outstandingInvoice)
                            <button type="button" class="btn w-100 btn-outline-secondary" disabled
                                    title="Settle your outstanding invoice before changing plans.">
                                @if($isPending)
                                    <i class="bi bi-hourglass-split me-1"></i>Awaiting payment
                                @elseif($isCurrent)
                                    <i class="bi bi-arrow-repeat me-1"></i>Switch cycle / re-confirm
                                @else
                                    <i class="bi bi-arrow-up-circle me-1"></i>Choose {{ $plan->name }}
                                @endif
                            </button>
                        @elseif($isPending)
                            <button type="button" class="btn w-100 btn-outline-warning" disabled>
                                <i class="bi bi-hourglass-split me-1"></i>Awaiting payment
                            </button>
                        @else
                            <form method="POST" action="{{ route('admin.subscription.change-plan') }}">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <input type="hidden" name="billing_cycle" :value="cycle">
                                <button type="submit"
                                        class="btn w-100 {{ $isCurrent ? 'btn-outline-secondary' : 'btn-primary' }}"
                                        onclick="return confirm('Choose {{ $plan->name }}? An invoice will be generated — your plan activates once paid.')">
                                    @if($isCurrent)
                                        <i class="bi bi-arrow-repeat me-1"></i>Switch cycle / re-confirm
                                    @else
                                        <i class="bi bi-arrow-up-circle me-1"></i>Choose {{ $plan->name }}
                                    @endif
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <x-empty-state title="No plans available"
                                   description="Contact support to set up a subscription plan."
                                   icon="bi-stars"/>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection
