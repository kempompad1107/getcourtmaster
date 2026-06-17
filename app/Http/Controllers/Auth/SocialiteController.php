<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        return $this->handleCallback('google', 'google_id');
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        return $this->handleCallback('facebook', 'facebook_id');
    }

    private function handleCallback(string $driver, string $idColumn)
    {
        try {
            $socialUser = Socialite::driver($driver)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors(['social' => 'Social login failed. Please try again.']);
        }

        // Look up by social ID first (most specific match).
        $user = User::where($idColumn, $socialUser->getId())->first();

        if (!$user) {
            // Try matching by email — this covers the case where the user
            // registered with email/password and is now linking social login.
            $userByEmail = User::where('email', $socialUser->getEmail())->first();

            if ($userByEmail) {
                // If this account already has a DIFFERENT social ID set for this
                // provider, reject: it means a different social account is already
                // linked, and overwriting it would allow account takeover.
                if ($userByEmail->$idColumn !== null && $userByEmail->$idColumn !== $socialUser->getId()) {
                    return redirect()->route('login')
                        ->withErrors(['social' => 'This email is linked to a different ' . ucfirst($driver) . ' account. Please sign in with your password.']);
                }

                // Safe to link: first-time social login for an existing email account.
                $userByEmail->update([$idColumn => $socialUser->getId()]);
                $user = $userByEmail;
            }
        }

        if (!$user) {
            $user = User::create([
                'name'              => $socialUser->getName(),
                'email'             => $socialUser->getEmail(),
                $idColumn           => $socialUser->getId(),
                'avatar'            => $socialUser->getAvatar(),
                'password'          => bcrypt(Str::random(32)),
                'user_type'         => 'customer',
                'is_active'         => true,
                'referral_code'     => strtoupper(substr(md5($socialUser->getEmail() . time()), 0, 8)),
                'email_verified_at' => now(),
            ]);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['social' => 'Your account has been deactivated.']);
        }

        Auth::login($user, true);

        return redirect()->intended(LoginController::landingFor($user));
    }
}
