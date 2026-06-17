@extends('layouts.app')
@section('title', $division->name . ' — Bracket')

@php
$canManage = auth()->user()->can('manageBrackets', $division->tournament);
$generated = $division->bracketLocked();
$hasResults = $matches->whereIn('status', ['finished', 'walkover'])->isNotEmpty();
$byType = $matches->groupBy('bracket_type');
$knockoutReady = $generated
    && $byType->has('knockout')
    && $matches->where('bracket_type', 'group')->whereNotIn('status', ['finished', 'walkover', 'bye', 'cancelled'])->isEmpty()
    && $matches->where('bracket_type', 'knockout')->whereNotNull('team1_id')->isEmpty();
@endphp

@section('content')

<x-page-header :title="$division->name"
               :subtitle="$division->tournament->name . ' · ' . $division->formatLabel() . ($generated ? '' : ' · bracket not generated')"
               :back="route('admin.tournaments.brackets.index')">
    <x-slot name="actions">
        <a href="{{ route('admin.tournaments.matches.index', ['division_id' => $division->id]) }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-controller me-1"></i>Matches
        </a>
        @if($canManage && $generated)
        @if($knockoutReady)
        <form method="POST" action="{{ route('admin.tournaments.brackets.seed-knockout', $division) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-arrow-right-circle me-1"></i>Seed Knockout</button>
        </form>
        @endif
        <form method="POST" action="{{ route('admin.tournaments.brackets.reset', $division) }}" class="d-inline"
              onsubmit="return confirm('{{ $hasResults ? 'This bracket has recorded results — resetting discards them. Continue?' : 'Reset this bracket? Teams are kept.' }}');">
            @csrf
            @method('DELETE')
            @if($hasResults)<input type="hidden" name="force" value="1">@endif
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
        </form>
        @endif
    </x-slot>
</x-page-header>

@if(!$generated)
{{-- ── Setup: seeding + generate ─────────────────────────────────────── --}}
<div class="row g-4">
    <div class="col-12 col-lg-6">
        @include('admin.tournaments.brackets._seeding')
    </div>
    <div class="col-12 col-lg-6">
        <x-card title="Generate Bracket">
            @if($teams->count() < 2)
            <p class="small text-muted mb-0">At least 2 registered teams are needed. There {{ $teams->count() === 1 ? 'is 1 team' : 'are ' . $teams->count() . ' teams' }} so far.</p>
            @elseif(!$canManage)
            <p class="small text-muted mb-0">You don't have permission to generate brackets.</p>
            @else
            <form method="POST" action="{{ route('admin.tournaments.brackets.generate', $division) }}"
                  x-data="{ format: @js($division->bracket_format ?? 'single_elimination') }">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-medium">Format</label>
                    <select name="format" class="form-select" x-model="format" required>
                        @foreach(App\Models\TournamentDivision::FORMATS as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-medium">Seeding</label>
                    <select name="seeding_method" class="form-select" required>
                        <option value="random" @selected($division->seeding_method === 'random')>Random</option>
                        <option value="manual" @selected($division->seeding_method === 'manual')>Manual (order on the left)</option>
                        <option value="rating" @selected($division->seeding_method === 'rating')>By rating (highest average first)</option>
                    </select>
                </div>

                <div x-show="format === 'round_robin'" x-cloak class="form-check mb-3">
                    <input type="hidden" name="double_round_robin" value="0">
                    <input class="form-check-input" type="checkbox" name="double_round_robin" id="gen-double-rr" value="1">
                    <label class="form-check-label" for="gen-double-rr">Double round robin (everyone plays twice)</label>
                </div>

                <div x-show="format === 'group_stage' || format === 'pool_play'" x-cloak>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-medium">Groups</label>
                            <input type="number" name="group_count" min="1" max="26" class="form-control"
                                   value="{{ max(1, (int) ceil($teams->count() / 4)) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Advance per group</label>
                            <input type="number" name="advance_per_group" min="1" max="8" class="form-control" value="2">
                        </div>
                    </div>
                    <div class="form-check mb-3" x-show="format === 'pool_play'">
                        <input type="hidden" name="knockout" value="0">
                        <input class="form-check-input" type="checkbox" name="knockout" id="gen-knockout" value="1">
                        <label class="form-check-label" for="gen-knockout">Add a knockout phase after pools</label>
                    </div>
                    <p class="small text-muted" x-show="format === 'group_stage'">Group stage always finishes with a knockout phase between qualifiers.</p>
                </div>

                <button type="submit" class="btn btn-primary w-100"
                        onclick="return confirm('Generate the bracket with {{ $teams->count() }} teams? Seeds lock once generated.');">
                    <i class="bi bi-bezier2 me-1"></i>Generate Bracket
                </button>
            </form>
            @endif
        </x-card>
    </div>
</div>

@else
{{-- ── Generated bracket ─────────────────────────────────────────────── --}}

@if($byType->has('group'))
<div class="row g-4 mb-4">
    @php $plainRR = $division->groups->isEmpty(); @endphp
    @foreach($plainRR ? [null] : $division->groups as $group)
    @php
    $groupMatches = $matches->where('bracket_type', 'group')->when($group, fn ($c) => $c->where('group_id', $group->id));
    $standings = $groupStandings[$group->id ?? 0] ?? [];
    @endphp
    <div class="col-12 col-xl-6">
        <x-card :title="$group?->name ?? 'Round Robin Standings'">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th><th>Team</th>
                            <th class="text-center">P</th><th class="text-center">W</th><th class="text-center">L</th>
                            <th class="text-center">PF</th><th class="text-center">PA</th><th class="text-center">±</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($standings as $i => $row)
                        <tr>
                            <td class="small fw-semibold">{{ $i + 1 }}</td>
                            <td class="small">{{ $row['team']?->name ?? '—' }}</td>
                            <td class="small text-center">{{ $row['played'] }}</td>
                            <td class="small text-center fw-medium">{{ $row['wins'] }}</td>
                            <td class="small text-center">{{ $row['losses'] }}</td>
                            <td class="small text-center">{{ $row['points_for'] }}</td>
                            <td class="small text-center">{{ $row['points_against'] }}</td>
                            <td class="small text-center {{ $row['point_diff'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <hr class="my-2">
            @foreach($groupMatches->sortBy(['round', 'bracket_pos']) as $match)
            @include('admin.tournaments.brackets._match-row', ['match' => $match])
            @endforeach
        </x-card>
    </div>
    @endforeach
</div>
@endif

@foreach([['winners', 'Winners Bracket'], ['main', 'Bracket'], ['losers', 'Elimination Bracket'], ['knockout', 'Knockout Phase']] as [$type, $title])
@if($byType->has($type))
<x-card :title="$title" class="mb-4">
    <div class="bracket-scroll">
        <div class="bracket-grid">
            @foreach($byType[$type]->groupBy('round')->sortKeys() as $round => $roundMatches)
            <div class="bracket-round">
                <div class="bracket-round-title">{{ $roundMatches->first()->round_name ?? "Round {$round}" }}</div>
                @foreach($roundMatches->sortBy('bracket_pos') as $match)
                @include('admin.tournaments.brackets._match-cell', ['match' => $match])
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
</x-card>
@endif
@endforeach

@if($byType->has('grand_final'))
<x-card title="Grand Final" class="mb-4">
    <div class="bracket-grid">
        @foreach($byType['grand_final']->sortBy('round') as $match)
        @if(!($match->round === 2 && $match->status === 'cancelled'))
        <div class="bracket-round" style="min-width:260px;">
            <div class="bracket-round-title">{{ $match->round_name }}</div>
            @include('admin.tournaments.brackets._match-cell', ['match' => $match])
        </div>
        @endif
        @endforeach
    </div>
</x-card>
@endif

@endif

@endsection
