{{-- Score entry modal: dynamic set rows up to best_of, winner auto-suggested. --}}
@php
$bestOf = (int) $match->tournament->getSetting('best_of', 3);
$pointsToWin = (int) $match->tournament->getSetting('points_to_win', 11);
@endphp
<div class="modal fade" id="scoreModal-{{ $match->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content"
             x-data="matchScoreForm({{ $bestOf }}, {{ $match->team1_id ?? 'null' }}, {{ $match->team2_id ?? 'null' }})">
            {{-- Sibling form, targeted from the footer via the HTML5 form attribute
                 (a nested form would be invalid HTML). --}}
            <form method="POST" action="{{ route('admin.tournaments.matches.walkover', $match) }}" id="walkoverForm-{{ $match->id }}"
                  onsubmit="return confirm('Record a walkover? The selected winner advances without a score.');">
                @csrf
                <input type="hidden" name="winner_team_id" :value="winnerId">
            </form>
            <form method="POST" action="{{ route('admin.tournaments.matches.score', $match) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Record Result — Match #{{ $match->match_number }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Best of {{ $bestOf }}, to {{ $pointsToWin }} points{{ $match->tournament->getSetting('win_by_2', true) ? ', win by 2' : '' }}.
                    </p>

                    <div class="d-flex align-items-center gap-2 mb-2 small fw-semibold">
                        <span class="flex-grow-1"></span>
                        <span style="width:88px" class="text-center text-truncate" title="{{ $match->team1?->name }}">{{ Str::limit($match->team1?->name, 10) }}</span>
                        <span style="width:88px" class="text-center text-truncate" title="{{ $match->team2?->name }}">{{ Str::limit($match->team2?->name, 10) }}</span>
                        <span style="width:30px"></span>
                    </div>

                    <template x-for="(set, idx) in sets" :key="idx">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="small text-muted flex-grow-1" x-text="'Game ' + (idx + 1)"></span>
                            <input type="number" min="0" max="99" class="form-control form-control-sm text-center" style="width:88px"
                                   :name="`sets[${idx}][team1]`" x-model.number="set.team1" required>
                            <input type="number" min="0" max="99" class="form-control form-control-sm text-center" style="width:88px"
                                   :name="`sets[${idx}][team2]`" x-model.number="set.team2" required>
                            <button type="button" class="btn btn-link btn-sm p-0 text-danger" style="width:30px"
                                    x-show="sets.length > 1 && idx === sets.length - 1" @click="sets.pop()" aria-label="Remove game">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </template>

                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-3"
                            x-show="sets.length < {{ $bestOf }}" @click="addSet()">
                        <i class="bi bi-plus-lg me-1"></i>Add Game
                    </button>

                    <div class="mb-2">
                        <label class="form-label fw-medium">Winner</label>
                        <select name="winner_team_id" class="form-select" x-model.number="winnerId" required>
                            <option value="">Choose…</option>
                            <option value="{{ $match->team1_id }}">{{ $match->team1?->name }}</option>
                            <option value="{{ $match->team2_id }}">{{ $match->team2?->name }}</option>
                        </select>
                        <small class="text-muted" x-show="suggestedWinnerId && suggestedWinnerId !== winnerId">
                            Scores suggest <strong x-text="suggestedWinnerId === team1Id ? @js($match->team1?->name) : @js($match->team2?->name)"></strong> won.
                        </small>
                    </div>

                    <div class="form-check">
                        <input type="hidden" name="override" value="0">
                        <input class="form-check-input" type="checkbox" name="override" id="score-override-{{ $match->id }}" value="1">
                        <label class="form-check-label small" for="score-override-{{ $match->id }}">
                            Override score validation (time-capped or modified games)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" form="walkoverForm-{{ $match->id }}" class="btn btn-outline-warning btn-sm me-auto" :disabled="!winnerId">Walkover</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="!winnerId">Save Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('matchScoreForm', (bestOf, team1Id, team2Id) => ({
        bestOf, team1Id, team2Id,
        sets: [{ team1: null, team2: null }],
        winnerId: '',

        get suggestedWinnerId() {
            let w1 = 0, w2 = 0;
            for (const s of this.sets) {
                if (s.team1 === null || s.team2 === null || s.team1 === s.team2) continue;
                s.team1 > s.team2 ? w1++ : w2++;
            }
            if (w1 === w2) return null;
            return w1 > w2 ? this.team1Id : this.team2Id;
        },

        init() {
            this.$watch('sets', () => {
                if (this.suggestedWinnerId) this.winnerId = this.suggestedWinnerId;
            }, { deep: true });
        },
        addSet() {
            if (this.sets.length < this.bestOf) this.sets.push({ team1: null, team2: null });
        },
    }));
});
</script>
@endpush
@endonce
