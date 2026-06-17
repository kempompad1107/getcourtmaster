{{-- Assign court / referee / time / notes. --}}
<div class="modal fade" id="scheduleModal-{{ $match->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.tournaments.matches.update', $match) }}">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Assign — Match #{{ $match->match_number }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        {{ $match->team1?->name ?? 'TBD' }} vs {{ $match->team2?->name ?? 'TBD' }}
                        · {{ $match->round_name ?? "Round {$match->round}" }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Court</label>
                        <select name="court_id" class="form-select">
                            <option value="">No court</option>
                        @php
                            $branchCourts = ($match->tournament && ! $match->tournament->is_all_branches && $match->tournament->branch_id)
                                ? $courts->where('branch_id', $match->tournament->branch_id)
                                : $courts;
                        @endphp
                        @foreach($branchCourts as $court)
                        <option value="{{ $court->id }}" @selected($match->court_id === $court->id)>{{ $court->name }}</option>
                        @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Referee</label>
                        <input type="text" name="referee_name" maxlength="100" class="form-control"
                               value="{{ $match->referee_name }}" placeholder="Type a name…"
                               list="refereeSuggestions" autocomplete="off">
                        <div class="form-text">Free text — staff names appear as suggestions, but any name works.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Scheduled time</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control"
                               value="{{ $match->scheduled_at?->format('Y-m-d\TH:i') }}">
                    </div>
                    <div>
                        <label class="form-label fw-medium">Notes</label>
                        <textarea name="notes" rows="2" maxlength="1000" class="form-control">{{ $match->notes }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
