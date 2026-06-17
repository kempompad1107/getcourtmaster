@extends('layouts.super')
@section('title', 'Subscription Plans')

@push('styles')
@include('super._partials.premium-ui')
<style>
    /* ── Premium pricing cards ── */
    .plan-card {
        position: relative; overflow: hidden; height: 100%;
        border: 1px solid var(--bs-border-color);
        display: flex; flex-direction: column;
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .plan-card::before {
        content: ''; position: absolute; inset: 0 0 auto 0; height: 4px;
        background: linear-gradient(90deg, #10b981, #34d399, transparent);
        opacity: .55;
    }
    .plan-card:hover { transform: translateY(-4px); border-color: rgba(16,185,129,.4); box-shadow: 0 22px 44px -28px rgba(0,0,0,.6); }
    .plan-card.is-featured { border-color: rgba(16,185,129,.55); }
    .plan-card.is-featured::before { opacity: 1; height: 5px; }

    .plan-ribbon {
        position: absolute; top: 1rem; right: -2.6rem; transform: rotate(45deg);
        background: #10b981; color: #fff; font-size: .6rem; font-weight: 800;
        letter-spacing: .09em; text-transform: uppercase; padding: .28rem 2.8rem;
        box-shadow: 0 5px 12px -4px rgba(16,185,129,.7); z-index: 2;
    }
    .plan-icon {
        width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center;
        font-size: 1.3rem; background: rgba(16,185,129,.12); color: #10b981; flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(16,185,129,.22);
    }
    .plan-price { font-size: 2.4rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; color: var(--bs-heading-color); font-variant-numeric: tabular-nums; }
    .plan-price .cur { font-size: 1.25rem; font-weight: 700; vertical-align: top; margin-right: .1rem; }
    .plan-per { color: var(--bs-secondary-color); font-size: .85rem; font-weight: 500; }
    .plan-save {
        display: inline-flex; align-items: center; gap: .3rem; font-size: .68rem; font-weight: 800;
        color: #10b981; background: rgba(16,185,129,.1); padding: .18rem .55rem; border-radius: 999px;
        text-transform: uppercase; letter-spacing: .04em;
    }
    .plan-spec { display: flex; align-items: center; gap: .65rem; padding: .5rem 0; border-bottom: 1px solid var(--bs-border-color); font-size: .82rem; }
    .plan-spec:last-child { border-bottom: 0; }
    .plan-spec > i { font-size: .95rem; flex-shrink: 0; color: #10b981; }
    .plan-spec.is-unlim > i { color: var(--bs-secondary-color); }
    .plan-spec .lbl { color: var(--bs-secondary-color); }
    .plan-spec .v { margin-left: auto; font-weight: 700; font-variant-numeric: tabular-nums; }
</style>
@endpush

@section('content')

<x-page-header title="Subscription Plans" :subtitle="$plans->count() . ' plans'">
    <x-slot name="actions">
        <a href="{{ route('super.plans.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Plan
        </a>
    </x-slot>
</x-page-header>

@if($plans->isEmpty())
<div class="card">
    <div class="card-body text-center py-5">
        <div class="plan-icon mx-auto mb-3"><i class="bi bi-box-seam"></i></div>
        <p class="fw-semibold mb-1">No plans yet</p>
        <p class="text-muted small mb-3">Create your first subscription plan to start onboarding paying tenants.</p>
        <a href="{{ route('super.plans.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Plan
        </a>
    </div>
</div>
@else

@php
    // Highlight the most-adopted plan; fall back to the priciest if none have tenants yet.
    $topByTenants = $plans->sortByDesc('tenants_count')->first();
    $featuredId   = ($topByTenants && $topByTenants->tenants_count > 0)
        ? $topByTenants->id
        : $plans->sortByDesc('price_monthly')->first()?->id;

    // Tier icons by ascending monthly price.
    $tierIcons = ['bi-rocket-takeoff', 'bi-stars', 'bi-gem', 'bi-trophy', 'bi-crown'];
    $iconFor   = [];
    foreach ($plans->sortBy('price_monthly')->values() as $i => $p) {
        $iconFor[$p->id] = $tierIcons[min($i, count($tierIcons) - 1)];
    }
@endphp

{{-- Summary --}}
<div class="kpi-grid mb-4" style="--kpi-cols:3">
    <x-stat-card label="Total Plans"        :value="$plans->count()" icon="bi-collection" color="emerald"/>
    <x-stat-card label="Active Plans"       :value="$plans->where('is_active', true)->count()" icon="bi-check-circle" color="emerald"/>
    <x-stat-card label="Subscribed Tenants" :value="$plans->sum('tenants_count')" icon="bi-buildings" color="purple"/>
</div>

<div class="row g-4">
    @foreach($plans as $plan)
    @php
        $isFeatured = $plan->id === $featuredId;
        $yearlySave = ($plan->price_yearly && $plan->price_monthly)
            ? (int) round((1 - ($plan->price_yearly / ($plan->price_monthly * 12))) * 100)
            : 0;
        $specs = [
            ['bi-diagram-3',   'Branches',  $plan->max_branches],
            ['bi-grid-3x3-gap','Courts',    $plan->max_courts],
            ['bi-person-badge','Staff',     $plan->max_staff],
            ['bi-people',      'Customers', $plan->max_customers ?? null],
        ];
    @endphp
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card plan-card {{ $isFeatured ? 'is-featured' : '' }}">
            @if($isFeatured)<span class="plan-ribbon">Popular</span>@endif

            <div class="card-body d-flex flex-column">
                {{-- Header --}}
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="plan-icon"><i class="bi {{ $iconFor[$plan->id] ?? 'bi-box-seam' }}"></i></div>
                    <div class="min-w-0">
                        <h6 class="fw-bold mb-1 text-truncate">{{ $plan->name }}</h6>
                        <x-badge :status="$plan->is_active ? 'active' : 'expired'">{{ $plan->is_active ? 'Active' : 'Inactive' }}</x-badge>
                    </div>
                </div>

                {{-- Price --}}
                <div class="d-flex align-items-end flex-wrap gap-2 mb-1">
                    <span class="plan-price"><span class="cur">₱</span>{{ number_format($plan->price_monthly) }}</span>
                    <span class="plan-per mb-1">/mo</span>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3" style="min-height:1.4rem">
                    @if($plan->price_yearly)
                        <span class="plan-per">₱{{ number_format($plan->price_yearly) }}/yr</span>
                        @if($yearlySave > 0)
                            <span class="plan-save"><i class="bi bi-tag-fill"></i>Save {{ $yearlySave }}%</span>
                        @endif
                    @else
                        <span class="plan-per text-muted">No yearly price</span>
                    @endif
                </div>

                {{-- Specs --}}
                <div class="mb-3">
                    @foreach($specs as [$icon, $label, $value])
                    @php $unlimited = is_null($value); @endphp
                    <div class="plan-spec {{ $unlimited ? 'is-unlim' : '' }}">
                        <i class="bi {{ $unlimited ? 'bi-infinity' : $icon }}"></i>
                        <span class="lbl">{{ $label }}</span>
                        <span class="v">{{ $unlimited ? 'Unlimited' : number_format($value) }}</span>
                    </div>
                    @endforeach
                    <div class="plan-spec">
                        <i class="bi bi-hourglass-split"></i>
                        <span class="lbl">Trial</span>
                        <span class="v">{{ $plan->trial_days ?? 0 }} days</span>
                    </div>
                    <div class="plan-spec">
                        <i class="bi bi-buildings"></i>
                        <span class="lbl">Tenants on plan</span>
                        @if($plan->tenants_count > 0)
                            <a href="{{ route('super.plans.show', $plan) }}" class="v text-decoration-none">
                                {{ $plan->tenants_count }} <i class="bi bi-arrow-right-short"></i>
                            </a>
                        @else
                            <span class="v text-muted">0</span>
                        @endif
                    </div>
                </div>

                <div class="mt-auto d-flex gap-2">
                    <a href="{{ route('super.plans.edit', $plan) }}"
                       class="btn btn-outline-secondary btn-sm flex-fill">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <form method="POST" action="{{ route('super.plans.destroy', $plan) }}"
                          onsubmit="return confirm('Delete this plan?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" title="Delete plan">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
