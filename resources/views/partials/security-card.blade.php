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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-shield-lock me-2"></i>Two-factor authentication
        </h6>
        @if ($user->two_factor_confirmed_at)
            <span class="badge bg-success-subtle text-success-emphasis">Enabled</span>
        @else
            <span class="badge bg-secondary-subtle text-secondary-emphasis">Off</span>
        @endif
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Two-factor authentication adds a one-time code to your sign-in. <strong>Optional</strong> — turn it on only if you want the extra protection.
        </p>

        @if ($user->two_factor_confirmed_at)
            <form method="POST" action="{{ route('2fa.disable') }}" class="row g-2">
                @csrf
                <div class="col-12 col-sm-7">
                    <input type="password" name="password" class="form-control" placeholder="Confirm your password" required>
                </div>
                <div class="col-12 col-sm-5">
                    <button class="btn btn-outline-danger w-100">
                        <i class="bi bi-shield-slash me-1"></i> Disable 2FA
                    </button>
                </div>
            </form>
        @else
            <details>
                <summary class="btn btn-outline-success btn-sm mb-3" style="list-style:none;">
                    <i class="bi bi-shield-plus me-1"></i> Enable 2FA
                </summary>
                <div class="mt-3">
                    @if ($qrUri)
                        <p class="small mb-2">Scan with Google Authenticator, Authy, or 1Password:</p>
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data={{ urlencode($qrUri) }}"
                            alt="QR code"
                            class="mb-2 border rounded"
                        >
                        <p class="small text-muted mb-1">Or enter this secret manually:</p>
                        <code class="d-inline-block p-2 bg-light rounded small mb-3">{{ $secret }}</code>
                    @endif
                    <form method="POST" action="{{ route('2fa.confirm') }}">
                        @csrf
                        <label class="form-label small">Enter the 6-digit code from your app</label>
                        <div class="input-group">
                            <input type="text" name="code" class="form-control font-monospace" inputmode="numeric" maxlength="6" pattern="\d{6}" required>
                            <button class="btn btn-success">Confirm</button>
                        </div>
                    </form>
                </div>
            </details>

            @if (session('recovery_codes'))
                <div class="alert alert-warning mt-3 small">
                    <strong>Save these recovery codes.</strong> Each can be used once if you lose your 2FA device.
                    <pre class="mb-0 mt-2 small">{{ implode(PHP_EOL, session('recovery_codes')) }}</pre>
                </div>
            @endif
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-semibold">
            <i class="bi bi-laptop me-2"></i>Signed-in devices
        </h6>
        <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $sessions->count() }}</span>
    </div>
    <div class="list-group list-group-flush">
        @forelse ($sessions as $s)
            <div class="list-group-item d-flex justify-content-between align-items-start">
                <div class="me-3" style="min-width:0;flex:1;">
                    <div class="fw-medium small">
                        {{ $s->device_label ?? 'Unknown device' }}
                        @if ($s->session_id === $currentSessionId)
                            <span class="badge bg-success-subtle text-success-emphasis ms-1">This device</span>
                        @endif
                    </div>
                    <div class="small text-muted">
                        {{ $s->ip }} · {{ $s->last_active_at?->diffForHumans() ?? '—' }}
                    </div>
                    <div class="small text-muted text-truncate" title="{{ $s->user_agent }}">
                        {{ $s->user_agent }}
                    </div>
                </div>
                @if ($s->session_id !== $currentSessionId)
                    <form method="POST" action="{{ route('devices.destroy', $s) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Sign out</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="list-group-item text-center text-muted py-4 small">No other active devices.</div>
        @endforelse
    </div>
</div>
