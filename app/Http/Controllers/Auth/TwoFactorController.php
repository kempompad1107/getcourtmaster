<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactor\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TotpService $totp) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        $secret = null;
        $qrUri = null;
        if (!$user->two_factor_confirmed_at && !$user->two_factor_secret) {
            $secret = $this->totp->generateSecret();
            $user->update(['two_factor_secret' => Crypt::encryptString($secret)]);
        } elseif (!$user->two_factor_confirmed_at && $user->two_factor_secret) {
            $secret = Crypt::decryptString($user->two_factor_secret);
        }

        if ($secret) {
            $qrUri = $this->totp->provisioningUri(
                $secret,
                $user->email,
                config('app.name', 'CourtMaster')
            );
        }

        return view('auth.two-factor', [
            'user'   => $user,
            'secret' => $secret,
            'qrUri'  => $qrUri,
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        if (!$user->two_factor_secret) {
            return back()->withErrors(['code' => 'No 2FA setup in progress.']);
        }

        $secret = Crypt::decryptString($user->two_factor_secret);
        if (!$this->totp->verify($secret, $data['code'])) {
            return back()->withErrors(['code' => 'Invalid code. Try again.']);
        }

        $codes = $this->totp->generateRecoveryCodes();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode(
                array_map(fn ($c) => Hash::make($c), $codes)
            )),
        ]);

        return back()->with('success', '2FA enabled.')
            ->with('recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return back()->with('success', '2FA disabled.');
    }

    public function challenge(): View
    {
        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ]);

        $userId = $request->session()->get('login.2fa.user_id');
        $intended = $request->session()->get('login.2fa.intended');
        abort_unless($userId, 419);

        $user = \App\Models\User::findOrFail($userId);

        $ok = false;
        if (!empty($data['code'])) {
            $secret = Crypt::decryptString($user->two_factor_secret);
            $ok = $this->totp->verify($secret, $data['code']);
        } elseif (!empty($data['recovery_code']) && $user->two_factor_recovery_codes) {
            $hashes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true) ?: [];
            foreach ($hashes as $i => $hash) {
                if (Hash::check($data['recovery_code'], $hash)) {
                    unset($hashes[$i]);
                    $user->update(['two_factor_recovery_codes' => Crypt::encryptString(json_encode(array_values($hashes)))]);
                    $ok = true;
                    break;
                }
            }
        }

        if (!$ok) {
            return back()->withErrors(['code' => 'Invalid code or recovery code.']);
        }

        auth()->login($user, $request->session()->pull('login.2fa.remember', false));
        $request->session()->forget(['login.2fa.user_id', 'login.2fa.remember', 'login.2fa.intended']);
        $request->session()->regenerate();

        return redirect()->intended($intended ?: \App\Http\Controllers\Auth\LoginController::landingFor($user));
    }
}
