<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In &mdash; {{ config('app.name') }}</title>
    @include('partials.favicon')
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --pb-blue:   #1d4ed8;
            --pb-blue-2: #1e3a8a;
            --pb-green:  #10b981;
            --pb-ball:   #d6ff3f;
            --pb-ball-2: #b6e600;
        }
        body.pb-login { background: #f3f6fb; overflow-x: hidden; }
        .pb-display { font-family: 'Archivo', system-ui, sans-serif; }

        .pb-split { min-height: 100vh; }

        /* ── Court panel (left) ───────────────────────────────── */
        .pb-court {
            position: relative; overflow: hidden;
            background:
                radial-gradient(120% 80% at 80% 10%, rgba(16,185,129,.35) 0%, transparent 55%),
                linear-gradient(150deg, var(--pb-blue) 0%, var(--pb-blue-2) 70%, #142a66 100%);
            color: #fff;
        }
        /* court line markings */
        .pb-court::before {
            content: ""; position: absolute; inset: 0; pointer-events: none; opacity: .5;
            background:
                /* outer border */
                linear-gradient(#fff,#fff) 36px 36px / calc(100% - 72px) 3px no-repeat,
                linear-gradient(#fff,#fff) 36px calc(100% - 36px) / calc(100% - 72px) 3px no-repeat,
                linear-gradient(#fff,#fff) 36px 36px / 3px calc(100% - 72px) no-repeat,
                linear-gradient(#fff,#fff) calc(100% - 36px) 36px / 3px calc(100% - 72px) no-repeat,
                /* centre (net) line */
                linear-gradient(#fff,#fff) 50% 36px / 3px calc(100% - 72px) no-repeat;
        }
        /* the kitchen / non-volley zone band */
        .pb-court::after {
            content: ""; position: absolute; left: 36px; right: 36px; top: 38%; height: 24%;
            background: rgba(16,185,129,.28); border-top: 3px solid rgba(255,255,255,.55);
            border-bottom: 3px solid rgba(255,255,255,.55); pointer-events: none;
        }
        .pb-court-inner { position: relative; z-index: 2; padding: clamp(2.5rem, 5vw, 4.5rem); }

        .pb-badge {
            display: inline-flex; align-items: center; gap: .55rem;
            font-family: 'Archivo', sans-serif; font-weight: 700; font-size: .72rem;
            letter-spacing: .22em; text-transform: uppercase;
            padding: .45rem 1rem; border-radius: 999px;
            background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25); color: #eafff6;
        }
        .pb-headline {
            font-family: 'Archivo', sans-serif; font-weight: 900; line-height: .95;
            font-size: clamp(2.6rem, 4.5vw, 4.2rem); letter-spacing: -.02em; margin: 1.5rem 0 0;
            text-shadow: 0 6px 30px rgba(0,0,0,.25);
        }
        .pb-headline .pop { color: var(--pb-ball); }
        .pb-sub { font-size: 1.05rem; color: rgba(255,255,255,.82); max-width: 30ch; margin-top: 1rem; }

        .pb-points { list-style: none; padding: 0; margin: 2rem 0 0; display: flex; flex-direction: column; gap: .6rem; }
        .pb-points li { display: flex; align-items: center; gap: .6rem; font-weight: 500; color: rgba(255,255,255,.9); }
        .pb-points i { color: var(--pb-ball); font-size: 1.1rem; }

        /* floating ball + paddle */
        .pb-floats { position: absolute; inset: 0; z-index: 1; pointer-events: none; }
        .pb-ball-svg { position: absolute; right: -40px; bottom: -50px; width: clamp(220px, 26vw, 340px); animation: pbFloat 6s ease-in-out infinite; filter: drop-shadow(0 24px 40px rgba(0,0,0,.35)); }
        .pb-paddle-svg { position: absolute; right: 150px; top: 60px; width: clamp(90px, 11vw, 140px); transform: rotate(18deg); animation: pbFloat 7s ease-in-out infinite reverse; filter: drop-shadow(0 18px 30px rgba(0,0,0,.4)); }
        @keyframes pbFloat { 0%,100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-16px) rotate(-3deg); } }
        @keyframes pbFloatP { 0%,100% { transform: translateY(0) rotate(18deg); } 50% { transform: translateY(-14px) rotate(24deg); } }
        .pb-paddle-svg { animation-name: pbFloatP; }

        /* ── Form panel (right) ───────────────────────────────── */
        .pb-form-wrap { display: flex; align-items: center; justify-content: center; padding: clamp(2rem, 5vw, 4rem) 1.25rem; }
        .pb-form { width: 100%; max-width: 420px; }
        .pb-mark {
            width: 56px; height: 56px; border-radius: 18px; display: grid; place-items: center;
            background: linear-gradient(140deg, var(--pb-blue), var(--pb-blue-2));
            box-shadow: 0 12px 26px -10px rgba(29,78,216,.6);
        }
        .pb-form h1 { font-family: 'Archivo', sans-serif; font-weight: 800; font-size: 1.7rem; letter-spacing: -.02em; margin: 0; color: #0f172a; }
        .pb-form .lede { color: #64748b; margin: .25rem 0 0; }

        .pb-login .form-control { padding: .7rem .9rem; border-radius: .7rem; border-color: #dbe3ee; }
        .pb-login .form-control:focus { border-color: var(--pb-green); box-shadow: 0 0 0 .25rem rgba(16,185,129,.18); }
        .pb-login .input-group .btn { border-radius: 0 .7rem .7rem 0; border-color: #dbe3ee; }
        .pb-login label.form-label { font-weight: 600; color: #334155; font-size: .85rem; }

        .pb-submit {
            --bs-btn-padding-y: .75rem; font-family: 'Archivo', sans-serif; font-weight: 700;
            letter-spacing: .02em; border-radius: .7rem; border: 0;
            background: linear-gradient(135deg, var(--pb-green) 0%, #059669 100%);
            box-shadow: 0 14px 26px -12px rgba(16,185,129,.7);
        }
        .pb-submit:hover { background: linear-gradient(135deg, #0ea271 0%, #047857 100%); }

        .pb-link { color: var(--pb-blue); font-weight: 600; text-decoration: none; }
        .pb-link:hover { text-decoration: underline; }
    </style>
</head>
<body data-bs-theme="light" class="auth-page pb-login" x-data="{ showPass: false }">

<div class="row g-0 pb-split">

    {{-- ── Court / brand panel ─────────────────────────────── --}}
    <div class="col-lg-6 pb-court d-none d-lg-block">
        <div class="pb-floats">
            {{-- Pickleball paddle --}}
            <svg class="pb-paddle-svg" viewBox="0 0 120 210" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="46" y="120" width="28" height="86" rx="10" fill="#0b2a6b"/>
                <rect x="50" y="124" width="20" height="60" rx="6" fill="#0a225a"/>
                <ellipse cx="60" cy="66" rx="56" ry="66" fill="#f8fafc"/>
                <ellipse cx="60" cy="66" rx="48" ry="58" fill="#1d4ed8"/>
                <ellipse cx="60" cy="66" rx="48" ry="58" fill="url(#pgrad)" opacity=".5"/>
                <defs><radialGradient id="pgrad" cx="38%" cy="30%"><stop offset="0%" stop-color="#60a5fa"/><stop offset="100%" stop-color="#1d4ed8"/></radialGradient></defs>
            </svg>
            {{-- Pickleball --}}
            <svg class="pb-ball-svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="ballg" cx="38%" cy="30%">
                        <stop offset="0%" stop-color="#eeff8f"/>
                        <stop offset="65%" stop-color="#d6ff3f"/>
                        <stop offset="100%" stop-color="#a9d400"/>
                    </radialGradient>
                </defs>
                <circle cx="100" cy="100" r="94" fill="url(#ballg)"/>
                <g fill="#8bbd00" opacity=".75">
                    <circle cx="100" cy="40" r="9"/>
                    <circle cx="62" cy="54" r="9"/>  <circle cx="138" cy="54" r="9"/>
                    <circle cx="44" cy="90" r="9"/>  <circle cx="100" cy="82" r="9"/>  <circle cx="156" cy="90" r="9"/>
                    <circle cx="74" cy="116" r="9"/> <circle cx="126" cy="116" r="9"/>
                    <circle cx="56" cy="142" r="9"/> <circle cx="100" cy="150" r="9"/> <circle cx="144" cy="142" r="9"/>
                </g>
                <ellipse cx="70" cy="58" rx="28" ry="17" fill="#ffffff" opacity=".4"/>
            </svg>
        </div>

        <div class="pb-court-inner d-flex flex-column h-100">
            <span class="pb-badge"><i class="bi bi-circle-fill" style="font-size:.5rem;color:var(--pb-ball)"></i>{{ config('app.name') }}</span>

            <div class="mt-auto">
                <h1 class="pb-headline">Reserve.<br>Rally.<br><span class="pop">Repeat.</span></h1>
                <p class="pb-sub">The all-in-one court management platform built for pickleball clubs.</p>
                <ul class="pb-points">
                    <li><i class="bi bi-lightning-charge-fill"></i> Live court status &amp; bookings</li>
                    <li><i class="bi bi-people-fill"></i> Members, staff &amp; POS in one place</li>
                    <li><i class="bi bi-graph-up-arrow"></i> Real-time revenue insights</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── Sign-in form ────────────────────────────────────── --}}
    <div class="col-12 col-lg-6 pb-form-wrap">
        <div class="pb-form">

            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="pb-mark">
                    <svg width="30" height="30" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="100" cy="100" r="92" fill="#d6ff3f"/>
                        <g fill="#1d4ed8" opacity=".85">
                            <circle cx="100" cy="48" r="11"/><circle cx="62" cy="74" r="11"/><circle cx="138" cy="74" r="11"/>
                            <circle cx="78" cy="118" r="11"/><circle cx="122" cy="118" r="11"/><circle cx="100" cy="152" r="11"/>
                        </g>
                    </svg>
                </div>
                <div>
                    <h1 class="pb-display">{{ config('app.name') }}</h1>
                    <p class="lede small mb-0">Game on — sign in to your courts.</p>
                </div>
            </div>

            @if(session('status'))
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill"></i>
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required
                           value="{{ old('email') }}"
                           placeholder="you@club.com"
                           class="form-control @error('email') is-invalid @enderror">
                    @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input id="password" name="password" :type="showPass ? 'text' : 'password'"
                               autocomplete="current-password" required
                               placeholder="••••••••"
                               class="form-control @error('password') is-invalid @enderror">
                        <button type="button" class="btn btn-outline-secondary" @click="showPass = !showPass">
                            <i class="bi" :class="showPass ? 'bi-eye-slash' : 'bi-eye'"></i>
                        </button>
                        @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember">
                        <label class="form-check-label small" for="remember_me">Remember me</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="small pb-link">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="btn btn-primary pb-submit w-100">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign in
                </button>
            </form>

        </div>
    </div>
</div>

</body>
</html>
