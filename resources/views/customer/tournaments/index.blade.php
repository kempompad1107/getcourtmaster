@extends('layouts.customer')

@section('title', 'Tournaments')

@section('content')

<x-page-header title="Tournaments" subtitle="Upcoming and ongoing events at your club."/>

@if($myTeams->isNotEmpty())
<x-card title="My Registrations" class="mb-4" flush>
    <div class="list-group list-group-flush">
        @foreach($myTeams as $team)
        <a href="{{ route('customer.tournaments.show', $team->tournament->slug) }}" class="list-group-item list-group-item-action">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="min-w-0">
                    <p class="mb-0 small fw-semibold text-truncate">{{ $team->tournament->name }} — {{ $team->division->name }}</p>
                    <small class="text-muted">
                        {{ $team->members->map(fn ($m) => $m->user->name)->implode(' / ') }}
                        · {{ $team->tournament->starts_at?->format('M j, Y') ?? 'Date TBA' }}
                    </small>
                </div>
                <x-badge :status="$team->status === 'confirmed' ? 'confirmed' : 'pending'">{{ ucfirst($team->status) }}</x-badge>
            </div>
        </a>
        @endforeach
    </div>
</x-card>
@endif

@if($tournaments->isEmpty())
<x-empty-state title="No tournaments right now"
    description="Check back soon — new events will appear here when registration opens."
    icon="bi-trophy"/>
@else
<div class="row g-4">
    @foreach($tournaments as $tournament)
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100 overflow-hidden">
            @if($tournament->cover_image && ($banner = file_url($tournament->cover_image)))
            <img src="{{ $banner }}" alt="" class="w-100" style="height:140px;object-fit:cover;">
            @else
            <div class="w-100 d-flex align-items-center justify-content-center bg-body-secondary" style="height:140px;">
                <i class="bi bi-trophy fs-1 text-muted"></i>
            </div>
            @endif
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                    <h6 class="fw-semibold mb-0">{{ $tournament->name }}</h6>
                    @include('admin.tournaments._status-badge', ['status' => $tournament->status])
                </div>
                <p class="small text-muted mb-2">{{ Str::limit($tournament->description, 90) }}</p>
                <div class="small text-muted mb-3">
                    @if($tournament->starts_at)
                    <div><i class="bi bi-calendar3 me-1"></i>{{ $tournament->starts_at->format('M j, Y') }}@if($tournament->ends_at && !$tournament->ends_at->isSameDay($tournament->starts_at)) – {{ $tournament->ends_at->format('M j, Y') }}@endif</div>
                    @endif
                    @if($tournament->venue)
                    <div><i class="bi bi-geo-alt me-1"></i>{{ $tournament->venue }}</div>
                    @endif
                    @if($tournament->status === 'registration_open' && $tournament->registration_closes_at)
                    <div><i class="bi bi-hourglass-split me-1"></i>Register by {{ $tournament->registration_closes_at->format('M j, g:i A') }}</div>
                    @endif
                    <div><i class="bi bi-people me-1"></i>{{ $tournament->teams_count }} team{{ $tournament->teams_count === 1 ? '' : 's' }} registered</div>
                </div>
                <a href="{{ route('customer.tournaments.show', $tournament->slug) }}" class="btn btn-outline-primary btn-sm mt-auto">
                    {{ $tournament->status === 'registration_open' ? 'View & Register' : 'View Details' }}
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endsection
