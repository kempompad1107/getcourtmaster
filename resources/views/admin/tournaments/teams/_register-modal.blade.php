{{--
    Staff team-registration modal. Alpine drives:
    - division select → number of player slots (team_size)
    - per-slot member search against admin.customers.search (name/email/phone)
    - per-slot skill level + rating capture (stored on the registration row)
--}}
@php
$divisionsJson = $tournament->divisions->map(fn ($d) => [
    'id' => $d->id,
    'name' => $d->name,
    'team_size' => (int) $d->team_size,
    'fee' => $tournament->effectiveEntryFee($d),
    'url' => route('admin.tournaments.teams.store', $d),
])->values();
@endphp

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content"
             x-data="tournamentRegisterForm(@js($divisionsJson), @js(route('admin.customers.search')))">
            <form method="POST" :action="division ? division.url : '#'" @submit="return !!division">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Register Team</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="form-label fw-medium">Division <span class="text-danger">*</span></label>
                            <select class="form-select" x-model.number="divisionId" required>
                                <option value="">Choose a division…</option>
                                <template x-for="d in divisions" :key="d.id">
                                    <option :value="d.id" x-text="d.name + (d.team_size === 1 ? ' (Singles)' : ' (Doubles)')"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label fw-medium">Team name</label>
                            <input type="text" name="name" maxlength="150" class="form-control" placeholder="Auto from last names">
                        </div>
                    </div>

                    <template x-if="division">
                        <div>
                            <hr>
                            <template x-for="(slot, idx) in slots" :key="idx">
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="small fw-semibold" x-text="division.team_size === 1 ? 'Player' : (idx === 0 ? 'Player 1 (captain)' : 'Player 2 (partner)')"></span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-danger" x-show="slot.member" @click="clearSlot(idx)">Change</button>
                                    </div>

                                    {{-- Picked member --}}
                                    <template x-if="slot.member">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bk-avatar flex-shrink-0" x-text="slot.member.name.substring(0, 1).toUpperCase()"></div>
                                            <div class="min-w-0">
                                                <p class="mb-0 small fw-medium" x-text="slot.member.name"></p>
                                                <small class="text-muted" x-text="[slot.member.email, slot.member.phone].filter(Boolean).join(' · ')"></small>
                                            </div>
                                            <input type="hidden" :name="`members[${idx}][user_id]`" :value="slot.member.id">
                                        </div>
                                    </template>

                                    {{-- Search --}}
                                    <template x-if="!slot.member">
                                        <div class="position-relative">
                                            <div class="search-field">
                                                <i class="bi bi-search"></i>
                                                <input type="text" :placeholder="'Search member by name, email or mobile…'"
                                                       x-model="slot.query"
                                                       @input.debounce.300ms="search(idx)"
                                                       autocomplete="off">
                                            </div>
                                            <div class="card shadow position-absolute start-0 end-0 mt-1" style="z-index:1060;max-height:220px;overflow-y:auto;"
                                                 x-show="slot.results.length > 0" x-cloak>
                                                <div class="list-group list-group-flush">
                                                    <template x-for="result in slot.results" :key="result.id">
                                                        <button type="button" class="list-group-item list-group-item-action py-2"
                                                                :disabled="isPicked(result.id)"
                                                                @click="pick(idx, result)">
                                                            <span class="small fw-medium" x-text="result.name"></span>
                                                            <small class="text-muted d-block" x-text="[result.email, result.phone].filter(Boolean).join(' · ')"></small>
                                                            <small class="text-danger" x-show="isPicked(result.id)">Already selected</small>
                                                        </button>
                                                    </template>
                                                </div>
                                            </div>
                                            <small class="text-muted" x-show="slot.searched && slot.results.length === 0 && slot.query.length > 1">No active members match.</small>
                                        </div>
                                    </template>

                                    <div class="row g-2 mt-1">
                                        <div class="col-6">
                                            <label class="form-label small fw-medium mb-1">Skill level</label>
                                            <input type="text" :name="`members[${idx}][skill_level]`" maxlength="50"
                                                   class="form-control form-control-sm" placeholder="e.g. 3.5 / Intermediate">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small fw-medium mb-1">Rating</label>
                                            <input type="number" :name="`members[${idx}][rating]`" step="0.01" min="0" max="10"
                                                   class="form-control form-control-sm" placeholder="e.g. 3.75">
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div x-show="division.fee > 0">
                                <label class="form-label fw-medium">Entry fee (<span x-text="division.fee.toFixed(2)"></span> {{ $tournament->currency }} per player)</label>
                                <select name="collect_method" class="form-select">
                                    <option value="">Collect later (team stays pending)</option>
                                    <option value="cash">Collect now — cash</option>
                                    <option value="wallet">Collect now — wallet debit</option>
                                </select>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" :disabled="!complete">Register Team</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('tournamentRegisterForm', (divisions, searchUrl) => ({
        divisions,
        searchUrl,
        divisionId: '',
        slots: [],

        get division() {
            return this.divisions.find(d => d.id === Number(this.divisionId)) || null;
        },
        get complete() {
            return !!this.division && this.slots.length > 0 && this.slots.every(s => s.member);
        },

        init() {
            this.$watch('divisionId', () => this.rebuildSlots());
        },
        rebuildSlots() {
            const size = this.division ? this.division.team_size : 0;
            this.slots = Array.from({ length: size }, () => ({
                query: '', results: [], member: null, searched: false,
            }));
        },
        async search(idx) {
            const slot = this.slots[idx];
            if (!slot || slot.query.trim().length < 2) {
                if (slot) { slot.results = []; slot.searched = false; }
                return;
            }
            try {
                const { data } = await window.axios.get(this.searchUrl, { params: { q: slot.query.trim() } });
                slot.results = data.customers || [];
                slot.searched = true;
            } catch (e) {
                slot.results = [];
                slot.searched = true;
            }
        },
        pick(idx, member) {
            if (this.isPicked(member.id)) return;
            this.slots[idx].member = member;
            this.slots[idx].results = [];
            this.slots[idx].query = '';
        },
        clearSlot(idx) {
            this.slots[idx].member = null;
            this.slots[idx].results = [];
            this.slots[idx].query = '';
            this.slots[idx].searched = false;
        },
        isPicked(id) {
            return this.slots.some(s => s.member && s.member.id === id);
        },
    }));
});
</script>
@endpush
@endonce
