@extends('layouts.app')
@section('title', 'Rankings')

@section('content')

@php
    $activeFilters = (int) request()->filled('tournament_id') + (int) request()->filled('division_id');
@endphp

<form method="GET" action="{{ route('admin.tournaments.rankings.index') }}" x-data="{ open: false }">
<x-page-header title="Rankings" subtitle="Wins, losses, points and differential per division.">
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
                 style="min-width:240px">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <label class="form-label small fw-semibold mb-1">Tournament</label>
                        <select name="tournament_id" class="form-select form-select-sm"
                                onchange="this.form.division_id.value=''; this.form.submit()">
                            <option value="">All tournaments</option>
                            @foreach($tournaments as $t)
                                <option value="{{ $t->id }}" @selected((int) request('tournament_id') === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold mb-1">Division</label>
                        <select name="division_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Choose a division…</option>
                            @foreach($divisions as $d)
                                <option value="{{ $d->id }}" @selected((int) request('division_id') === $d->id)>{{ $d->tournament->name }} — {{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                        @if($activeFilters)
                            <a href="{{ route('admin.tournaments.rankings.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </x-slot>
</x-page-header>
</form>

@if($division)
    @if(!empty($groupStandings))
        <div class="row g-4">
            @foreach($groupStandings as $groupName => $standings)
            <div class="col-12 col-xl-6">
                <x-card :title="$division->name . ' — ' . $groupName" flush>
                    @include('admin.tournaments.rankings._table', ['standings' => $standings])
                </x-card>
            </div>
            @endforeach
        </div>
    @else
        <x-card :title="$division->name . ' — Leaderboard'" flush>
            @include('admin.tournaments.rankings._table', ['standings' => $standings])
        </x-card>
    @endif
@elseif($divisions->isEmpty())
    <x-empty-state title="No generated brackets yet"
        description="Rankings appear once a division's bracket is generated and matches are played."
        icon="bi-list-ol"
        :action="route('admin.tournaments.brackets.index')"
        actionLabel="Go to Brackets"/>
@else
    <x-empty-state title="Pick a division"
        description="Choose a tournament and division to see its leaderboard."
        icon="bi-list-ol"/>
@endif

@endsection
