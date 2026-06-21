@extends('layouts.customer')

@section('title', 'Tournaments')

@push('styles')
<style>
    /* Status tab bar */
    .trn-tab-bar { display: flex; gap: .25rem; flex-wrap: wrap; }
    .trn-tab {
        padding: .35rem .9rem; border-radius: .5rem; font-size: .82rem; font-weight: 600;
        border: 1px solid var(--bs-border-color); background: transparent;
        color: var(--bs-secondary-color); text-decoration: none; transition: all .15s;
    }
    .trn-tab:hover { background: var(--bs-secondary-bg); color: var(--bs-body-color); }
    .trn-tab.active { background: #10b981; border-color: #10b981; color: #fff; }

    /* Tournament card banner placeholder */
    .trn-banner-placeholder {
        height: 140px;
        background: linear-gradient(135deg, rgba(16,185,129,.15) 0%, rgba(16,185,129,.05) 100%);
        display: flex; align-items: center; justify-content: center;
        border-bottom: 1px solid var(--bs-border-color);
    }

    /* My registrations row icon */
    .trn-reg-ico {
        width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
        display: grid; place-items: center; font-size: .95rem;
        background: rgba(16,185,129,.1); color: #10b981;
        border: 1px solid rgba(16,185,129,.2);
    }

    /* Fee chip on card */
    .trn-fee {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .7rem; font-weight: 700; letter-spacing: .02em;
        padding: .2rem .55rem; border-radius: 99px;
        background: var(--bs-secondary-bg); color: var(--bs-secondary-color);
        border: 1px solid var(--bs-border-color);
    }
    .trn-fee.free { background: rgba(16,185,129,.1); color: #10b981; border-color: rgba(16,185,129,.25); }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0">Tournaments</h4>
        <p class="text-muted small mb-0">Upcoming and ongoing events at your club.</p>
    </div>
</div>

{{-- My Registrations --}}
@if($myTeams->isNotEmpty())
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">My Registrations</h6>
    </div>
    <div class="list-group list-group-flush">
        @foreach($myTeams as $team)
        <a href="{{ route('customer.tournaments.show', $team->tournament->slug) }}"
           class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
            <div class="trn-reg-ico">
                <i class="bi bi-trophy"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="small fw-semibold text-truncate">
                    {{ $team->tournament->name }} — {{ $team->division->name }}
                </div>
                <div class="small text-muted">
                    {{ $team->members->map(fn ($m) => $m->user->name)->implode(' / ') }}
                    · {{ $team->tournament->starts_at?->format('M j, Y') ?? 'Date TBA' }}
                </div>
            </div>
            <x-badge :status="$team->status === 'confirmed' ? 'confirmed' : 'pending'">
                {{ ucfirst($team->status) }}
            </x-badge>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- Status tabs --}}
<div class="trn-tab-bar mb-3">
    @foreach(['all' => 'All', 'open' => 'Registration Open', 'ongoing' => 'Ongoing', 'past' => 'Completed'] as $key => $label)
        <a href="{{ route('customer.tournaments.index', ['tab' => $key]) }}"
           class="trn-tab {{ $tab === $key ? 'active' : '' }}">
            {{ $label }}
        </a>
    @endforeach
</div>

{{-- Tournament grid --}}
@if($tournaments->isEmpty())
    <x-empty-state
        title="{{ $tab === 'open' ? 'No open tournaments' : ($tab === 'ongoing' ? 'No ongoing tournaments' : ($tab === 'past' ? 'No completed tournaments' : 'No tournaments right now')) }}"
        description="Check back soon — new events will appear here when registration opens."
        icon="bi-trophy"/>
@else
<div class="row g-4">
    @foreach($tournaments as $tournament)
    @php
        $fee = (float) ($tournament->entry_fee ?? 0);
        $isFree = $fee <= 0;
    @endphp
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100 overflow-hidden">
            {{-- Banner --}}
            @if($tournament->cover_image && ($banner = file_url($tournament->cover_image)))
                <img src="{{ $banner }}" alt="" class="w-100" style="height:140px;object-fit:cover;">
            @else
                <div class="trn-banner-placeholder">
                    <i class="bi bi-trophy fs-1" style="color:rgba(16,185,129,.4)"></i>
                </div>
            @endif

            <div class="card-body d-flex flex-column gap-2">
                {{-- Title + status --}}
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <h6 class="fw-semibold mb-0 lh-sm">{{ $tournament->name }}</h6>
                    @include('admin.tournaments._status-badge', ['status' => $tournament->status])
                </div>

                {{-- Description --}}
                @if($tournament->description)
                <p class="small text-muted mb-0">{{ Str::limit($tournament->description, 90) }}</p>
                @endif

                {{-- Meta info --}}
                <div class="small text-muted d-flex flex-column gap-1">
                    @if($tournament->starts_at)
                    <div>
                        <i class="bi bi-calendar3 me-1 opacity-60"></i>
                        {{ $tournament->starts_at->format('M j, Y') }}
                        @if($tournament->ends_at && !$tournament->ends_at->isSameDay($tournament->starts_at))
                            – {{ $tournament->ends_at->format('M j, Y') }}
                        @endif
                    </div>
                    @endif
                    @if($tournament->venue)
                    <div><i class="bi bi-geo-alt me-1 opacity-60"></i>{{ $tournament->venue }}</div>
                    @endif
                    @if($tournament->status === 'registration_open' && $tournament->registration_closes_at)
                    <div class="text-danger">
                        <i class="bi bi-hourglass-split me-1"></i>Register by {{ $tournament->registration_closes_at->format('M j, g:i A') }}
                    </div>
                    @endif
                </div>

                {{-- Footer: teams count + fee + button --}}
                <div class="d-flex align-items-center justify-content-between gap-2 mt-auto pt-1">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small text-muted">
                            <i class="bi bi-people me-1"></i>{{ $tournament->teams_count }} {{ Str::plural('team', $tournament->teams_count) }}
                        </span>
                        @if($isFree)
                            <span class="trn-fee free"><i class="bi bi-check-circle"></i>Free</span>
                        @else
                            <span class="trn-fee">From ₱{{ number_format($fee, 0) }}</span>
                        @endif
                    </div>
                    <a href="{{ route('customer.tournaments.show', $tournament->slug) }}"
                       class="btn btn-primary btn-sm flex-shrink-0">
                        {{ $tournament->status === 'registration_open' ? 'Register' : 'View' }}
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
