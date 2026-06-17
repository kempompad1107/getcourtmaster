<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DeviceSessionController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = UserSession::where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->orderByDesc('last_active_at')
            ->get();

        return view('auth.devices', [
            'sessions'         => $sessions,
            'currentSessionId' => $request->session()->getId(),
        ]);
    }

    public function destroy(Request $request, UserSession $session): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        $session->update(['revoked_at' => now()]);

        return back()->with('success', 'Device signed out.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $current = $request->session()->getId();

        UserSession::where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->where('session_id', '!=', $current)
            ->update(['revoked_at' => now()]);

        // Invalidate Laravel sessions for "other devices" via session store driver
        Auth::logoutOtherDevices($request->input('password', ''));

        return back()->with('success', 'Other devices signed out.');
    }
}
