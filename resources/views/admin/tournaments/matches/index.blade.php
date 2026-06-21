@extends('layouts.app')
@section('title', 'Tournament Matches')

@section('content')

<x-page-header title="Matches" subtitle="Schedule courts and referees, call matches, record scores."/>

<x-filter-bar :searchable="false"
              :active-count="collect(['tournament_id','division_id','status','court_id','date'])->filter(fn ($k) => request()->filled($k))->count()"
              :clear="route('admin.tournaments.matches.index')">
    <x-slot name="filters">
        <div>
            <label class="form-label small fw-semibold mb-1">Tournament</label>
            <select name="tournament_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All tournaments</option>
                @foreach($tournaments as $t)
                <option value="{{ $t->id }}" @selected((int) request('tournament_id') === $t->id)>{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        @if($divisions->isNotEmpty())
        <div>
            <label class="form-label small fw-semibold mb-1">Division</label>
            <select name="division_id" class="form-select form-select-sm">
                <option value="">All divisions</option>
                @foreach($divisions as $d)
                <option value="{{ $d->id }}" @selected((int) request('division_id') === $d->id)>{{ $d->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div>
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Active (default)</option>
                @foreach(['pending','scheduled','called','playing','finished','walkover','bye','cancelled'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Court</label>
            <select name="court_id" class="form-select form-select-sm">
                <option value="">All courts</option>
                @foreach($courts as $court)
                <option value="{{ $court->id }}" @selected((int) request('court_id') === $court->id)>{{ $court->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small fw-semibold mb-1">Date</label>
            <input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm">
        </div>
    </x-slot>
</x-filter-bar>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>Match</th>
                    <th>Teams</th>
                    <th>Court / Referee</th>
                    <th>Schedule</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($matches as $match)
                @php $canManage = auth()->user()->can('manageMatches', $match->tournament); @endphp
                <tr>
                    <td data-label="Match">
                        <p class="mb-0 small fw-semibold">#{{ $match->match_number }} · {{ $match->round_name ?? "Round {$match->round}" }}</p>
                        <small class="text-muted d-block">{{ $match->tournament->name }} · {{ $match->division->name }}{{ $match->group ? ' · ' . $match->group->name : '' }}</small>
                    </td>
                    <td data-label="Teams">
                        <span class="small {{ $match->winner_team_id && $match->winner_team_id === $match->team1_id ? 'fw-bold' : '' }}">{{ $match->team1?->name ?? 'TBD' }}</span>
                        <small class="text-muted d-block">vs</small>
                        <span class="small {{ $match->winner_team_id && $match->winner_team_id === $match->team2_id ? 'fw-bold' : '' }}">{{ $match->team2?->name ?? 'TBD' }}</span>
                    </td>
                    <td data-label="Court / Ref">
                        <span class="small">{{ $match->court?->name ?? 'No court' }}</span>
                        <small class="text-muted d-block">{{ $match->referee_name ?: 'No referee' }}</small>
                    </td>
                    <td data-label="Schedule">
                        <span class="small">{{ $match->scheduled_at?->format('M j, g:i A') ?? '—' }}</span>
                    </td>
                    <td data-label="Score"><span class="small">{{ $match->scoreSummary() }}</span></td>
                    <td data-label="Status">
                        <x-badge :status="match($match->status) { 'finished' => 'completed', 'walkover' => 'info', 'playing' => 'active', 'called' => 'pending', 'scheduled' => 'info', 'cancelled' => 'cancelled', 'bye' => 'neutral', default => 'neutral' }">
                            {{ ucfirst($match->status) }}
                        </x-badge>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        @if($canManage)
                            @if($match->isPlayable())
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#scoreModal-{{ $match->id }}">Score</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal-{{ $match->id }}">Assign</button>
                                @if(in_array($match->status, ['scheduled', 'pending'], true))
                                <form method="POST" action="{{ route('admin.tournaments.matches.status', $match) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="status" value="called">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Call</button>
                                </form>
                                @elseif($match->status === 'called')
                                <form method="POST" action="{{ route('admin.tournaments.matches.status', $match) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="status" value="playing">
                                    <button type="submit" class="btn btn-outline-primary btn-sm">Start</button>
                                </form>
                                @endif
                            @elseif(in_array($match->status, ['finished', 'walkover'], true))
                            <form method="POST" action="{{ route('admin.tournaments.matches.undo', $match) }}" class="d-inline"
                                  onsubmit="return confirm('Undo this result? The winner is pulled back from the next round.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">Undo</button>
                            </form>
                            @elseif($match->status === 'pending' && (!$match->team1_id || !$match->team2_id))
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#scheduleModal-{{ $match->id }}">Assign</button>
                            @endif
                        @endif
                        <a href="{{ route('admin.tournaments.brackets.show', $match->division_id) }}" class="btn btn-outline-secondary btn-sm">Bracket</a>
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="7" class="cell-plain">
                        <x-empty-state title="No matches found"
                            description="Generate a bracket for a division to create its matches."
                            icon="bi-controller"
                            :action="route('admin.tournaments.brackets.index')"
                            actionLabel="Go to Brackets"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($matches->hasPages())
    <div class="px-4 py-3 border-top">
        {{ $matches->withQueryString()->links() }}
    </div>
    @endif
</div>

@push('modals')
@foreach($matches as $match)
@if(auth()->user()->can('manageMatches', $match->tournament))
@if($match->isPlayable())
@include('admin.tournaments.matches._score-modal', ['match' => $match])
@endif
@include('admin.tournaments.matches._schedule-modal', ['match' => $match, 'courts' => $courts])
@endif
@endforeach

{{-- One shared datalist for every schedule modal's referee input --}}
<datalist id="refereeSuggestions">
    @foreach($refereeSuggestions as $name)
    <option value="{{ $name }}">
    @endforeach
</datalist>
@endpush

@endsection
