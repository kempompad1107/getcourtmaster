@extends('layouts.app')
@section('title', 'New Customer')

@section('content')

<x-page-header title="New Customer" :back="route('admin.customers.index')" backLabel="Customers"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-8 col-xl-7">

<form method="POST" action="{{ route('admin.customers.store') }}">
    @csrf

    <div class="card mb-4">
        <div class="card-body">

            <p class="text-muted small fw-semibold text-uppercase letter-spacing-wide mb-3"
               style="font-size:.68rem;letter-spacing:.07em;color:var(--bs-secondary-color)">
                Customer Details
            </p>

            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label">Full name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           class="form-control @error('name') is-invalid @enderror"
                           placeholder="e.g. Juan dela Cruz">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                           class="form-control @error('email') is-invalid @enderror"
                           placeholder="email@example.com">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="form-control @error('phone') is-invalid @enderror"
                           placeholder="+63 9XX XXX XXXX">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                        <option value="">—</option>
                        <option value="male"   @selected(old('gender') === 'male')>Male</option>
                        <option value="female" @selected(old('gender') === 'female')>Female</option>
                        <option value="other"  @selected(old('gender') === 'other')>Other</option>
                    </select>
                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Date of birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->format('Y-m-d') }}"
                           class="form-control @error('date_of_birth') is-invalid @enderror">
                    <div class="form-text">Used for age/gender-restricted tournament divisions.</div>
                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="input-group" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="password" required
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="Min. 8 characters">
                        <button type="button" class="btn btn-outline-secondary" @click="show = !show" tabindex="-1">
                            <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                        </button>
                    </div>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>

            <hr class="my-4">

            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" checked>
                <label class="form-check-label" for="is_active">Active — customer can log in and make bookings</label>
            </div>

        </div>
    </div>

    {{-- Footer actions --}}
    <div class="d-flex gap-2 justify-content-end border-top pt-4 mb-4">
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-plus"></i>Create Customer
        </button>
    </div>

</form>

</div>
</div>

@endsection
