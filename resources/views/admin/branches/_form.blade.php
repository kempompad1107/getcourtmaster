@php
    $branch ??= null;
    $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
    $hours = old('operating_hours', $branch?->operating_hours ?? []);
@endphp

@push('styles')
<style>
.hours-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding: .75rem 1.25rem;
}
.hours-times {
    display: flex;
    align-items: center;
    gap: .5rem;
    flex-shrink: 0;
}
.hours-times .form-control { width: 118px; }
@media (max-width: 420px) {
    .hours-row { flex-direction: column; align-items: flex-start; gap: .35rem; }
    .hours-times { width: 100%; }
    .hours-times .form-control { flex: 1; width: auto; }
}
</style>
@endpush

{{-- Basic info --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">Branch Information</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-sm-8">
                <label class="form-label fw-medium">Branch name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $branch?->name) }}" required
                       placeholder="e.g. Main Branch"
                       class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-4">
                <label class="form-label fw-medium">
                    Slug
                    <span class="text-muted fw-normal small">optional</span>
                </label>
                <input type="text" name="slug" value="{{ old('slug', $branch?->slug) }}"
                       placeholder="auto from name"
                       class="form-control font-monospace @error('slug') is-invalid @enderror">
                @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label fw-medium">Address</label>
                <input type="text" name="address" value="{{ old('address', $branch?->address) }}"
                       placeholder="Street address"
                       class="form-control @error('address') is-invalid @enderror">
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-sm-6">
                <label class="form-label fw-medium">City</label>
                <input type="text" name="city" value="{{ old('city', $branch?->city) }}"
                       placeholder="City" class="form-control">
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-medium">Phone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                    <input type="text" name="phone" value="{{ old('phone', $branch?->phone) }}"
                           placeholder="+63 9XX XXX XXXX" class="form-control">
                </div>
            </div>
            <div class="col-sm-3">
                <label class="form-label fw-medium">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" value="{{ old('email', $branch?->email) }}"
                           placeholder="branch@example.com"
                           class="form-control @error('email') is-invalid @enderror">
                </div>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <label class="form-label fw-medium d-flex align-items-center gap-1">
                    Google Maps link
                    <span class="position-relative d-inline-flex" style="line-height:1" x-data="{ tip: false }">
                        <i class="bi bi-info-circle text-muted"
                           style="font-size:.8rem;cursor:default"
                           @mouseenter="tip=true" @mouseleave="tip=false"></i>
                        <div x-show="tip" x-cloak x-transition.opacity.duration.150ms
                             class="position-absolute bottom-100 start-50 translate-middle-x mb-2 px-3 py-2 rounded-2 shadow-sm small text-nowrap"
                             style="background:var(--bs-dark,#1e293b);color:#fff;z-index:200;font-weight:400;line-height:1.5;pointer-events:none">
                            On Google Maps, search the branch → click <strong style="color:#fff">Share</strong> → <strong style="color:#fff">Copy link</strong>, then paste it here.
                            <div class="position-absolute start-50 translate-middle-x"
                                 style="top:100%;border:5px solid transparent;border-top-color:var(--bs-dark,#1e293b);width:0;height:0"></div>
                        </div>
                    </span>
                </label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <input type="url" name="map_url"
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
            </div>
        </div>
    </div>
</div>

{{-- Operating hours --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">Operating Hours</h6>
    </div>
    <div class="card-body p-0">
        @foreach($days as $day)
        @php
            $row    = $hours[$day] ?? ['is_open' => false, 'open' => '08:00', 'close' => '22:00'];
            $isOpen = (bool) ($row['is_open'] ?? false);
        @endphp
        <div class="hours-row {{ !$loop->last ? 'border-bottom' : '' }}">
            <input type="hidden" name="operating_hours[{{ $day }}][is_open]" value="0">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox"
                       name="operating_hours[{{ $day }}][is_open]" value="1"
                       id="hours_{{ $day }}" @checked($isOpen)>
                <label class="form-check-label fw-medium" for="hours_{{ $day }}">
                    {{ ucfirst($day) }}
                </label>
            </div>
            <div class="hours-times">
                <input type="time" name="operating_hours[{{ $day }}][open]"
                       value="{{ $row['open'] ?? '08:00' }}"
                       class="form-control form-control-sm">
                <span class="text-muted small">to</span>
                <input type="time" name="operating_hours[{{ $day }}][close]"
                       value="{{ $row['close'] ?? '22:00' }}"
                       class="form-control form-control-sm">
                @if(!$isOpen)
                <span class="badge text-bg-secondary ms-1" style="font-size:.65rem;white-space:nowrap">Closed</span>
                @endif
            </div>
        </div>
        @endforeach
        <div class="px-4 py-2 border-top">
            <p class="form-text mb-0">Uncheck a day to mark it closed. Times are local to the tenant timezone.</p>
        </div>
    </div>
</div>

{{-- Settings & submit --}}
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0 fw-semibold">Settings</h6>
    </div>
    <div class="card-body d-flex gap-4">
        <div class="form-check">
            <input type="hidden" name="is_main" value="0">
            <input class="form-check-input" type="checkbox" name="is_main" id="is_main"
                   value="1" @checked(old('is_main', $branch?->is_main))>
            <label class="form-check-label fw-medium" for="is_main">
                Main branch
                <small class="d-block text-muted fw-normal">Primary location</small>
            </label>
        </div>
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" @checked(old('is_active', $branch?->is_active ?? true))>
            <label class="form-check-label fw-medium" for="is_active">
                Active
                <small class="d-block text-muted fw-normal">Visible to customers</small>
            </label>
        </div>
    </div>
</div>

{{-- Actions --}}
<div class="d-flex justify-content-end gap-2 mb-4">
    <a href="{{ route('admin.branches.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-lg me-1"></i>{{ $submitLabel ?? 'Save Branch' }}
    </button>
</div>
