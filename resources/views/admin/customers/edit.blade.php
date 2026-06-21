@extends('layouts.app')
@section('title', 'Edit Customer')

@section('content')

<x-page-header title="Edit: {{ $customer->name }}" :back="route('admin.customers.index')" backLabel="Customers"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-8 col-xl-7">

<form method="POST" action="{{ route('admin.customers.update', $customer) }}">
    @csrf
    @method('PUT')

    <div class="card mb-4">
        <div class="card-body">

            <p class="mb-3" style="font-size:.68rem;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--bs-secondary-color)">
                Customer Details
            </p>

            <div class="row g-3">

                <div class="col-sm-6">
                    <label class="form-label">Full name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $customer->name) }}" required
                           class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" required
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"
                           class="form-control @error('phone') is-invalid @enderror"
                           placeholder="+63 9XX XXX XXXX">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                        <option value="">—</option>
                        <option value="male"   @selected(old('gender', $customer->gender) === 'male')>Male</option>
                        <option value="female" @selected(old('gender', $customer->gender) === 'female')>Female</option>
                        <option value="other"  @selected(old('gender', $customer->gender) === 'other')>Other</option>
                    </select>
                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">Date of birth</label>
                    <input type="date" name="date_of_birth" max="{{ now()->format('Y-m-d') }}"
                           value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}"
                           class="form-control @error('date_of_birth') is-invalid @enderror">
                    <div class="form-text">Used for age/gender-restricted tournament divisions.</div>
                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label">New password</label>
                    <div class="input-group" x-data="{ show: false }">
                        <input :type="show ? 'text' : 'password'" name="password"
                               class="form-control @error('password') is-invalid @enderror"
                               placeholder="Leave blank to keep current">
                        <button type="button" class="btn btn-outline-secondary" @click="show = !show" tabindex="-1">
                            <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                        </button>
                    </div>
                    <div class="form-text">Min. 8 characters. Leave blank to keep the current password.</div>
                    @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

            </div>

            <hr class="my-4">

            <div class="form-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" id="is_active"
                       class="form-check-input" @checked($customer->is_active)>
                <label class="form-check-label" for="is_active">Active — customer can log in and make bookings</label>
            </div>

        </div>
    </div>

    {{-- Footer actions --}}
    <div class="d-flex gap-2 justify-content-end border-top pt-4 mb-4">
        <a href="{{ route('admin.customers.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-floppy"></i>Save Changes
        </button>
    </div>

</form>

</div>
</div>

@endsection
