<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        if (!$user->is_active) {
            return back()->withErrors(['email' => 'This account is disabled.'])->withInput();
        }

        // 2FA gate — defer login until the challenge is satisfied.
        if ($user->two_factor_enabled && $user->two_factor_confirmed_at) {
            $request->session()->put('login.2fa.user_id', $user->id);
            $request->session()->put('login.2fa.remember', $request->boolean('remember'));
            $request->session()->put('login.2fa.intended', self::landingFor($user));
            return redirect()->route('2fa.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(self::landingFor($user));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Route a freshly-authenticated user to the dashboard appropriate for their type.
     * Shared by the password flow, 2FA challenge, OTP login, and social-login callbacks.
     */
    public static function landingFor(User $user): string
    {
        if ($user->isSuperAdmin() || $user->hasRole('super_admin')) {
            return route('super.dashboard');
        }

        if ($user->isCustomer()) {
            return route('customer.dashboard');
        }

        // business_owner or staff — both land in the tenant admin
        if ($user->tenant_id) {
            return route('admin.dashboard');
        }

        // Authenticated but no tenant context — fall back to customer portal.
        return route('customer.dashboard');
    }
}
