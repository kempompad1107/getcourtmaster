<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verify — {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body data-bs-theme="light" class="auth-page min-vh-100 d-flex align-items-center">
<div class="auth-orb" style="width:680px;height:680px;background:#10b981;top:-220px;left:-230px"></div>
<div class="auth-orb" style="width:520px;height:520px;background:#3b82f6;bottom:-130px;right:-160px"></div>
<div class="container py-5 position-relative" style="max-width:440px; z-index:1">
    <div class="text-center mb-4">
        <div class="auth-brand-icon mb-3"><i class="bi bi-shield-lock-fill fs-4 text-white"></i></div>
        <h4 class="auth-title mb-1">Two-factor verification</h4>
        <p class="auth-subtitle small mb-0">Enter the 6-digit code from your authenticator app</p>
    </div>
    <div class="card">
        <div class="card-body p-4 p-sm-5">

            @if ($errors->any())
                <div class="alert alert-danger small">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('2fa.verify') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Authenticator code</label>
                    <input type="text" name="code" class="form-control" inputmode="numeric" maxlength="6" autofocus>
                </div>
                <details class="mb-3">
                    <summary class="small text-muted">Use a recovery code instead</summary>
                    <input type="text" name="recovery_code" class="form-control mt-2" placeholder="xxxxx-xxxxx">
                </details>
                <button class="btn btn-primary w-100">Verify</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
