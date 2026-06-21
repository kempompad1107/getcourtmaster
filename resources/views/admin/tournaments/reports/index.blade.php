@extends('layouts.app')
@section('title', 'Tournament Reports')

@push('styles')
<style>
    .report-card {
        border: 1px solid var(--bs-border-color);
        border-radius: var(--bs-border-radius);
        transition: box-shadow .2s ease, border-color .2s ease;
    }
    .report-card:hover {
        box-shadow: 0 8px 24px -12px rgba(15,38,67,.18);
        border-color: rgba(16,185,129,.4);
    }
    .report-ico {
        width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
        display: grid; place-items: center; font-size: 1.1rem;
        background: rgba(16,185,129,.1); color: #10b981;
        border: 1px solid rgba(16,185,129,.2);
    }
    /* Emerald active state on tournament list */
    .trn-list .list-group-item.active {
        background-color: rgba(16,185,129,.1);
        border-color: rgba(16,185,129,.3);
        color: var(--bs-body-color);
    }
    .trn-list .list-group-item.active .trn-list-sub {
        color: #10b981 !important;
    }
</style>
@endpush

@section('content')

<x-page-header title="Tournament Reports" subtitle="Pick a tournament, choose a report type, export to PDF."/>

@if($tournaments->isEmpty())
<x-empty-state title="No tournaments yet"
    description="Reports become available once you create a tournament."
    icon="bi-clipboard-data"
    :action="route('admin.tournaments.create')"
    actionLabel="New Tournament"/>
@else
<div class="row g-4" x-data="{ tournament: '{{ $tournaments->first()->id }}' }">

    {{-- Tournament selector --}}
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0 fw-semibold">Select Tournament</h6>
            </div>
            <div class="list-group list-group-flush trn-list">
                @foreach($tournaments as $tournament)
                <button type="button"
                        class="list-group-item list-group-item-action"
                        :class="tournament === '{{ $tournament->id }}' ? 'active' : ''"
                        @click="tournament = '{{ $tournament->id }}'">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="min-w-0">
                            <p class="mb-0 small fw-semibold text-truncate">{{ $tournament->name }}</p>
                            <small class="trn-list-sub text-muted">
                                {{ $tournament->starts_at?->format('M j, Y') ?? 'No date' }} · {{ $tournament->teams_count }} {{ Str::plural('team', $tournament->teams_count) }}
                            </small>
                        </div>
                        @if($tournament->archived_at)
                        <x-badge status="expired">Archived</x-badge>
                        @endif
                    </div>
                </button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Report types --}}
    <div class="col-12 col-lg-8">
        <div class="row g-3">
            @foreach($types as $key => $label)
            @php
            $icon = match($key) {
                'summary'      => 'bi-clipboard-data',
                'participants' => 'bi-person-lines-fill',
                'teams'        => 'bi-people',
                'matches'      => 'bi-controller',
                'winners'      => 'bi-trophy',
                'fees'         => 'bi-cash-stack',
                default        => 'bi-file-text',
            };
            $desc = match($key) {
                'summary'      => 'Divisions, team counts, match progress and fee totals.',
                'participants' => 'Every registered player with contact, skill and payment status.',
                'teams'        => 'All teams with players, seeds and registration details.',
                'matches'      => 'Every finished match with scores, courts and referees.',
                'winners'      => 'Champions and runners-up per division.',
                'fees'         => 'Entry fee collection: gross, refunds, net and per-method totals.',
                default        => '',
            };
            @endphp
            <div class="col-12 col-sm-6">
                <div class="report-card card h-100">
                    <div class="card-body d-flex flex-column gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="report-ico">
                                <i class="bi {{ $icon }}"></i>
                            </div>
                            <h6 class="mb-0 fw-semibold">{{ $label }}</h6>
                        </div>
                        <p class="small text-muted flex-grow-1 mb-0">{{ $desc }}</p>
                        <template x-if="tournament">
                            <a :href="'{{ url('admin/tournaments') }}/' + tournament + '/reports/{{ $key }}'"
                               class="btn btn-primary btn-sm align-self-start">
                                <i class="bi bi-eye me-1"></i>View Report
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
