@extends('layouts.customer')

@section('title', 'My Profile')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold mb-0">My Profile</h4>
    <p class="text-muted mb-0">Manage your account details and security.</p>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person-vcard text-primary"></i>
                <h6 class="mb-0 fw-semibold">Profile information</h6>
            </div>
            <form method="POST" action="{{ route('customer.profile.update') }}" class="card-body">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-person me-1 text-muted"></i>Full name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-envelope me-1 text-muted"></i>Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-telephone me-1 text-muted"></i>Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}">
                </div>
                <div class="row g-2 mb-1">
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-gender-ambiguous me-1 text-muted"></i>Gender</label>
                        <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                            <option value="">Prefer not to say</option>
                            <option value="male"   @selected(old('gender', $user->gender) === 'male')>Male</option>
                            <option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option>
                            <option value="other"  @selected(old('gender', $user->gender) === 'other')>Other</option>
                        </select>
                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label"><i class="bi bi-calendar-event me-1 text-muted"></i>Date of birth</label>
                        <input type="date" name="date_of_birth" class="form-control @error('date_of_birth') is-invalid @enderror"
                               value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}"
                               max="{{ now()->format('Y-m-d') }}">
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <small class="text-muted d-block mb-3">Used to check eligibility for age- and gender-restricted tournament divisions.</small>
                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save</button>
            </form>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-key text-primary"></i>
                <h6 class="mb-0 fw-semibold">Change password</h6>
            </div>
            <form method="POST" action="{{ route('customer.profile.password') }}" class="card-body">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-lock me-1 text-muted"></i>Current password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-shield-lock me-1 text-muted"></i>New password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><i class="bi bi-shield-check me-1 text-muted"></i>Confirm new password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <button class="btn btn-outline-primary"><i class="bi bi-check-lg me-1"></i>Change password</button>
            </form>
        </div>
    </div>
</div>

<hr class="my-4">

<h5 class="mb-3"><i class="bi bi-shield-lock me-1"></i>Security</h5>
@include('partials.security-card')
@endsection
