@extends('layouts.app')
@section('title', 'Tournament Teams')

@section('content')

<x-page-header title="Teams" subtitle="All tournament registrations. Register new teams from a tournament's Teams tab."/>

<x-filter-bar placeholder="Search team, player name, email, mobile, member ID…"
              :active-count="(int) request()->filled('tournament_id') + (int) request()->filled('division_id') + (int) request()->filled('status')"
              :clear="route('admin.tournaments.teams.index')">
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
                <option value="">All statuses</option>
                @foreach(['pending', 'confirmed', 'withdrawn', 'disqualified'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
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
                    <th>Team</th>
                    <th>Tournament / Division</th>
                    <th>Players</th>
                    <th>Fees</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($teams as $team)
                @php $fee = $team->tournament->effectiveEntryFee($team->division); @endphp
                <tr>
                    <td data-label="Team">
                        <p class="mb-0 small fw-semibold">{{ $team->name }}</p>
                        <small class="text-muted">{{ $team->created_at->format('M j, Y') }} · via {{ $team->registered_via }}</small>
                    </td>
                    <td data-label="Tournament">
                        <a href="{{ route('admin.tournaments.show', [$team->tournament_id, 'tab' => 'teams']) }}" class="small d-block">{{ $team->tournament->name }}</a>
                        <small class="text-muted">{{ $team->division->name }}</small>
                    </td>
                    <td data-label="Players">
                        @foreach($team->members as $member)
                        <div class="d-flex align-items-center gap-2 {{ !$loop->last ? 'mb-1' : '' }}">
                            @if($member->user->avatar && ($avatar = file_url($member->user->avatar)))
                            <img src="{{ $avatar }}" alt="" class="rounded-circle flex-shrink-0" style="width:24px;height:24px;object-fit:cover;">
                            @else
                            <div class="bk-avatar flex-shrink-0" style="width:24px;height:24px;font-size:.7rem;">{{ strtoupper(substr($member->user->name, 0, 1)) }}</div>
                            @endif
                            <div class="min-w-0">
                                <span class="small fw-medium">{{ $member->user->name }}</span>
                                <small class="text-muted d-block">{{ $member->skill_level ?: '—' }}{{ $member->rating !== null ? ' · ' . number_format($member->rating, 2) : '' }}</small>
                            </div>
                        </div>
                        @endforeach
                    </td>
                    <td data-label="Fees">
                        @if($fee <= 0)
                        <x-badge status="neutral">Free</x-badge>
                        @else
                        @php $paid = $team->members->filter->hasPaid()->count(); @endphp
                        <x-badge :status="$paid === $team->members->count() ? 'active' : 'pending'">{{ $paid }}/{{ $team->members->count() }} paid</x-badge>
                        @endif
                    </td>
                    <td data-label="Status">
                        <x-badge :status="match($team->status) { 'confirmed' => 'confirmed', 'pending' => 'pending', 'disqualified' => 'cancelled', 'withdrawn' => 'expired', default => 'neutral' }">
                            {{ ucfirst($team->status) }}
                        </x-badge>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.tournaments.show', [$team->tournament_id, 'tab' => 'teams']) }}" class="btn btn-primary btn-sm">Manage</a>
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="6" class="cell-plain">
                        <x-empty-state title="No teams found"
                            description="Try adjusting your filters, or register teams from a tournament page."
                            icon="bi-people"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($teams->hasPages())
    <div class="px-4 py-3 border-top">
        {{ $teams->withQueryString()->links() }}
    </div>
    @endif
</div>

@endsection
