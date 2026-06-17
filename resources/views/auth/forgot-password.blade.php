<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password &mdash; {{ config('app.name') }}</title>
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
                    <i class="bi bi-shield-lock-fill fs-4 text-white"></i>
                </div>
                <h4 class="auth-title mb-1">Forgot password?</h4>
                <p class="auth-subtitle small mb-0">Enter your email and we'll send a reset link</p>
            </div>

            <div class="card">
                <div class="card-body p-4 p-sm-5">

                    @if(session('status'))
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
                        {{ session('status') }}
                    </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="email" class="form-label fw-medium">Email address</label>
                            <input id="email" name="email" type="email" autocomplete="email" required
                                   value="{{ old('email') }}"
                                   class="form-control @error('email') is-invalid @enderror">
                            @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Send reset link</button>
                    </form>
                </div>
            </div>

            <p class="text-center auth-foot small mt-3">
                <a href="{{ route('login') }}">
                    <i class="bi bi-arrow-left me-1"></i>Back to sign in
                </a>
            </p>

        </div>
    </div>
</div>
</body>
</html>
