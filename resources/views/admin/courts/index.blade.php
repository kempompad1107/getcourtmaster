@extends('layouts.app')
@section('title', 'Courts')

@push('styles')
<style>
    .court-card {
        height: 100%; border: 1px solid var(--bs-border-color); overflow: hidden;
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .court-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 16px 32px -22px rgba(0,0,0,.5);
    }

    .court-card.s-available   { --c:#22c55e; --crgb:34,197,94;   }
    .court-card.s-occupied    { --c:#fb7185; --crgb:251,113,133; }
    .court-card.s-reserved    { --c:#fbbf24; --crgb:251,191,36;  }
    .court-card.s-maintenance { --c:#fb923c; --crgb:251,146,60;  }
    .court-card.s-closed      { --c:#94a3b8; --crgb:148,163,184; }

    .court-card-header {
        padding: 1rem 1.25rem .85rem;
        background: rgba(var(--crgb),.07);
        border-bottom: 1px solid rgba(var(--crgb),.18);
        position: relative;
    }
    .court-card-header::before {
        content:''; position:absolute; top:0; left:0; right:0; height:3px;
        background: var(--c);
    }

    .court-status-dot {
        width:8px; height:8px; border-radius:50%; flex-shrink:0;
        background: var(--c); box-shadow: 0 0 6px var(--c);
    }
    .court-type-badge {
        font-size:.65rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase;
        padding:.2rem .55rem; border-radius:999px;
        background:rgba(var(--crgb),.12); color:var(--c);
        border:1px solid rgba(var(--crgb),.25);
    }
    .court-price { display:grid; grid-template-columns:1fr 1fr; gap:.5rem; }
    .court-price-chip {
        padding:.5rem .75rem; border-radius:.65rem;
        background:var(--bs-body-bg-alt, rgba(148,163,184,.06));
        border:1px solid var(--bs-border-color);
    }
    .court-price-chip .l { font-size:.6rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--bs-secondary-color); }
    .court-price-chip .v { font-weight:800; font-size:.95rem; line-height:1.3; }
    .court-amenity {
        font-size:.68rem; font-weight:500; padding:.2rem .55rem; border-radius:999px;
        background:rgba(148,163,184,.1); color:var(--bs-secondary-color);
        border:1px solid var(--bs-border-color);
    }
    .court-card .card-footer { background:transparent; border-top:1px solid var(--bs-border-color); padding:.65rem 1rem; }
</style>
@endpush

@section('content')

<x-page-header title="Courts"
    :subtitle="$courts->total() . ' ' . Str::plural('court', $courts->total()) . ' across ' . ($branchCount = auth()->user()->tenant->branches()->count()) . ' ' . Str::plural('branch', $branchCount)">
    <x-slot name="actions">
        <a href="{{ route('admin.courts.status-board') }}" class="btn btn-outline-secondary">
            <i class="bi bi-grid-3x3-gap me-1"></i>Status Board
        </a>
        @can('create', App\Models\Court::class)
        @php $courtLimit = app(\App\Services\PlanLimitGuard::class)->check(auth()->user()->tenant, 'courts'); @endphp
        @if($courtLimit['allowed'])
            <a href="{{ route('admin.courts.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Court
            </a>
        @else
            <button class="btn btn-primary" disabled
                    title="Plan limit reached ({{ $courtLimit['used'] }}/{{ $courtLimit['max'] }} on {{ $courtLimit['plan'] }})">
                <i class="bi bi-lock-fill me-1"></i>Add Court
            </button>
        @endif
        @endcan
    </x-slot>
</x-page-header>

@include('admin._partials.plan-limit-banner', ['resource' => 'courts'])

<x-filter-bar placeholder="Search courts..."
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

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
    @forelse($courts as $court)
    @php
        $statusClass = 's-' . (in_array($court->status, ['available','occupied','reserved','maintenance','closed']) ? $court->status : 'closed');
    @endphp
    <div class="col">
        <div class="card court-card {{ $statusClass }} d-flex flex-column">

            <div class="court-card-header">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <div class="min-w-0">
                        <h6 class="fw-bold mb-1 text-truncate" style="letter-spacing:-.01em">{{ $court->name }}</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="court-type-badge">{{ $court->type }}</span>
                            @if($court->branch)
                            <span class="text-muted" style="font-size:.75rem">
                                <i class="bi bi-shop" style="font-size:.7rem"></i>
                                {{ $court->branch->name }}
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1 flex-shrink-0 mt-1">
                        <span class="court-status-dot"></span>
                        <span class="fw-medium text-capitalize" style="font-size:.72rem;color:var(--c)">{{ $court->status }}</span>
                    </div>
                </div>
            </div>

            <div class="card-body d-flex flex-column gap-3 pt-3">
                <div class="court-price">
                    <div class="court-price-chip">
                        <div class="l">Base</div>
                        <div class="v">&#8369;{{ number_format($court->base_hourly_rate) }}<span class="fw-normal text-muted" style="font-size:.75rem">/hr</span></div>
                    </div>
                    <div class="court-price-chip">
                        <div class="l">Peak</div>
                        <div class="v">&#8369;{{ number_format($court->peak_hourly_rate) }}<span class="fw-normal text-muted" style="font-size:.75rem">/hr</span></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-1">
                    <span class="court-amenity"><i class="bi bi-people" style="font-size:.65rem"></i> {{ $court->capacity }}</span>
                    @foreach(array_slice($court->amenities ?? [], 0, 3) as $amenity)
                    <span class="court-amenity">{{ $amenity }}</span>
                    @endforeach
                    @if(count($court->amenities ?? []) > 3)
                    <span class="court-amenity">+{{ count($court->amenities) - 3 }}</span>
                    @endif
                </div>
            </div>

            <div class="card-footer d-flex align-items-center gap-2">
                <a href="{{ route('admin.courts.edit', $court) }}"
                   class="btn btn-primary flex-grow-1">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <a href="{{ route('admin.bookings.calendar', ['court' => $court->id]) }}"
                   class="btn btn-outline-secondary" title="Availability">
                    <i class="bi bi-calendar-check"></i>
                </a>
                @canany(['update','delete'], $court)
                <div class="dropdown">
                    <button class="btn btn-outline-secondary" data-bs-toggle="dropdown" title="More actions">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @can('update', $court)
                        @foreach(['available','maintenance','closed'] as $s)
                        <li>
                            <form method="POST" action="{{ route('admin.courts.status', $court) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $s }}">
                                <button class="dropdown-item">Set {{ ucfirst($s) }}</button>
                            </form>
                        </li>
                        @endforeach
                        @endcan
                        @can('delete', $court)
                        @can('update', $court)<li><hr class="dropdown-divider"></li>@endcan
                        <li>
                            <form method="POST" action="{{ route('admin.courts.destroy', $court) }}"
                                  onsubmit="return confirm('Delete {{ addslashes($court->name) }}? Existing bookings are kept, but the court is removed from listings.');">
                                @csrf @method('DELETE')
                                <button class="dropdown-item text-danger">
                                    <i class="bi bi-trash me-2"></i>Delete court
                                </button>
                            </form>
                        </li>
                        @endcan
                    </ul>
                </div>
                @endcanany
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
