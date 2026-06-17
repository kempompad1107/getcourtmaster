@extends('layouts.app')
@section('title', 'Rankings')

@section('content')

<x-page-header title="Rankings" subtitle="Leaderboards per division — wins, losses, points and differential."/>

<form method="GET" class="card mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-5">
                <label class="form-label small fw-semibold mb-1">Tournament</label>
                <select name="tournament_id" class="form-select form-select-sm" onchange="this.form.division_id.value=''; this.form.submit()">
                    <option value="">All tournaments</option>
                    @foreach($tournaments as $t)
                    <option value="{{ $t->id }}" @selected((int) request('tournament_id') === $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-5">
                <label class="form-label small fw-semibold mb-1">Division</label>
                <select name="division_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Choose a division…</option>
                    @foreach($divisions as $d)
                    <option value="{{ $d->id }}" @selected((int) request('division_id') === $d->id)>{{ $d->tournament->name }} — {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">View</button>
            </div>
        </div>
    </div>
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
        description="Choose a tournament and division above to see its leaderboard."
        icon="bi-list-ol"/>
@endif

@endsection
