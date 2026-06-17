<div class="row g-4">
    <div class="col-12 col-lg-7">
        <x-card title="About">
            @if($tournament->logo && ($logoUrl = file_url($tournament->logo)))
            <img src="{{ $logoUrl }}" alt="Logo" class="rounded mb-3" style="max-width:72px;max-height:72px;object-fit:cover;">
            @endif
            <p class="small mb-3" style="white-space:pre-line;">{{ $tournament->description ?: 'No description yet.' }}</p>

            <dl class="row small mb-0">
                <dt class="col-sm-4 text-muted fw-medium">Organizer</dt>
                <dd class="col-sm-8">{{ $tournament->organizer_name ?: '—' }}</dd>
                <dt class="col-sm-4 text-muted fw-medium">Contact</dt>
                <dd class="col-sm-8">
                    {{ $tournament->contact_phone ?: '—' }}
                    @if($tournament->contact_email)<span class="text-muted d-block">{{ $tournament->contact_email }}</span>@endif
                </dd>
                <dt class="col-sm-4 text-muted fw-medium">Venue</dt>
                <dd class="col-sm-8">
                    {{ $tournament->venue ?: '—' }}
                    @if($tournament->address)<span class="text-muted d-block">{{ $tournament->address }}</span>@endif
                    @if($tournament->google_maps_url)
                    <a href="{{ $tournament->google_maps_url }}" target="_blank" rel="noopener" class="d-inline-flex align-items-center gap-1">
                        Open map <i class="bi bi-box-arrow-up-right small"></i>
                    </a>
                    @endif
                </dd>
                <dt class="col-sm-4 text-muted fw-medium">Registration</dt>
                <dd class="col-sm-8">
                    {{ $tournament->registration_opens_at?->format('M j, Y g:i A') ?? '—' }}
                    → {{ $tournament->registration_closes_at?->format('M j, Y g:i A') ?? '—' }}
                </dd>
                <dt class="col-sm-4 text-muted fw-medium">Entry fee</dt>
                <dd class="col-sm-8">{{ $tournament->currency }} {{ number_format($tournament->entry_fee, 2) }} per player</dd>
                <dt class="col-sm-4 text-muted fw-medium">Max participants</dt>
                <dd class="col-sm-8">{{ $tournament->max_participants ?: 'No cap' }}</dd>
            </dl>
        </x-card>
    </div>
    <div class="col-12 col-lg-5">
        @if($tournament->rules)
        <x-card title="Rules" class="mb-4">
            <div class="small text-muted" style="white-space:pre-line;max-height:260px;overflow-y:auto;">{{ $tournament->rules }}</div>
        </x-card>
        @endif
        @if($tournament->waiver)
        <x-card title="Waiver">
            <div class="small text-muted" style="white-space:pre-line;max-height:200px;overflow-y:auto;">{{ $tournament->waiver }}</div>
        </x-card>
        @endif
        @if(!$tournament->rules && !$tournament->waiver)
        <x-card title="Rules & Waiver">
            <p class="small text-muted mb-0">No rules or waiver added yet. <a href="{{ route('admin.tournaments.edit', $tournament) }}">Add them</a> so players see them at registration.</p>
        </x-card>
        @endif
    </div>
</div>
