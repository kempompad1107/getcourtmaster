@extends('layouts.app')
@section('title', 'Tournament Reports')

@section('content')

<x-page-header title="Tournament Reports" subtitle="Pick a tournament, then a report. Every report exports to PDF, Excel and CSV."/>

@if($tournaments->isEmpty())
<x-empty-state title="No tournaments yet"
    description="Reports become available once you create a tournament."
    icon="bi-clipboard-data"
    :action="route('admin.tournaments.create')"
    actionLabel="New Tournament"/>
@else
<div class="row g-4" x-data="{ tournament: '{{ $tournaments->first()->id }}' }">
    <div class="col-12 col-lg-4">
        <x-card title="Tournament" flush>
            <div class="list-group list-group-flush">
                @foreach($tournaments as $tournament)
                <button type="button" class="list-group-item list-group-item-action"
                        :class="tournament === '{{ $tournament->id }}' ? 'active' : ''"
                        @click="tournament = '{{ $tournament->id }}'">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="min-w-0">
                            <p class="mb-0 small fw-semibold text-truncate">{{ $tournament->name }}</p>
                            <small :class="tournament === '{{ $tournament->id }}' ? '' : 'text-muted'">
                                {{ $tournament->starts_at?->format('M j, Y') ?? 'No date' }} · {{ $tournament->teams_count }} teams
                            </small>
                        </div>
                        @if($tournament->archived_at)
                        <x-badge status="expired">Archived</x-badge>
                        @endif
                    </div>
                </button>
                @endforeach
            </div>
        </x-card>
    </div>
    <div class="col-12 col-lg-8">
        <div class="row g-3">
            @foreach($types as $key => $label)
            @php
            $icon = match($key) {
                'summary' => 'bi-clipboard-data',
                'participants' => 'bi-person-lines-fill',
                'teams' => 'bi-people',
                'matches' => 'bi-controller',
                'winners' => 'bi-trophy',
                'fees' => 'bi-cash-stack',
                default => 'bi-file-text',
            };
            @endphp
            <div class="col-12 col-sm-6">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi {{ $icon }} text-primary fs-5"></i>
                            <h6 class="mb-0 fw-semibold">{{ $label }}</h6>
                        </div>
                        <p class="small text-muted flex-grow-1 mb-3">
                            {{ match($key) {
                                'summary' => 'Divisions, team counts, match progress and fee totals.',
                                'participants' => 'Every registered player with contact, skill and payment status.',
                                'teams' => 'All teams with players, seeds and registration details.',
                                'matches' => 'Every finished match with scores, courts and referees.',
                                'winners' => 'Champions and runners-up per division.',
                                'fees' => 'Entry fee collection: gross, refunds, net and per-method totals.',
                            } }}
                        </p>
                        <template x-if="tournament">
                            <a :href="'{{ url('admin/tournaments') }}/' + tournament + '/reports/{{ $key }}'" class="btn btn-outline-primary btn-sm">
                                View Report
                            </a>
                        </template>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

@endsection
