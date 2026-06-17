<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use App\Services\FileStorageService;
use App\Services\TwoFactor\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly FileStorageService $files) {}

    public function edit(Request $request, TotpService $totp): View
    {
        $user = $request->user();

        $secret = null;
        $qrUri  = null;
        if (!$user->two_factor_confirmed_at) {
            if (empty($user->two_factor_secret)) {
                $secret = $totp->generateSecret();
                $user->forceFill(['two_factor_secret' => Crypt::encryptString($secret)])->save();
            } else {
                $secret = Crypt::decryptString($user->two_factor_secret);
            }
            $qrUri = $totp->provisioningUri($secret, $user->email, config('app.name', 'CourtMaster'));
        }

        $sessions = UserSession::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get();

        return view('customer.profile.edit', [
            'user'             => $user,
            'secret'           => $secret,
            'qrUri'            => $qrUri,
            'sessions'         => $sessions,
            'currentSessionId' => $request->session()->getId(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'preferences' => ['nullable', 'array'],
            'notification_preferences' => ['nullable', 'array'],
        ]);

        if ($request->hasFile('avatar')) {
            // Don't try to delete a previous avatar that lives on a social
            // provider (absolute URL); replaceFile handles that safely.
            $data['avatar'] = $this->files->replaceFile(
                $request->file('avatar'),
                $user->avatar,
                FileStorageService::FOLDER_PROFILES . '/' . $user->id,
            );
        } else {
            unset($data['avatar']);
        }

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Password changed.');
    }
}
