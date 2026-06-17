<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify code — {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body data-bs-theme="light" class="auth-page min-vh-100 d-flex align-items-center">
<div class="auth-orb" style="width:680px;height:680px;background:#10b981;top:-220px;left:-230px"></div>
<div class="auth-orb" style="width:520px;height:520px;background:#3b82f6;bottom:-130px;right:-160px"></div>
<div class="container py-5 position-relative" style="max-width:440px; z-index:1">
    <div class="text-center mb-4">
        <div class="auth-brand-icon mb-3"><i class="bi bi-shield-check fs-4 text-white"></i></div>
        <h4 class="auth-title mb-1">Enter your code</h4>
        <p class="auth-subtitle small mb-0">We sent a 6-digit code to <strong class="text-white-50">{{ $email }}</strong></p>
    </div>
    <div class="card">
        <div class="card-body p-4 p-sm-5">

            @if ($errors->any())
                <div class="alert alert-danger small">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('otp.verify') }}">
                @csrf
                <input type="text" name="code" class="form-control text-center fs-3 fw-bold" style="letter-spacing:.5em" inputmode="numeric" maxlength="6" pattern="\d{6}" autofocus required>
                <button class="btn btn-primary w-100 mt-3">Verify and sign in</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
