<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password &mdash; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body data-bs-theme="light" class="auth-page min-vh-100 d-flex align-items-center">
<div class="auth-orb" style="width:680px;height:680px;background:#10b981;top:-220px;left:-230px"></div>
<div class="auth-orb" style="width:520px;height:520px;background:#3b82f6;bottom:-130px;right:-160px"></div>
<div class="container py-5 position-relative" style="z-index:1">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">

            {{-- Brand --}}
            <div class="text-center mb-4">
                <div class="auth-brand-icon mb-3">
                    <i class="bi bi-lock-fill fs-4 text-white"></i>
                </div>
                <h4 class="auth-title mb-1">Set new password</h4>
                <p class="auth-subtitle small mb-0">Choose a strong password for your account</p>
            </div>

            <div class="card">
                <div class="card-body p-4 p-sm-5">
                    <form method="POST" action="{{ route('password.store') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <div class="mb-3">
                            <label for="email" class="form-label fw-medium">Email address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required
                                   value="{{ old('email', $request->email) }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-medium">New password</label>
                            <input id="password" name="password" type="password"
                                   autocomplete="new-password" required
                                   class="form-control @error('password') is-invalid @enderror">
                            @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label fw-medium">Confirm new password</label>
                            <input id="password_confirmation" name="password_confirmation" type="password"
                                   autocomplete="new-password" required
                                   class="form-control">
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Reset password</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
