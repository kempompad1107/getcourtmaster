@extends('layouts.customer')

@section('title', $division->name . ' — Bracket')

@section('content')

<x-page-header :title="$division->name"
               :subtitle="$tournament->name"
               :back="route('customer.tournaments.show', $tournament->slug)"/>

@php $byType = $matches->groupBy('bracket_type'); @endphp

@if(!$division->bracketLocked())
<x-empty-state title="Bracket not ready yet"
    description="The draw will appear here once the organizers generate it."
    icon="bi-bezier2"/>
@else

@if(!empty($groupStandings))
<div class="row g-4 mb-4">
    @foreach($groupStandings as $name => $standings)
    <div class="col-12 col-xl-6">
        <x-card :title="$name">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Team</th><th class="text-center">P</th><th class="text-center">W</th><th class="text-center">L</th><th class="text-center">±</th></tr>
                    </thead>
                    <tbody>
                        @foreach($standings as $i => $row)
                        <tr>
                            <td class="small fw-semibold">{{ $i + 1 }}</td>
                            <td class="small">{{ $row['team']?->name ?? '—' }}</td>
                            <td class="small text-center">{{ $row['played'] }}</td>
                            <td class="small text-center fw-medium">{{ $row['wins'] }}</td>
                            <td class="small text-center">{{ $row['losses'] }}</td>
                            <td class="small text-center {{ $row['point_diff'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $row['point_diff'] > 0 ? '+' : '' }}{{ $row['point_diff'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
    @endforeach
</div>
@endif

@if($byType->has('group'))
<x-card title="Match Schedule & Results" class="mb-4">
    @foreach($byType['group']->sortBy(['round', 'bracket_pos']) as $match)
    @include('admin.tournaments.brackets._match-row', ['match' => $match])
    @endforeach
</x-card>
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
