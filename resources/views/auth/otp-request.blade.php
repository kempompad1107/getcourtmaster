<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in with email — {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body data-bs-theme="light" class="auth-page min-vh-100 d-flex align-items-center">
<div class="auth-orb" style="width:680px;height:680px;background:#10b981;top:-220px;left:-230px"></div>
<div class="auth-orb" style="width:520px;height:520px;background:#3b82f6;bottom:-130px;right:-160px"></div>
<div class="container py-5 position-relative" style="max-width:440px; z-index:1">
    <div class="text-center mb-4">
        <div class="auth-brand-icon mb-3"><i class="bi bi-envelope-paper-fill fs-4 text-white"></i></div>
        <h4 class="auth-title mb-1">{{ config('app.name') }}</h4>
        <p class="auth-subtitle small mb-0">Sign in with a one-time code</p>
    </div>
    <div class="card">
        <div class="card-body p-4 p-sm-5">
            <h5 class="fw-semibold mb-3">Sign in with a one-time code</h5>

            @if (session('success'))
                <div class="alert alert-success small">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger small">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('otp.send') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                </div>
                <button class="btn btn-primary w-100">Send code</button>
            </form>
            <p class="small text-muted text-center mt-3 mb-0">
                <a href="{{ route('login') }}" class="text-primary text-decoration-none">Use password instead</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
