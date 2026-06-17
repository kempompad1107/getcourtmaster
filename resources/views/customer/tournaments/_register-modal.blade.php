{{-- Customer self-registration modal for one division. --}}
@php $fee = $tournament->effectiveEntryFee($division); @endphp
<div class="modal fade" id="registerModal-{{ $division->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content"
             @if(!$division->isSingles()) x-data="partnerPicker(@js(route('customer.tournaments.partner-search')))" @endif>
            <form method="POST" action="{{ route('customer.tournaments.register', $division) }}">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title fw-semibold">Register — {{ $division->name }}</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($fee > 0)
                    <div class="alert alert-info py-2 small mb-3">
                        Entry fee: <strong>{{ $tournament->currency }} {{ number_format($fee, 2) }}</strong> per player.
                    </div>
                    @endif

                    <div class="row g-2 mb-3">
                        <div class="col-12"><span class="small fw-semibold">Your details</span></div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Skill level</label>
                            <input type="text" name="skill_level" maxlength="50" class="form-control form-control-sm" placeholder="e.g. 3.5 / Intermediate">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Rating (optional)</label>
                            <input type="number" name="rating" step="0.01" min="0" max="10" class="form-control form-control-sm" placeholder="e.g. 3.75">
                        </div>
                    </div>

                    @if(!$division->isSingles())
                    <div class="mb-3">
                        <span class="small fw-semibold d-block mb-1">Your partner</span>

                        <template x-if="partner">
                            <div class="d-flex align-items-center gap-2 border rounded p-2">
                                <div class="bk-avatar flex-shrink-0" x-text="partner.name.substring(0, 1).toUpperCase()"></div>
                                <div class="min-w-0 flex-grow-1">
                                    <p class="mb-0 small fw-medium" x-text="partner.name"></p>
                                    <small class="text-muted" x-text="partner.email"></small>
                                </div>
                                <button type="button" class="btn btn-link btn-sm p-0 text-danger" @click="partner = null">Change</button>
                                <input type="hidden" name="partner_user_id" :value="partner.id">
                            </div>
                        </template>

                        <template x-if="!partner">
                            <div class="position-relative">
                                <div class="search-field">
                                    <i class="bi bi-search"></i>
                                    <input type="text" placeholder="Search a member (min 3 letters)…"
                                           x-model="query" @input.debounce.350ms="search()" autocomplete="off">
                                </div>
                                <div class="card shadow position-absolute start-0 end-0 mt-1" style="z-index:1060;max-height:200px;overflow-y:auto;"
                                     x-show="results.length > 0" x-cloak>
                                    <div class="list-group list-group-flush">
                                        <template x-for="result in results" :key="result.id">
                                            <button type="button" class="list-group-item list-group-item-action py-2" @click="pick(result)">
                                                <span class="small fw-medium" x-text="result.name"></span>
                                                <small class="text-muted d-block" x-text="result.email"></small>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <small class="text-muted" x-show="searched && results.length === 0 && query.length >= 3">No members match.</small>
                            </div>
                        </template>

                        <div class="row g-2 mt-1">
                            <div class="col-6">
                                <label class="form-label small mb-1">Partner skill</label>
                                <input type="text" name="partner_skill_level" maxlength="50" class="form-control form-control-sm">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1">Partner rating</label>
                                <input type="number" name="partner_rating" step="0.01" min="0" max="10" class="form-control form-control-sm">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label small mb-1">Team name (optional)</label>
                            <input type="text" name="team_name" maxlength="150" class="form-control form-control-sm" placeholder="Auto from last names">
                        </div>
                    </div>
                    @endif

                    @if($fee > 0)
                    <div class="form-check mb-2">
                        <input type="hidden" name="pay_with_wallet" value="0">
                        <input class="form-check-input" type="checkbox" name="pay_with_wallet" id="payWallet-{{ $division->id }}" value="1"
                               @checked(auth()->user()->wallet_balance >= $fee * $division->team_size)>
                        <label class="form-check-label small" for="payWallet-{{ $division->id }}">
                            Pay {{ $tournament->currency }} {{ number_format($fee * $division->team_size, 2) }} from my wallet
                            (balance: {{ number_format(auth()->user()->wallet_balance, 2) }})
                        </label>
                    </div>
                    @endif

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="waiver_accepted" id="waiver-{{ $division->id }}" value="1" required>
                        <label class="form-check-label small" for="waiver-{{ $division->id }}">
                            I accept the tournament rules{{ $tournament->waiver ? ' and waiver' : '' }}.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"
                            @if(!$division->isSingles()) :disabled="!partner" @endif>Register</button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('partnerPicker', (searchUrl) => ({
        searchUrl,
        query: '',
        results: [],
        partner: null,
        searched: false,

        async search() {
            if (this.query.trim().length < 3) {
                this.results = [];
                this.searched = false;
                return;
            }
            try {
                const { data } = await window.axios.get(this.searchUrl, { params: { q: this.query.trim() } });
                this.results = data.members || [];
                this.searched = true;
            } catch (e) {
                this.results = [];
                this.searched = true;
            }
        },
        pick(member) {
            this.partner = member;
            this.results = [];
            this.query = '';
        },
    }));
});
</script>
@endpush
@endonce
