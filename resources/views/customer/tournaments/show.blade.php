@extends('layouts.customer')

@section('title', $tournament->name)

@section('content')

<x-page-header :title="$tournament->name" :back="route('customer.tournaments.index')"/>

@if($tournament->cover_image && ($banner = file_url($tournament->cover_image)))
<div class="mb-4 rounded overflow-hidden">
    <img src="{{ $banner }}" alt="{{ $tournament->name }} banner" class="w-100" style="max-height:220px;object-fit:cover;">
</div>
@endif

<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    @include('admin.tournaments._status-badge', ['status' => $tournament->status])
    @if($tournament->starts_at)
    <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i>{{ $tournament->starts_at->format('M j, Y') }}@if($tournament->ends_at && !$tournament->ends_at->isSameDay($tournament->starts_at)) – {{ $tournament->ends_at->format('M j, Y') }}@endif</span>
    @endif
    @if($tournament->venue)
    <span class="small text-muted">
        <i class="bi bi-geo-alt me-1"></i>{{ $tournament->venue }}
        @if($tournament->google_maps_url)
        <a href="{{ $tournament->google_maps_url }}" target="_blank" rel="noopener">(map)</a>
        @endif
    </span>
    @endif
    @if($registrationOpen && $tournament->registration_closes_at)
    <span class="small text-muted"><i class="bi bi-hourglass-split me-1"></i>Register by {{ $tournament->registration_closes_at->format('M j, g:i A') }}</span>
    @endif
</div>

<div class="row g-4">
    <div class="col-12 col-lg-7">
        @if($myTeams->isNotEmpty())
        <x-card title="My Registrations" class="mb-4" flush>
            <div class="list-group list-group-flush">
                @foreach($myTeams as $team)
                <div class="list-group-item">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="min-w-0">
                            <p class="mb-0 small fw-semibold">{{ $team->division->name }} — {{ $team->name }}</p>
                            <small class="text-muted">{{ $team->members->map(fn ($m) => $m->user->name)->implode(' / ') }}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <x-badge :status="$team->status === 'confirmed' ? 'confirmed' : 'pending'">{{ ucfirst($team->status) }}</x-badge>
                            @if(in_array($tournament->status, ['registration_open', 'registration_closed'], true))
                            <form method="POST" action="{{ route('customer.tournaments.withdraw', $team) }}"
                                  onsubmit="return confirm('Withdraw from {{ $team->division->name }}? Wallet payments are refunded automatically.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Withdraw</button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </x-card>
        @endif

        <x-card title="Divisions" flush>
            <div class="list-group list-group-flush">
                @forelse($tournament->divisions as $division)
                @php
                $fee = $tournament->effectiveEntryFee($division);
                $isFull = $division->max_entries !== null && $division->teams_count >= $division->max_entries;
                $alreadyIn = $myDivisionIds->contains($division->id);
                @endphp
                <div class="list-group-item">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="min-w-0">
                            <p class="mb-0 small fw-semibold">{{ $division->name }}</p>
                            <small class="text-muted">
                                {{ App\Models\TournamentDivision::GENDERS[$division->gender] ?? ucfirst($division->gender) }}
                                · {{ $division->isSingles() ? 'Singles' : 'Doubles' }}
                                @if($division->skill_level) · {{ $division->skill_level }} @endif
                                @if($division->min_age || $division->max_age) · Age {{ $division->min_age ?? '0' }}–{{ $division->max_age ?? '∞' }} @endif
                                · {{ $division->teams_count }}{{ $division->max_entries ? '/' . $division->max_entries : '' }} teams
                                · {{ $fee > 0 ? $tournament->currency . ' ' . number_format($fee, 2) . '/player' : 'Free' }}
                            </small>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            @if($division->bracketLocked())
                            <a href="{{ route('customer.tournaments.bracket', [$tournament->slug, $division]) }}" class="btn btn-outline-secondary btn-sm">Bracket</a>
                            @endif
                            @if($alreadyIn)
                            <x-badge status="confirmed">Registered</x-badge>
                            @elseif($isFull)
                            <x-badge status="expired">Full</x-badge>
                            @elseif($registrationOpen && !$division->bracketLocked())
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerModal-{{ $division->id }}">Register</button>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="list-group-item">
                    <p class="small text-muted mb-0">Divisions will be announced soon.</p>
                </div>
                @endforelse
            </div>
        </x-card>
    </div>

    <div class="col-12 col-lg-5">
        @if($tournament->description)
        <x-card title="About" class="mb-4">
            <p class="small mb-0" style="white-space:pre-line;">{{ $tournament->description }}</p>
            @if($tournament->organizer_name || $tournament->contact_phone || $tournament->contact_email)
            <hr>
            <p class="small text-muted mb-0">
                @if($tournament->organizer_name)Organized by <strong>{{ $tournament->organizer_name }}</strong><br>@endif
                @if($tournament->contact_phone)<i class="bi bi-telephone me-1"></i>{{ $tournament->contact_phone }}<br>@endif
                @if($tournament->contact_email)<i class="bi bi-envelope me-1"></i>{{ $tournament->contact_email }}@endif
            </p>
            @endif
        </x-card>
        @endif
        @if($tournament->rules)
        <x-card title="Rules" class="mb-4">
            <div class="small text-muted" style="white-space:pre-line;max-height:240px;overflow-y:auto;">{{ $tournament->rules }}</div>
        </x-card>
        @endif
        @if($tournament->waiver)
        <x-card title="Waiver">
            <div class="small text-muted" style="white-space:pre-line;max-height:200px;overflow-y:auto;">{{ $tournament->waiver }}</div>
        </x-card>
        @endif
    </div>
</div>

@if($registrationOpen)
@push('modals')
@foreach($tournament->divisions as $division)
@if(!$myDivisionIds->contains($division->id) && !$division->bracketLocked() && !($division->max_entries !== null && $division->teams_count >= $division->max_entries))
@include('customer.tournaments._register-modal', ['tournament' => $tournament, 'division' => $division])
@endif
@endforeach
@endpush
@endif

@endsection
