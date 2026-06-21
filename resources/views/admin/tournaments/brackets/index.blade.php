@extends('layouts.app')
@section('title', 'Brackets')

@section('content')

@php
    $activeFilters = (int) request()->filled('tournament_id');
@endphp

<form method="GET" action="{{ route('admin.tournaments.brackets.index') }}" x-data="{ open: false }">
<x-page-header title="Brackets" subtitle="Pick a division to view or generate its bracket.">
    <x-slot name="actions">
        <div class="position-relative" @click.outside="open = false">
            <button type="button" @click="open = !open"
                    class="btn {{ $activeFilters ? 'btn-primary' : 'btn-outline-secondary' }} position-relative d-flex align-items-center gap-2">
                <i class="bi bi-sliders2"></i>
                <span class="d-none d-md-inline fw-medium" style="font-size:.875rem">Filters</span>
                @if($activeFilters)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          style="font-size:.55rem">{{ $activeFilters }}</span>
                @endif
            </button>
            <div x-show="open" x-cloak
                 class="position-absolute end-0 mt-1 p-3 rounded-3 shadow-lg border bg-body z-3"
                 style="min-width:220px">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <label class="form-label small fw-semibold mb-1">Tournament</label>
                        <select name="tournament_id" class="form-select form-select-sm">
                            <option value="">All tournaments</option>
                            @foreach($tournaments as $t)
                                <option value="{{ $t->id }}" @selected((int) request('tournament_id') === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                        @if($activeFilters)
                            <a href="{{ route('admin.tournaments.brackets.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
</x-page-header>
</form>

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
                        <a href="{{ route('admin.tournaments.brackets.show', $division) }}" class="btn btn-primary btn-sm">
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
    <div class="px-4 py-3 border-top">
        {{ $divisions->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
