{{--
    Seed-order editor: drag to reorder (SortableJS) with up/down buttons as the
    touch/keyboard fallback. Saves via POST to brackets.seeds. Used only while
    the bracket is not generated.
--}}
<x-card title="Seed Order" subtitle="Drag to reorder. Used when seeding is set to Manual.">
    @if($teams->isEmpty())
    <p class="small text-muted mb-0">No teams registered yet.</p>
    @else
    <form method="POST" action="{{ route('admin.tournaments.brackets.seeds', $division) }}" x-data="seedList()">
        @csrf
        <ul class="seed-list" x-ref="list">
            @foreach($teams as $team)
            <li class="seed-item" data-id="{{ $team->id }}">
                <i class="bi bi-grip-vertical seed-handle"></i>
                <span class="seed-num">{{ $loop->iteration }}</span>
                <div class="min-w-0 flex-grow-1">
                    <span class="small fw-medium d-block text-truncate">{{ $team->name }}</span>
                    <small class="text-muted">
                        {{ $team->members->map(fn ($m) => $m->user->name)->implode(' / ') }}
                        @if($team->averageRating())· avg {{ number_format($team->averageRating(), 2) }}@endif
                    </small>
                </div>
                <div class="btn-group btn-group-sm flex-shrink-0">
                    <button type="button" class="btn btn-outline-secondary" @click="move($el.closest('li'), -1)" aria-label="Move up"><i class="bi bi-chevron-up"></i></button>
                    <button type="button" class="btn btn-outline-secondary" @click="move($el.closest('li'), 1)" aria-label="Move down"><i class="bi bi-chevron-down"></i></button>
                </div>
                {{-- Travels with the <li>, so DOM order = submitted order --}}
                <input type="hidden" name="ordered_ids[]" value="{{ $team->id }}">
            </li>
            @endforeach
        </ul>
        @if(auth()->user()->can('manageBrackets', $division->tournament))
        <button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-2">Save Seed Order</button>
        @endif
    </form>
    @endif
</x-card>

@once
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('seedList', () => ({
        init() {
            if (window.Sortable) {
                window.Sortable.create(this.$refs.list, {
                    handle: '.seed-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: () => this.renumber(),
                });
            }
        },
        move(li, delta) {
            const sibling = delta < 0 ? li.previousElementSibling : li.nextElementSibling;
            if (!sibling) return;
            delta < 0 ? li.parentNode.insertBefore(li, sibling) : li.parentNode.insertBefore(sibling, li);
            this.renumber();
        },
        renumber() {
            this.$refs.list.querySelectorAll('li').forEach((li, i) => {
                li.querySelector('.seed-num').textContent = i + 1;
            });
        },
    }));
});
</script>
@endpush
@endonce
