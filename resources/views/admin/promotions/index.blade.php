@extends('layouts.app')
@section('title', 'Promotions')

@push('styles')
<style>
    /* ── Promotions — coupon-style cards over the admin theme ── */
    .promo-card {
        position: relative; height: 100%; overflow: hidden;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .promo-card:hover { transform: translateY(-4px); border-color: rgba(16,185,129,.4); box-shadow: 0 18px 36px -24px rgba(0,0,0,.75); }
    .promo-card.is-off { opacity: .72; }
    .promo-code {
        display: inline-flex; align-items: center; gap: .45rem;
        font-family: ui-monospace, monospace; font-weight: 700; letter-spacing: .08em;
        padding: .45rem .8rem; border-radius: .6rem; font-size: .95rem;
        background: rgba(16,185,129,.1); color: #34d399; border: 1px dashed rgba(16,185,129,.5);
    }
    .promo-value { font-size: 1.95rem; font-weight: 800; letter-spacing: -.02em; line-height: 1; }
    .promo-meta i { width: 1rem; color: var(--bs-secondary-color); }
</style>
@endpush

@section('content')

<x-page-header title="Promotions &amp; Discounts">
    <x-slot name="actions">
        <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Create Promotion
        </a>
    </x-slot>
</x-page-header>

@if($promotions->isEmpty())
<div class="card">
    <div class="card-body">
        <x-empty-state title="No promotions yet" icon="bi-tag-fill"
                       description="Create your first discount code to attract customers."/>
    </div>
</div>
@else
<div class="row g-4">
    @foreach($promotions as $promo)
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card promo-card {{ $promo->is_active ? '' : 'is-off' }}">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                    <span class="promo-code"><i class="bi bi-ticket-perforated"></i>{{ $promo->code }}</span>
                    <x-badge :status="$promo->is_active ? 'active' : 'expired'">{{ $promo->is_active ? 'Active' : 'Inactive' }}</x-badge>
                </div>

                <h6 class="fw-semibold mb-1">{{ $promo->name }}</h6>
                <div class="promo-value text-success mb-3">
                    @if($promo->type === 'percentage')
                    {{ $promo->value }}%<span class="fs-6 fw-semibold text-body-secondary"> off</span>
                    @else
                    ₱{{ number_format($promo->value) }}<span class="fs-6 fw-semibold text-body-secondary"> off</span>
                    @endif
                </div>

                <div class="promo-meta small text-muted flex-grow-1 mb-3 d-flex flex-column gap-1">
                    @if($promo->starts_at || $promo->expires_at)
                    <div><i class="bi bi-calendar me-1"></i>{{ $promo->starts_at?->format('M j') ?? 'Now' }} – {{ $promo->expires_at?->format('M j, Y') ?? 'No expiry' }}</div>
                    @endif
                    <div><i class="bi bi-check2-circle me-1"></i>Used {{ $promo->usages_count }} times{{ $promo->max_uses ? " / {$promo->max_uses}" : '' }}</div>
                    @if($promo->min_booking_amount)
                    <div><i class="bi bi-arrow-up-circle me-1"></i>Min. booking: ₱{{ number_format($promo->min_booking_amount) }}</div>
                    @endif
                </div>

                <div class="d-flex gap-2 pt-3 border-top">
                    <a href="{{ route('admin.promotions.edit', $promo) }}"
                       class="btn btn-outline-primary btn-sm flex-fill"><i class="bi bi-pencil me-1"></i>Edit</a>
                    <form method="POST" action="{{ route('admin.promotions.destroy', $promo) }}"
                          onsubmit="return confirm('Delete this promotion?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete promotion"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
