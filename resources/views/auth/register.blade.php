<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account &mdash; {{ config('app.name') }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body data-bs-theme="light" class="auth-page min-vh-100 d-flex align-items-center">
<div class="auth-orb" style="width:680px;height:680px;background:#10b981;top:-220px;left:-230px"></div>
<div class="auth-orb" style="width:520px;height:520px;background:#3b82f6;bottom:-130px;right:-160px"></div>
<div class="container py-5 position-relative" style="z-index:1">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-8 col-lg-6 col-xl-5">

            <div class="text-center mb-4">
                <div class="auth-brand-icon mb-3">
                    <svg width="28" height="28" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
                    </svg>
                </div>
                <h4 class="auth-title mb-1">Create your account</h4>
                <p class="auth-subtitle small mb-0">Pick a way to get started</p>
            </div>

            <div class="card shadow-sm mb-3">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-2">
                        <i class="bi bi-qr-code-scan text-success me-1"></i>
                        I'm a player joining a venue
                    </h6>
                    <p class="text-muted small mb-3">
                        Member signups happen through your venue's signup link. Ask the front desk
                        for a QR code or signup URL — they look like
                        <code class="small">/t/your-venue/register</code>.
                    </p>
                    <p class="text-muted small mb-0">
                        Already have an account?
                        <a href="{{ route('login') }}" class="text-primary text-decoration-none fw-medium">Sign in here</a>.
                    </p>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-semibold mb-2">
                        <i class="bi bi-building-add text-primary me-1"></i>
                        I want to register my business
                    </h6>
                    <p class="text-muted small mb-0">
                        New venues are onboarded by our team. Reach out at
                        <a href="mailto:hello@{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com' }}"
                           class="text-primary text-decoration-none">hello@{{ parse_url(config('app.url'), PHP_URL_HOST) ?: 'example.com' }}</a>
                        and we'll set you up.
                    </p>
                </div>
            </div>

            <p class="text-center auth-foot small mt-3">
                <a href="{{ route('login') }}">&larr; Back to sign in</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
