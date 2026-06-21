@php
$canRegister = auth()->user()->can('manageRegistrations', $tournament);
$feeByDivision = $tournament->divisions->mapWithKeys(fn ($d) => [$d->id => $tournament->effectiveEntryFee($d)]);
@endphp

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">Registered Teams</h6>
        @if($canRegister && $tournament->divisions->isNotEmpty())
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#registerTeamModal">
            <i class="bi bi-person-plus me-1"></i>Register Team
        </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>Team</th>
                    <th>Division</th>
                    <th>Players</th>
                    <th>Fees</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($teams as $team)
                <tr>
                    <td data-label="Team">
                        <p class="mb-0 small fw-semibold">{{ $team->name }}</p>
                        <small class="text-muted">Seed: {{ $team->seed ?? '—' }} · via {{ $team->registered_via }}</small>
                    </td>
                    <td data-label="Division"><span class="small">{{ $team->division->name }}</span></td>
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
                                @if($member->is_captain)<i class="bi bi-star-fill text-warning small" title="Captain"></i>@endif
                                <small class="text-muted d-block">{{ $member->skill_level ?: 'Skill —' }}{{ $member->rating !== null ? ' · ' . number_format($member->rating, 2) : '' }}</small>
                            </div>
                        </div>
                        @endforeach
                    </td>
                    <td data-label="Fees">
                        @if(($feeByDivision[$team->division_id] ?? 0) <= 0)
                        <x-badge status="neutral">Free</x-badge>
                        @else
                        @php $paid = $team->members->filter->hasPaid()->count(); @endphp
                        <x-badge :status="$paid === $team->members->count() ? 'active' : 'pending'">
                            {{ $paid }}/{{ $team->members->count() }} paid
                        </x-badge>
                        @endif
                    </td>
                    <td data-label="Status">
                        <x-badge :status="match($team->status) { 'confirmed' => 'confirmed', 'pending' => 'pending', 'disqualified' => 'cancelled', default => 'neutral' }">
                            {{ ucfirst($team->status) }}
                        </x-badge>
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        @if($canRegister)
                        @if(($feeByDivision[$team->division_id] ?? 0) > 0 && $team->members->contains(fn ($m) => !$m->hasPaid()))
                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#collectFeeModal-{{ $team->id }}">Collect</button>
                        @endif
                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#withdrawModal-{{ $team->id }}">Withdraw</button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr class="stack-skip">
                    <td colspan="6" class="cell-plain">
                        <x-empty-state title="No teams registered"
                            description="Register members into a division to get started."
                            icon="bi-people"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($canRegister)
@push('modals')
@if($tournament->divisions->isNotEmpty())
@include('admin.tournaments.teams._register-modal', [
    'tournament' => $tournament,
    'modalId' => 'registerTeamModal',
])
@endif

@foreach($teams as $team)
@if(($feeByDivision[$team->division_id] ?? 0) > 0 && $team->members->contains(fn ($m) => !$m->hasPaid()))
<div class="modal fade" id="collectFeeModal-{{ $team->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.tournaments.teams.collect', $team) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Collect Entry Fee — {{ $team->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        {{ $tournament->currency }} {{ number_format($feeByDivision[$team->division_id], 2) }} per player.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Player</label>
                        <select name="member_id" class="form-select">
                            <option value="">All unpaid players</option>
                            @foreach($team->members->filter(fn ($m) => !$m->hasPaid()) as $member)
                            <option value="{{ $member->id }}">{{ $member->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-medium">Payment method</label>
                        <select name="method" class="form-select" required>
                            <option value="cash">Cash (collected at desk)</option>
                            <option value="wallet">Wallet (debit member balance)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Collect</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="withdrawModal-{{ $team->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.tournaments.teams.withdraw', $team) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Withdraw — {{ $team->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Reason</label>
                        <input type="text" name="reason" maxlength="255" class="form-control" placeholder="e.g. Injury">
                    </div>
                    @if($team->payments()->whereIn('status', ['paid', 'partial'])->exists())
                    <div class="form-check">
                        <input type="hidden" name="refund" value="0">
                        <input class="form-check-input" type="checkbox" name="refund" id="withdraw-refund-{{ $team->id }}" value="1" checked>
                        <label class="form-check-label" for="withdraw-refund-{{ $team->id }}">
                            Refund paid entry fees
                            <small class="text-muted d-block">Wallet payments are credited back automatically; cash refunds are flagged for the desk.</small>
                        </label>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Withdraw Team</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endpush
@endif
