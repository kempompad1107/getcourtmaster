<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tenant->name }}</title>
    <meta name="description" content="{{ $settings['tagline'] ?? $tenant->name }}">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @php
        $brand        = $settings['brand_color'] ?? '#10b981';
        $heroImage    = file_url($settings['hero_image'] ?? null);
        $about        = trim($settings['about'] ?? '');
        $rules        = trim($settings['rules'] ?? '');
        $hasContact   = $tenant->email || $tenant->phone || $tenant->address;
        $hasSocial    = !empty($settings['website']) || !empty($settings['facebook']) || !empty($settings['instagram']);

        // Split tenant name so the LAST word is in the brand colour (matches
        // the reference design's "CLOCK TOWER PICKLEBALL" / "PICKLEBALL" accent).
        $words      = preg_split('/\s+/', trim($tenant->name));
        $accentWord = count($words) > 1 ? array_pop($words) : null;
        $namePrefix = implode(' ', $words);
    @endphp
    <style>
        :root { --brand: {{ $brand }}; }
        body { color: #1f2937; }
        .navbar-brand-logo { width:48px; height:48px; object-fit:contain; }
        .nav-link.scroll-link { color:#374151; font-weight:500; }
        .nav-link.scroll-link:hover { color: var(--brand); }
        .btn-brand { background: var(--brand); border-color: var(--brand); color:#fff; }
        .btn-brand:hover { filter: brightness(.92); color:#fff; }
        .btn-brand-outline { color: var(--brand); border-color: var(--brand); background:#fff; }
        .btn-brand-outline:hover { background: var(--brand); color:#fff; }
        .hero {
            position: relative;
            padding: 7rem 0 7.5rem;
            isolation: isolate;
            color: #fff;
            background:
                radial-gradient(circle at 1px 1px, rgba(0,0,0,.08) 1px, transparent 0) 0 0 / 18px 18px,
                linear-gradient(135deg, #f8fafc 0%, #eef2f6 100%);
        }
        .hero.has-image {
            color: #fff;
            background-image:
                linear-gradient(180deg, rgba(15,23,42,.55) 0%, rgba(15,23,42,.7) 100%),
                var(--hero-image);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        .hero-title {
            font-weight: 900;
            font-size: clamp(2rem, 5vw, 3.75rem);
            letter-spacing: -.01em;
            color: inherit;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .hero.has-image .hero-title { text-shadow: 0 2px 16px rgba(0,0,0,.45); }
        .hero:not(.has-image) .hero-title { color: #0f172a; }
        .hero-accent { color: var(--brand); }
        .hero.has-image .hero-accent {
            color: #fff;
            text-shadow: 0 0 22px var(--brand), 0 0 6px rgba(0,0,0,.45);
        }
        .hero-welcome {
            font-weight: 700;
            font-size: clamp(1.25rem, 2.75vw, 2rem);
            text-transform: uppercase;
            letter-spacing: .02em;
            color: inherit;
        }
        .hero:not(.has-image) .hero-welcome { color: #0f172a; }
        .hero-tagline {
            font-size: 1.1rem; line-height: 1.6;
            color: rgba(255,255,255,.92);
        }
        .hero:not(.has-image) .hero-tagline { color: #4b5563; }
        .hero.has-image .btn-outline-secondary {
            color: #fff; border-color: rgba(255,255,255,.6); background: transparent;
        }
        .hero.has-image .btn-outline-secondary:hover {
            background: #fff; color: #0f172a; border-color: #fff;
        }
        .section-heading {
            font-weight: 800; color:#0f172a; letter-spacing:-.01em;
            font-size: clamp(1.5rem, 3vw, 2rem); text-transform: uppercase;
        }
        .section-heading-accent { color: var(--brand); }
        .info-card { border:1px solid #e5e7eb; border-radius:12px; padding:1.25rem; height:100%; }
        .rule-line { padding:.5rem 0; border-bottom:1px solid #f1f5f9; }
        .rule-line:last-child { border-bottom: 0; }
        .navbar { background:#fff; border-bottom:1px solid #e5e7eb; }
        html { scroll-behavior: smooth; scroll-padding-top: 76px; }
        section { scroll-margin-top: 76px; }
    </style>
</head>
<body data-bs-theme="light">

{{-- ── Top nav ───────────────────────────────────────────────────────── --}}
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="#top">
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $tenant->name }}" class="navbar-brand-logo">
            @else
                <div class="d-flex align-items-center justify-content-center rounded-3"
                     style="width:42px;height:42px;background:var(--brand);color:#fff">
                    <i class="bi bi-shop fs-5"></i>
                </div>
            @endif
            <span class="fw-bold d-none d-sm-inline" style="color:#0f172a">{{ $tenant->name }}</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <i class="bi bi-list fs-3"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                @if($about)    <li class="nav-item"><a class="nav-link scroll-link" href="#about">About Us</a></li> @endif
                @if($rules)    <li class="nav-item"><a class="nav-link scroll-link" href="#rules">Rules</a></li>    @endif
                @if($branches->isNotEmpty() || $tenant->address)
                               <li class="nav-item"><a class="nav-link scroll-link" href="#location">Location</a></li>
                @endif
                @if($hasContact || $hasSocial)
                               <li class="nav-item"><a class="nav-link scroll-link" href="#contact">Contact</a></li>
                @endif
                <li class="nav-item ms-lg-2">
                    <a href="{{ route('register.tenant', $tenant) }}" class="btn btn-brand fw-semibold px-3">
                        SIGN UP
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

{{-- ── Hero (image used as background when set) ─────────────────────── --}}
<header class="hero {{ $heroImage ? 'has-image' : '' }}" id="top"
        @if($heroImage) style="--hero-image: url('{{ $heroImage }}')" @endif>
    <div class="container text-center">
        <p class="hero-welcome mb-2">Welcome to</p>
        <h1 class="hero-title mb-3">
            <span>{{ $namePrefix }}</span>
            @if($accentWord){{ $accentWord }} @endif
        </h1>
        @if(!empty($settings['tagline']))
            <p class="hero-tagline mx-auto mb-4" style="max-width: 640px">{{ $settings['tagline'] }}</p>
        @endif
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('register.tenant', $tenant) }}" class="btn btn-brand btn-lg fw-semibold px-4">
                SIGN UP
            </a>
            @if($hasContact || $hasSocial)
                <a href="#contact" class="btn btn-outline-secondary btn-lg fw-semibold px-4">
                    CONTACT US
                </a>
            @endif
        </div>
    </div>
</header>

{{-- ── About Us ──────────────────────────────────────────────────────── --}}
@if($about)
<section id="about" class="py-5">
    <div class="container">
        <div class="row justify-content-center text-center mb-4">
            <div class="col-md-10 col-lg-8">
                <h2 class="section-heading mb-3">About <span class="section-heading-accent">Us</span></h2>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                @foreach(preg_split("/\r?\n\r?\n+/", $about) as $para)
                    <p class="lead text-muted">{{ $para }}</p>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif

{{-- ── Rules ─────────────────────────────────────────────────────────── --}}
@if($rules)
<section id="rules" class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center text-center mb-4">
            <div class="col-md-10 col-lg-8">
                <h2 class="section-heading mb-3">House <span class="section-heading-accent">Rules</span></h2>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="info-card bg-white">
                    @foreach(preg_split("/\r?\n+/", $rules) as $i => $line)
                        @php $line = trim($line); @endphp
                        @if($line !== '')
                            <div class="rule-line d-flex align-items-start gap-3">
                                <span class="badge text-bg-secondary rounded-pill flex-shrink-0"
                                      style="background-color: var(--brand) !important;">{{ $i + 1 }}</span>
                                <span>{{ $line }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endif

{{-- ── Location ──────────────────────────────────────────────────────── --}}
@if($branches->isNotEmpty() || $tenant->address)
<section id="location" class="py-5">
    <div class="container">
        <div class="row justify-content-center text-center mb-4">
            <div class="col-md-10 col-lg-8">
                <h2 class="section-heading mb-3">Our <span class="section-heading-accent">Locations</span></h2>
            </div>
        </div>
        <div class="row g-3 justify-content-center">
            @foreach($branches as $branch)
                @php
                    $fullAddress = trim(($branch->address ? $branch->address . ', ' : '') . ($branch->city ?? ''), ', ');
                    // Prefer the branch's own Google Maps link if set, otherwise
                    // fall back to an address search.
                    $mapsUrl = $branch->map_url
                        ?: ($fullAddress
                            ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($fullAddress)
                            : null);
                @endphp
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="mb-0 fw-bold" style="color:#0f172a">{{ $branch->name }}</h6>
                            @if($branch->is_main)
                                <span class="badge" style="background: var(--brand); color:#fff">MAIN</span>
                            @endif
                        </div>
                        @if($fullAddress)
                            <p class="text-muted small mb-2">
                                <i class="bi bi-geo-alt me-1" style="color: var(--brand)"></i>{{ $fullAddress }}
                            </p>
                        @endif
                        @if($branch->phone)
                            <p class="text-muted small mb-2">
                                <i class="bi bi-telephone me-1" style="color: var(--brand)"></i>{{ $branch->phone }}
                            </p>
                        @endif
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            @if($mapsUrl)
                                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-map me-1"></i>Directions
                                </a>
                            @endif
                            <a href="{{ route('register.tenant', $tenant) }}?branch={{ $branch->id }}"
                               class="btn btn-sm btn-brand-outline">
                                <i class="bi bi-person-plus me-1"></i>Sign up here
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ── Contact ───────────────────────────────────────────────────────── --}}
@if($hasContact || $hasSocial)
<section id="contact" class="py-5 bg-light">
    <div class="container">
        <div class="row justify-content-center text-center mb-4">
            <div class="col-md-10 col-lg-8">
                <h2 class="section-heading mb-3">Get in <span class="section-heading-accent">Touch</span></h2>
            </div>
        </div>
        <div class="row g-3 justify-content-center">
            @if($tenant->email)
            <div class="col-12 col-md-4">
                <div class="info-card bg-white text-center">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
                         style="width:48px;height:48px;background:var(--brand);color:#fff">
                        <i class="bi bi-envelope fs-5"></i>
                    </div>
                    <p class="small text-muted mb-1">Email</p>
                    <a href="mailto:{{ $tenant->email }}"
                       class="fw-semibold text-decoration-none" style="color:#0f172a">{{ $tenant->email }}</a>
                </div>
            </div>
            @endif
            @if($tenant->phone)
            <div class="col-12 col-md-4">
                <div class="info-card bg-white text-center">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
                         style="width:48px;height:48px;background:var(--brand);color:#fff">
                        <i class="bi bi-telephone fs-5"></i>
                    </div>
                    <p class="small text-muted mb-1">Phone</p>
                    <a href="tel:{{ $tenant->phone }}"
                       class="fw-semibold text-decoration-none" style="color:#0f172a">{{ $tenant->phone }}</a>
                </div>
            </div>
            @endif
            @if($tenant->address || $tenant->city)
            <div class="col-12 col-md-4">
                <div class="info-card bg-white text-center">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2"
                         style="width:48px;height:48px;background:var(--brand);color:#fff">
                        <i class="bi bi-geo-alt fs-5"></i>
                    </div>
                    <p class="small text-muted mb-1">Address</p>
                    <p class="fw-semibold mb-0" style="color:#0f172a">
                        {{ trim(($tenant->address ? $tenant->address . ', ' : '') . ($tenant->city ?? ''), ', ') }}
                    </p>
                </div>
            </div>
            @endif
        </div>

        @if($hasSocial)
        <div class="d-flex justify-content-center gap-2 mt-4">
            @if(!empty($settings['website']))
                <a href="{{ $settings['website'] }}" target="_blank" rel="noopener"
                   class="btn btn-outline-secondary"><i class="bi bi-globe me-1"></i>Website</a>
            @endif
            @if(!empty($settings['facebook']))
                <a href="{{ $settings['facebook'] }}" target="_blank" rel="noopener"
                   class="btn btn-outline-secondary"><i class="bi bi-facebook me-1"></i>Facebook</a>
            @endif
            @if(!empty($settings['instagram']))
                <a href="{{ $settings['instagram'] }}" target="_blank" rel="noopener"
                   class="btn btn-outline-secondary"><i class="bi bi-instagram me-1"></i>Instagram</a>
            @endif
        </div>
        @endif
    </div>
</section>
@endif

{{-- ── Footer ────────────────────────────────────────────────────────── --}}
<footer class="py-4 border-top text-center small text-muted">
    <div class="container">
        <p class="mb-1">
            &copy; {{ now()->year }} {{ $tenant->name }}.
            <a href="{{ route('login') }}" class="text-decoration-none ms-2">Member sign in</a>
        </p>
        <p class="mb-0">Powered by <a href="{{ url('/') }}" class="text-decoration-none">{{ config('app.name') }}</a></p>
    </div>
</footer>

</body>
</html>
