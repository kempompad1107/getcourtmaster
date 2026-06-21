@extends('layouts.app')
@section('title', 'Brackets')

@section('content')

<x-page-header title="Brackets" subtitle="Pick a division to view or generate its bracket."/>

<x-filter-bar :searchable="false"
              :active-count="(int) request()->filled('tournament_id')"
              :clear="route('admin.tournaments.brackets.index')">
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
                    <th class="text-center">Teams</th>
                    <th class="text-center">Format</th>
                    <th>Bracket</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($divisions as $division)
                <tr>
                    <td data-label="Division"><span class="small fw-semibold">{{ $division->name }}</span></td>
                    <td data-label="Tournament"><span class="small">{{ $division->tournament->name }}</span></td>
                    <td data-label="Teams" class="text-center"><span class="small fw-medium">{{ $division->teams_count }}</span></td>
                    <td data-label="Format" class="text-center"><span class="small">{{ $division->formatLabel() }}</span></td>
                    <td data-label="Bracket">
                        @if($division->bracketLocked())
                        <x-badge status="active">Generated</x-badge>
                        @else
                        <x-badge status="neutral">Not generated</x-badge>
                        @endif
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.tournaments.brackets.show', $division) }}" class="btn btn-outline-primary btn-sm">
                            {{ $division->bracketLocked() ? 'View Bracket' : 'Set Up' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="6" class="cell-plain">
                        <x-empty-state title="No divisions yet"
                            description="Create a tournament with divisions first, then generate brackets here."
                            icon="bi-bezier2"
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
