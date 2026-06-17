@php $tournament ??= null; @endphp

@php
    $branches ??= collect();
    $isAllBranches = old('is_all_branches', $tournament?->is_all_branches ?? true);
    $isAllBranches = filter_var($isAllBranches, FILTER_VALIDATE_BOOLEAN);
    $selectedBranch = old('branch_id', $tournament?->branch_id);
    // When the user can only reach a single branch (staff scoped to their home
    // branch), there is nothing to choose — the exclusive branch is implicitly
    // theirs, so we skip the picker and bind it automatically.
    $singleBranch = $branches->count() === 1 ? $branches->first() : null;
    if ($singleBranch && ! $selectedBranch) {
        $selectedBranch = $singleBranch->id;
    }
@endphp

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Branch Scope</h6></div>
    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input type="hidden" name="is_all_branches" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_all_branches"
                   name="is_all_branches" value="1" {{ $isAllBranches ? 'checked' : '' }}
                   onchange="document.getElementById('branch-picker').classList.toggle('d-none', this.checked)">
            <label class="form-check-label fw-medium" for="is_all_branches">Open to all branches</label>
            <div class="form-text">Turn off to make this tournament exclusive to a single branch.</div>
        </div>
        <div id="branch-picker" class="{{ $isAllBranches ? 'd-none' : '' }}">
            @if($singleBranch)
                {{-- Single-branch staff: the exclusive branch is automatically their own. --}}
                <input type="hidden" name="branch_id" value="{{ $singleBranch->id }}">
                <label class="form-label fw-medium">Branch</label>
                <div class="form-control-plaintext fw-semibold">
                    <i class="bi bi-geo-alt me-1 text-primary"></i>{{ $singleBranch->name }}
                </div>
                <div class="form-text">This tournament will be exclusive to your branch.</div>
            @else
                <label class="form-label fw-medium">Branch <span class="text-danger">*</span></label>
                <select name="branch_id" class="form-select @error('branch_id') is-invalid @enderror">
                    <option value="">Select a branch…</option>
                    @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((int) $selectedBranch === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            @endif
            @error('branch_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Tournament Details</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-8">
                <label class="form-label fw-medium">Tournament name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $tournament?->name) }}" required maxlength="150"
                       class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Visibility <span class="text-danger">*</span></label>
                <select name="visibility" class="form-select @error('visibility') is-invalid @enderror">
                    <option value="private" @selected(old('visibility', $tournament?->visibility ?? 'private') === 'private')>Private</option>
                    <option value="public" @selected(old('visibility', $tournament?->visibility) === 'public')>Public (visible in customer portal)</option>
                </select>
                @error('visibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Description</label>
                <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                          placeholder="What players should know about this event…">{{ old('description', $tournament?->description) }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-sm-6">
                <label class="form-label fw-medium">Cover image (banner)</label>
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"
                       class="form-control @error('cover_image') is-invalid @enderror">
                @error('cover_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">JPG, PNG or WebP. Max 5 MB.</div>
                @if($tournament?->cover_image && ($banner = file_url($tournament->cover_image)))
                <img src="{{ $banner }}" alt="Current banner" class="rounded mt-2" style="max-width:240px;max-height:120px;object-fit:cover;">
                @endif
            </div>
            <div class="col-sm-6">
                <label class="form-label fw-medium">Logo</label>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"
                       class="form-control @error('logo') is-invalid @enderror">
                @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Square works best. Max 5 MB.</div>
                @if($tournament?->logo && ($logoUrl = file_url($tournament->logo)))
                <img src="{{ $logoUrl }}" alt="Current logo" class="rounded mt-2" style="max-width:80px;max-height:80px;object-fit:cover;">
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Venue & Organizer</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label fw-medium">Venue</label>
                <input type="text" name="venue" value="{{ old('venue', $tournament?->venue) }}"
                       class="form-control @error('venue') is-invalid @enderror">
                @error('venue')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label class="form-label fw-medium">Address</label>
                <input type="text" name="address" value="{{ old('address', $tournament?->address) }}"
                       class="form-control @error('address') is-invalid @enderror">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Google Maps link</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <input type="url" name="google_maps_url" value="{{ old('google_maps_url', $tournament?->google_maps_url) }}"
                           placeholder="https://maps.app.goo.gl/…"
                           class="form-control @error('google_maps_url') is-invalid @enderror">
                    @if(!empty(old('google_maps_url', $tournament?->google_maps_url)))
                    <a href="{{ old('google_maps_url', $tournament?->google_maps_url) }}" target="_blank" rel="noopener"
                       class="btn btn-outline-secondary" title="Open in new tab">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    @endif
                </div>
                @error('google_maps_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Organizer</label>
                <input type="text" name="organizer_name" value="{{ old('organizer_name', $tournament?->organizer_name) }}"
                       class="form-control @error('organizer_name') is-invalid @enderror">
                @error('organizer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Contact number</label>
                <input type="text" name="contact_phone" value="{{ old('contact_phone', $tournament?->contact_phone) }}" maxlength="30"
                       class="form-control @error('contact_phone') is-invalid @enderror">
                @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Email</label>
                <input type="email" name="contact_email" value="{{ old('contact_email', $tournament?->contact_email) }}"
                       class="form-control @error('contact_email') is-invalid @enderror">
                @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Schedule & Capacity</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-6 col-lg-3">
                <label class="form-label fw-medium">Registration opens</label>
                <input type="datetime-local" name="registration_opens_at"
                       value="{{ old('registration_opens_at', $tournament?->registration_opens_at?->format('Y-m-d\TH:i')) }}"
                       class="form-control @error('registration_opens_at') is-invalid @enderror">
                @error('registration_opens_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label fw-medium">Registration closes</label>
                <input type="datetime-local" name="registration_closes_at"
                       value="{{ old('registration_closes_at', $tournament?->registration_closes_at?->format('Y-m-d\TH:i')) }}"
                       class="form-control @error('registration_closes_at') is-invalid @enderror">
                @error('registration_closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label fw-medium">Tournament starts</label>
                <input type="datetime-local" name="starts_at"
                       value="{{ old('starts_at', $tournament?->starts_at?->format('Y-m-d\TH:i')) }}"
                       class="form-control @error('starts_at') is-invalid @enderror">
                @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label class="form-label fw-medium">Tournament ends</label>
                <input type="datetime-local" name="ends_at"
                       value="{{ old('ends_at', $tournament?->ends_at?->format('Y-m-d\TH:i')) }}"
                       class="form-control @error('ends_at') is-invalid @enderror">
                @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Maximum participants</label>
                <input type="number" name="max_participants" min="2" max="10000"
                       value="{{ old('max_participants', $tournament?->max_participants) }}"
                       class="form-control @error('max_participants') is-invalid @enderror">
                @error('max_participants')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Leave blank for no overall cap.</div>
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Default entry fee <span class="text-danger">*</span></label>
                <input type="number" name="entry_fee" step="0.01" min="0" required
                       value="{{ old('entry_fee', $tournament?->entry_fee ?? '0.00') }}"
                       class="form-control @error('entry_fee') is-invalid @enderror">
                @error('entry_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Per player. Divisions may override this.</div>
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Currency <span class="text-danger">*</span></label>
                <input type="text" name="currency" maxlength="3" required
                       value="{{ old('currency', $tournament?->currency ?? $defaultCurrency ?? 'PHP') }}"
                       class="form-control text-uppercase @error('currency') is-invalid @enderror">
                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Rules & Waiver</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-medium">Tournament rules</label>
                <textarea name="rules" rows="5" class="form-control @error('rules') is-invalid @enderror"
                          placeholder="Format, scoring, conduct…">{{ old('rules', $tournament?->rules) }}</textarea>
                @error('rules')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label fw-medium">Waiver</label>
                <textarea name="waiver" rows="4" class="form-control @error('waiver') is-invalid @enderror"
                          placeholder="Liability waiver players accept when registering…">{{ old('waiver', $tournament?->waiver) }}</textarea>
                @error('waiver')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <a href="{{ $tournament ? route('admin.tournaments.show', $tournament) : route('admin.tournaments.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">{{ $submitLabel ?? 'Save Tournament' }}</button>
</div>
