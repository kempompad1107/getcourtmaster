<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\OtpCodeNotification;
use App\Services\TwoFactor\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class OtpLoginController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(): View
    {
        return view('auth.otp-request');
    }

    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $key = 'otp-send:' . $request->ip() . ':' . $data['email'];
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['email' => 'Too many requests. Try again in a minute.']);
        }
        RateLimiter::hit($key, 60);

        $user = User::where('email', $data['email'])->first();
        if ($user) {
            $result = $this->otp->issue($data['email'], 'email', 'login', $user, $request->ip());
            $user->notify(new OtpCodeNotification($result['code'], 'login'));
        }

        $request->session()->put('otp.email', $data['email']);

        return redirect()->route('otp.verify.show')
            ->with('success', 'If that email exists, a code has been sent.');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        if (!$request->session()->has('otp.email')) {
            return redirect()->route('otp.request');
        }
        return view('auth.otp-verify', ['email' => $request->session()->get('otp.email')]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $email = $request->session()->get('otp.email');
        abort_unless($email, 419);

        $record = $this->otp->verify($email, $data['code'], 'login');
        if (!$record) {
            return back()->withErrors(['code' => 'Invalid or expired code.']);
        }

        $user = User::where('email', $email)->firstOrFail();
        Auth::login($user, true);
        $request->session()->forget('otp.email');
        $request->session()->regenerate();

        return redirect()->intended(LoginController::landingFor($user));
    }
}
