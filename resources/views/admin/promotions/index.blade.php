@extends('layouts.app')
@section('title', 'Promotions')

@push('styles')
<style>
    .promo-card {
        position: relative; height: 100%; overflow: hidden;
        border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .promo-card:hover {
        transform: translateY(-3px);
        border-color: rgba(16,185,129,.35);
        box-shadow: 0 16px 32px -20px rgba(0,0,0,.55);
    }
    .promo-card.is-off { border-left: 3px solid var(--bs-border-color); }
    .promo-card.is-on  { border-left: 3px solid #10b981; }
    .promo-code {
        display: inline-flex; align-items: center; gap: .4rem;
        font-family: ui-monospace, monospace; font-weight: 700; letter-spacing: .06em;
        padding: .35rem .7rem; border-radius: .55rem; font-size: .85rem;
        background: rgba(16,185,129,.1); color: #34d399;
        border: 1px dashed rgba(16,185,129,.45);
    }
    .promo-value {
        font-size: clamp(1.5rem, 4vw, 2rem);
        font-weight: 800; letter-spacing: -.02em; line-height: 1;
    }
    .promo-meta i { width: 1rem; color: var(--bs-secondary-color); }
</style>
@endpush

@section('content')

@php
    $filterStatus = request('status');
    $activeFilters = (int) request()->filled('status');
@endphp

<form method="GET" action="{{ route('admin.promotions.index') }}" x-data="{ open: false }">
<x-page-header title="Promotions & Discounts">
    <x-slot name="actions">
        {{-- Filter icon --}}
        <div class="position-relative" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="btn {{ $activeFilters ? 'btn-primary' : 'btn-outline-secondary' }} position-relative">
                <i class="bi bi-sliders2"></i>
                @if($activeFilters)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                      style="font-size:.55rem">{{ $activeFilters }}</span>
                @endif
            </button>
            <div x-show="open" x-cloak
                 class="position-absolute end-0 mt-1 p-3 rounded-3 shadow-lg border bg-body z-3"
                 style="min-width:200px">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active"   @selected($filterStatus === 'active')>Active</option>
                            <option value="inactive" @selected($filterStatus === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                        @if($activeFilters)
                        <a href="{{ route('admin.promotions.index') }}" class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('admin.promotions.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i>Create Promotion
        </a>
    </x-slot>
</x-page-header>
</form>

@if($promotions->isEmpty())
    <x-empty-state title="No promotions yet" icon="bi-tag-fill"
        description="{{ $activeFilters ? 'No promotions match this filter.' : 'Create your first discount code to attract customers.' }}"/>
@else
<div class="row g-4">
    @foreach($promotions as $promo)
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card promo-card {{ $promo->is_active ? 'is-on' : 'is-off' }}">
            <div class="card-body d-flex flex-column">

                {{-- Code + status --}}
                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                    <span class="promo-code"><i class="bi bi-ticket-perforated"></i>{{ $promo->code }}</span>
                    <x-badge :status="$promo->is_active ? 'active' : 'expired'">
                        {{ $promo->is_active ? 'Active' : 'Inactive' }}
                    </x-badge>
                </div>

                {{-- Name --}}
                <p class="fw-semibold small mb-1">{{ $promo->name }}</p>

                {{-- Value --}}
                <div class="promo-value text-success mb-3">
                    @if($promo->type === 'percentage')
                        {{ $promo->value }}%<span class="fs-6 fw-semibold text-body-secondary"> off</span>
                    @else
                        ₱{{ number_format($promo->value) }}<span class="fs-6 fw-semibold text-body-secondary"> off</span>
                    @endif
                </div>

                {{-- Meta --}}
                <div class="promo-meta small text-muted flex-grow-1 mb-3 d-flex flex-column gap-1">
                    @if($promo->starts_at || $promo->expires_at)
                    <div>
                        <i class="bi bi-calendar"></i>
                        {{ $promo->starts_at?->format('M j') ?? 'Now' }} – {{ $promo->expires_at?->format('M j, Y') ?? 'No expiry' }}
                    </div>
                    @endif
                    <div>
                        <i class="bi bi-check2-circle"></i>
                        Used {{ $promo->usages_count }} time{{ $promo->usages_count === 1 ? '' : 's' }}{{ $promo->max_uses ? " / {$promo->max_uses}" : '' }}
                    </div>
                    @if($promo->min_booking_amount)
                    <div>
                        <i class="bi bi-arrow-up-circle"></i>
                        Min. booking: ₱{{ number_format($promo->min_booking_amount) }}
                    </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="d-flex gap-2 pt-3 border-top">
                    <a href="{{ route('admin.promotions.edit', $promo) }}"
                       class="btn btn-primary btn-sm flex-grow-1">
                        <i class="bi bi-pencil"></i>Edit
                    </a>
                    <form method="POST" action="{{ route('admin.promotions.destroy', $promo) }}"
                          onsubmit="return confirm('Delete this promotion?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
    @endforeach
</div>

@if($promotions->hasPages())
<div class="mt-4">{{ $promotions->withQueryString()->links() }}</div>
@endif
@endif

@endsection
