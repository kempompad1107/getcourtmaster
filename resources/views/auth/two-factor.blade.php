@extends('layouts.app')

@section('title', 'Two-Factor Authentication')

@section('content')
<div class="container" style="max-width:560px;">
    <h4 class="mb-3">Two-Factor Authentication</h4>

    @if ($user->two_factor_confirmed_at)
        <div class="alert alert-success">2FA is enabled on this account.</div>

        <form method="POST" action="{{ route('2fa.disable') }}" class="card border-0 shadow-sm">
            @csrf
            <div class="card-body">
                <p class="text-muted small">To disable 2FA, confirm your password.</p>
                <input type="password" name="password" class="form-control mb-3" placeholder="Current password" required>
                <button class="btn btn-outline-danger">Disable 2FA</button>
            </div>
        </form>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <p class="mb-2">Scan this QR with Google Authenticator, Authy, or 1Password.</p>
                @if ($qrUri)
                    <img
                        src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($qrUri) }}"
                        alt="QR code"
                        class="my-3"
                    >
                @endif
                <p class="small text-muted">Or enter this secret manually:</p>
                <code class="d-block p-2 bg-light rounded">{{ $secret }}</code>

                <hr>

                <form method="POST" action="{{ route('2fa.confirm') }}" class="mt-3">
                    @csrf
                    <label class="form-label">Enter the 6-digit code from your app</label>
                    <input type="text" name="code" class="form-control" inputmode="numeric" maxlength="6" pattern="\d{6}" required>
                    <button class="btn btn-success mt-3">Confirm and enable 2FA</button>
                </form>
            </div>
        </div>

        @if (session('recovery_codes'))
            <div class="alert alert-warning mt-3">
                <strong>Save these recovery codes</strong>. Each can be used once if you lose your 2FA device.
                <pre class="mb-0 mt-2">{{ implode(PHP_EOL, session('recovery_codes')) }}</pre>
            </div>
        @endif
    @endif
</div>
@endsection
