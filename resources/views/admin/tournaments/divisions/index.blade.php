@extends('layouts.app')
@section('title', 'Tournament Divisions')

@section('content')

<x-page-header title="Divisions" subtitle="All divisions across your tournaments. Divisions are managed inside each tournament.">
</x-page-header>

<x-filter-bar placeholder="Search division name…"
              :active-count="(int) request()->filled('tournament_id')"
              :clear="route('admin.tournaments.divisions.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Tournament</label>
            <select name="tournament_id" class="form-select form-select-sm">
                <option value="">All tournaments</option>
                @foreach($tournaments as $t)
                <option value="{{ $t->id }}" @selected((int) request('tournament_id') === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
    </x-slot>
</x-filter-bar>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>Division</th>
                    <th>Tournament</th>
                    <th class="text-center">Type</th>
                    <th class="text-center">Format</th>
                    <th class="text-center">Entries</th>
                    <th>Bracket</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($divisions as $division)
                <tr>
                    <td data-label="Division">
                        <p class="mb-0 small fw-semibold">{{ $division->name }}</p>
                        <small class="text-muted">{{ $division->skill_level ?: 'Any skill' }}</small>
                    </td>
                    <td data-label="Tournament">
                        <a href="{{ route('admin.tournaments.show', [$division->tournament_id, 'tab' => 'divisions']) }}" class="small">{{ $division->tournament->name }}</a>
                    </td>
                    <td data-label="Type" class="text-center">
                        <span class="small">{{ App\Models\TournamentDivision::GENDERS[$division->gender] ?? ucfirst($division->gender) }} {{ $division->isSingles() ? 'Singles' : 'Doubles' }}</span>
                    </td>
                    <td data-label="Format" class="text-center"><span class="small">{{ $division->formatLabel() }}</span></td>
                    <td data-label="Entries" class="text-center">
                        <span class="small fw-medium">{{ $division->teams_count }}{{ $division->max_entries ? ' / ' . $division->max_entries : '' }}</span>
                    </td>
                    <td data-label="Bracket">
                        @if($division->bracketLocked())
                        <x-badge status="active">Generated</x-badge>
                        @else
                        <x-badge status="neutral">Not generated</x-badge>
                        @endif
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.tournaments.brackets.show', $division) }}" class="btn btn-outline-primary btn-sm">Bracket</a>
                        <a href="{{ route('admin.tournaments.show', [$division->tournament_id, 'tab' => 'divisions']) }}" class="btn btn-outline-secondary btn-sm">Manage</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="cell-plain">
                        <x-empty-state title="No divisions yet"
                            description="Open a tournament and add divisions from its Divisions tab."
                            icon="bi-diagram-3"
                            :action="route('admin.tournaments.index')"
                            actionLabel="Go to Tournaments"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($divisions->hasPages())
    <div class="card-footer">
        {{ $divisions->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
