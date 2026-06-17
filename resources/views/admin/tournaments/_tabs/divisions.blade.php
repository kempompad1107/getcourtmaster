@php $canManage = auth()->user()->can('update', $tournament); @endphp

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-semibold">Divisions</h6>
        @if($canManage)
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#divisionModal-new">
            <i class="bi bi-plus-lg me-1"></i>Add Division
        </button>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>Division</th>
                    <th>Eligibility</th>
                    <th class="text-center">Format</th>
                    <th class="text-center">Entries</th>
                    <th class="text-end">Entry Fee</th>
                    <th>Bracket</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tournament->divisions as $division)
                <tr>
                    <td data-label="Division">
                        <p class="mb-0 small fw-semibold">{{ $division->name }}</p>
                        <small class="text-muted">{{ App\Models\TournamentDivision::GENDERS[$division->gender] ?? ucfirst($division->gender) }} · {{ $division->isSingles() ? 'Singles' : 'Doubles' }}</small>
                    </td>
                    <td data-label="Eligibility">
                        <span class="small">
                            {{ $division->skill_level ?: 'Any skill' }}
                            @if($division->min_age || $division->max_age)
                                · Age {{ $division->min_age ?? '0' }}–{{ $division->max_age ?? '∞' }}
                            @endif
                        </span>
                    </td>
                    <td data-label="Format" class="text-center">
                        <span class="small">{{ $division->formatLabel() }}</span>
                    </td>
                    <td data-label="Entries" class="text-center">
                        <span class="small fw-medium">{{ $division->teams_count }}{{ $division->max_entries ? ' / ' . $division->max_entries : '' }}</span>
                    </td>
                    <td data-label="Entry Fee" class="text-end">
                        <span class="small">{{ $tournament->currency }} {{ number_format($tournament->effectiveEntryFee($division), 2) }}</span>
                        @if($division->entry_fee === null)<small class="text-muted d-block">inherited</small>@endif
                    </td>
                    <td data-label="Bracket">
                        @if($division->bracketLocked())
                        <x-badge status="active">Generated</x-badge>
                        @else
                        <x-badge status="neutral">Not generated</x-badge>
                        @endif
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.tournaments.brackets.show', $division) }}" class="btn btn-outline-primary btn-sm">Bracket</a>
                        @if($canManage)
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#divisionModal-{{ $division->id }}">Edit</button>
                        <form method="POST" action="{{ route('admin.tournaments.divisions.destroy', $division) }}" class="d-inline"
                              onsubmit="return confirm('Delete division {{ $division->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="cell-plain">
                        <x-empty-state title="No divisions yet"
                            description="Add divisions like Men's Doubles or Open Singles so players can register."
                            icon="bi-diagram-3"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($canManage)
@push('modals')
{{-- Create + edit share the same field set --}}
@foreach(collect([null])->concat($tournament->divisions) as $division)
@php
$modalId = $division ? "divisionModal-{$division->id}" : 'divisionModal-new';
$action = $division
    ? route('admin.tournaments.divisions.update', $division)
    : route('admin.tournaments.divisions.store', $tournament);
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}">
                @csrf
                @if($division) @method('PUT') @endif
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">{{ $division ? "Edit: {$division->name}" : 'Add Division' }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-8">
                            <label class="form-label fw-medium">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" required maxlength="120" class="form-control"
                                   value="{{ $division?->name }}" placeholder="e.g. Men's Doubles — Intermediate">
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-medium">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select">
                                @foreach(App\Models\TournamentDivision::GENDERS as $value => $label)
                                <option value="{{ $value }}" @selected(($division?->gender ?? 'open') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-medium">Description</label>
                            <textarea name="description" rows="2" class="form-control">{{ $division?->description }}</textarea>
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label fw-medium">Team size <span class="text-danger">*</span></label>
                            <select name="team_size" class="form-select" @if($division?->bracketLocked()) disabled @endif>
                                <option value="1" @selected((int) ($division?->team_size ?? 2) === 1)>Singles (1)</option>
                                <option value="2" @selected((int) ($division?->team_size ?? 2) === 2)>Doubles (2)</option>
                            </select>
                            @if($division?->bracketLocked())
                            <input type="hidden" name="team_size" value="{{ $division->team_size }}">
                            @endif
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label fw-medium">Skill level</label>
                            <input type="text" name="skill_level" maxlength="50" class="form-control"
                                   value="{{ $division?->skill_level }}" placeholder="e.g. 3.0–3.5">
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label fw-medium">Min age</label>
                            <input type="number" name="min_age" min="1" max="120" class="form-control" value="{{ $division?->min_age }}">
                        </div>
                        <div class="col-6 col-sm-3">
                            <label class="form-label fw-medium">Max age</label>
                            <input type="number" name="max_age" min="1" max="120" class="form-control" value="{{ $division?->max_age }}">
                        </div>
                        <div class="col-6 col-sm-4">
                            <label class="form-label fw-medium">Max entries</label>
                            <input type="number" name="max_entries" min="2" max="512" class="form-control" value="{{ $division?->max_entries }}">
                            <div class="form-text">Blank = unlimited.</div>
                        </div>
                        <div class="col-6 col-sm-4">
                            <label class="form-label fw-medium">Entry fee override</label>
                            <input type="number" name="entry_fee" step="0.01" min="0" class="form-control" value="{{ $division?->entry_fee }}">
                            <div class="form-text">Blank = tournament fee ({{ $tournament->currency }} {{ number_format($tournament->entry_fee, 2) }}).</div>
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label fw-medium">Bracket format</label>
                            <select name="bracket_format" class="form-select" @if($division?->bracketLocked()) disabled @endif>
                                <option value="">Decide later</option>
                                @foreach(App\Models\TournamentDivision::FORMATS as $value => $label)
                                <option value="{{ $value }}" @selected($division?->bracket_format === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($division?->bracketLocked())
                            <input type="hidden" name="bracket_format" value="{{ $division->bracket_format }}">
                            @endif
                        </div>
                        <div class="col-12 col-sm-4">
                            <label class="form-label fw-medium">Seeding</label>
                            <select name="seeding_method" class="form-select">
                                <option value="random" @selected(($division?->seeding_method ?? 'random') === 'random')>Random</option>
                                <option value="manual" @selected($division?->seeding_method === 'manual')>Manual (drag & drop)</option>
                                <option value="rating" @selected($division?->seeding_method === 'rating')>By rating</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">{{ $division ? 'Save Changes' : 'Add Division' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endpush
@endif
