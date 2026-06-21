{{--
    Security card: 2FA management + active devices list.
    Used inside Admin Settings (security tab) and Customer Profile (security row).

    Expects:
      - $user : auth()->user() (passed by caller)
      - $sessions : collection of UserSession rows
      - $currentSessionId : current session id
      - 2FA setup state (secret + qrUri) — null when 2FA already confirmed or not yet started
        (caller fetches via TwoFactorController::securityState($user, $totp))
--}}
@php
    $secret = $secret ?? null;
    $qrUri  = $qrUri  ?? null;
    $sessions = $sessions ?? collect();
    $currentSessionId = $currentSessionId ?? request()->session()->getId();
@endphp

{{-- ── Two-Factor Authentication ─────────────────────────────── --}}
<div class="card mb-4">
    <div class="card-header set-head">
        <div class="flex-grow-1">
            <h6 class="mb-0 fw-semibold">Two-Factor Authentication</h6>
            <small class="text-muted">Add a one-time code to your sign-in for extra security.</small>
        </div>
        @if($user->two_factor_confirmed_at)
            <span class="badge bg-success-subtle text-success-emphasis">Enabled</span>
        @else
            <span class="badge bg-secondary-subtle text-secondary-emphasis">Off</span>
        @endif
    </div>
    <div class="card-body">

        @if($user->two_factor_confirmed_at)

            {{-- 2FA is ON — show disable form --}}
            <p class="small text-muted mb-3">
                2FA is active on your account. Enter your password below to turn it off.
            </p>
            <form method="POST" action="{{ route('2fa.disable') }}">
                @csrf
                <div class="d-flex flex-column flex-sm-row gap-2" style="max-width:32rem">
                    <input type="password" name="password" class="form-control"
                           placeholder="Confirm your password" required>
                    <button class="btn btn-outline-danger flex-shrink-0">
                        <i class="bi bi-shield-slash me-1"></i>Disable 2FA
                    </button>
                </div>
            </form>

        @else

            {{-- 2FA is OFF — show enable flow --}}
            <p class="small text-muted mb-3">
                Two-factor authentication is <strong>optional</strong> — turn it on if you want extra protection for your account.
            </p>

            <div x-data="{ open: false }">
                <button type="button" class="btn btn-primary btn-sm"
                        @click="open = !open" x-show="!open">
                    <i class="bi bi-shield-plus me-1"></i>Enable 2FA
                </button>

                <div x-show="open" x-transition x-cloak>
                    @if($qrUri)
                        <p class="small mb-2">Scan with Google Authenticator, Authy, or 1Password:</p>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($qrUri) }}"
                             alt="QR code" class="mb-3 border rounded d-block">
                        <p class="small text-muted mb-1">Or enter this secret manually:</p>
                        <code class="d-inline-block px-3 py-2 rounded small mb-3"
                              style="background:var(--bs-secondary-bg);letter-spacing:.08em">{{ $secret }}</code>
                    @endif

                    <form method="POST" action="{{ route('2fa.confirm') }}" style="max-width:24rem">
                        @csrf
                        <label class="form-label small fw-medium">6-digit code from your authenticator app</label>
                        <div class="input-group">
                            <input type="text" name="code" class="form-control font-monospace"
                                   inputmode="numeric" maxlength="6" pattern="\d{6}"
                                   placeholder="000000" required autofocus>
                            <button class="btn btn-primary">Confirm</button>
                        </div>
                    </form>

                    <button type="button" class="btn btn-link btn-sm text-muted mt-2 p-0"
                            @click="open = false">Cancel</button>
                </div>
            </div>

            @if(session('recovery_codes'))
                <div class="alert alert-warning d-flex gap-2 mt-3 small">
                    <i class="bi bi-exclamation-triangle flex-shrink-0 mt-1"></i>
                    <div>
                        <strong>Save these recovery codes.</strong> Each can be used once if you lose your 2FA device.
                        <pre class="mb-0 mt-2 small">{{ implode(PHP_EOL, session('recovery_codes')) }}</pre>
                    </div>
                </div>
            @endif

        @endif

    </div>
</div>

{{-- ── Signed-in Devices ──────────────────────────────────────── --}}
<div class="card">
    <div class="card-header set-head">
        <div class="flex-grow-1">
            <h6 class="mb-0 fw-semibold">Signed-in Devices</h6>
            <small class="text-muted">All sessions currently logged in to your account.</small>
        </div>
        <span class="badge bg-secondary-subtle text-secondary-emphasis">
            {{ $sessions->count() }} {{ Str::plural('device', $sessions->count()) }}
        </span>
    </div>

    @if($sessions->isEmpty())
        <div class="card-body text-center text-muted small py-5">
            <i class="bi bi-laptop fs-3 d-block mb-2 opacity-50"></i>
            No active devices found.
        </div>
    @else
        <div class="list-group list-group-flush">
            @foreach($sessions as $s)
            @php
                $ua = $s->user_agent ?? '';
                $isMobile  = preg_match('/iPhone|Android|Mobile/i', $ua);
                $isTablet  = preg_match('/iPad|Tablet/i', $ua);
                $deviceIcon = $isMobile ? 'bi-phone' : ($isTablet ? 'bi-tablet' : 'bi-laptop');

                // Parse a readable browser + OS label
                $browser = 'Unknown browser';
                if (str_contains($ua, 'Edg/'))         $browser = 'Edge';
                elseif (str_contains($ua, 'Chrome/'))  $browser = 'Chrome';
                elseif (str_contains($ua, 'Firefox/')) $browser = 'Firefox';
                elseif (str_contains($ua, 'Safari/'))  $browser = 'Safari';
                elseif (str_contains($ua, 'OPR/'))     $browser = 'Opera';

                $os = '';
                if (str_contains($ua, 'Windows'))       $os = 'Windows';
                elseif (str_contains($ua, 'Mac OS X'))  $os = 'macOS';
                elseif (str_contains($ua, 'iPhone'))    $os = 'iPhone';
                elseif (str_contains($ua, 'iPad'))      $os = 'iPad';
                elseif (str_contains($ua, 'Android'))   $os = 'Android';
                elseif (str_contains($ua, 'Linux'))     $os = 'Linux';

                $deviceLabel = $s->device_label ?? ($os ? "$browser · $os" : $browser);
                $isCurrent   = $s->session_id === $currentSessionId;
            @endphp
            <div class="list-group-item d-flex align-items-center gap-3 py-3">
                {{-- Device icon --}}
                <div class="flex-shrink-0 text-muted" style="font-size:1.3rem">
                    <i class="bi {{ $deviceIcon }}"></i>
                </div>
                {{-- Info --}}
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-medium small">{{ $deviceLabel }}</span>
                        @if($isCurrent)
                            <span class="badge bg-success-subtle text-success-emphasis">This device</span>
                        @endif
                    </div>
                    <div class="small text-muted mt-1">
                        {{ $s->ip }}
                        @if($s->last_active_at)
                            · {{ $s->last_active_at->diffForHumans() }}
                        @endif
                    </div>
                </div>
                {{-- Sign out --}}
                @if(!$isCurrent)
                    <form method="POST" action="{{ route('devices.destroy', $s) }}" class="flex-shrink-0">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Sign out</button>
                    </form>
                @endif
            </div>
            @endforeach
        </div>
    @endif
</div>
