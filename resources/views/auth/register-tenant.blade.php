<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join {{ $tenant->name }} &mdash; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body data-bs-theme="light" class="auth-page min-vh-100 d-flex align-items-center" x-data="{ showPass: false }">
<div class="auth-orb" style="width:680px;height:680px;background:#10b981;top:-220px;left:-230px"></div>
<div class="auth-orb" style="width:520px;height:520px;background:#3b82f6;bottom:-130px;right:-160px"></div>
<div class="container py-5 position-relative" style="z-index:1">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

            {{-- Brand --}}
            <div class="text-center mb-4">
                @if($tenant->logo)
                    <img src="{{ $tenant->logo }}" alt="{{ $tenant->name }}"
                         class="rounded-3 mb-3" style="width:64px;height:64px;object-fit:cover;box-shadow:0 10px 30px -8px rgba(0,0,0,.5)">
                @else
                    <div class="auth-brand-icon mb-3">
                        <i class="bi bi-shop text-white fs-4"></i>
                    </div>
                @endif
                <h4 class="auth-title mb-1">Join {{ $tenant->name }}</h4>
                <p class="auth-subtitle small mb-0">Create your member account to book courts &amp; track your games</p>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('register.tenant.store', $tenant) }}">
                        @csrf

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label for="name" class="form-label fw-medium">Full name</label>
                                <input id="name" name="name" type="text" autocomplete="name" required
                                       value="{{ old('name') }}"
                                       class="form-control @error('name') is-invalid @enderror">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="phone" class="form-label fw-medium">Phone number</label>
                                <input id="phone" name="phone" type="tel" autocomplete="tel"
                                       value="{{ old('phone') }}"
                                       class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">Email address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required
                                   value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label for="home_branch_id" class="form-label fw-medium">
                                Home branch
                                <span class="text-muted fw-normal small">— where you'll mostly play</span>
                            </label>
                            <select id="home_branch_id" name="home_branch_id" required
                                    class="form-select @error('home_branch_id') is-invalid @enderror">
                                @foreach($branches as $b)
                                    <option value="{{ $b->id }}"
                                            @selected(old('home_branch_id', $selectedBranchId) == $b->id)>
                                        {{ $b->name }}@if($b->is_main) (Main){{-- @endif --}}@endif
                                        @if($b->city) — {{ $b->city }}@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('home_branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">You can still book at any branch — this is just your default.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium">Password</label>
                            <div class="input-group">
                                <input id="password" name="password" :type="showPass ? 'text' : 'password'"
                                       autocomplete="new-password" required
                                       class="form-control @error('password') is-invalid @enderror">
                                <button type="button" class="btn btn-outline-secondary" @click="showPass = !showPass">
                                    <i class="bi" :class="showPass ? 'bi-eye-slash' : 'bi-eye'"></i>
                                </button>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label fw-medium">Confirm password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   autocomplete="new-password" required
                                   class="form-control">
                        </div>

                        <div class="mb-3">
                            <label for="referral_code" class="form-label fw-medium">
                                Referral code <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input id="referral_code" name="referral_code" type="text"
                                   value="{{ old('referral_code') }}"
                                   class="form-control text-uppercase" style="letter-spacing:.05em">
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label small" for="terms">
                                I agree to the
                                <a href="#" class="text-primary text-decoration-none">Terms of Service</a>
                                and
                                <a href="#" class="text-primary text-decoration-none">Privacy Policy</a>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Create account</button>
                    </form>
                </div>
            </div>

            <p class="text-center auth-foot small mt-3">
                Already a member of {{ $tenant->name }}?
                <a href="{{ route('login') }}" class="fw-medium">Sign in</a>
            </p>

        </div>
    </div>
</div>
</body>
</html>
