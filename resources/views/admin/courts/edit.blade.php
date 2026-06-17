@extends('layouts.app')
@section('title', 'Edit ' . $court->name)

@section('content')

<x-page-header :title="'Edit: ' . $court->name" :back="route('admin.courts.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('admin.courts.update', $court) }}" enctype="multipart/form-data">
    @csrf @method('PUT')

    {{-- Basic info --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Basic Information</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-medium">Branch</label>
                    @if($branches->count() === 1)
                        @php $only = $branches->first(); @endphp
                        <input type="hidden" name="branch_id" value="{{ $only->id }}">
                        <input type="text" value="{{ $only->name }}" disabled class="form-control">
                    @else
                        <select name="branch_id" required class="form-select @error('branch_id') is-invalid @enderror">
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}"
                                    @selected(old('branch_id', $court->branch_id) == $b->id)>
                                    {{ $b->name }}@if($b->is_main) (Main)@endif
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div class="col-12">
                    <label class="form-label fw-medium">Court name</label>
                    <input type="text" name="name" value="{{ old('name', $court->name) }}" required
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Type</label>
                    <select name="type" class="form-select">
                        <option value="indoor"  @selected(old('type', $court->type) === 'indoor')>Indoor</option>
                        <option value="outdoor" @selected(old('type', $court->type) === 'outdoor')>Outdoor</option>
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Capacity</label>
                    <input type="number" name="capacity" value="{{ old('capacity', $court->capacity) }}"
                           min="1" max="20" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Description</label>
                    <textarea name="description" rows="2" class="form-control">{{ old('description', $court->description) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- Pricing --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Pricing</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Daylight rate (₱/hr)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="base_hourly_rate"
                               value="{{ old('base_hourly_rate', $court->base_hourly_rate) }}"
                               min="0" step="50" class="form-control">
                    </div>
                    <div class="form-text">Applied during daytime hours (outside the evening window set in <a href="{{ route('admin.settings.index') }}">Settings → Booking</a>).</div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Evening rate (₱/hr)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="peak_hourly_rate"
                               value="{{ old('peak_hourly_rate', $court->peak_hourly_rate) }}"
                               min="0" step="50" class="form-control">
                    </div>
                    <div class="form-text">Applied during evening hours.</div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Weekend rate (₱/hr)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="weekend_hourly_rate"
                               value="{{ old('weekend_hourly_rate', $court->weekend_hourly_rate) }}"
                               min="0" step="50" class="form-control">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Booking rules --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Booking Rules</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Min booking (min)</label>
                    <input type="number" name="min_booking_minutes"
                           value="{{ old('min_booking_minutes', $court->min_booking_minutes) }}"
                           min="15" step="15" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Max booking (min)</label>
                    <input type="number" name="max_booking_minutes"
                           value="{{ old('max_booking_minutes', $court->max_booking_minutes) }}"
                           min="30" step="30" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Buffer time (min)</label>
                    <input type="number" name="buffer_minutes"
                           value="{{ old('buffer_minutes', $court->buffer_minutes) }}"
                           min="0" max="60" step="5" class="form-control">
                </div>
            </div>
        </div>
    </div>

    {{-- Amenities --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Amenities</h6></div>
        <div class="card-body">
            <div class="row g-2">
                @foreach(['paddles','balls','water','lighting','air_conditioning','showers','lockers','wifi','parking','spectator_area'] as $amenity)
                <div class="col-6 col-sm-4 col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="amenities[]"
                               id="amenity_{{ $amenity }}" value="{{ $amenity }}"
                               @checked(in_array($amenity, old('amenities', $court->amenities ?? [])))>
                        <label class="form-check-label small" for="amenity_{{ $amenity }}">
                            {{ str_replace('_', ' ', ucfirst($amenity)) }}
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Current photos --}}
    @if($court->hasMedia('photos'))
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Current Photos</h6></div>
        <div class="card-body">
            <div class="row g-2">
                @foreach($court->getMedia('photos') as $media)
                {{-- The delete <form> lives outside the main update form (below) to
                     avoid nesting forms, which the HTML parser rejects — a nested
                     form silently closes the outer form and orphans the submit
                     button. The button is associated back via the `form` attribute. --}}
                <div class="col-4 col-sm-3 col-md-2 position-relative">
                    <img src="{{ $media->getUrl() }}" alt=""
                         class="img-thumbnail w-100" style="height:80px;object-fit:cover">
                    <button type="submit" form="delete-media-{{ $media->id }}"
                            class="btn btn-outline-danger btn-sm p-0 lh-1 position-absolute top-0 end-0 m-1"
                            style="width:20px;height:20px;font-size:.65rem" title="Delete">&times;</button>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Add photos --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Add Photos</h6></div>
        <div class="card-body">
            <input type="file" name="photos[]" multiple accept="image/*" class="form-control">
        </div>
    </div>

    {{-- Footer actions --}}
    <div class="d-flex align-items-center justify-content-between">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                   value="1" @checked(old('is_active', $court->is_active))>
            <label class="form-check-label fw-medium" for="is_active">Active</label>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.courts.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Update Court</button>
        </div>
    </div>

</form>

{{-- Photo-delete forms, kept out of the main update form to avoid nested forms.
     Each is targeted by its button in the "Current Photos" card via form=""... --}}
@if($court->hasMedia('photos'))
    @foreach($court->getMedia('photos') as $media)
    <form id="delete-media-{{ $media->id }}" method="POST"
          action="{{ route('admin.courts.media.destroy', [$court, $media->id]) }}" class="d-none">
        @csrf @method('DELETE')
    </form>
    @endforeach
@endif
</div>
</div>

@endsection
