@php
    $branch ??= null;
    $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    $hours = old('operating_hours', $branch?->operating_hours ?? []);
@endphp

{{-- Basic info --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Branch Information</h6></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-8">
                <label class="form-label fw-medium">Branch name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $branch?->name) }}" required
                       class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">Slug</label>
                <input type="text" name="slug" value="{{ old('slug', $branch?->slug) }}"
                       placeholder="auto from name"
                       class="form-control @error('slug') is-invalid @enderror">
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label fw-medium">Address</label>
                <input type="text" name="address" value="{{ old('address', $branch?->address) }}"
                       class="form-control @error('address') is-invalid @enderror">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-sm-6">
                <label class="form-label fw-medium">City</label>
                <input type="text" name="city" value="{{ old('city', $branch?->city) }}"
                       class="form-control">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-medium">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $branch?->phone) }}"
                       class="form-control">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-medium">Email</label>
                <input type="email" name="email" value="{{ old('email', $branch?->email) }}"
                       class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label fw-medium">
                    Google Maps link
                    <span class="text-muted small fw-normal">(used by the "Directions" button on your public page)</span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <input type="url" name="map_url" id="map_url_input"
                           value="{{ old('map_url', $branch?->map_url) }}"
                           placeholder="https://maps.app.goo.gl/..."
                           class="form-control @error('map_url') is-invalid @enderror">
                    @if(!empty(old('map_url', $branch?->map_url)))
                        <a href="{{ old('map_url', $branch?->map_url) }}" target="_blank" rel="noopener"
                           class="btn btn-outline-secondary" title="Open in new tab">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    @endif
                </div>
                @error('map_url')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">
                    On Google Maps, search the branch &rarr; click <strong>Share</strong> &rarr; <strong>Copy link</strong>, then paste it here.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Operating hours --}}
<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0 fw-semibold">Operating Hours</h6></div>
    <div class="card-body">
        @foreach($days as $day)
            @php
                $row = $hours[$day] ?? ['is_open' => false, 'open' => '08:00', 'close' => '22:00'];
                $isOpen = (bool) ($row['is_open'] ?? false);
            @endphp
            <div class="row g-2 align-items-center mb-2">
                <div class="col-4 col-sm-3">
                    <div class="form-check">
                        <input type="hidden" name="operating_hours[{{ $day }}][is_open]" value="0">
                        <input class="form-check-input" type="checkbox"
                               name="operating_hours[{{ $day }}][is_open]" value="1"
                               id="hours_{{ $day }}" @checked($isOpen)>
                        <label class="form-check-label fw-medium" for="hours_{{ $day }}">
                            {{ ucfirst($day) }}
                        </label>
                    </div>
                </div>
                <div class="col-4 col-sm-3">
                    <input type="time" name="operating_hours[{{ $day }}][open]"
                           value="{{ $row['open'] ?? '08:00' }}" class="form-control form-control-sm">
                </div>
                <div class="col-4 col-sm-3">
                    <input type="time" name="operating_hours[{{ $day }}][close]"
                           value="{{ $row['close'] ?? '22:00' }}" class="form-control form-control-sm">
                </div>
            </div>
        @endforeach
        <div class="form-text">Uncheck a day to mark it closed. Times are local to the tenant timezone.</div>
    </div>
</div>

{{-- Status --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="d-flex gap-4">
        <div class="form-check">
            <input type="hidden" name="is_main" value="0">
            <input class="form-check-input" type="checkbox" name="is_main" id="is_main"
                   value="1" @checked(old('is_main', $branch?->is_main))>
            <label class="form-check-label fw-medium" for="is_main">Main branch</label>
        </div>
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" @checked(old('is_active', $branch?->is_active ?? true))>
            <label class="form-check-label fw-medium" for="is_active">Active</label>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.branches.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel ?? 'Save Branch' }}</button>
    </div>
</div>
