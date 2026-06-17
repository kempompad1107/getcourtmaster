@extends('layouts.app')
@section('title', 'Tournaments')

@section('content')

<x-page-header title="Tournaments" subtitle="Create and run events from registration to champions.">
    <x-slot name="actions">
        @can('create', App\Models\Tournament::class)
        <a href="{{ route('admin.tournaments.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Tournament
        </a>
        @endcan
    </x-slot>
</x-page-header>

<x-filter-bar placeholder="Search name, venue, organizer…"
              :active-count="(int) request()->filled('status') + (int) request()->filled('visibility') + (int) request()->boolean('archived')"
              :clear="route('admin.tournaments.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All statuses</option>
                @foreach(App\Models\Tournament::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Visibility</label>
            <select name="visibility" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="public" @selected(request('visibility') === 'public')>Public</option>
                <option value="private" @selected(request('visibility') === 'private')>Private</option>
            </select>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="archived" id="filter-archived" value="1" @checked(request()->boolean('archived'))>
            <label class="form-check-label small fw-semibold" for="filter-archived">Show archived only</label>
        </div>
    </x-slot>
</x-filter-bar>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>Tournament</th>
                    <th>Venue</th>
                    <th>Dates</th>
                    <th>Registration Closes</th>
                    <th class="text-center">Divisions</th>
                    <th class="text-center">Teams</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tournaments as $tournament)
                <tr>
                    <td data-label="Tournament">
                        <div class="d-flex align-items-center gap-2">
                            @if($tournament->cover_image && ($banner = file_url($tournament->cover_image)))
                            <img src="{{ $banner }}" alt="" class="rounded flex-shrink-0" style="width:48px;height:32px;object-fit:cover;">
                            @else
                            <div class="rounded bg-body-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:32px;">
                                <i class="bi bi-trophy text-muted small"></i>
                            </div>
                            @endif
                            <div class="min-w-0">
                                <p class="mb-0 small fw-semibold text-truncate">{{ $tournament->name }}</p>
                                <small class="text-muted d-block text-truncate">{{ Str::limit($tournament->description, 60) ?: ucfirst($tournament->visibility) }}</small>
                            </div>
                        </div>
                    </td>
                    <td data-label="Venue">
                        <span class="small">{{ $tournament->venue ?: '—' }}</span>
                        @if($tournament->address)
                        <small class="text-muted d-block text-truncate" style="max-width:180px;">{{ $tournament->address }}</small>
                        @endif
                    </td>
                    <td data-label="Dates">
                        <span class="small">
                            @if($tournament->starts_at)
                                {{ $tournament->starts_at->format('M j, Y') }}
                                @if($tournament->ends_at && !$tournament->ends_at->isSameDay($tournament->starts_at))
                                    – {{ $tournament->ends_at->format('M j, Y') }}
                                @endif
                            @else
                                —
                            @endif
                        </span>
                    </td>
                    <td data-label="Reg. Closes">
                        <span class="small">{{ $tournament->registration_closes_at?->format('M j, Y g:i A') ?? '—' }}</span>
                    </td>
                    <td data-label="Divisions" class="text-center">
                        <span class="small fw-medium">{{ $tournament->divisions_count }}</span>
                    </td>
                    <td data-label="Teams" class="text-center">
                        <span class="small fw-medium">{{ $tournament->teams_count }}</span>
                    </td>
                    <td data-label="Status">
                        <div class="d-flex flex-column align-items-start gap-1">
                            @include('admin.tournaments._status-badge', ['status' => $tournament->status])
                            @if($tournament->archived_at)
                            <x-badge status="expired">Archived</x-badge>
                            @endif
                        </div>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.tournaments.show', $tournament) }}" class="btn btn-outline-primary btn-sm">View</a>
                        @can('update', $tournament)
                        <a href="{{ route('admin.tournaments.edit', $tournament) }}" class="btn btn-outline-secondary btn-sm">Edit</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="cell-plain">
                        <x-empty-state title="No tournaments found"
                            description="Create your first tournament to start taking registrations."
                            icon="bi-trophy"
                            :action="route('admin.tournaments.create')"
                            actionLabel="New Tournament"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tournaments->hasPages())
    <div class="card-footer">
        {{ $tournaments->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
