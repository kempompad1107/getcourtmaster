@extends('layouts.customer')

@section('title', 'My Profile')

@push('styles')
<style>
    .pf-avatar {
        width: 72px; height: 72px; border-radius: 50%;
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        display: grid; place-items: center;
        font-size: 1.75rem; font-weight: 700; color: #fff;
        flex-shrink: 0;
    }
    .set-head {
        font-size: .68rem; font-weight: 700; letter-spacing: .07em;
        text-transform: uppercase; color: var(--bs-secondary-color);
        padding: .75rem 1.25rem .5rem; border-bottom: 1px solid var(--bs-border-color);
    }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <h4 class="fw-bold mb-0">My Profile</h4>
        <p class="text-muted small mb-0">Manage your account details and security.</p>
    </div>
</div>

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        {{ $errors->first() }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-4">

    {{-- ── Left: Profile info ──────────────────────────────── --}}
    <div class="col-12 col-lg-7">

        {{-- Avatar identity bar --}}
        <div class="card mb-4">
            <div class="card-body d-flex align-items-center gap-4 py-4">
                <div class="pf-avatar">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                <div>
                    <div class="fw-bold fs-6 mb-0">{{ $user->name }}</div>
                    <div class="small text-muted">{{ $user->email }}</div>
                    @if($user->phone)
                        <div class="small text-muted">{{ $user->phone }}</div>
                    @endif
                    @if($user->activeMembership?->plan?->name)
                        <span class="badge rounded-pill mt-1" style="background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25);">
                            {{ $user->activeMembership->plan->name }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Profile form --}}
        <div class="card">
            <div class="set-head">Personal Information</div>
            <form method="POST" action="{{ route('customer.profile.update') }}" class="card-body pt-4">
                @csrf @method('PUT')

                <div class="mb-3">
                    <label class="form-label fw-medium">Full Name</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">Email Address</label>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $user->email) }}" required autocomplete="email">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">Phone Number</label>
                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="row g-3 mb-1">
                    <div class="col-6">
                        <label class="form-label fw-medium">Gender</label>
                        <select name="gender" class="form-select @error('gender') is-invalid @enderror">
                            <option value="">Prefer not to say</option>
                            <option value="male"   @selected(old('gender', $user->gender) === 'male')>Male</option>
                            <option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option>
                            <option value="other"  @selected(old('gender', $user->gender) === 'other')>Other</option>
                        </select>
                        @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-medium">Date of Birth</label>
                        <input type="date" name="date_of_birth"
                               class="form-control @error('date_of_birth') is-invalid @enderror"
                               value="{{ old('date_of_birth', optional($user->date_of_birth)->format('Y-m-d')) }}"
                               max="{{ now()->format('Y-m-d') }}">
                        @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-text mb-4">Used to check eligibility for age- and gender-restricted tournament divisions.</div>

                <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </form>
        </div>
    </div>

    {{-- ── Right: Password + Security ─────────────────────── --}}
    <div class="col-12 col-lg-5 d-flex flex-column gap-4">

        {{-- Change password --}}
        <div class="card">
            <div class="set-head">Change Password</div>
            <form method="POST" action="{{ route('customer.profile.password') }}" class="card-body pt-4">
                @csrf @method('PUT')

                <div class="mb-3">
                    <label class="form-label fw-medium">Current Password</label>
                    <input type="password" name="current_password"
                           class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                    @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label fw-medium">New Password</label>
                    <input type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>

                <button class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Update Password</button>
            </form>
        </div>

        {{-- Security (2FA, sessions) --}}
        @include('partials.security-card')

    </div>
</div>

@endsection
