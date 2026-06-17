<div class="card">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Brackets by Division</h6></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-stack">
            <thead class="table-light">
                <tr>
                    <th>Division</th>
                    <th class="text-center">Teams</th>
                    <th class="text-center">Format</th>
                    <th class="text-center">Seeding</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tournament->divisions as $division)
                <tr>
                    <td data-label="Division"><span class="small fw-semibold">{{ $division->name }}</span></td>
                    <td data-label="Teams" class="text-center"><span class="small">{{ $division->teams_count }}</span></td>
                    <td data-label="Format" class="text-center"><span class="small">{{ $division->formatLabel() }}</span></td>
                    <td data-label="Seeding" class="text-center"><span class="small">{{ ucfirst($division->seeding_method) }}</span></td>
                    <td data-label="Status">
                        @if($division->bracketLocked())
                        <x-badge status="active">Generated {{ $division->bracket_generated_at->format('M j') }}</x-badge>
                        @else
                        <x-badge status="neutral">Not generated</x-badge>
                        @endif
                    </td>
                    <td data-label="" class="cell-actions text-end">
                        <a href="{{ route('admin.tournaments.brackets.show', $division) }}" class="btn btn-outline-primary btn-sm">
                            {{ $division->bracketLocked() ? 'View Bracket' : 'Set Up Bracket' }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="cell-plain">
                        <x-empty-state title="No divisions yet"
                            description="Add divisions first — each division gets its own bracket."
                            icon="bi-bezier2"/>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
