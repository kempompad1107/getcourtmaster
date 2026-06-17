@extends('layouts.app')
@section('title', $tournament->name)

@section('content')

<x-page-header :title="$tournament->name" :back="route('admin.tournaments.index')">
    <x-slot name="actions">
        @can('update', $tournament)
        @if($tournament->status === 'draft')
        <form method="POST" action="{{ route('admin.tournaments.publish', $tournament) }}" class="d-inline"
              onsubmit="return confirm('Open registration for {{ $tournament->name }}?');">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-megaphone me-1"></i>Publish</button>
        </form>
        @endif
        <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach(App\Models\Tournament::STATUS_TRANSITIONS[$tournament->status] ?? [] as $next)
                <li>
                    <form method="POST" action="{{ route('admin.tournaments.status', $tournament) }}"
                          onsubmit="return confirm('Set status to {{ str_replace('_', ' ', $next) }}?');">
                        @csrf
                        <input type="hidden" name="status" value="{{ $next }}">
                        <button type="submit" class="dropdown-item">
                            Mark as {{ ucwords(str_replace('_', ' ', $next)) }}
                        </button>
                    </form>
                </li>
                @endforeach
                <li><hr class="dropdown-divider"></li>
                @can('duplicate', $tournament)
                <li>
                    <form method="POST" action="{{ route('admin.tournaments.duplicate', $tournament) }}">
                        @csrf
                        <button type="submit" class="dropdown-item">Duplicate</button>
                    </form>
                </li>
                @endcan
                @can('archive', $tournament)
                <li>
                    <form method="POST" action="{{ route('admin.tournaments.archive', $tournament) }}">
                        @csrf
                        <button type="submit" class="dropdown-item">
                            {{ $tournament->archived_at ? 'Restore from archive' : 'Archive' }}
                        </button>
                    </form>
                </li>
                @endcan
                @can('delete', $tournament)
                <li>
                    <form method="POST" action="{{ route('admin.tournaments.destroy', $tournament) }}"
                          onsubmit="return confirm('Delete {{ $tournament->name }}? This cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">Delete</button>
                    </form>
                </li>
                @endcan
            </ul>
        </div>
        @endcan
    </x-slot>
</x-page-header>

@if($tournament->cover_image && ($banner = file_url($tournament->cover_image)))
<div class="mb-4 rounded overflow-hidden position-relative" style="max-height:200px;">
    <img src="{{ $banner }}" alt="{{ $tournament->name }} banner" class="w-100" style="object-fit:cover;max-height:200px;">
</div>
@endif

<div class="d-flex flex-wrap align-items-center gap-2 mb-4">
    @include('admin.tournaments._status-badge', ['status' => $tournament->status])
    <x-badge color="{{ $tournament->visibility === 'public' ? 'blue' : 'gray' }}">{{ ucfirst($tournament->visibility) }}</x-badge>
    @if($tournament->archived_at)
    <x-badge status="expired">Archived</x-badge>
    @endif
    @if($tournament->starts_at)
    <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i>{{ $tournament->starts_at->format('M j, Y') }}@if($tournament->ends_at && !$tournament->ends_at->isSameDay($tournament->starts_at)) – {{ $tournament->ends_at->format('M j, Y') }}@endif</span>
    @endif
    @if($tournament->venue)
    <span class="small text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $tournament->venue }}</span>
    @endif
</div>

<div class="kpi-grid mb-4" style="--kpi-cols:5">
    <x-stat-card label="Divisions" :value="$stats['divisions']" icon="bi-diagram-3" color="purple" small/>
    <x-stat-card label="Teams" :value="$stats['teams']" icon="bi-people" color="blue" small/>
    <x-stat-card label="Players" :value="$stats['players']" icon="bi-person-badge" color="emerald" small/>
    <x-stat-card label="Matches Played" :value="$stats['matches_done'] . ' / ' . $stats['matches_total']" icon="bi-controller" color="amber" small/>
    <x-stat-card label="Fees Collected" :value="$tournament->currency . ' ' . number_format($stats['fees_collected'], 2)" icon="bi-cash-stack" color="emerald" small/>
</div>

<div x-data="{ tab: new URLSearchParams(location.search).get('tab') || 'overview' }">
    <ul class="nav nav-pills flex-nowrap overflow-auto no-scrollbar gap-1 mb-3">
        @foreach(['overview' => 'Overview', 'divisions' => 'Divisions', 'teams' => 'Teams', 'brackets' => 'Brackets', 'settings' => 'Settings'] as $key => $label)
        <li class="nav-item">
            <button type="button" class="nav-link py-1 px-3"
                    :class="tab === '{{ $key }}' ? 'active' : ''"
                    @click="tab = '{{ $key }}'; history.replaceState(null, '', '?tab={{ $key }}')">{{ $label }}</button>
        </li>
        @endforeach
        <li class="nav-item ms-auto d-none d-md-block">
            <a href="{{ route('admin.tournaments.matches.index', ['tournament_id' => $tournament->id]) }}" class="nav-link py-1 px-3">
                Matches <i class="bi bi-arrow-right-short"></i>
            </a>
        </li>
    </ul>

    <div x-show="tab === 'overview'" x-cloak>
        @include('admin.tournaments._tabs.overview')
    </div>
    <div x-show="tab === 'divisions'" x-cloak>
        @include('admin.tournaments._tabs.divisions')
    </div>
    <div x-show="tab === 'teams'" x-cloak>
        @include('admin.tournaments._tabs.teams')
    </div>
    <div x-show="tab === 'brackets'" x-cloak>
        @include('admin.tournaments._tabs.brackets')
    </div>
    <div x-show="tab === 'settings'" x-cloak>
        @include('admin.tournaments._tabs.settings')
    </div>
</div>

@endsection
