@extends('layouts.app')
@section('title', 'Edit Customer')

@section('content')

<x-page-header title="Edit Customer" :back="route('admin.customers.show', $customer)"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-7 col-xl-6">

<div class="card">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Full name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $customer->name) }}" required
                           class="form-control form-control-sm @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" required
                           class="form-control form-control-sm @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}"
                           class="form-control form-control-sm @error('phone') is-invalid @enderror"
                           placeholder="+63 9XX XXX XXXX">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Gender</label>
                    <select name="gender" class="form-select form-select-sm @error('gender') is-invalid @enderror">
                        <option value="">—</option>
                        <option value="male"   @selected(old('gender', $customer->gender) === 'male')>Male</option>
                        <option value="female" @selected(old('gender', $customer->gender) === 'female')>Female</option>
                        <option value="other"  @selected(old('gender', $customer->gender) === 'other')>Other</option>
                    </select>
                    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">Date of birth</label>
                    <input type="date" name="date_of_birth" max="{{ now()->format('Y-m-d') }}"
                           value="{{ old('date_of_birth', optional($customer->date_of_birth)->format('Y-m-d')) }}"
                           class="form-control form-control-sm @error('date_of_birth') is-invalid @enderror">
                    <div class="form-text">Used for age/gender-restricted tournament divisions.</div>
                    @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-sm-6">
                    <label class="form-label fw-medium">New password</label>
                    <input type="password" name="password"
                           class="form-control form-control-sm @error('password') is-invalid @enderror"
                           placeholder="Leave blank to keep current">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Min. 8 characters. Leave blank to keep the current password.</div>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" id="is_active"
                               class="form-check-input" @checked($customer->is_active)>
                        <label class="form-check-label" for="is_active">Active (can log in and make bookings)</label>
                    </div>
                </div>

            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-outline-secondary btn-sm">Cancel</a>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-floppy me-1"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

</div>
</div>

@endsection
