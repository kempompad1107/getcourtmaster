@extends('layouts.app')
@section('title', 'Courts')

@push('styles')
<style>
    /* ── Courts — refined card grid over the admin theme ── */
    .court-card {
        height: 100%; border: 1px solid var(--bs-border-color);
        transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
    }
    .court-card:hover {
        transform: translateY(-3px);
        border-color: rgba(16,185,129,.35);
        box-shadow: 0 16px 32px -22px rgba(0,0,0,.7);
    }
    .court-card.s-available   { --c: #34d399; --crgb: 52,211,153; }
    .court-card.s-occupied    { --c: #fb7185; --crgb: 251,113,133; }
    .court-card.s-reserved    { --c: #fbbf24; --crgb: 251,191,36; }
    .court-card.s-maintenance { --c: #fb923c; --crgb: 251,146,60; }
    .court-card.s-closed      { --c: #94a3b8; --crgb: 148,163,184; }

    .court-media { position: relative; height: 168px; overflow: hidden; }
    .court-media img { width: 100%; height: 100%; object-fit: cover; }
    .court-media-ph { width: 100%; height: 100%; display: grid; place-items: center; background: rgba(var(--crgb), .1); }
    .court-media-ph i { font-size: 2.8rem; color: var(--c); opacity: .45; }
    .court-status-pill {
        position: absolute; top: .7rem; right: .7rem; z-index: 2;
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .3rem .75rem; border-radius: 999px;
        font-size: .7rem; font-weight: 600; text-transform: capitalize; color: #fff;
        background: rgba(15,23,42,.72); backdrop-filter: blur(6px);
        border: 1px solid rgba(255,255,255,.14);
    }
    .court-status-pill .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--c); box-shadow: 0 0 8px var(--c); }

    .court-price { display: flex; gap: .5rem; }
    .court-price-chip {
        flex: 1; padding: .5rem .7rem; border-radius: .7rem;
        background: var(--bs-body-bg-alt, rgba(148,163,184,.06)); border: 1px solid var(--bs-border-color);
    }
    .court-price-chip .l { font-size: .62rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--bs-secondary-color); }
    .court-price-chip .v { font-weight: 700; font-size: .95rem; }
    .court-amenity {
        font-size: .7rem; font-weight: 500; padding: .2rem .6rem; border-radius: 999px;
        background: rgba(148,163,184,.12); color: var(--bs-secondary-color); border: 1px solid var(--bs-border-color);
    }
    .court-card .card-footer { background: transparent; border-top: 1px solid var(--bs-border-color); }
</style>
@endpush

@section('content')

<x-page-header title="Courts"
    :subtitle="$courts->total() . ' courts across ' . auth()->user()->tenant->branches()->count() . ' branches'">
    <x-slot name="actions">
        <a href="{{ route('admin.courts.status-board') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-grid-3x3-gap me-1"></i>Status Board
        </a>
        @can('create', App\Models\Court::class)
        @php $courtLimit = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, 'courts'); @endphp
        @if($courtLimit['allowed'])
            <a href="{{ route('admin.courts.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Add Court
            </a>
        @else
            <button class="btn btn-primary btn-sm" disabled title="Plan limit reached ({{ $courtLimit['used'] }}/{{ $courtLimit['max'] }} on {{ $courtLimit['plan'] }})">
                <i class="bi bi-lock-fill me-1"></i>Add Court
            </button>
        @endif
        @endcan
    </x-slot>
</x-page-header>

@include('admin._partials.plan-limit-banner', ['resource' => 'courts'])

{{-- Unified filter bar: search + Filters popover (status, type) --}}
<x-filter-bar placeholder="Search courts…"
              :active-count="(int) request()->filled('status') + (int) request()->filled('type')"
              :clear="route('admin.courts.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach(['available','occupied','reserved','maintenance','closed'] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Type</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">All types</option>
                <option value="indoor"  @selected(request('type') === 'indoor')>Indoor</option>
                <option value="outdoor" @selected(request('type') === 'outdoor')>Outdoor</option>
            </select>
        </div>
    </x-slot>
</x-filter-bar>

{{-- Courts grid --}}
<div class="row g-4">
    @forelse($courts as $court)
    @php
    $statusClass = 's-' . (in_array($court->status, ['available','occupied','reserved','maintenance','closed']) ? $court->status : 'closed');
    @endphp
    <div class="col-12 col-sm-6 col-lg-4">
        <div class="card court-card {{ $statusClass }} overflow-hidden d-flex flex-column">
            <div class="court-media">
                @if($court->hasMedia('photos'))
                    <img src="{{ $court->getFirstMediaUrl('photos') }}" alt="{{ $court->name }}">
                @else
                    <div class="court-media-ph"><i class="bi bi-grid-3x3-gap"></i></div>
                @endif
                <span class="court-status-pill"><span class="dot"></span>{{ $court->status }}</span>
            </div>

            <div class="card-body d-flex flex-column">
                <div class="mb-3">
                    <h6 class="fw-semibold mb-0 text-truncate">{{ $court->name }}</h6>
                    <small class="text-muted">{{ ucfirst($court->type) }} &bull; Capacity: {{ $court->capacity }}</small>
                </div>

                <div class="court-price mb-3">
                    <div class="court-price-chip">
                        <div class="l">Base</div>
                        <div class="v">₱{{ number_format($court->base_hourly_rate) }}<span class="fw-normal text-muted small">/hr</span></div>
                    </div>
                    <div class="court-price-chip">
                        <div class="l">Peak</div>
                        <div class="v">₱{{ number_format($court->peak_hourly_rate) }}<span class="fw-normal text-muted small">/hr</span></div>
                    </div>
                </div>

                @if($court->amenities)
                <div class="d-flex flex-wrap gap-1">
                    @foreach(array_slice($court->amenities, 0, 3) as $amenity)
                    <span class="court-amenity">{{ $amenity }}</span>
                    @endforeach
                    @if(count($court->amenities) > 3)
                    <span class="court-amenity">+{{ count($court->amenities) - 3 }}</span>
                    @endif
                </div>
                @endif
            </div>

            <div class="card-footer d-flex gap-2">
                <a href="{{ route('admin.courts.edit', $court) }}"
                   class="btn btn-outline-secondary btn-sm flex-fill"><i class="bi bi-pencil me-1"></i>Edit</a>
                <a href="{{ route('admin.courts.availability', $court) }}"
                   class="btn btn-outline-primary btn-sm flex-fill"><i class="bi bi-calendar-check me-1"></i>Availability</a>
                @can('update', $court)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="dropdown" title="Set status">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach(['available','maintenance','closed'] as $s)
                        <li>
                            <form method="POST" action="{{ route('admin.courts.status', $court) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $s }}">
                                <button class="dropdown-item">Set {{ ucfirst($s) }}</button>
                            </form>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endcan
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <x-empty-state
            title="No courts found"
            description="Get started by adding your first court."
            icon="bi-grid-3x3-gap"
            @can('create', App\Models\Court::class)
            action="{{ route('admin.courts.create') }}"
            actionLabel="Add Court"
            @endcan/>
    </div>
    @endforelse
</div>

@if($courts->hasPages())
<div class="d-flex justify-content-center mt-4">
    {{ $courts->links() }}
</div>
@endif

@endsection
