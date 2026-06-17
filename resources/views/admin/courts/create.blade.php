@extends('layouts.app')
@section('title', 'Add Court')

@section('content')

<x-page-header title="Add New Court" :back="route('admin.courts.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('admin.courts.store') }}" enctype="multipart/form-data">
    @csrf

    {{-- Basic info --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Basic Information</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-medium">Court name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Type <span class="text-danger">*</span></label>
                    <select name="type" required class="form-select">
                        <option value="indoor"  @selected(old('type') === 'indoor')>Indoor</option>
                        <option value="outdoor" @selected(old('type') === 'outdoor')>Outdoor</option>
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-medium">Capacity (players)</label>
                    <input type="number" name="capacity" value="{{ old('capacity', 4) }}" min="1" max="20"
                           class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label fw-medium">Description</label>
                    <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
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
                    <label class="form-label fw-medium">Daylight rate (₱/hr) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="base_hourly_rate" value="{{ old('base_hourly_rate', 400) }}"
                               min="0" step="50" required class="form-control">
                    </div>
                    <div class="form-text">Applied during daytime hours (outside the evening window set in <a href="{{ route('admin.settings.index') }}">Settings → Booking</a>).</div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Evening rate (₱/hr)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="peak_hourly_rate" value="{{ old('peak_hourly_rate', 600) }}"
                               min="0" step="50" class="form-control">
                    </div>
                    <div class="form-text">Applied during evening hours.</div>
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Weekend rate (₱/hr)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" name="weekend_hourly_rate" value="{{ old('weekend_hourly_rate') }}"
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
                    <input type="number" name="min_booking_minutes" value="{{ old('min_booking_minutes', 60) }}"
                           min="15" step="15" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Max booking (min)</label>
                    <input type="number" name="max_booking_minutes" value="{{ old('max_booking_minutes', 240) }}"
                           min="30" step="30" class="form-control">
                </div>
                <div class="col-sm-4">
                    <label class="form-label fw-medium">Buffer time (min)</label>
                    <input type="number" name="buffer_minutes" value="{{ old('buffer_minutes', 15) }}"
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
                               @checked(in_array($amenity, old('amenities', [])))>
                        <label class="form-check-label small" for="amenity_{{ $amenity }}">
                            {{ str_replace('_', ' ', ucfirst($amenity)) }}
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Photos --}}
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0 fw-semibold">Photos</h6></div>
        <div class="card-body">
            <input type="file" name="photos[]" multiple accept="image/*" class="form-control">
            <div class="form-text">Up to 5 photos. JPEG or PNG, max 5MB each.</div>
        </div>
    </div>

    {{-- Footer actions --}}
    <div class="d-flex align-items-center justify-content-between">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" checked>
            <label class="form-check-label fw-medium" for="is_active">Active (visible for booking)</label>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.courts.index') }}" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Create Court</button>
        </div>
    </div>

</form>
</div>
</div>

@endsection
