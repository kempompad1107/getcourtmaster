@extends('layouts.app')
@section('title', 'My Subscription')

@push('styles')
<style>
    /* ── Subscription plan picker — pricing cards ── */
    .sub-plan {
        height: 100%; border: 1px solid var(--bs-border-color); border-radius: 1rem;
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .sub-plan:hover { transform: translateY(-3px); border-color: rgba(16,185,129,.4); box-shadow: 0 14px 30px -22px rgba(0,0,0,.7); }
    .sub-plan.is-current { border-color: rgba(16,185,129,.5); background: rgba(16,185,129,.05); }
    .sub-price { font-size: 1.85rem; font-weight: 800; letter-spacing: -.02em; }
</style>
@endpush

@section('content')

<x-page-header title="My Subscription"
                subtitle="Manage your plan, renew, or upgrade. Pay online or settle invoices manually."/>

@php
    $currentPlan = $subscription?->plan;
    $cycle       = $subscription?->billing_cycle ?? 'monthly';
@endphp

<div class="row g-4">

    {{-- Current plan + renew --}}
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Current Plan</h6></div>
            <div class="card-body">
                @if($subscription && $currentPlan)
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h4 class="mb-0">{{ $currentPlan->name }}</h4>
                        <x-badge :status="match($subscription->status) { 'active' => 'active', 'trialing' => 'pending', 'cancelled' => 'cancelled', default => 'pending' }">{{ ucfirst($subscription->status) }}</x-badge>
                    </div>
                    <p class="text-muted mb-3">
                        ₱{{ number_format($subscription->amount, 2) }} / {{ $cycle === 'yearly' ? 'year' : 'month' }}
                    </p>
                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">Billing cycle</span><span>{{ ucfirst($cycle) }}</span>
                        </li>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span class="text-muted">Renews on</span>
                            <span>{{ $subscription->renews_at?->format('M j, Y') ?? '—' }}</span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Account status</span>
                            <span>{{ ucfirst($tenant->status) }}</span>
                        </li>
                    </ul>

                    @if($outstandingInvoice)
                        <div class="alert alert-warning small mb-0">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            You have an invoice due — settle it on the right to keep your plan active.
                        </div>
                    @elseif($canRenew)
                        <form method="POST" action="{{ route('admin.subscription.renew') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-arrow-repeat me-1"></i>Renew now (generate invoice)
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-primary w-100" disabled>
                            <i class="bi bi-arrow-repeat me-1"></i>Renew now (generate invoice)
                        </button>
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-clock-history me-1"></i>
                            Renewal opens {{ $renewOpensAt?->format('M j, Y') ?? 'closer to your renewal date' }}.
                            You can pay within {{ \App\Models\TenantSubscription::RENEWAL_WINDOW_DAYS }} days of expiry.
                        </small>
                    @endif
                @else
                    <x-empty-state title="No active plan"
                                   message="Choose a plan below to get started."
                                   icon="bi-stars"/>
                @endif
            </div>
        </div>
    </div>

    {{-- Outstanding invoice --}}
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0 fw-semibold">Outstanding Invoice</h6></div>
            <div class="card-body">
                @if($outstandingInvoice)
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="font-monospace">{{ $outstandingInvoice->invoice_number }}</div>
                            <small class="text-muted">
                                Due {{ $outstandingInvoice->due_at?->format('M j, Y') ?? '—' }}
                                @if($outstandingInvoice->isOverdue())
                                    <span class="text-danger fw-medium">· Overdue</span>
                                @endif
                            </small>
                        </div>
                        <div class="text-end">
                            <div class="h5 mb-0">₱{{ number_format($outstandingInvoice->total, 2) }}</div>
                            <x-badge :status="$outstandingInvoice->status">{{ ucfirst($outstandingInvoice->status) }}</x-badge>
                        </div>
                    </div>

                    @if(count($onlineGateways))
                        <p class="small text-muted mb-2">Pay online now:</p>
                        <div class="d-flex gap-2 flex-wrap mb-3">
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
                        <div class="alert alert-info small mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Online payment isn't enabled yet. Settle this invoice via bank transfer / cash;
                            We will mark it paid.
                        </div>
                    @endif

                    <a href="{{ route('admin.subscription-invoices.pdf', $outstandingInvoice) }}"
                       class="btn btn-link btn-sm p-0">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Download invoice PDF
                    </a>
                @else
                    <x-empty-state title="Nothing due"
                                   message="You have no outstanding invoices."
                                   icon="bi-check2-circle"/>
                @endif
                <hr>
                <a href="{{ route('admin.subscription-invoices.index') }}" class="small">
                    <i class="bi bi-receipt-cutoff me-1"></i>View full billing history
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Plan picker / upgrade --}}
<div class="card mt-4" x-data="{ cycle: '{{ $cycle }}' }">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 fw-semibold">Available Plans</h6>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" @click="cycle='monthly'"
                    :class="cycle==='monthly' ? 'btn-secondary' : 'btn-outline-secondary'" class="btn">Monthly</button>
            <button type="button" @click="cycle='yearly'"
                    :class="cycle==='yearly' ? 'btn-secondary' : 'btn-outline-secondary'" class="btn">Yearly</button>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            @forelse($plans as $plan)
                @php $isCurrent = $currentPlan && $currentPlan->id === $plan->id; @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="sub-plan p-3 d-flex flex-column @if($isCurrent) is-current @endif">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="mb-0 fw-bold">{{ $plan->name }}</h6>
                            @if($isCurrent)<x-badge status="active">Current</x-badge>@endif
                        </div>
                        @if($plan->description)
                            <small class="text-muted mb-2">{{ $plan->description }}</small>
                        @endif
                        <div class="mb-3">
                            <span x-show="cycle==='monthly'">
                                <span class="sub-price">₱{{ number_format($plan->price_monthly, 0) }}</span>
                                <span class="text-muted small">/mo</span>
                            </span>
                            <span x-show="cycle==='yearly'" x-cloak>
                                <span class="sub-price">₱{{ number_format($plan->price_yearly, 0) }}</span>
                                <span class="text-muted small">/yr</span>
                            </span>
                        </div>
                        <ul class="list-unstyled small text-muted mb-3 d-flex flex-column gap-1">
                            @if($plan->max_courts)<li><i class="bi bi-check-circle-fill text-success me-1"></i>{{ $plan->max_courts }} courts</li>@endif
                            @if($plan->max_staff)<li><i class="bi bi-check-circle-fill text-success me-1"></i>{{ $plan->max_staff }} staff</li>@endif
                            @if($plan->max_branches)<li><i class="bi bi-check-circle-fill text-success me-1"></i>{{ $plan->max_branches }} branches</li>@endif
                        </ul>
                        <form method="POST" action="{{ route('admin.subscription.change-plan') }}" class="mt-auto">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="billing_cycle" :value="cycle">
                            <button type="submit"
                                    class="btn w-100 {{ $isCurrent ? 'btn-outline-secondary' : 'btn-primary' }}"
                                    onclick="return confirm('Change to the {{ $plan->name }} plan? This takes effect immediately and generates an invoice.')">
                                @if($isCurrent)
                                    <i class="bi bi-arrow-repeat me-1"></i>Switch cycle / re-confirm
                                @else
                                    <i class="bi bi-arrow-up-circle me-1"></i>Choose {{ $plan->name }}
                                @endif
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <x-empty-state title="No plans available"
                                   message="Contact support to set up a subscription plan."
                                   icon="bi-stars"/>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection
